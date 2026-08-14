<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;

class DireccionModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    /**
     * id_persona asociado al usuario, o null si no tiene datos personales.
     */
    public function ConsultarIdPersonaPorUsuario(int $idUsuario): ?int
    {
        $sql = "SELECT id_persona FROM persona WHERE id_usuario = :id_usuario";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_usuario' => $idUsuario]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Direcciones del usuario con el nombre del estado.
     */
    public function ConsultarDireccionesDelUsuario(int $idPersona): array
    {
        $sql = "SELECT d.id_direccion, d.nombre, d.calle, d.numero_exterior,
                       d.numero_interior, d.colonia, d.codigo_postal, d.municipio,
                       e.nombre AS estado, d.pais, d.es_principal
                FROM direcciones d
                JOIN estados_mexico e ON d.id_estado = e.id_estado
                WHERE d.id_persona = :id_persona
                ORDER BY d.es_principal DESC, d.fecha_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_persona' => $idPersona]);

        return $stmt->fetchAll();
    }

    /**
     * Una dirección del usuario o null si no existe / no le pertenece.
     */
    public function ConsultarDireccionDelUsuario(int $idPersona, int $idDireccion): ?array
    {
        $sql = "SELECT d.id_direccion, d.nombre, d.calle, d.numero_exterior,
                       d.numero_interior, d.colonia, d.codigo_postal, d.municipio,
                       e.nombre AS estado, d.pais, d.es_principal
                FROM direcciones d
                JOIN estados_mexico e ON d.id_estado = e.id_estado
                WHERE d.id_persona = :id_persona AND d.id_direccion = :id_direccion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_persona' => $idPersona, 'id_direccion' => $idDireccion]);

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Crea una dirección para el usuario. La primera dirección queda como
     * principal automáticamente. Devuelve la dirección creada o null.
     */
    public function CrearDireccion(int $idPersona, array $datos): ?array
    {
        $esPrincipal = (bool) ($datos['es_principal'] ?? false);
        $esPrimera = $this->CuentaDirecciones($idPersona) === 0;

        $this->db->beginTransaction();

        try {
            if ($esPrincipal || $esPrimera) {
                $this->QuitarPrincipal($idPersona);
            }

            $sql = "INSERT INTO direcciones
                        (id_persona, nombre, calle, numero_exterior, numero_interior,
                         colonia, codigo_postal, municipio, id_estado, es_principal)
                    VALUES
                        (:id_persona, :nombre, :calle, :numero_exterior, :numero_interior,
                         :colonia, :codigo_postal, :municipio, :id_estado, :es_principal)
                    RETURNING id_direccion";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id_persona'      => $idPersona,
                'nombre'          => $datos['nombre'],
                'calle'           => $datos['calle'],
                'numero_exterior' => $datos['numero_exterior'],
                'numero_interior' => $datos['numero_interior'] ?? null,
                'colonia'         => $datos['colonia'],
                'codigo_postal'   => $datos['codigo_postal'],
                'municipio'       => $datos['municipio'],
                'id_estado'       => (int) $datos['id_estado'],
                'es_principal'    => $esPrincipal || $esPrimera ? 't' : 'f',
            ]);

            $idDireccion = (int) $stmt->fetchColumn();

            $this->db->commit();

            return $this->ConsultarDireccionDelUsuario($idPersona, $idDireccion);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza una dirección del usuario y devuelve la dirección actualizada.
     */
    public function ActualizarDireccion(int $idPersona, int $idDireccion, array $datos): ?array
    {
        $actual = $this->ConsultarDireccionDelUsuario($idPersona, $idDireccion);

        if ($actual === null) {
            return null;
        }

        $esPrincipal = (bool) ($datos['es_principal'] ?? false);

        $this->db->beginTransaction();

        try {
            if ($esPrincipal) {
                $this->QuitarPrincipal($idPersona);
            }

            $sql = "UPDATE direcciones
                    SET nombre = :nombre,
                        calle = :calle,
                        numero_exterior = :numero_exterior,
                        numero_interior = :numero_interior,
                        colonia = :colonia,
                        codigo_postal = :codigo_postal,
                        municipio = :municipio,
                        id_estado = :id_estado,
                        es_principal = :es_principal
                    WHERE id_persona = :id_persona AND id_direccion = :id_direccion";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre'          => $datos['nombre'],
                'calle'           => $datos['calle'],
                'numero_exterior' => $datos['numero_exterior'],
                'numero_interior' => $datos['numero_interior'] ?? null,
                'colonia'         => $datos['colonia'],
                'codigo_postal'   => $datos['codigo_postal'],
                'municipio'       => $datos['municipio'],
                'id_estado'       => (int) $datos['id_estado'],
                'es_principal'    => $esPrincipal ? 't' : ($actual['es_principal'] ? 't' : 'f'),
                'id_persona'      => $idPersona,
                'id_direccion'    => $idDireccion,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->ConsultarDireccionDelUsuario($idPersona, $idDireccion);
    }

    /**
     * Elimina una dirección del usuario. Si era la principal, deja otra como principal.
     */
    public function EliminarDireccion(int $idPersona, int $idDireccion): bool
    {
        $actual = $this->ConsultarDireccionDelUsuario($idPersona, $idDireccion);

        if ($actual === null) {
            return false;
        }

        $eraPrincipal = (bool) $actual['es_principal'];

        $sql = "DELETE FROM direcciones
                WHERE id_persona = :id_persona AND id_direccion = :id_direccion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_persona' => $idPersona, 'id_direccion' => $idDireccion]);

        if ($eraPrincipal && $this->CuentaDirecciones($idPersona) > 0) {
            $sql = "UPDATE direcciones
                    SET es_principal = 't'
                    WHERE id_direccion = (
                        SELECT id_direccion FROM direcciones
                        WHERE id_persona = :id_persona
                        ORDER BY fecha_registro DESC
                        LIMIT 1
                    )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_persona' => $idPersona]);
        }

        return true;
    }

    /**
     * Marca una dirección como principal y quita el estatus a las demás.
     */
    public function EstablecerPrincipal(int $idPersona, int $idDireccion): bool
    {
        if ($this->ConsultarDireccionDelUsuario($idPersona, $idDireccion) === null) {
            return false;
        }

        $this->db->beginTransaction();

        try {
            $this->QuitarPrincipal($idPersona);

            $sql = "UPDATE direcciones SET es_principal = 't'
                    WHERE id_persona = :id_persona AND id_direccion = :id_direccion";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id_persona' => $idPersona, 'id_direccion' => $idDireccion]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Resuelve el id del estado a partir de su nombre (sin distinguir mayúsculas).
     */
    public function ConsultarEstadoPorNombre(string $nombre): ?int
    {
        $sql = "SELECT id_estado FROM estados_mexico WHERE LOWER(nombre) = LOWER(:nombre)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['nombre' => trim($nombre)]);

        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Catálogo de estados de la república.
     */
    public function ConsultarEstados(): array
    {
        $sql = "SELECT id_estado, nombre FROM estados_mexico ORDER BY nombre";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Asentamientos (colonias) del catálogo local para un código postal.
     */
    public function ConsultarColoniasPorCodigoPostal(string $codigoPostal): array
    {
        $sql = "SELECT c.codigo_postal, c.colonia, c.municipio, e.nombre AS estado
                FROM codigos_postales c
                JOIN estados_mexico e ON c.id_estado = e.id_estado
                WHERE c.codigo_postal = :codigo_postal
                ORDER BY c.colonia";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['codigo_postal' => $codigoPostal]);

        return $stmt->fetchAll();
    }

    private function QuitarPrincipal(int $idPersona): void
    {
        $sql = "UPDATE direcciones SET es_principal = 'f' WHERE id_persona = :id_persona";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_persona' => $idPersona]);
    }

    private function CuentaDirecciones(int $idPersona): int
    {
        $sql = "SELECT COUNT(*) FROM direcciones WHERE id_persona = :id_persona";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_persona' => $idPersona]);

        return (int) $stmt->fetchColumn();
    }
}
