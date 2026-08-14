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
     * Consulta base del catálogo público: solo productos activos.
     * Cada filtro se aplica en su propia función para no sobrecargar
     * la consulta principal.
     */
    private function consultaBase(
        string $condiciones = '',
        array $params = [],
        string $orden = 'p.nombre ASC'
    ): array {
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

        $sql .= $condiciones;
        $sql .= " ORDER BY $orden";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Catálogo completo de productos activos (sin filtros).
     */
    public function ConsultaProductos(): array
    {
        return $this->consultaBase();
    }

    /**
     * Filtro por categoría.
     */
    public function ConsultarPorCategoria(int $idCategoria): array
    {
        return $this->consultaBase(
            " AND p.id_categoria = :id_categoria",
            ['id_categoria' => $idCategoria]
        );
    }

    /**
     * Filtro por nombre (búsqueda parcial, sin distinguir mayúsculas).
     */
    public function ConsultarPorNombre(string $nombre): array
    {
        return $this->consultaBase(
            " AND p.nombre ILIKE '%' || :nombre || '%'",
            ['nombre' => $nombre]
        );
    }

    /**
     * Filtro por rango de precio (los límites son opcionales).
     */
    public function ConsultarPorRangoDePrecio(?float $precioMin, ?float $precioMax): array
    {
        $condiciones = '';
        $params = [];

        if ($precioMin !== null) {
            $condiciones .= " AND p.precio >= :precio_min";
            $params['precio_min'] = $precioMin;
        }

        if ($precioMax !== null) {
            $condiciones .= " AND p.precio <= :precio_max";
            $params['precio_max'] = $precioMax;
        }

        return $this->consultaBase($condiciones, $params);
    }

    /**
     * Filtro por disponibilidad: 'disponible' (existencia > 0) o 'agotado'.
     */
    public function ConsultarPorDisponibilidad(string $disponibilidad): array
    {
        $condiciones = $disponibilidad === 'disponible'
            ? " AND p.existencia > 0"
            : " AND p.existencia = 0";

        return $this->consultaBase($condiciones);
    }

    /**
     * Catálogo público combinando varios filtros a la vez.
     * $filtros admite: id_categoria, precio_min, precio_max, nombre,
     * disponibilidad y orden (precio_asc | precio_desc).
     */
    public function ConsultarFiltrado(array $filtros): array
    {
        $condiciones = '';
        $params = [];

        if (isset($filtros['id_categoria']) && $filtros['id_categoria'] !== '') {
            $condiciones .= " AND p.id_categoria = :id_categoria";
            $params['id_categoria'] = (int) $filtros['id_categoria'];
        }

        if (isset($filtros['precio_min']) && $filtros['precio_min'] !== '') {
            $condiciones .= " AND p.precio >= :precio_min";
            $params['precio_min'] = (float) $filtros['precio_min'];
        }

        if (isset($filtros['precio_max']) && $filtros['precio_max'] !== '') {
            $condiciones .= " AND p.precio <= :precio_max";
            $params['precio_max'] = (float) $filtros['precio_max'];
        }

        if (isset($filtros['nombre']) && $filtros['nombre'] !== '') {
            $condiciones .= " AND p.nombre ILIKE '%' || :nombre || '%'";
            $params['nombre'] = $filtros['nombre'];
        }

        if (($filtros['disponibilidad'] ?? '') === 'disponible') {
            $condiciones .= " AND p.existencia > 0";
        } elseif (($filtros['disponibilidad'] ?? '') === 'agotado') {
            $condiciones .= " AND p.existencia = 0";
        }

        $orden = 'p.nombre ASC';
        $ordenFiltro = (string) ($filtros['orden'] ?? '');
        if ($ordenFiltro === 'precio_asc') {
            $orden = 'p.precio ASC';
        } elseif ($ordenFiltro === 'precio_desc') {
            $orden = 'p.precio DESC';
        }

        if ($condiciones === '' && $ordenFiltro === '') {
            return $this->ConsultaProductos();
        }

        return $this->consultaBase($condiciones, $params, $orden);
    }

    /**
     * Detalle de un producto activo, incluyendo sus imágenes.
     */
    public function DetallesProducto(int $idProducto): ?array
    {
        $sql = "SELECT p.id_producto, p.identificador, p.nombre, p.descripcion, p.precio,
                       p.existencia, p.moneda, p.estado,
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

        $sql = "SELECT id_imagen, nombre_archivo, ruta_drive, url_publica, es_principal
                FROM imagenes
                WHERE id_producto = :id_producto
                ORDER BY es_principal DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_producto' => $idProducto]);

        $producto['imagenes'] = $stmt->fetchAll();

        return $producto;
    }

    /**
     * Consulta administrativa: todos los productos o solo activos/inactivos.
     */
    public function ConsultarTodosLosProductos(?string $estado): array
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

    public function RegistrarProducto(array $datos): int
    {
        $this->db->beginTransaction();

        try {
            $sql = "INSERT INTO productos
                        (identificador, id_vendedor, id_categoria, nombre, descripcion, precio, existencia, estado)
                    VALUES (:identificador, :id_vendedor, :id_categoria, :nombre, :descripcion, :precio, :existencia, 'activo')
                    RETURNING id_producto";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'identificador'  => trim($datos['identificador']),
                'id_vendedor'    => (int) $datos['id_vendedor'],
                'id_categoria'   => (int) $datos['id_categoria'],
                'nombre'         => trim($datos['nombre']),
                'descripcion'    => trim((string) ($datos['descripcion'] ?? '')),
                'precio'         => (float) $datos['precio'],
                'existencia'     => (int) ($datos['existencia'] ?? 0),
            ]);

            $idProducto = (int) $stmt->fetchColumn();

            if (!empty($datos['imagen']) && is_array($datos['imagen'])) {
                $this->RegistrarImagen($idProducto, $datos['imagen']);
            }

            $this->db->commit();

            return $idProducto;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function RegistrarImagen(int $idProducto, array $imagen): void
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

    public function ModificarProducto(int $idProducto, array $datos): bool
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

    public function CambiarEstadoDelProducto(int $idProducto, string $estado): bool
    {
        $sql = "UPDATE productos SET estado = :estado WHERE id_producto = :id_producto";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['estado' => $estado, 'id_producto' => $idProducto]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Precio de un producto activo (se usa al agregar al carrito).
     */
    public function ConsultarPrecioDelProducto(int $idProducto): ?float
    {
        $sql = "SELECT precio FROM productos
                WHERE id_producto = :id_producto AND estado = 'activo'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id_producto' => $idProducto]);

        $precio = $stmt->fetchColumn();

        return $precio === false ? null : (float) $precio;
    }
}
