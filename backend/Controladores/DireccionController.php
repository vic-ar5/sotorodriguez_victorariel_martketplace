<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\DireccionModel;
use App\util\AuthGuard;
use App\util\Http;

class DireccionController
{
    private DireccionModel $modelo;

    public function __construct()
    {
        $this->modelo = new DireccionModel();
    }

    public function index(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPersona = $this->modelo->ConsultarIdPersonaPorUsuario((int) $usuario['sub']);

        if ($idPersona === null) {
            Flight::json([]);
            return;
        }

        Flight::json($this->modelo->ConsultarDireccionesDelUsuario($idPersona));
    }

    public function crear(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPersona = $this->modelo->ConsultarIdPersonaPorUsuario((int) $usuario['sub']);

        if ($idPersona === null) {
            Flight::json(['error' => 'El usuario no tiene datos personales'], 422);
            return;
        }

        $validados = $this->validarDatos(Http::bodyTodo());

        if (isset($validados['error'])) {
            Flight::json(['error' => $validados['error']], 422);
            return;
        }

        $direccion = $this->modelo->CrearDireccion($idPersona, $validados);

        if ($direccion === null) {
            Flight::json(['error' => 'No se pudo guardar la dirección'], 422);
            return;
        }

        Flight::json($direccion, 201);
    }

    public function actualizar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPersona = $this->modelo->ConsultarIdPersonaPorUsuario((int) $usuario['sub']);

        if ($idPersona === null) {
            Flight::json(['error' => 'El usuario no tiene datos personales'], 422);
            return;
        }

        $validados = $this->validarDatos(Http::bodyTodo());

        if (isset($validados['error'])) {
            Flight::json(['error' => $validados['error']], 422);
            return;
        }

        $direccion = $this->modelo->ActualizarDireccion(
            $idPersona,
            (int) Http::param('id'),
            $validados,
        );

        if ($direccion === null) {
            Flight::json(['error' => 'La dirección no existe'], 404);
            return;
        }

        Flight::json($direccion);
    }

    public function eliminar(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPersona = $this->modelo->ConsultarIdPersonaPorUsuario((int) $usuario['sub']);

        if ($idPersona === null) {
            Flight::json(['error' => 'La dirección no existe'], 404);
            return;
        }

        if (!$this->modelo->EliminarDireccion($idPersona, (int) Http::param('id'))) {
            Flight::json(['error' => 'La dirección no existe'], 404);
            return;
        }

        Flight::json(['mensaje' => 'Dirección eliminada']);
    }

    public function establecerPrincipal(): void
    {
        $usuario = AuthGuard::requireRol('comprador');

        $idPersona = $this->modelo->ConsultarIdPersonaPorUsuario((int) $usuario['sub']);

        if ($idPersona === null) {
            Flight::json(['error' => 'La dirección no existe'], 404);
            return;
        }

        if (!$this->modelo->EstablecerPrincipal($idPersona, (int) Http::param('id'))) {
            Flight::json(['error' => 'La dirección no existe'], 404);
            return;
        }

        Flight::json(['mensaje' => 'Dirección principal actualizada']);
    }

    /**
     * Catálogo de estados de la república (respaldo para captura manual).
     */
    public function estados(): void
    {
        Flight::json($this->modelo->ConsultarEstados());
    }

    /**
     * Asentamientos (colonias) por código postal, consultados del catálogo
     * local codigos_postales (sin API externa).
     */
    public function coloniasPorCodigoPostal(): void
    {
        $codigoPostal = trim((string) Http::query('codigo_postal', ''));

        if (!preg_match('/^[0-9]{5}$/', $codigoPostal)) {
            Flight::json(['error' => 'El código postal debe tener 5 dígitos'], 422);
            return;
        }

        $colonias = $this->modelo->ConsultarColoniasPorCodigoPostal($codigoPostal);

        Flight::json([
            'codigo_postal' => $codigoPostal,
            'asentamientos' => $colonias,
        ]);
    }

    /**
     * Valida y normaliza los campos de una dirección.
     */
    private function validarDatos(array $datos): array
    {
        $normalizados = [];

        foreach (['nombre', 'calle', 'numero_exterior', 'colonia', 'municipio'] as $campo) {
            $valor = trim((string) ($datos[$campo] ?? ''));
            if ($valor === '') {
                return ['error' => ucfirst(str_replace('_', ' ', $campo)) . ' es obligatorio'];
            }
            $normalizados[$campo] = $valor;
        }

        $normalizados['numero_interior'] = trim((string) ($datos['numero_interior'] ?? '')) ?: null;

        $codigoPostal = trim((string) ($datos['codigo_postal'] ?? ''));

        if (!preg_match('/^[0-9]{5}$/', $codigoPostal)) {
            return ['error' => 'El código postal debe tener 5 dígitos'];
        }

        $normalizados['codigo_postal'] = $codigoPostal;

        $estado = trim((string) ($datos['estado'] ?? ''));
        $idEstado = (int) ($datos['id_estado'] ?? 0);

        if ($idEstado >= 1) {
            $normalizados['id_estado'] = $idEstado;
        } elseif ($estado !== '') {
            $idResuelto = $this->modelo->ConsultarEstadoPorNombre($estado);
            if ($idResuelto === null) {
                return ['error' => "El estado '$estado' no es válido"];
            }
            $normalizados['id_estado'] = $idResuelto;
        } else {
            return ['error' => 'El estado es obligatorio'];
        }

        $normalizados['es_principal'] = Http::esVerdadero($datos['es_principal'] ?? false);

        return $normalizados;
    }
}
