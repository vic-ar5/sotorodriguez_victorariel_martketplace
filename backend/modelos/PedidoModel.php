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
    public function generar(int $idUsuario, int $idDireccion): ?int
    {
        $sql = "SELECT d.id_direccion
                FROM direcciones d
                JOIN persona p ON d.id_persona = p.id_persona
                JOIN usuario u ON p.id_usuario = u.id_usuario
                WHERE u.id_usuario = :id_usuario AND d.id_direccion = :id_direccion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario, 'id_direccion' => $idDireccion]);

        if ($stmt->fetchColumn() === false) {
            return null;
        }

        $idCarrito = (new CarritoModel())->activo($idUsuario);

        if ($idCarrito === null) {
            return null;
        }

        $sql = "SELECT COUNT(*) FROM detalle_carrito WHERE id_carrito = :id_carrito";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_carrito' => $idCarrito]);

        if ((int) $stmt->fetchColumn() === 0) {
            return null;
        }

        $this->db->beginTransaction();

        try {
            // 1) Insertar el pedido (estado 1 = Pendiente)
            $sql = "INSERT INTO pedidos (id_usuario, id_direccion, id_estado_pedido, total)
                    SELECT c.id_usuario, :id_direccion, 1, SUM(dc.subtotal)
                    FROM carrito c
                    JOIN detalle_carrito dc ON dc.id_carrito = c.id_carrito
                    WHERE c.id_usuario = :id_usuario AND c.estado = 'activo'
                    GROUP BY c.id_usuario
                    RETURNING id_pedido";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_usuario' => $idUsuario, 'id_direccion' => $idDireccion]);

            $idPedido = (int) $stmt->fetchColumn();

            // 2) Copiar el detalle del carrito al pedido
            $sql = "INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
                    SELECT :id_pedido, dc.id_producto, dc.cantidad, dc.precio_unitario
                    FROM detalle_carrito dc
                    JOIN carrito c ON dc.id_carrito = c.id_carrito
                    WHERE c.id_usuario = :id_usuario AND c.estado = 'activo'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_pedido' => $idPedido, 'id_usuario' => $idUsuario]);

            // 3) Descontar existencias
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

            // 4) Cerrar el carrito
            $sql = "UPDATE carrito SET estado = 'convertido'
                    WHERE id_usuario = :id_usuario AND estado = 'activo'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_usuario' => $idUsuario]);

            $this->db->commit();

            return $idPedido;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function mios(int $idUsuario): array
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

    public function detalleDeUsuario(int $idUsuario, int $idPedido): ?array
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

        $pedido['detalle'] = $this->detalleItems($idPedido);

        return $pedido;
    }

    public function detalleItems(int $idPedido): array
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
    public function todos(?int $idEstado): array
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

    public function cambiarEstado(int $idPedido, int $idEstado): bool
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
