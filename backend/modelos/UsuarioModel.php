<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;

class UsuarioModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    /**
     * Consulta administrativa de usuarios. $activo = true/false/null (todos).
     */
    public function ConsultarTodosLosUsuarios(?bool $activo): array
    {
        $sql = "SELECT u.id_usuario,
                       u.nombre_usuario,
                       u.correo,
                       u.activo,
                       r.nombre AS rol,
                       p.nombre,
                       p.apellido_paterno,
                       p.apellido_materno,
                       p.telefono,
                       u.fecha_registro
                FROM usuario u
                JOIN roles r    ON u.id_rol = r.id_rol
                LEFT JOIN persona p ON u.id_usuario = p.id_usuario";

        $params = [];

        if ($activo !== null) {
            $sql .= " WHERE u.activo = :activo";
            $params['activo'] = (int) $activo;
        }

        $sql .= " ORDER BY u.fecha_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Perfil del usuario actual (datos de cuenta + persona).
     */
    public function ConsultarMiPerfil(int $idUsuario): ?array
    {
        $sql = "SELECT u.id_usuario,
                       u.nombre_usuario,
                       u.correo,
                       r.nombre AS rol,
                       p.nombre,
                       p.apellido_paterno,
                       p.apellido_materno,
                       p.telefono
                FROM usuario u
                JOIN roles r       ON u.id_rol = r.id_rol
                LEFT JOIN persona p ON u.id_usuario = p.id_usuario
                WHERE u.id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Actualiza los datos personales del usuario actual.
     */
    public function ActualizarMiPerfil(int $idUsuario, array $datos): bool
    {
        $permitidos = ['nombre', 'apellido_paterno', 'apellido_materno', 'telefono'];
        $asignaciones = [];
        $params = ['id_usuario' => $idUsuario];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = "$campo = :$campo";
                $params[$campo] = trim((string) ($datos[$campo] ?? ''));
            }
        }

        if ($asignaciones === []) {
            return false;
        }

        $sql = "UPDATE persona SET " . implode(', ', $asignaciones)
            . " WHERE id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }
}
