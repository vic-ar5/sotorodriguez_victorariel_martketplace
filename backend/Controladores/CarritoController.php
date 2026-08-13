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

        $idCarrito = $this->modelo->activo((int) $usuario['sub']);

        if ($idCarrito === null) {
            Flight::json(['id_carrito' => null, 'items' => [], 'total' => 0]);
            Flight::stop();
        }

        Flight::json($this->modelo->ver($idCarrito));
    }

    public function agregar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idProducto = (int) Http::body('id_producto', 0);
        $cantidad = (int) Http::body('cantidad', 1);

        if ($idProducto < 1) {
            Flight::json(['error' => 'id_producto es obligatorio'], 422);
            Flight::stop();
        }

        if ($cantidad < 1) {
            Flight::json(['error' => 'La cantidad debe ser al menos 1'], 422);
            Flight::stop();
        }

        $idCarrito = $this->modelo->agregar((int) $usuario['sub'], $idProducto, $cantidad);

        if ($idCarrito === null) {
            Flight::json(['error' => 'Producto no disponible'], 404);
            Flight::stop();
        }

        Flight::json(['mensaje' => 'Producto agregado al carrito', 'id_carrito' => $idCarrito], 201);
    }

    public function modificarCantidad(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idProducto = (int) Http::param('id');
        $cantidad = (int) Http::body('cantidad', 0);

        if ($cantidad < 1) {
            Flight::json(['error' => 'La cantidad debe ser al menos 1'], 422);
            Flight::stop();
        }

        if (!$this->modelo->modificarCantidad((int) $usuario['sub'], $idProducto, $cantidad)) {
            Flight::json(['error' => 'El producto no está en tu carrito'], 404);
            Flight::stop();
        }

        Flight::json(['mensaje' => 'Cantidad actualizada']);
    }

    public function eliminar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idProducto = (int) Http::param('id');

        if (!$this->modelo->eliminar((int) $usuario['sub'], $idProducto)) {
            Flight::json(['error' => 'El producto no está en tu carrito'], 404);
            Flight::stop();
        }

        Flight::json(['mensaje' => 'Producto eliminado del carrito']);
    }
}
