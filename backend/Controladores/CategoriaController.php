<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\CategoriaModel;
use App\util\AuthGuard;
use App\util\Http;

class CategoriaController
{
    private CategoriaModel $modelo;

    public function __construct()
    {
        $this->modelo = new CategoriaModel();
    }

    /**
     * Catálogo público: solo categorías activas.
     */
    public function index(): void
    {
        Flight::json($this->modelo->ConsultarCategorias(true));
    }

    public function adminIndex(): void
    {
        AuthGuard::requireRol('administrador');
        Flight::json($this->modelo->ConsultarCategorias(false));
    }

    public function crear(): void
    {
        AuthGuard::requireRol('administrador');

        $datos = Http::bodyTodo();

        if (trim((string) ($datos['nombre'] ?? '')) === '') {
            Flight::json(['error' => 'El nombre de la categoría es obligatorio'], 422);
            return;
        }

        $idCategoria = $this->modelo->RegistrarCategoria($datos);
        Flight::json(['mensaje' => 'Categoría creada', 'id_categoria' => $idCategoria], 201);
    }

    public function actualizar(): void
    {
        AuthGuard::requireRol('administrador');

        $idCategoria = (int) Http::param('id');
        $datos = Http::bodyTodo();

        if (!$this->modelo->ModificarCategoria($idCategoria, $datos)) {
            Flight::json(['error' => 'Categoría no encontrada'], 404);
            return;
        }

        Flight::json(['mensaje' => 'Categoría actualizada']);
    }

    public function cambiarEstado(): void
    {
        AuthGuard::requireRol('administrador');

        $idCategoria = (int) Http::param('id');
        $activo = Http::esVerdadero(Http::body('activo'));

        if (!$this->modelo->CambiarEstadoDeCategoria($idCategoria, $activo)) {
            Flight::json(['error' => 'Categoría no encontrada'], 404);
            return;
        }

        Flight::json(['mensaje' => $activo ? 'Categoría activada' : 'Categoría desactivada']);
    }
}
