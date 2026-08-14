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
            return;
        }

        $idPedido = $this->modelo->GenerarPedido((int) $usuario['sub'], $idDireccion);

        if ($idPedido === null) {
            Flight::json(['error' => 'El carrito está vacío o la dirección no es válida'], 422);
            return;
        }

        Flight::json(['mensaje' => 'Pedido generado', 'id_pedido' => $idPedido], 201);
    }

    public function mios(): void
    {
        $usuario = AuthGuard::requireRol('comprador');
        Flight::json($this->modelo->ConsultarPedidosDelComprador((int) $usuario['sub']));
    }

    public function confirmarPago(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPedido = (int) Http::param('id');

        if ($idPedido < 1) {
            Flight::json(['error' => 'Id de pedido inválido'], 422);
            return;
        }

        $resultado = $this->modelo->ConfirmarPago((int) $usuario['sub'], $idPedido);

        if (!$resultado['ok']) {
            if ($resultado['error'] === 'no_encontrado') {
                Flight::json(['error' => 'Pedido no encontrado'], 404);
                return;
            }

            Flight::json(['error' => 'El pedido no se puede confirmar'], 422);
            return;
        }

        Flight::json(['mensaje' => 'Pago confirmado']);
    }

    public function cancelar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPedido = (int) Http::param('id');

        if ($idPedido < 1) {
            Flight::json(['error' => 'Id de pedido inválido'], 422);
            return;
        }

        $resultado = $this->modelo->CancelarPedido((int) $usuario['sub'], $idPedido);

        if (!$resultado['ok']) {
            if ($resultado['error'] === 'no_encontrado') {
                Flight::json(['error' => 'Pedido no encontrado'], 404);
                return;
            }

            Flight::json(['error' => 'El pedido ya no se puede cancelar'], 422);
            return;
        }

        Flight::json(['mensaje' => 'Pedido cancelado']);
    }

    public function detallePropio(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPedido = (int) Http::param('id');
        $pedido = $this->modelo->ConsultarDetalleDePedidoDelComprador((int) $usuario['sub'], $idPedido);

        if ($pedido === null) {
            Flight::json(['error' => 'Pedido no encontrado'], 404);
            return;
        }

        Flight::json($pedido);
    }

    public function todos(): void
    {
        AuthGuard::requireRol('administrador');

        $estado = Http::query('estado', '');
        $usuario = Http::query('usuario', '');
        $folio = Http::query('folio', '');

        Flight::json($this->modelo->ConsultarTodosLosPedidos($estado, $usuario, $folio));
    }

    public function detalleAdmin(): void
    {
        AuthGuard::requireRol('administrador');

        $idPedido = (int) Http::param('id');

        if ($idPedido < 1) {
            Flight::json(['error' => 'Id de pedido inválido'], 422);
            return;
        }

        $pedido = $this->modelo->ConsultarDetalleDePedidoAdmin($idPedido);

        if ($pedido === null) {
            Flight::json(['error' => 'Pedido no encontrado'], 404);
            return;
        }

        Flight::json($pedido);
    }

    public function cambiarEstado(): void
    {
        AuthGuard::requireRol('administrador');

        $idPedido = (int) Http::param('id');
        $estado = trim((string) Http::body('estado', ''));

        if ($idPedido < 1) {
            Flight::json(['error' => 'Id de pedido inválido'], 422);
            return;
        }

        if ($estado === '') {
            Flight::json(['error' => 'El campo estado es obligatorio'], 422);
            return;
        }

        $resultado = $this->modelo->ActualizarEstadoDelPedido($idPedido, $estado);

        if (!$resultado['ok']) {
            if ($resultado['error'] === 'no_encontrado') {
                Flight::json(['error' => 'Pedido no encontrado'], 404);
                return;
            }

            if ($resultado['error'] === 'transicion_invalida') {
                Flight::json([
                    'error' => 'No se puede pasar el pedido a ' . $estado
                        . ' desde el estado ' . ($resultado['estado'] ?? 'actual'),
                ], 422);
                return;
            }

            Flight::json(['error' => 'Estado inválido'], 422);
            return;
        }

        Flight::json(['mensaje' => 'Estado del pedido actualizado']);
    }

    public function confirmarEntrega(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPedido = (int) Http::param('id');

        if ($idPedido < 1) {
            Flight::json(['error' => 'Id de pedido inválido'], 422);
            return;
        }

        $resultado = $this->modelo->ConfirmarEntrega((int) $usuario['sub'], $idPedido);

        if (!$resultado['ok']) {
            if ($resultado['error'] === 'no_encontrado') {
                Flight::json(['error' => 'Pedido no encontrado'], 404);
                return;
            }

            Flight::json(['error' => 'El pedido aún no está enviado o ya fue entregado'], 422);
            return;
        }

        Flight::json(['mensaje' => 'Entrega confirmada']);
    }
}
