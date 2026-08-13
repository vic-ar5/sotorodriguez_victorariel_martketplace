<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\PedidoModel;
use App\util\AuthGuard;
use App\util\Http;

class PedidoController
{
    private PedidoModel $modelo;

    public function __construct()
    {
        $this->modelo = new PedidoModel();
    }

    /**
     * Genera un pedido a partir del carrito activo del comprador.
     */
    public function crear(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idDireccion = (int) Http::body('id_direccion', 0);

        if ($idDireccion < 1) {
            Flight::json(['error' => 'id_direccion es obligatorio'], 422);
            Flight::stop();
        }

        $idPedido = $this->modelo->generar((int) $usuario['sub'], $idDireccion);

        if ($idPedido === null) {
            Flight::json(['error' => 'El carrito está vacío o la dirección no es válida'], 422);
            Flight::stop();
        }

        Flight::json(['mensaje' => 'Pedido generado', 'id_pedido' => $idPedido], 201);
    }

    public function mios(): void
    {
        $usuario = AuthGuard::requireRol('comprador');
        Flight::json($this->modelo->mios((int) $usuario['sub']));
    }

    public function detallePropio(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPedido = (int) Http::param('id');
        $pedido = $this->modelo->detalleDeUsuario((int) $usuario['sub'], $idPedido);

        if ($pedido === null) {
            Flight::json(['error' => 'Pedido no encontrado'], 404);
            Flight::stop();
        }

        Flight::json($pedido);
    }

    public function todos(): void
    {
        AuthGuard::requireRol('administrador');

        $estado = Http::query('estado');

        $idEstado = null;
        if ($estado !== null && $estado !== '') {
            $idEstado = (int) $estado;
        }

        Flight::json($this->modelo->todos($idEstado));
    }

    public function cambiarEstado(): void
    {
        AuthGuard::requireRol('administrador');

        $idPedido = (int) Http::param('id');
        $idEstado = (int) Http::body('id_estado_pedido', 0);

        if ($idEstado < 1 || $idEstado > 6) {
            Flight::json(['error' => 'id_estado_pedido inválido (1 a 6)'], 422);
            Flight::stop();
        }

        if (!$this->modelo->cambiarEstado($idPedido, $idEstado)) {
            Flight::json(['error' => 'Pedido no encontrado'], 404);
            Flight::stop();
        }

        Flight::json(['mensaje' => 'Estado del pedido actualizado']);
    }
}
