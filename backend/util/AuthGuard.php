<?php

declare(strict_types=1);

namespace App\util;

use Flight;

/**
 * Guardas de autenticación para rutas protegidas.
 */
class AuthGuard
{
    public static function tokenActual(): ?string
    {
        $auth = Flight::request()->getHeader('Authorization');

        if ($auth !== '' && preg_match('/Bearer\s+(.+)/i', $auth, $coincidencias) === 1) {
            return trim($coincidencias[1]);
        }

        return null;
    }

    /**
     * Exige un token válido y un rol específico. Devuelve el payload JWT.
     */
    public static function requireRol(string $rol): array
    {
        $token = self::tokenActual();

        if ($token === null) {
            Flight::json(['error' => 'No autorizado'], 401);
            Flight::stop();
        }

        $payload = JwtHelper::verificar($token);

        if ($payload === null) {
            Flight::json(['error' => 'Token inválido o expirado'], 401);
            Flight::stop();
        }

        if (($payload['rol'] ?? null) !== $rol) {
            Flight::json(['error' => 'Acceso denegado para este rol'], 403);
            Flight::stop();
        }

        if (($payload['activo'] ?? false) !== true) {
            Flight::json(['error' => 'Usuario inactivo'], 403);
            Flight::stop();
        }

        return $payload;
    }
}
