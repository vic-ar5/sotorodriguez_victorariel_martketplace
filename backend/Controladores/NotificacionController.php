<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\NotificacionModel;
use App\util\AuthGuard;

class NotificacionController
{
    private NotificacionModel $modelo;

    public function __construct()
    {
        $this->modelo = new NotificacionModel();
    }

    /**
     * Notificaciones del comprador autenticado con el total de no leídas.
     */
    public function index(): void
    {
        $usuario = AuthGuard::requireRol('comprador');
        $idUsuario = (int) $usuario['sub'];

        Flight::json([
            'no_leidas'      => $this->modelo->ContarNoLeidas($idUsuario),
            'notificaciones' => $this->modelo->Consultar($idUsuario),
        ]);
    }

    /**
     * Marca como leídas todas las notificaciones del comprador.
     */
    public function leer(): void
    {
        $usuario = AuthGuard::requireRol('comprador');
        $this->modelo->MarcarTodasLeidas((int) $usuario['sub']);

        Flight::json(['mensaje' => 'Notificaciones leídas']);
    }
}
