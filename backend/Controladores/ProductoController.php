<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use App\modelos\ProductoModel;
use App\util\AuthGuard;
use App\util\Http;

class ProductoController
{
    private ProductoModel $modelo;

    public function __construct()
    {
        $this->modelo = new ProductoModel();
    }

    /**
     * Catálogo público. Cada filtro del query string se despacha a su
     * propia consulta; sin filtros se devuelve el catálogo completo.
     * ?id_categoria=&precio_min=&precio_max=&nombre=&disponibilidad=
     */
    public function index(): void
    {
        $idCategoria = Http::query('id_categoria');
        $precioMin = Http::query('precio_min');
        $precioMax = Http::query('precio_max');
        $nombre = Http::query('nombre');
        $disponibilidad = Http::query('disponibilidad');

        if ($idCategoria !== null && $idCategoria !== '') {
            Flight::json($this->modelo->ConsultarPorCategoria((int) $idCategoria));
            Flight::stop();
        }

        if (($precioMin !== null && $precioMin !== '') || ($precioMax !== null && $precioMax !== '')) {
            Flight::json($this->modelo->ConsultarPorRangoDePrecio(
                $precioMin !== null && $precioMin !== '' ? (float) $precioMin : null,
                $precioMax !== null && $precioMax !== '' ? (float) $precioMax : null,
            ));
            Flight::stop();
        }

        if ($nombre !== null && $nombre !== '') {
            Flight::json($this->modelo->ConsultarPorNombre($nombre));
            Flight::stop();
        }

        if ($disponibilidad === 'disponible' || $disponibilidad === 'agotado') {
            Flight::json($this->modelo->ConsultarPorDisponibilidad($disponibilidad));
            Flight::stop();
        }

        Flight::json($this->modelo->ConsultaProductos());
    }

    public function show(): void
    {
        $idProducto = (int) Http::param('id');
        $producto = $this->modelo->DetallesProducto($idProducto);

        if ($producto === null) {
            Flight::json(['error' => 'Producto no encontrado'], 404);
            Flight::stop();
        }

        Flight::json($producto);
    }

    public function crear(): void
    {
        AuthGuard::requireRol('administrador');

        $datos = Http::bodyTodo();
        $requeridos = ['identificador', 'id_vendedor', 'id_categoria', 'nombre', 'precio'];
        $faltantes = [];

        foreach ($requeridos as $campo) {
            if (trim((string) ($datos[$campo] ?? '')) === '') {
                $faltantes[] = $campo;
            }
        }

        if ($faltantes !== []) {
            Flight::json(['error' => 'Campos requeridos faltantes', 'campos' => $faltantes], 422);
            Flight::stop();
        }

        if ((float) $datos['precio'] < 0) {
            Flight::json(['error' => 'El precio no puede ser negativo'], 422);
            Flight::stop();
        }

        $idProducto = $this->modelo->RegistrarProducto($datos);
        Flight::json(['mensaje' => 'Producto creado', 'id_producto' => $idProducto], 201);
    }

    public function actualizar(): void
    {
        AuthGuard::requireRol('administrador');

        $idProducto = (int) Http::param('id');
        $datos = Http::bodyTodo();

        if (!$this->modelo->ModificarProducto($idProducto, $datos)) {
            Flight::json(['error' => 'No se pudo actualizar: sin campos válidos o producto inexistente'], 422);
            Flight::stop();
        }

        Flight::json(['mensaje' => 'Producto actualizado']);
    }

    public function cambiarEstado(): void
    {
        AuthGuard::requireRol('administrador');

        $idProducto = (int) Http::param('id');
        $estado = trim((string) Http::body('estado', ''));

        if (!in_array($estado, ['activo', 'inactivo'], true)) {
            Flight::json(['error' => "El estado debe ser 'activo' o 'inactivo'"], 422);
            Flight::stop();
        }

        if (!$this->modelo->CambiarEstadoDelProducto($idProducto, $estado)) {
            Flight::json(['error' => 'Producto no encontrado'], 404);
            Flight::stop();
        }

        Flight::json(['mensaje' => "Producto $estado"]);
    }

    /**
     * Consulta administrativa de productos.
     * ?estado=activo | inactivo | (vacío = todos)
     */
    public function adminIndex(): void
    {
        AuthGuard::requireRol('administrador');

        $estado = Http::query('estado');
        Flight::json($this->modelo->ConsultarTodosLosProductos($estado === null ? null : (string) $estado));
    }
}
