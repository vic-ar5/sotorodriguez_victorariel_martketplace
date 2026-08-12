<?php

use App\Controladores\ProductoController;

Flight::route('GET /productos', [ProductoController::class, 'index']);
Flight::route('GET /productos/@id', [ProductoController::class, 'show']);
Flight::route('POST /productos', [ProductoController::class, 'store']);
Flight::route('PUT /productos/@id', [ProductoController::class, 'update']);
Flight::route('DELETE /productos/@id', [ProductoController::class, 'destroy']);
