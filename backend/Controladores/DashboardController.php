<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\PedidoModel;
use App\modelos\ProductoModel;
use App\util\AuthGuard;

class DashboardController
{
    private ProductoModel $productos;
    private PedidoModel $pedidos;

    public function __construct()
    {
        $this->productos = new ProductoModel();
        $this->pedidos = new PedidoModel();
    }

    /**
     * Resumen del panel administrativo: solo los productos que subió el
     * administrador logueado y los pedidos que los incluyen.
     */
    public function index(): void
    {
        $usuario = AuthGuard::requireRol('administrador');

        Flight::json([
            'productos' => $this->productos->ConsultarResumenDelVendedor((int) $usuario['sub']),
            'pedidos'   => $this->pedidos->ConsultarResumenDePedidosDelVendedor((int) $usuario['sub']),
        ]);
    }
}
