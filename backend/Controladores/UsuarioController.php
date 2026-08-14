<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\UsuarioModel;
use App\util\AuthGuard;
use App\util\Http;

class UsuarioController
{
    private UsuarioModel $modelo;

    public function __construct()
    {
        $this->modelo = new UsuarioModel();
    }

    /**
     * Consulta administrativa de usuarios.
     * ?activo=1 | 0 | (vacío = todos)
     */
    public function index(): void
    {
        AuthGuard::requireRol('administrador');

        $activoParam = Http::query('activo');

        $activo = null;
        if ($activoParam !== null && $activoParam !== '') {
            $activo = Http::esVerdadero($activoParam);
        }

        Flight::json($this->modelo->ConsultarTodosLosUsuarios($activo));
    }

    /**
     * Perfil del comprador autenticado (requiere token con rol comprador).
     */
    public function miPerfil(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $perfil = $this->modelo->ConsultarMiPerfil((int) $usuario['sub']);

        if ($perfil === null) {
            Flight::json(['error' => 'Usuario no encontrado'], 404);
            return;
        }

        Flight::json($perfil);
    }

    /**
     * Actualiza los datos personales del comprador autenticado.
     */
    public function actualizarMiPerfil(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $datos = Http::bodyTodo();

        if (!$this->modelo->ActualizarMiPerfil((int) $usuario['sub'], $datos)) {
            Flight::json(['error' => 'No se pudo actualizar el perfil'], 422);
            return;
        }

        Flight::json(['mensaje' => 'Perfil actualizado']);
    }
}
