<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use PDOException;
use App\modelos\AuthModel;
use App\util\JwtHelper;

class AuthController
{
    private AuthModel $modelo;

    public function __construct()
    {
        $this->modelo = new AuthModel();
    }

    /**
     * Registro público: solo crea usuarios con rol comprador.
     */
    public function registro(): void
    {
        $datos = Flight::request()->data->getData();

        $requeridos = ['nombre_usuario', 'correo', 'contrasena', 'nombre', 'apellido_paterno'];
        $faltantes = [];

        foreach ($requeridos as $campo) {
            if (trim((string) ($datos[$campo] ?? '')) === '') {
                $faltantes[] = $campo;
            }
        }

        if ($faltantes !== []) {
            Flight::json(['error' => 'Campos requeridos faltantes', 'campos' => $faltantes], 422);
            return;
        }

        if (strlen($datos['contrasena']) < 8) {
            Flight::json(['error' => 'La contraseña debe tener al menos 8 caracteres'], 422);
            return;
        }

        try {
            $idUsuario = $this->modelo->RegistrarComprador($datos);
            Flight::json(['mensaje' => 'Registro exitoso', 'id_usuario' => $idUsuario], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                Flight::json(['error' => 'El nombre de usuario o correo ya está registrado'], 409);
            } else {
                Flight::json(['error' => 'No se pudo registrar el usuario'], 500);
            }
            return;
        }
    }

    /**
     * Inicio de sesión por correo y contraseña; devuelve un token JWT.
     */
    public function login(): void
    {
        $datos = Flight::request()->data->getData();

        $correo = strtolower(trim((string) ($datos['correo'] ?? '')));
        $contrasena = (string) ($datos['contrasena'] ?? '');

        if ($correo === '' || $contrasena === '') {
            Flight::json(['error' => 'Correo y contraseña son obligatorios'], 422);
            return;
        }

        $usuario = $this->modelo->ConsultarUsuarioPorCorreo($correo);

        if ($usuario === null || !password_verify($contrasena, $usuario['contrasena_hash'])) {
            Flight::json(['error' => 'Correo o contraseña incorrectos'], 401);
            return;
        }

        if (!self::boolActivo($usuario['activo'])) {
            Flight::json(['error' => 'Usuario inactivo'], 403);
            return;
        }

        $token = JwtHelper::generar([
            'sub'    => (int) $usuario['id_usuario'],
            'rol'    => $usuario['rol'],
            'activo' => true,
        ]);

        Flight::json([
            'token'      => $token,
            'id_usuario' => (int) $usuario['id_usuario'],
            'rol'        => $usuario['rol'],
        ]);
    }

    private static function boolActivo($valor): bool
    {
        return $valor === true
            || $valor === 1
            || $valor === '1'
            || $valor === 't'
            || $valor === 'true';
    }
}
