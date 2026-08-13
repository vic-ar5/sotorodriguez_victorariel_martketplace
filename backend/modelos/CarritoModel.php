<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;

class CarritoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    public function activo(int $idUsuario): ?int
    {
        $sql = "SELECT id_carrito FROM carrito
                WHERE id_usuario = :id_usuario AND estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function crear(int $idUsuario): int
    {
        $sql = "INSERT INTO carrito (id_usuario)
                VALUES (:id_usuario)
                RETURNING id_carrito";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Agrega un producto al carrito activo (crea el carrito si no existe).
     * Devuelve el id del carrito o null si el producto no está activo.
     */
    public function agregar(int $idUsuario, int $idProducto, int $cantidad): ?int
    {
        $precio = (new ProductoModel())->ConsultarPrecioDelProducto($idProducto);

        if ($precio === null) {
            return null;
        }

        $idCarrito = $this->activo($idUsuario);

        if ($idCarrito === null) {
            $idCarrito = $this->crear($idUsuario);
        }

        $sql = "INSERT INTO detalle_carrito (id_carrito, id_producto, cantidad, precio_unitario)
                VALUES (:id_carrito, :id_producto, :cantidad, :precio_unitario)
                ON CONFLICT (id_carrito, id_producto)
                DO UPDATE SET cantidad = detalle_carrito.cantidad + EXCLUDED.cantidad";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_carrito'      => $idCarrito,
            'id_producto'     => $idProducto,
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio,
        ]);

        return $idCarrito;
    }

    public function modificarCantidad(int $idUsuario, int $idProducto, int $cantidad): bool
    {
        $idCarrito = $this->activo($idUsuario);

        if ($idCarrito === null) {
            return false;
        }

        $sql = "UPDATE detalle_carrito
                SET cantidad = :cantidad
                WHERE id_carrito = :id_carrito AND id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'cantidad'    => $cantidad,
            'id_carrito'  => $idCarrito,
            'id_producto' => $idProducto,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function eliminar(int $idUsuario, int $idProducto): bool
    {
        $idCarrito = $this->activo($idUsuario);

        if ($idCarrito === null) {
            return false;
        }

        $sql = "DELETE FROM detalle_carrito
                WHERE id_carrito = :id_carrito AND id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_carrito'  => $idCarrito,
            'id_producto' => $idProducto,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function ver(int $idCarrito): array
    {
        $sql = "SELECT dc.id_detalle_carrito,
                       dc.id_producto,
                       p.nombre,
                       dc.cantidad,
                       dc.precio_unitario,
                       dc.subtotal,
                       p.existencia
                FROM detalle_carrito dc
                JOIN productos p ON dc.id_producto = p.id_producto
                WHERE dc.id_carrito = :id_carrito
                ORDER BY dc.fecha_agregado";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_carrito' => $idCarrito]);

        $items = $stmt->fetchAll();

        $sql = "SELECT SUM(dc.cantidad) AS productos, SUM(dc.subtotal) AS total
                FROM detalle_carrito dc
                WHERE dc.id_carrito = :id_carrito";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_carrito' => $idCarrito]);

        $totales = $stmt->fetch();

        return [
            'id_carrito' => $idCarrito,
            'items'      => $items,
            'total'      => (float) ($totales['total'] ?? 0),
        ];
    }
}
