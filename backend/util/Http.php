<?php

declare(strict_types=1);

namespace App\util;

use Flight;

/**
 * Accesos cómodos a parámetros de ruta, query string y cuerpo JSON.
 */
class Http
{
    public static function param(string $nombre, $porDefecto = null)
    {
        $params = Flight::router()->executedRoute->params ?? [];

        return $params[$nombre] ?? $porDefecto;
    }

    public static function query(string $nombre, $porDefecto = null)
    {
        return Flight::request()->query[$nombre] ?? $porDefecto;
    }

    public static function body(string $nombre, $porDefecto = null)
    {
        return Flight::request()->data[$nombre] ?? $porDefecto;
    }

    public static function bodyTodo(): array
    {
        return Flight::request()->data->getData();
    }

    /**
     * Interpreta un valor booleano que puede llegar como true/false, 't'/'f', '1'/'0'.
     */
    public static function esVerdadero($valor): bool
    {
        return $valor === true
            || $valor === 1
            || $valor === '1'
            || $valor === 't'
            || $valor === 'true';
    }
}
