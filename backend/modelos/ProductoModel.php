<?php

declare(strict_types=1);

namespace App\modelos;

use PDO;
use Flight;
use Throwable;

class ProductoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Flight::db();
    }

    /**
     * Catálogo público con filtros combinados (todos opcionales).
     * id_categoria, precio_min, precio_max, nombre, disponibilidad y orden.
     */
    public function index(array $filtros = []): array
    {
        $sql = "SELECT p.id_producto,
                       p.nombre,
                       p.descripcion,
                       p.precio,
                       p.existencia,
                       p.moneda,
                       v.id_usuario      AS id_vendedor,
                       c.nombre          AS categoria,
                       i.url_publica     AS imagen
                FROM productos p
                JOIN usuario v    ON p.id_vendedor = v.id_usuario
                JOIN categorias c ON p.id_categoria = c.id_categoria
                LEFT JOIN imagenes i ON i.id_producto = p.id_producto AND i.es_principal = TRUE
                WHERE p.estado = 'activo'";

        $params = [];

        if (!empty($filtros['id_categoria'])) {
            $sql .= " AND p.id_categoria = :id_categoria";
            $params['id_categoria'] = (int) $filtros['id_categoria'];
        }

        if (isset($filtros['precio_min']) && $filtros['precio_min'] !== '') {
            $sql .= " AND p.precio >= :precio_min";
            $params['precio_min'] = (float) $filtros['precio_min'];
        }

        if (isset($filtros['precio_max']) && $filtros['precio_max'] !== '') {
            $sql .= " AND p.precio <= :precio_max";
            $params['precio_max'] = (float) $filtros['precio_max'];
        }

        if (!empty($filtros['nombre'])) {
            $sql .= " AND p.nombre ILIKE '%' || :nombre || '%'";
            $params['nombre'] = $filtros['nombre'];
        }

        if (!empty($filtros['disponibilidad'])) {
            if ($filtros['disponibilidad'] === 'disponible') {
                $sql .= " AND p.existencia > 0";
            } elseif ($filtros['disponibilidad'] === 'agotado') {
                $sql .= " AND p.existencia = 0";
            }
        }

        switch ($filtros['orden'] ?? '') {
            case 'precio_desc':
                $sql .= " ORDER BY p.precio DESC";
                break;
            case 'precio_asc':
                $sql .= " ORDER BY p.precio ASC";
                break;
            default:
                $sql .= " ORDER BY p.nombre ASC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function mostrar(int $idProducto): ?array
    {
        $sql = "SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.existencia, p.moneda,
                       p.fecha_registro, p.fecha_actualizacion,
                       c.nombre AS categoria,
                       v.nombre_usuario AS vendedor,
                       pv.nombre || ' ' || pv.apellido_paterno AS nombre_vendedor
                FROM productos p
                JOIN categorias c  ON p.id_categoria = c.id_categoria
                JOIN usuario  v    ON p.id_vendedor = v.id_usuario
                LEFT JOIN persona pv ON v.id_usuario = pv.id_usuario
                WHERE p.id_producto = :id_producto
                  AND p.estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_producto' => $idProducto]);

        $producto = $stmt->fetch();

        if ($producto === false) {
            return null;
        }

        $producto['imagenes'] = $this->imagenes($idProducto);

        return $producto;
    }

    public function imagenes(int $idProducto): array
    {
        $sql = "SELECT id_imagen, nombre_archivo, ruta_drive, url_publica, es_principal
                FROM imagenes
                WHERE id_producto = :id_producto
                ORDER BY es_principal DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_producto' => $idProducto]);

        return $stmt->fetchAll();
    }

    /**
     * Consulta administrativa: todos los productos o solo activos/inactivos.
     */
    public function adminIndex(?string $estado): array
    {
        $sql = "SELECT p.id_producto, p.nombre, p.precio, p.existencia, p.estado,
                       c.nombre AS categoria, p.fecha_registro, p.fecha_actualizacion
                FROM productos p
                JOIN categorias c ON p.id_categoria = c.id_categoria";

        $params = [];

        if ($estado === 'activo' || $estado === 'inactivo') {
            $sql .= " WHERE p.estado = :estado";
            $params['estado'] = $estado;
        }

        $sql .= " ORDER BY p.fecha_registro DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function crear(array $datos): int
    {
        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO productos
                        (id_vendedor, id_categoria, nombre, descripcion, precio, existencia, estado)
                    VALUES (:id_vendedor, :id_categoria, :nombre, :descripcion, :precio, :existencia, 'activo')
                    RETURNING id_producto";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id_vendedor'  => (int) $datos['id_vendedor'],
                'id_categoria' => (int) $datos['id_categoria'],
                'nombre'       => trim($datos['nombre']),
                'descripcion'  => trim((string) ($datos['descripcion'] ?? '')),
                'precio'       => (float) $datos['precio'],
                'existencia'   => (int) ($datos['existencia'] ?? 0),
            ]);

            $idProducto = (int) $stmt->fetchColumn();

            if (!empty($datos['imagen']) && is_array($datos['imagen'])) {
                $this->agregarImagen($idProducto, $datos['imagen']);
            }

            $this->db->commit();

            return $idProducto;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function agregarImagen(int $idProducto, array $imagen): void
    {
        $sql = "INSERT INTO imagenes (id_producto, nombre_archivo, ruta_drive, url_publica, es_principal)
                VALUES (:id_producto, :nombre_archivo, :ruta_drive, :url_publica, TRUE)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_producto'    => $idProducto,
            'nombre_archivo' => trim((string) ($imagen['nombre_archivo'] ?? '')),
            'ruta_drive'     => trim((string) ($imagen['ruta_drive'] ?? '')),
            'url_publica'    => trim((string) ($imagen['url_publica'] ?? '')),
        ]);
    }

    public function actualizar(int $idProducto, array $datos): bool
    {
        $permitidos = ['id_categoria', 'nombre', 'descripcion', 'precio', 'existencia'];
        $asignaciones = [];
        $params = ['id_producto' => $idProducto];

        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $datos)) {
                $asignaciones[] = "$campo = :$campo";
                $params[$campo] = $datos[$campo];
            }
        }

        if ($asignaciones === []) {
            return false;
        }

        $sql = "UPDATE productos SET " . implode(', ', $asignaciones)
            . " WHERE id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function cambiarEstado(int $idProducto, string $estado): bool
    {
        $sql = "UPDATE productos SET estado = :estado WHERE id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['estado' => $estado, 'id_producto' => $idProducto]);

        return $stmt->rowCount() > 0;
    }

    public function precio(int $idProducto): ?float
    {
        $sql = "SELECT precio FROM productos
                WHERE id_producto = :id_producto AND estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_producto' => $idProducto]);

        $precio = $stmt->fetchColumn();

        return $precio === false ? null : (float) $precio;
    }
}
