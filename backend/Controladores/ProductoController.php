<?php

declare(strict_types=1);

namespace App\Controladores;

use Flight;
use PDOException;
use RuntimeException;
use Throwable;
use App\modelos\CategoriaModel;
use App\modelos\ProductoModel;
use App\util\AuthGuard;
use App\util\GoogleDriveService;
use App\util\Http;

class ProductoController
{
    private ProductoModel $modelo;

    public function __construct()
    {
        $this->modelo = new ProductoModel();
    }

    /**
     * Catálogo público con filtros combinables.
     * ?id_categoria=&precio_min=&precio_max=&nombre=&disponibilidad=&orden=
     */
    public function index(): void
    {
        $filtros = [
            'id_categoria'   => (string) Http::query('id_categoria', ''),
            'precio_min'     => (string) Http::query('precio_min', ''),
            'precio_max'     => (string) Http::query('precio_max', ''),
            'nombre'         => (string) Http::query('nombre', ''),
            'disponibilidad' => (string) Http::query('disponibilidad', ''),
            'orden'          => (string) Http::query('orden', ''),
        ];

        Flight::json($this->modelo->ConsultarFiltrado($filtros));
    }

    public function show(): void
    {
        $idProducto = (int) Http::param('id');
        $producto = $this->modelo->DetallesProducto($idProducto);

        if ($producto === null) {
            Flight::json(['error' => 'Producto no encontrado'], 404);
            return;
        }

        Flight::json($producto);
    }

