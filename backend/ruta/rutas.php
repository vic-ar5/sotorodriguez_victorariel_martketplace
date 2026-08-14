<?php

declare(strict_types=1);

use App\Controladores\AuthController;
use App\Controladores\CarritoController;
use App\Controladores\CategoriaController;
use App\Controladores\DashboardController;
use App\Controladores\DireccionController;
use App\Controladores\NotificacionController;
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
Flight::route('GET /api/imagenes/@id:[0-9]+', [ProductoController::class, 'descargarImagen']);
Flight::route('GET /api/categorias', [CategoriaController::class, 'index']);
Flight::route('GET /api/estados-mexico', [DireccionController::class, 'estados']);

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
    Flight::route('POST /pedidos/@id:[0-9]+/confirmar', [PedidoController::class, 'confirmarPago']);
    Flight::route('POST /pedidos/@id:[0-9]+/confirmar-entrega', [PedidoController::class, 'confirmarEntrega']);
    Flight::route('POST /pedidos/@id:[0-9]+/cancelar', [PedidoController::class, 'cancelar']);
    Flight::route('GET /pedidos/mios', [PedidoController::class, 'mios']);
    Flight::route('GET /pedidos/@id:[0-9]+', [PedidoController::class, 'detallePropio']);

    Flight::route('GET /notificaciones', [NotificacionController::class, 'index']);
    Flight::route('POST /notificaciones/leer', [NotificacionController::class, 'leer']);

    Flight::route('GET /direcciones', [DireccionController::class, 'index']);
    Flight::route('POST /direcciones', [DireccionController::class, 'crear']);
    Flight::route('PUT /direcciones/@id:[0-9]+', [DireccionController::class, 'actualizar']);
    Flight::route('DELETE /direcciones/@id:[0-9]+', [DireccionController::class, 'eliminar']);
    Flight::route('POST /direcciones/@id:[0-9]+/principal', [DireccionController::class, 'establecerPrincipal']);

    Flight::route('GET /cp-mx/colonias', [DireccionController::class, 'coloniasPorCodigoPostal']);
});

/*
|--------------------------------------------------------------------------
| Rutas del administrador (requieren token con rol administrador)
|--------------------------------------------------------------------------
*/

Flight::group('/api/admin', function (): void {
    Flight::route('GET /dashboard', [DashboardController::class, 'index']);

    Flight::route('GET /productos', [ProductoController::class, 'adminIndex']);
    Flight::route('GET /productos/mios', [ProductoController::class, 'adminMisProductos']);
    Flight::route('GET /productos/@id:[0-9]+', [ProductoController::class, 'adminShow']);
    Flight::route('POST /productos', [ProductoController::class, 'crear']);
    Flight::route('PUT /productos/@id:[0-9]+', [ProductoController::class, 'actualizar']);
    Flight::route('PATCH /productos/@id:[0-9]+/estado', [ProductoController::class, 'cambiarEstado']);

    Flight::route('GET /categorias', [CategoriaController::class, 'adminIndex']);
    Flight::route('POST /categorias', [CategoriaController::class, 'crear']);
    Flight::route('PUT /categorias/@id:[0-9]+', [CategoriaController::class, 'actualizar']);
    Flight::route('PATCH /categorias/@id:[0-9]+/estado', [CategoriaController::class, 'cambiarEstado']);

    Flight::route('GET /usuarios', [UsuarioController::class, 'index']);

    Flight::route('GET /pedidos', [PedidoController::class, 'todos']);
    Flight::route('GET /pedidos/@id:[0-9]+', [PedidoController::class, 'detalleAdmin']);
    Flight::route('PATCH /pedidos/@id:[0-9]+/estado', [PedidoController::class, 'cambiarEstado']);
});
