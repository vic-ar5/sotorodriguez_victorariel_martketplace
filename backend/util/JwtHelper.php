<?php

declare(strict_types=1);

namespace App\util;

/**
 * Genera y verifica tokens JWT (HS256) sin dependencias externas.
 */
class JwtHelper
{
    private static function base64UrlEncode(string $dato): string
    {
        return rtrim(strtr(base64_encode($dato), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $dato): string
    {
        return base64_decode(strtr($dato, '-_', '+/'), true) ?: '';
    }

    private static function secreto(): string
    {
        return getenv('JWT_SECRET') ?: 'cambiar_por_un_secreto_largo_y_aleatorio';
    }

    public static function generar(array $datos): string
    {
        $ahora = time();

        $cabecera = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = self::base64UrlEncode(json_encode(array_merge([
            'iat' => $ahora,
            'exp' => $ahora + (int) (getenv('JWT_TTL') ?: 3600),
            'iss' => 'marketplace-mx',
        ], $datos)));

        $firma = self::base64UrlEncode(
            hash_hmac('sha256', "$cabecera.$payload", self::secreto(), true)
        );

        return "$cabecera.$payload.$firma";
    }

    public static function verificar(string $token): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        [$cabecera, $payload, $firma] = $partes;

        $firmaEsperada = self::base64UrlEncode(
            hash_hmac('sha256', "$cabecera.$payload", self::secreto(), true)
        );

        if (!hash_equals($firma, $firmaEsperada)) {
            return null;
        }

        $datos = json_decode(self::base64UrlDecode($payload), true);
        if (!is_array($datos)) {
            return null;
        }

        if (isset($datos['exp']) && (int) $datos['exp'] < time()) {
            return null;
        }

        return $datos;
    }
}