    public function crear(): void
    {
        $usuario = AuthGuard::requireRol('administrador');

        $datos = Http::bodyTodo();
        $requeridos = ['identificador', 'id_categoria', 'nombre', 'precio'];
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

        if ((float) $datos['precio'] < 0) {
            Flight::json(['error' => 'El precio no puede ser negativo'], 422);
            return;
        }

        $idVendedor = (int) ($datos['id_vendedor'] ?? $usuario['sub']);
        $datos['id_vendedor'] = $idVendedor;

        // ---------------------------------------------------------------
        // Subida de imágenes a Google Drive (carpeta de la categoría)
        // ---------------------------------------------------------------
        $archivos = $this->archivosSubidos();
        $metadatosImagenes = [];

        if ($archivos !== []) {
            $categoria = (new CategoriaModel())->ConsultarCategoria((int) $datos['id_categoria']);

            if ($categoria === null) {
                Flight::json(['error' => 'La categoría no existe'], 422);
                return;
            }

            $carpetaNombre = trim($categoria['nombre']);
            $drive = new GoogleDriveService();

            try {
                $idCarpeta = $drive->obtenerOCrearCarpeta($carpetaNombre);

                foreach ($archivos as $archivo) {
                    $metadatosImagenes[] = $drive->subirImagen(
                        $archivo['tmp_name'],
                        $archivo['name'],
                        $idCarpeta,
                        $carpetaNombre,
                        (string) $archivo['type'],
                    );
                }
            } catch (RuntimeException $e) {
                Flight::json(['error' => $e->getMessage()], 500);
                return;
            }

            $datos['imagen'] = $metadatosImagenes;
        }

        try {
            $idProducto = $this->modelo->RegistrarProducto($datos);
            Flight::json(['mensaje' => 'Producto creado', 'id_producto' => $idProducto], 201);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                Flight::json(['error' => 'El identificador del producto ya está registrado'], 409);
            } else {
                Flight::json(['error' => 'No se pudo registrar el producto: ' . $e->getMessage()], 500);
            }
        } catch (Throwable $e) {
            Flight::json(['error' => 'No se pudo registrar el producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Normaliza $_FILES para aceptar una o varias imágenes
     * (campos "imagen" o "imagenes").
     */
    private function archivosSubidos(): array
    {
        $archivos = [];
        $imagenes = $_FILES['imagenes'] ?? $_FILES['imagen'] ?? null;

        if (!is_array($imagenes)) {
            return $archivos;
        }

        if (is_array($imagenes['name'] ?? null)) {
            foreach ($imagenes['name'] as $indice => $nombre) {
                if (($imagenes['error'][$indice] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $archivos[] = [
                    'name'     => (string) $nombre,
                    'type'     => (string) ($imagenes['type'][$indice] ?? ''),
                    'tmp_name' => (string) ($imagenes['tmp_name'][$indice] ?? ''),
                    'error'    => (int) ($imagenes['error'][$indice] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int) ($imagenes['size'][$indice] ?? 0),
                ];
            }
        } elseif (($imagenes['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $archivos[] = [
                'name'     => (string) ($imagenes['name'] ?? ''),
                'type'     => (string) ($imagenes['type'] ?? ''),
                'tmp_name' => (string) ($imagenes['tmp_name'] ?? ''),
                'error'    => (int) ($imagenes['error'] ?? UPLOAD_ERR_NO_FILE),
                'size'     => (int) ($imagenes['size'] ?? 0),
            ];
        }

        return array_map(function (array $archivo): array {
            $archivo['type'] = GoogleDriveService::tipoMimePara((string) $archivo['name']);
            return $archivo;
        }, array_filter($archivos, function (array $archivo): bool {
            return GoogleDriveService::tipoMimePara((string) $archivo['name']) !== '';
        }));
    }

    public function actualizar(): void
    {
        AuthGuard::requireRol('administrador');

        $idProducto = (int) Http::param('id');
        $datos = Http::bodyTodo();

        if (!$this->modelo->ModificarProducto($idProducto, $datos)) {
            Flight::json(['error' => 'No se pudo actualizar: sin campos válidos o producto inexistente'], 422);
            return;
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
            return;
        }

        if (!$this->modelo->CambiarEstadoDelProducto($idProducto, $estado)) {
            Flight::json(['error' => 'Producto no encontrado'], 404);
            return;
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

    /**
     * Productos del administrador logueado (los que él mismo subió).
     */
    public function adminMisProductos(): void
    {
        $usuario = AuthGuard::requireRol('administrador');

        Flight::json($this->modelo->ConsultarProductosDelVendedor((int) $usuario['sub']));
    }

    /**
     * Detalle de un producto propio del administrador (incluye inactivos).
     */
    public function adminShow(): void
    {
        $usuario = AuthGuard::requireRol('administrador');

        $idProducto = (int) Http::param('id');
        $producto = $this->modelo->DetallesProductoDelVendedor((int) $usuario['sub'], $idProducto);

        if ($producto === null) {
            Flight::json(['error' => 'Producto no encontrado'], 404);
            return;
        }

        Flight::json($producto);
    }

    /**
     * Descargar imagen de un producto desde Google Drive.
     * Sirve la imagen con headers CORS correctos.
     * GET /api/imagenes/@id
     */
    public function descargarImagen(): void
    {
        try {
            $idImagen = (int) Http::param('id');

            // Obtener metadata de la imagen desde la BD
            $db = Flight::db();
            $stmt = $db->prepare('SELECT id_imagen, nombre_archivo FROM imagenes WHERE id_imagen = :id');
            $stmt->execute(['id' => $idImagen]);
            $imagen = $stmt->fetch();

            if (!$imagen) {
                Flight::json(['error' => 'Imagen no encontrada'], 404);
                return;
            }

            // Obtener el ID del archivo de Google Drive
            // En la tabla imagenes, almacenamos el ID en una columna llamada 'id_archivo_drive'
            // Si no existe, lo extraemos de la URL pública
            $stmt = $db->prepare('SELECT url_publica FROM imagenes WHERE id_imagen = :id');
            $stmt->execute(['id' => $idImagen]);
            $resultado = $stmt->fetch();
            $urlPublica = $resultado['url_publica'] ?? '';

            // Extraer el ID del archivo de la URL: https://drive.google.com/thumbnail?id=XXXXXX&sz=w1000
            if (preg_match('/id=([a-zA-Z0-9_-]+)/', $urlPublica, $coincidencias)) {
                $idArchivoDrive = $coincidencias[1];
            } else {
                Flight::json(['error' => 'No se pudo extraer el ID de Google Drive'], 500);
                return;
            }

            // Descargar la imagen desde Google Drive
            $googleDrive = new GoogleDriveService();
            $contenido = $googleDrive->descargarArchivo($idArchivoDrive);

            // Obtener metadatos para el tipo MIME
            $metadatos = $googleDrive->obtenerMetadatos($idArchivoDrive);
            $tipoMime = $metadatos['mimeType'] ?? GoogleDriveService::tipoMimePara($imagen['nombre_archivo']);

            // Devolver la imagen con headers CORS
            Flight::response()
                ->header('Content-Type', $tipoMime)
                ->header('Content-Length', strlen($contenido))
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Access-Control-Allow-Origin', '*');

            echo $contenido;
        } catch (Throwable $e) {
            Flight::json(['error' => 'Error al descargar la imagen: ' . $e->getMessage()], 500);
        }
    }
}
