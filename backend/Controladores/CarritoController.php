<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\CarritoModel;
use App\util\AuthGuard;
use App\util\Http;

class CarritoController
{
    private CarritoModel $modelo;

    public function __construct()
    {
        $this->modelo = new CarritoModel();
    }

    public function index(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idCarrito = $this->modelo->ConsultarCarritoActivo((int) $usuario['sub']);

        if ($idCarrito === null) {
            Flight::json(['id_carrito' => null, 'items' => [], 'total' => 0]);
            return;
        }

        Flight::json($this->modelo->ConsultarDetallesDelCarrito($idCarrito));
    }

    public function agregar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idProducto = (int) Http::body('id_producto', 0);
        $cantidad = (int) Http::body('cantidad', 1);

        if ($idProducto < 1) {
            Flight::json(['error' => 'id_producto es obligatorio'], 422);
            return;
        }

        if ($cantidad < 1) {
            Flight::json(['error' => 'La cantidad debe ser al menos 1'], 422);
            return;
        }

        $idCarrito = $this->modelo->AgregarProductoAlCarrito((int) $usuario['sub'], $idProducto, $cantidad);

        if (!$idCarrito['ok']) {
            if ($idCarrito['error'] === 'no_disponible') {
                Flight::json(['error' => 'Producto no disponible'], 404);
                return;
            }

            Flight::json([
                'error'  => 'Superaste el stock del producto',
                'stock'  => $idCarrito['stock'],
                'maximo' => $idCarrito['maximo'],
            ], 422);
            return;
        }

        Flight::json(['mensaje' => 'Producto agregado al carrito', 'id_carrito' => $idCarrito['id_carrito']], 201);
    }

    public function vaciar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        if (!$this->modelo->VaciarCarrito((int) $usuario['sub'])) {
            Flight::json(['error' => 'No hay un carrito activo'], 404);
            return;
        }

        Flight::json(['mensaje' => 'Carrito cancelado']);
    }

    public function modificarCantidad(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idProducto = (int) Http::param('id');
        $cantidad = (int) Http::body('cantidad', 0);

        $resultado = $this->modelo->ModificarCantidadDelProducto(
            (int) $usuario['sub'],
            $idProducto,
            $cantidad,
        );

        if (!$resultado['ok']) {
            if ($resultado['error'] === 'cantidad_invalida') {
                Flight::json(['error' => 'La cantidad debe ser al menos 1'], 422);
                return;
            }

            if ($resultado['error'] === 'supera_stock') {
                Flight::json([
                    'error' => 'Superaste el stock del producto',
                    'stock' => $resultado['stock'],
                ], 422);
                return;
            }

            Flight::json(['error' => 'El producto no está en tu carrito'], 404);
            return;
        }

        Flight::json(['mensaje' => 'Cantidad actualizada']);
    }

    public function eliminar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idProducto = (int) Http::param('id');

        if (!$this->modelo->EliminarProductoDelCarrito((int) $usuario['sub'], $idProducto)) {
            Flight::json(['error' => 'El producto no está en tu carrito'], 404);
            return;
        }

        Flight::json(['mensaje' => 'Producto eliminado del carrito']);
    }
}
