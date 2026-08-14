<?php

declare(strict_types=1);

use App\Controladores\AuthController;
use App\Controladores\CarritoController;
use App\Controladores\CategoriaController;
use App\Controladores\PedidoController;
use App\Controladores\ProductoController;
use App\Controladores\UsuarioController;

/*
|--------------------------------------------------------------------------
| Rutas públicas
|--------------------------------------------------------------------------
*/

Flight::route('GET /api/productos', [ProductoController::class, 'index']);
Flight::route('GET /api/productos/@id:[0-9]+', [ProductoController::class, 'show']);
Flight::route('GET /api/categorias', [CategoriaController::class, 'index']);

Flight::route('POST /api/auth/registro', [AuthController::class, 'registro']);
Flight::route('POST /api/auth/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rutas del comprador (requieren token con rol comprador)
|--------------------------------------------------------------------------
*/

Flight::group('/api', function (): void {
    Flight::route('GET /carrito', [CarritoController::class, 'index']);
    Flight::route('POST /carrito/items', [CarritoController::class, 'agregar']);
    Flight::route('PATCH /carrito/items/@id:[0-9]+', [CarritoController::class, 'modificarCantidad']);
    Flight::route('DELETE /carrito/items/@id:[0-9]+', [CarritoController::class, 'eliminar']);
    Flight::route('DELETE /carrito', [CarritoController::class, 'vaciar']);

    Flight::route('GET /usuarios/mi-perfil', [UsuarioController::class, 'miPerfil']);
    Flight::route('PUT /usuarios/mi-perfil', [UsuarioController::class, 'actualizarMiPerfil']);

    Flight::route('POST /pedidos', [PedidoController::class, 'crear']);
    Flight::route('GET /pedidos/mios', [PedidoController::class, 'mios']);
    Flight::route('GET /pedidos/@id:[0-9]+', [PedidoController::class, 'detallePropio']);
});

/*
|--------------------------------------------------------------------------
| Rutas del administrador (requieren token con rol administrador)
|--------------------------------------------------------------------------
*/

Flight::group('/api/admin', function (): void {
    Flight::route('GET /productos', [ProductoController::class, 'adminIndex']);
    Flight::route('POST /productos', [ProductoController::class, 'crear']);
    Flight::route('PUT /productos/@id:[0-9]+', [ProductoController::class, 'actualizar']);
    Flight::route('PATCH /productos/@id:[0-9]+/estado', [ProductoController::class, 'cambiarEstado']);

    Flight::route('GET /categorias', [CategoriaController::class, 'adminIndex']);
    Flight::route('POST /categorias', [CategoriaController::class, 'crear']);
    Flight::route('PUT /categorias/@id:[0-9]+', [CategoriaController::class, 'actualizar']);
    Flight::route('PATCH /categorias/@id:[0-9]+/estado', [CategoriaController::class, 'cambiarEstado']);

    Flight::route('GET /usuarios', [UsuarioController::class, 'index']);

    Flight::route('GET /pedidos', [PedidoController::class, 'todos']);
    Flight::route('PATCH /pedidos/@id:[0-9]+/estado', [PedidoController::class, 'cambiarEstado']);
});
