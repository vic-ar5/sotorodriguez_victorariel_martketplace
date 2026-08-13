<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;
use Throwable;

class AuthModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    /**
     * Registra un usuario con rol comprador y su persona asociada.
     */
    public function registrar(array $datos): int
    {
        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO usuario (id_rol, nombre_usuario, correo, contrasena_hash, activo)
                    SELECT id_rol, :nombre_usuario, :correo, :contrasena_hash, TRUE
                    FROM roles
                    WHERE nombre = 'comprador'
                    RETURNING id_usuario";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre_usuario' => trim($datos['nombre_usuario']),
                'correo'         => strtolower(trim($datos['correo'])),
                'contrasena_hash' => password_hash($datos['contrasena'], PASSWORD_BCRYPT),
            ]);

            $idUsuario = (int) $stmt->fetchColumn();

            $sql = "INSERT INTO persona (id_usuario, nombre, apellido_paterno, apellido_materno, telefono)
                    VALUES (:id_usuario, :nombre, :apellido_paterno, :apellido_materno, :telefono)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id_usuario'       => $idUsuario,
                'nombre'           => trim($datos['nombre']),
                'apellido_paterno' => trim($datos['apellido_paterno']),
                'apellido_materno' => trim((string) ($datos['apellido_materno'] ?? '')),
                'telefono'         => trim((string) ($datos['telefono'] ?? '')),
            ]);

            $this->db->commit();

            return $idUsuario;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function porCorreo(string $correo): ?array
    {
        $sql = "SELECT u.id_usuario,
                       u.contrasena_hash,
                       r.nombre AS rol,
                       u.activo
                FROM usuario u
                JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.correo = :correo";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['correo' => strtolower(trim($correo))]);

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }
}
