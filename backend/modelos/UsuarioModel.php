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
    public function index(?bool $activo): array
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
            $params['activo'] = $activo;
        }

        $sql .= " ORDER BY u.fecha_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
