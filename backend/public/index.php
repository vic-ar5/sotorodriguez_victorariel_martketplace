<?php

declare(strict_types=1);

use PDO;
use Flight;

require dirname(__DIR__) . '/vendor/autoload.php';

function cargarEnv(string $archivo): void
{
    if (!is_file($archivo)) {
        return;
    }

    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#') {
            continue;
        }

        [$clave, $valor] = array_pad(explode('=', $linea, 2), 2, '');
        $clave = trim($clave);
        $valor = trim($valor, " \t\n\r\0\x0B\"'");

        putenv($clave . '=' . $valor);
        $_ENV[$clave] = $valor;
    }
}

cargarEnv(dirname(__DIR__, 2) . '/.env');

Flight::register('db', PDO::class, [
    sprintf(
        '%s:host=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_CONNECTION') ?: 'mysql',
        getenv('DB_HOST') ?: 'localhost',
        getenv('DB_NAME') ?: 'marketplace',
    ),
    getenv('DB_USER') ?: 'root',
    getenv('DB_PASS') ?: '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
]);

Flight::before('start', function () {
    Flight::response()
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

    if (Flight::request()->method === 'OPTIONS') {
        Flight::response()->status(204)->send();
        Flight::stop();
    }
});

require dirname(__DIR__) . '/ruta/rutas.php';

Flight::start();
