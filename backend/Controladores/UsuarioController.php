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
}
