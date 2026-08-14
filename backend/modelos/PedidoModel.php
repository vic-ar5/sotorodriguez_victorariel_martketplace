<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;
use Throwable;

class PedidoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    /**
     * Convierte el carrito activo en un pedido (transacción).
     * Devuelve el id del pedido o null si el carrito está vacío o la
     * dirección no pertenece al usuario.
     */
    public function GenerarPedido(int $idUsuario, int $idDireccion): ?int
    {
        if (!$this->DireccionPerteneceAlUsuario($idUsuario, $idDireccion)) {
            return null;
        }

        $idCarrito = (new CarritoModel())->ConsultarCarritoActivo($idUsuario);

        if ($idCarrito === null || !$this->CarritoTieneProductos($idCarrito)) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            $idPedido = $this->InsertarPedido($idUsuario, $idDireccion);
            $this->CopiarDetalleDelCarritoAlPedido($idPedido, $idUsuario);
            $this->CerrarCarrito($idUsuario);

            $this->db->commit();

            return $idPedido;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Verifica que la dirección indicada pertenezca al usuario.
     */
    private function DireccionPerteneceAlUsuario(int $idUsuario, int $idDireccion): bool
    {
        $sql = "SELECT d.id_direccion
                FROM direcciones d
                JOIN persona p ON d.id_persona = p.id_persona
                JOIN usuario u ON p.id_usuario = u.id_usuario
                WHERE u.id_usuario = :id_usuario AND d.id_direccion = :id_direccion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario, 'id_direccion' => $idDireccion]);

        return $stmt->fetchColumn() !== false;
    }

    private function CarritoTieneProductos(int $idCarrito): bool
    {
        $sql = "SELECT COUNT(*) FROM detalle_carrito WHERE id_carrito = :id_carrito";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_carrito' => $idCarrito]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Inserta el pedido con estado 'Pendiente' y su total calculado
     * desde el carrito activo. Devuelve el id del pedido recién creado.
     */
    private function InsertarPedido(int $idUsuario, int $idDireccion): int
    {
        $sql = "INSERT INTO pedidos (id_usuario, id_direccion, id_estado_pedido, total)
                SELECT c.id_usuario, :id_direccion,
                       (SELECT id_estado_pedido FROM estados_pedido WHERE nombre = 'Pendiente'),
                       SUM(dc.subtotal)
                FROM carrito c
                JOIN detalle_carrito dc ON dc.id_carrito = c.id_carrito
                WHERE c.id_usuario = :id_usuario AND c.estado = 'activo'
                GROUP BY c.id_usuario
                RETURNING id_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario, 'id_direccion' => $idDireccion]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Copia los productos del carrito activo al detalle del pedido.
     * NOTA: subtotal es GENERATED ALWAYS, no se inserta.
     */
    private function CopiarDetalleDelCarritoAlPedido(int $idPedido, int $idUsuario): void
    {
        $sql = "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
                SELECT :id_pedido, dc.id_producto, dc.cantidad, dc.precio_unitario
                FROM detalle_carrito dc
                JOIN carrito c ON dc.id_carrito = c.id_carrito
                WHERE c.id_usuario = :id_usuario AND c.estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);
    }

    /**
     * Cierra el carrito activo para que no se use en nuevos pedidos.
     */
    private function CerrarCarrito(int $idUsuario): void
    {
        $sql = "UPDATE carrito SET estado = 'convertido'
                WHERE id_usuario = :id_usuario AND estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);
    }

    /**
     * Confirma el pago de un pedido del comprador: pasa su estado de
     * 'Pendiente' a 'Confirmado'. Devuelve:
     *   ['ok' => true,  'ya_confirmado' => bool]     si se confirmó,
     *   ['ok' => false, 'error' => 'no_encontrado']  si no existe o no es suyo,
     *   ['ok' => false, 'error' => 'estado_invalido', 'estado'?] si no está pendiente.
     */
    public function ConfirmarPago(int $idUsuario, int $idPedido): array
    {
        $sql = "SELECT p.id_pedido, ep.nombre AS estado
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE p.id_pedido = :id_pedido AND p.id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return ['ok' => false, 'error' => 'no_encontrado'];
        }

        if ($pedido['estado'] === 'Confirmado') {
            return ['ok' => true, 'ya_confirmado' => true];
        }

        if ($pedido['estado'] !== 'Pendiente') {
            return [
                'ok'     => false,
                'error'  => 'estado_invalido',
                'estado' => $pedido['estado'],
            ];
        }

        $sql = "UPDATE pedidos
                SET id_estado_pedido = (
                    SELECT id_estado_pedido FROM estados_pedido WHERE nombre = 'Confirmado'
                )
                WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

        return ['ok' => true, 'ya_confirmado' => false];
    }

    /**
     * Cancela un pedido del comprador mientras esté 'Pendiente' o 'Confirmado',
     * y devuelve el stock reservado a cada producto. Devuelve:
     *   ['ok' => true,  'estado_anterior' => string]  si se canceló,
     *   ['ok' => false, 'error' => 'no_encontrado']   si no existe o no es suyo,
     *   ['ok' => false, 'error' => 'no_cancelable', 'estado'?] si ya no puede cancelarse.
     */
    public function CancelarPedido(int $idUsuario, int $idPedido): array
    {
        $sql = "SELECT p.id_pedido, ep.nombre AS estado
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE p.id_pedido = :id_pedido AND p.id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return ['ok' => false, 'error' => 'no_encontrado'];
        }

        $estado = $pedido['estado'];

        if ($estado !== 'Pendiente' && $estado !== 'Confirmado') {
            return ['ok' => false, 'error' => 'no_cancelable', 'estado' => $estado];
        }

        $this->db->beginTransaction();

        try {
            $sql = "UPDATE pedidos
                    SET id_estado_pedido = (
                        SELECT id_estado_pedido FROM estados_pedido WHERE nombre = 'Cancelado'
                    )
                    WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

            $sql = "SELECT id_producto, cantidad FROM detalle_pedido WHERE id_pedido = :id_pedido";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_pedido' => $idPedido]);

            $items = $stmt->fetchAll();

            foreach ($items as $item) {
                (new ProductoModel())->RestaurarStock(
                    (int) $item['id_producto'],
                    (int) $item['cantidad'],
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['ok' => true, 'estado_anterior' => $estado];
    }

    /**
     * Pedidos de un comprador (listado).
     */
    public function ConsultarPedidosDelComprador(int $idUsuario): array
    {
        $sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, p.moneda,
                       ep.nombre AS estado
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE p.id_usuario = :id_usuario
                ORDER BY p.fecha_pedido DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        return $stmt->fetchAll();
    }

    /**
     * Detalle de un pedido del comprador, con sus items, la persona que
     * lo compró y la dirección completa. Sirve de recibo.
     */
    public function ConsultarDetalleDePedidoDelComprador(int $idUsuario, int $idPedido): ?array
    {
        $sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, p.moneda,
                       ep.nombre AS estado,
                       pe.nombre AS comprador_nombre,
                       pe.apellido_paterno AS comprador_apellido_paterno,
                       pe.apellido_materno AS comprador_apellido_materno,
                       d.nombre AS direccion_nombre,
                       d.calle AS direccion_calle,
                       d.numero_exterior AS direccion_numero_exterior,
                       d.numero_interior AS direccion_numero_interior,
                       d.colonia AS direccion_colonia,
                       d.codigo_postal AS direccion_codigo_postal,
                       d.municipio AS direccion_municipio,
                       e.nombre AS direccion_estado,
                       d.pais AS direccion_pais
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                JOIN usuario u         ON p.id_usuario = u.id_usuario
                LEFT JOIN persona pe   ON u.id_usuario = pe.id_usuario
                LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion
                LEFT JOIN estados_mexico e ON d.id_estado = e.id_estado
                WHERE p.id_pedido = :id_pedido AND p.id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return null;
        }

        $pedido['persona'] = trim(
            ($pedido['comprador_nombre'] ?? '')
            . ' ' . ($pedido['comprador_apellido_paterno'] ?? '')
            . ($pedido['comprador_apellido_materno'] !== null ? ' ' . $pedido['comprador_apellido_materno'] : ''),
        );

        $pedido['direccion'] = [
            'nombre'          => $pedido['direccion_nombre'],
            'calle'           => $pedido['direccion_calle'],
            'numero_exterior' => $pedido['direccion_numero_exterior'],
            'numero_interior' => $pedido['direccion_numero_interior'],
            'colonia'         => $pedido['direccion_colonia'],
            'codigo_postal'   => $pedido['direccion_codigo_postal'],
            'municipio'       => $pedido['direccion_municipio'],
            'estado'          => $pedido['direccion_estado'],
            'pais'            => $pedido['direccion_pais'],
        ];

        unset(
            $pedido['comprador_nombre'],
            $pedido['comprador_apellido_paterno'],
            $pedido['comprador_apellido_materno'],
            $pedido['direccion_nombre'],
            $pedido['direccion_calle'],
            $pedido['direccion_numero_exterior'],
            $pedido['direccion_numero_interior'],
            $pedido['direccion_colonia'],
            $pedido['direccion_codigo_postal'],
            $pedido['direccion_municipio'],
            $pedido['direccion_estado'],
            $pedido['direccion_pais'],
        );

        $pedido['detalle'] = $this->ConsultarItemsDelPedido($idPedido);

        return $pedido;
    }

    private function ConsultarItemsDelPedido(int $idPedido): array
    {
        $sql = "SELECT dp.id_producto, p.identificador, p.nombre, dp.cantidad,
                       dp.precio_unitario, dp.subtotal,
                       COALESCE(NULLIF(TRIM(vp.nombre || ' ' || vp.apellido_paterno), ''), v.nombre_usuario) AS vendedor
                FROM detalle_pedido dp
                JOIN productos p ON dp.id_producto = p.id_producto
                JOIN usuario v   ON p.id_vendedor = v.id_usuario
                LEFT JOIN persona vp ON v.id_usuario = vp.id_usuario
                WHERE dp.id_pedido = :id_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido]);

        return $stmt->fetchAll();
    }

    /**
     * Consulta administrativa de pedidos; se puede filtrar por estado,
     * usuario (nombre de usuario o persona) y folio (número de pedido).
     */
    public function ConsultarTodosLosPedidos(
        ?string $estado,
        ?string $usuario,
        ?string $folio,
    ): array {
        $sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, p.moneda,
                       ep.nombre AS estado,
                       u.nombre_usuario,
                       TRIM(COALESCE(pe.nombre, '')
                            || ' ' || COALESCE(pe.apellido_paterno, '')
                            || ' ' || COALESCE(pe.apellido_materno, '')) AS comprador,
                       d.nombre || ' - ' || d.calle || ' ' || d.numero_exterior AS direccion
                FROM pedidos p
                JOIN usuario u        ON p.id_usuario = u.id_usuario
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                LEFT JOIN persona pe ON u.id_usuario = pe.id_usuario
                LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion";

        $condiciones = [];
        $params = [];

        if ($estado !== null && $estado !== '') {
            $condiciones[] = "ep.nombre = :estado";
            $params['estado'] = $estado;
        }

        if ($usuario !== null && $usuario !== '') {
            $condiciones[] = "(u.nombre_usuario ILIKE :usuario
                              OR pe.nombre ILIKE :usuario
                              OR pe.apellido_paterno ILIKE :usuario
                              OR pe.apellido_materno ILIKE :usuario)";
            $params['usuario'] = '%' . $usuario . '%';
        }

        if ($folio !== null && $folio !== '') {
            $condiciones[] = "p.numero_pedido ILIKE :folio";
            $params['folio'] = '%' . $folio . '%';
        }

        if ($condiciones !== []) {
            $sql .= " WHERE " . implode(' AND ', $condiciones);
        }

        $sql .= " ORDER BY p.fecha_pedido DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Detalle administrativo de un pedido: comprador, dirección y productos.
     * Sirve para revisar el pedido completo desde el panel del administrador.
     */
    public function ConsultarDetalleDePedidoAdmin(int $idPedido): ?array
    {
        $sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, p.moneda,
                       ep.nombre AS estado,
                       u.nombre_usuario,
                       pe.nombre AS comprador_nombre,
                       pe.apellido_paterno AS comprador_apellido_paterno,
                       pe.apellido_materno AS comprador_apellido_materno,
                       d.nombre AS direccion_nombre,
                       d.calle AS direccion_calle,
                       d.numero_exterior AS direccion_numero_exterior,
                       d.numero_interior AS direccion_numero_interior,
                       d.colonia AS direccion_colonia,
                       d.codigo_postal AS direccion_codigo_postal,
                       d.municipio AS direccion_municipio,
                       e.nombre AS direccion_estado,
                       d.pais AS direccion_pais
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                JOIN usuario u         ON p.id_usuario = u.id_usuario
                LEFT JOIN persona pe   ON u.id_usuario = pe.id_usuario
                LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion
                LEFT JOIN estados_mexico e ON d.id_estado = e.id_estado
                WHERE p.id_pedido = :id_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return null;
        }

        $pedido['persona'] = trim(
            ($pedido['comprador_nombre'] ?? '')
            . ' ' . ($pedido['comprador_apellido_paterno'] ?? '')
            . ($pedido['comprador_apellido_materno'] !== null ? ' ' . $pedido['comprador_apellido_materno'] : ''),
        );

        $pedido['direccion'] = [
            'nombre'          => $pedido['direccion_nombre'],
            'calle'           => $pedido['direccion_calle'],
            'numero_exterior' => $pedido['direccion_numero_exterior'],
            'numero_interior' => $pedido['direccion_numero_interior'],
            'colonia'         => $pedido['direccion_colonia'],
            'codigo_postal'   => $pedido['direccion_codigo_postal'],
            'municipio'       => $pedido['direccion_municipio'],
            'estado'          => $pedido['direccion_estado'],
            'pais'            => $pedido['direccion_pais'],
        ];

        unset(
            $pedido['comprador_nombre'],
            $pedido['comprador_apellido_paterno'],
            $pedido['comprador_apellido_materno'],
            $pedido['direccion_nombre'],
            $pedido['direccion_calle'],
            $pedido['direccion_numero_exterior'],
            $pedido['direccion_numero_interior'],
            $pedido['direccion_colonia'],
            $pedido['direccion_codigo_postal'],
            $pedido['direccion_municipio'],
            $pedido['direccion_estado'],
            $pedido['direccion_pais'],
        );

        $pedido['detalle'] = $this->ConsultarItemsDelPedido($idPedido);

        return $pedido;
    }

    /**
     * Resumen de pedidos que incluyen productos de un vendedor (panel
     * administrativo): total y cantidades por estado.
     */
    public function ConsultarResumenDePedidosDelVendedor(int $idVendedor): array
    {
        $sql = "SELECT COUNT(DISTINCT p.id_pedido) AS total
                FROM pedidos p
                JOIN detalle_pedido dp ON dp.id_pedido = p.id_pedido
                JOIN productos pr      ON pr.id_producto = dp.id_producto
                WHERE pr.id_vendedor = :id_vendedor";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_vendedor' => $idVendedor]);

        $resumen = ['total' => (int) $stmt->fetchColumn(), 'por_estado' => []];

        $sql = "SELECT ep.nombre AS estado, COUNT(DISTINCT p.id_pedido) AS cantidad
                FROM pedidos p
                JOIN detalle_pedido dp ON dp.id_pedido = p.id_pedido
                JOIN productos pr      ON pr.id_producto = dp.id_producto
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE pr.id_vendedor = :id_vendedor
                GROUP BY ep.id_estado_pedido, ep.nombre
                ORDER BY ep.id_estado_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_vendedor' => $idVendedor]);

        $resumen['por_estado'] = $stmt->fetchAll();

        return $resumen;
    }

    /**
     * Cambia el estado de un pedido por parte del administrador. Valida que
     * la transición sea válida y, cuando el pedido pasa a 'Enviado', crea
     * una notificación para el comprador. Devuelve:
     *   ['ok' => true, 'estado' => string]             si se cambió,
     *   ['ok' => false, 'error' => 'no_encontrado']    si el pedido no existe,
     *   ['ok' => false, 'error' => 'estado_invalido']  si el destino no es válido,
     *   ['ok' => false, 'error' => 'transicion_invalida', 'estado'?] si no procede.
     */
    public function ActualizarEstadoDelPedido(int $idPedido, string $estado): array
    {
        $destinos = ['Preparando', 'Enviado', 'Entregado', 'Cancelado'];

        if (!in_array($estado, $destinos, true)) {
            return ['ok' => false, 'error' => 'estado_invalido'];
        }

        $sql = "SELECT p.id_pedido, p.id_usuario, p.numero_pedido, ep.nombre AS estado
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE p.id_pedido = :id_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return ['ok' => false, 'error' => 'no_encontrado'];
        }

        $estadoActual = $pedido['estado'];

        $origenesValidos = [
            'Preparando' => ['Confirmado'],
            'Enviado'    => ['Preparando'],
            'Entregado'  => ['Enviado'],
            'Cancelado'  => ['Pendiente', 'Confirmado', 'Preparando', 'Enviado'],
        ];

        if (!in_array($estadoActual, $origenesValidos[$estado], true)) {
            return [
                'ok'     => false,
                'error'  => 'transicion_invalida',
                'estado' => $estadoActual,
            ];
        }

        $this->db->beginTransaction();

        try {
            $sql = "UPDATE pedidos
                    SET id_estado_pedido = (
                        SELECT id_estado_pedido FROM estados_pedido WHERE nombre = :estado
                    )
                    WHERE id_pedido = :id_pedido";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['estado' => $estado, 'id_pedido' => $idPedido]);

            if ($estado === 'Enviado') {
                $notificacion = 'Tu pedido ' . $pedido['numero_pedido']
                    . ' ha sido enviado. Confirma la entrega cuando lo recibas.';

                (new NotificacionModel())->Crear(
                    (int) $pedido['id_usuario'],
                    $idPedido,
                    $notificacion,
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['ok' => true, 'estado' => $estado];
    }

    /**
     * El comprador confirma la entrega de su pedido: solo se permite cuando
     * el pedido está 'Enviado' y pasa a 'Entregado'. Marca como leídas las
     * notificaciones de ese pedido. Devuelve:
     *   ['ok' => true]                                  si se confirmó,
     *   ['ok' => false, 'error' => 'no_encontrado']     si no existe o no es suyo,
     *   ['ok' => false, 'error' => 'estado_invalido', 'estado'?] si no está enviado.
     */
    public function ConfirmarEntrega(int $idUsuario, int $idPedido): array
    {
        $sql = "SELECT p.id_pedido, ep.nombre AS estado
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                WHERE p.id_pedido = :id_pedido AND p.id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return ['ok' => false, 'error' => 'no_encontrado'];
        }

        if ($pedido['estado'] !== 'Enviado') {
            return [
                'ok'     => false,
                'error'  => 'estado_invalido',
                'estado' => $pedido['estado'],
            ];
        }

        $this->db->beginTransaction();

        try {
            $sql = "UPDATE pedidos
                    SET id_estado_pedido = (
                        SELECT id_estado_pedido FROM estados_pedido WHERE nombre = 'Entregado'
                    )
                    WHERE id_pedido = :id_pedido AND id_usuario = :id_usuario";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

            (new NotificacionModel())->MarcarLeidasDePedido($idUsuario, $idPedido);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['ok' => true];
    }
}
