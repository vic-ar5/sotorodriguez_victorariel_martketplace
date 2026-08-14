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

    /**
     * Busca el carrito en estado 'activo' del usuario; null si no existe.
     */
    public function ConsultarCarritoActivo(int $idUsuario): ?int
    {
        $sql = "SELECT id_carrito FROM carrito
                WHERE id_usuario = :id_usuario AND estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function CrearCarrito(int $idUsuario): int
    {
        $sql = "INSERT INTO carrito (id_usuario)
                VALUES (:id_usuario)
                RETURNING id_carrito";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Agrega un producto al carrito activo (crea el carrito si no existe)
     * y descuenta la cantidad del stock del producto.
     * Devuelve:
     *   ['ok' => true,  'id_carrito' => int]                   si se agregó,
     *   ['ok' => false, 'error' => 'no_disponible']            si el producto no está activo,
     *   ['ok' => false, 'error' => 'supera_stock', 'stock', 'maximo'] si excede la existencia.
     */
    public function AgregarProductoAlCarrito(int $idUsuario, int $idProducto, int $cantidad): array
    {
        $producto = (new ProductoModel())->ConsultarProductoActivo($idProducto);

        if ($producto === null) {
            return ['ok' => false, 'error' => 'no_disponible'];
        }

        $stock = (int) $producto['existencia'];

        if ($cantidad > $stock) {
            return [
                'ok'     => false,
                'error'  => 'supera_stock',
                'stock'  => $stock,
                'maximo' => $stock,
            ];
        }

        $idCarrito = $this->ConsultarCarritoActivo($idUsuario);

        if ($idCarrito === null) {
            $idCarrito = $this->CrearCarrito($idUsuario);
        }

        $existente = $this->ConsultarCantidadExistente($idCarrito, $idProducto);
        $nuevaCantidad = $existente + $cantidad;

        $sql = "INSERT INTO detalle_carrito (id_carrito, id_producto, cantidad, precio_unitario)
                VALUES (:id_carrito, :id_producto, :cantidad, :precio_unitario)
                ON CONFLICT (id_carrito, id_producto)
                DO UPDATE SET cantidad = EXCLUDED.cantidad";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_carrito'      => $idCarrito,
            'id_producto'     => $idProducto,
            'cantidad'        => $nuevaCantidad,
            'precio_unitario' => $producto['precio'],
        ]);

        (new ProductoModel())->DescontarStock($idProducto, $cantidad);

        return ['ok' => true, 'id_carrito' => $idCarrito];
    }

    /**
     * Cantidad actual de un producto dentro de un carrito.
     */
    public function ConsultarCantidadExistente(int $idCarrito, int $idProducto): int
    {
        $sql = "SELECT cantidad FROM detalle_carrito
                WHERE id_carrito = :id_carrito AND id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_carrito' => $idCarrito, 'id_producto' => $idProducto]);

        $cantidad = $stmt->fetchColumn();

        return $cantidad === false ? 0 : (int) $cantidad;
    }

    /**
     * Elimina todos los productos del carrito activo del usuario y
     * devuelve el stock a cada producto.
     */
    public function VaciarCarrito(int $idUsuario): bool
    {
        $idCarrito = $this->ConsultarCarritoActivo($idUsuario);

        if ($idCarrito === null) {
            return false;
        }

        $items = $this->ConsultarDetallesDelCarrito($idCarrito)['items'];

        foreach ($items as $item) {
            (new ProductoModel())->RestaurarStock(
                (int) $item['id_producto'],
                (int) $item['cantidad'],
            );
        }

        $sql = "DELETE FROM detalle_carrito WHERE id_carrito = :id_carrito";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_carrito' => $idCarrito]);

        return true;
    }

    /**
     * Cambia la cantidad de un producto en el carrito ajustando el stock
     * del producto. Devuelve:
     *   ['ok' => true]                                        si se actualizó,
     *   ['ok' => false, 'error' => 'no_carrito'|'no_en_carrito'|'cantidad_invalida'|'no_disponible']
     *   ['ok' => false, 'error' => 'supera_stock', 'stock']   si excede la existencia.
     */
    public function ModificarCantidadDelProducto(int $idUsuario, int $idProducto, int $cantidad): array
    {
        $idCarrito = $this->ConsultarCarritoActivo($idUsuario);

        if ($idCarrito === null) {
            return ['ok' => false, 'error' => 'no_carrito'];
        }

        $actual = $this->ConsultarCantidadExistente($idCarrito, $idProducto);

        if ($actual === 0) {
            return ['ok' => false, 'error' => 'no_en_carrito'];
        }

        if ($cantidad < 1) {
            return ['ok' => false, 'error' => 'cantidad_invalida'];
        }

        $diferencia = $cantidad - $actual;

        if ($diferencia > 0) {
            $producto = (new ProductoModel())->ConsultarProductoActivo($idProducto);

            if ($producto === null) {
                return ['ok' => false, 'error' => 'no_disponible'];
            }

            $stock = (int) $producto['existencia'];

            if ($diferencia > $stock) {
                return [
                    'ok'    => false,
                    'error' => 'supera_stock',
                    'stock' => $stock,
                ];
            }

            (new ProductoModel())->DescontarStock($idProducto, $diferencia);
        } elseif ($diferencia < 0) {
            (new ProductoModel())->RestaurarStock($idProducto, -$diferencia);
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

        return ['ok' => true];
    }

    /**
     * Elimina un producto del carrito activo del usuario y le devuelve
     * el stock al producto.
     */
    public function EliminarProductoDelCarrito(int $idUsuario, int $idProducto): bool
    {
        $idCarrito = $this->ConsultarCarritoActivo($idUsuario);

        if ($idCarrito === null) {
            return false;
        }

        $cantidad = $this->ConsultarCantidadExistente($idCarrito, $idProducto);

        if ($cantidad === 0) {
            return false;
        }

        $sql = "DELETE FROM detalle_carrito
                WHERE id_carrito = :id_carrito AND id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_carrito'  => $idCarrito,
            'id_producto' => $idProducto,
        ]);

        (new ProductoModel())->RestaurarStock($idProducto, $cantidad);

        return true;
    }

    /**
     * Detalle del carrito con sus productos y el total.
     */
    public function ConsultarDetallesDelCarrito(int $idCarrito): array
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
