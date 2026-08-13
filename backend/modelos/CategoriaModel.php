<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;

class CategoriaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    public function index(bool $soloActivas = true): array
    {
        $sql = "SELECT id_categoria, nombre, descripcion, activo
                FROM categorias";

        if ($soloActivas) {
            $sql .= " WHERE activo = TRUE";
        }

        $sql .= " ORDER BY nombre";

        $stmt = $this->db->query($sql);

        return $stmt->fetchAll();
    }

    public function crear(array $datos): int
    {
        $sql = "INSERT INTO categorias (nombre, descripcion)
                VALUES (:nombre, :descripcion)
                RETURNING id_categoria";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nombre'      => trim($datos['nombre']),
            'descripcion' => trim((string) ($datos['descripcion'] ?? '')),
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function actualizar(int $idCategoria, array $datos): bool
    {
        $sql = "UPDATE categorias
                SET nombre      = :nombre,
                    descripcion = :descripcion
                WHERE id_categoria = :id_categoria";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'nombre'       => trim($datos['nombre']),
            'descripcion'  => trim((string) ($datos['descripcion'] ?? '')),
            'id_categoria' => $idCategoria,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function cambiarEstado(int $idCategoria, bool $activo): bool
    {
        $sql = "UPDATE categorias SET activo = :activo WHERE id_categoria = :id_categoria";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'activo'       => $activo,
            'id_categoria' => $idCategoria,
        ]);

        return $stmt->rowCount() > 0;
    }
}
