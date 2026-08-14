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
        $auth = self::cabeceraAutorizacion();

        if ($auth !== '' && preg_match('/Bearer\s+(.+)/i', $auth, $coincidencias) === 1) {
            return trim($coincidencias[1]);
        }

        return null;
    }

    /**
     * Lee el encabezado Authorization desde las fuentes que usa cada
     * servidor web. Apache con mod_rewrite (rewrites vía .htaccess) o
     * PHP-CGI/FastCGI no siempre deja HTTP_AUTHORIZATION en $_SERVER.
     */
    private static function cabeceraAutorizacion(): string
    {
        $servidor = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($servidor !== '') {
            return (string) $servidor;
        }

        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() as $clave => $valor) {
                if (strcasecmp((string) $clave, 'Authorization') === 0) {
                    return (string) $valor;
                }
            }
        }

        return '';
    }

    /**
     * Exige un token válido y un rol específico. Devuelve el payload JWT.
     */
    public static function requireRol(string $rol): array
    {
        $token = self::tokenActual();

        if ($token === null) {
            Flight::json(['error' => 'No autorizado'], 401);
            exit;
        }

        $payload = JwtHelper::verificar($token);

        if ($payload === null) {
            Flight::json(['error' => 'Token inválido o expirado'], 401);
            exit;
        }

        if (($payload['rol'] ?? null) !== $rol) {
            Flight::json(['error' => 'Acceso denegado para este rol'], 403);
            exit;
        }

        if (($payload['activo'] ?? false) !== true) {
            Flight::json(['error' => 'Usuario inactivo'], 403);
            exit;
        }

        return $payload;
    }
}
