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
            $this->DescontarExistencias($idUsuario);
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
     * Inserta el pedido con estado 1 (Pendiente) y su total calculado
     * desde el carrito activo. Devuelve el id del pedido recién creado.
     */
    private function InsertarPedido(int $idUsuario, int $idDireccion): int
    {
        $sql = "INSERT INTO pedidos (id_usuario, id_direccion, id_estado_pedido, total)
                SELECT c.id_usuario, :id_direccion, 1, SUM(dc.subtotal)
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
     * Resta las cantidades compradas a la existencia de cada producto.
     */
    private function DescontarExistencias(int $idUsuario): void
    {
        $sql = "UPDATE productos pr
                SET existencia = pr.existencia - dc.cantidad
                FROM detalle_carrito dc
                JOIN carrito c ON dc.id_carrito = c.id_carrito
                WHERE dc.id_producto = pr.id_producto
                  AND c.id_usuario = :id_usuario
                  AND c.estado = 'activo'
                  AND pr.estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);
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
     * Detalle de un pedido del comprador, con sus items.
     */
    public function ConsultarDetalleDePedidoDelComprador(int $idUsuario, int $idPedido): ?array
    {
        $sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, p.moneda,
                       ep.nombre AS estado,
                       d.nombre || ' - ' || d.calle || ' ' || d.numero_exterior AS direccion
                FROM pedidos p
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion
                WHERE p.id_pedido = :id_pedido AND p.id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

        $pedido = $stmt->fetch();

        if ($pedido === false) {
            return null;
        }

        $pedido['detalle'] = $this->ConsultarItemsDelPedido($idPedido);

        return $pedido;
    }

    private function ConsultarItemsDelPedido(int $idPedido): array
    {
        $sql = "SELECT dp.id_producto, p.nombre, dp.cantidad, dp.precio_unitario, dp.subtotal
                FROM detalle_pedido dp
                JOIN productos p ON dp.id_producto = p.id_producto
                WHERE dp.id_pedido = :id_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_pedido' => $idPedido]);

        return $stmt->fetchAll();
    }

    /**
     * Consulta administrativa de pedidos; se puede filtrar por estado.
     */
    public function ConsultarTodosLosPedidos(?int $idEstado): array
    {
        $sql = "SELECT p.id_pedido, p.numero_pedido, p.fecha_pedido, p.total, p.moneda,
                       ep.nombre AS estado,
                       u.nombre_usuario,
                       d.nombre || ' - ' || d.calle || ' ' || d.numero_exterior AS direccion
                FROM pedidos p
                JOIN usuario u        ON p.id_usuario = u.id_usuario
                JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
                LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion";

        $params = [];

        if ($idEstado !== null) {
            $sql .= " WHERE p.id_estado_pedido = :id_estado_pedido";
            $params['id_estado_pedido'] = $idEstado;
        }

        $sql .= " ORDER BY p.fecha_pedido DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Cambia el estado de un pedido (1 Pendiente ... 6 Cancelado).
     */
    public function ActualizarEstadoDelPedido(int $idPedido, int $idEstado): bool
    {
        $sql = "UPDATE pedidos SET id_estado_pedido = :id_estado_pedido
                WHERE id_pedido = :id_pedido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_estado_pedido' => $idEstado,
            'id_pedido'        => $idPedido,
        ]);

        return $stmt->rowCount() > 0;
    }
}
