<?php

declare(strict_types=1);

namespace App\util;

use RuntimeException;

/**
 * Cliente mínimo para la API de Google Drive (v3) usando OAuth2 con refresh token.
 *
 * Requiere en el archivo .env:
 *   GOOGLE_DRIVE_CLIENT_ID
 *   GOOGLE_DRIVE_CLIENT_SECRET
 *   GOOGLE_DRIVE_REFRESH_TOKEN
 *   GOOGLE_DRIVE_ROOT_FOLDER_ID   (carpeta raíz "Marketplace-Mexico" en Drive)
 *
 * Las imágenes se guardan en Drive dentro de la carpeta de su categoría:
 *   Marketplace-Mexico/{categoria}/{archivo}
 * En la base de datos solo se guardan los metadatos (ruta y URL pública).
 */
class GoogleDriveService
{
    private const URL_TOKEN = 'https://oauth2.googleapis.com/token';
    private const URL_DRIVE = 'https://www.googleapis.com/drive/v3/files';
    private const URL_SUBIDA = 'https://www.googleapis.com/upload/drive/v3/files';

    /**
     * Intercambia el refresh token por un access token vigente.
     */
    private function obtenerAccessToken(): string
    {
        $clientId = getenv('GOOGLE_DRIVE_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_DRIVE_CLIENT_SECRET');
        $refreshToken = getenv('GOOGLE_DRIVE_REFRESH_TOKEN');

        if ($clientId === false || $clientId === '' || $clientSecret === false || $clientSecret === ''
            || $refreshToken === false || $refreshToken === '') {
            throw new RuntimeException(
                'Google Drive no está configurado: revisa GOOGLE_DRIVE_CLIENT_ID, GOOGLE_DRIVE_CLIENT_SECRET y GOOGLE_DRIVE_REFRESH_TOKEN en el archivo .env'
            );
        }

        $ch = curl_init(self::URL_TOKEN);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'refresh_token',
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $respuesta = curl_exec($ch);
        $estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $datos = json_decode((string) $respuesta, true);

        if ($estado < 200 || $estado >= 300 || !isset($datos['access_token'])) {
            throw new RuntimeException('No se pudo obtener el token de acceso de Google Drive');
        }

        return (string) $datos['access_token'];
    }

    /**
     * Realiza una petición a la API de Drive devolviendo el JSON como arreglo.
     */
    private function peticion(string $metodo, string $url, string $token, array $cabeceras = [], $cuerpo = null): array
    {
        $ch = curl_init($url);
        $opciones = [
            CURLOPT_CUSTOMREQUEST  => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => array_merge(["Authorization: Bearer $token"], $cabeceras),
        ];

        if ($cuerpo !== null) {
            $opciones[CURLOPT_POSTFIELDS] = $cuerpo;
        }

        curl_setopt_array($ch, $opciones);
        $respuesta = curl_exec($ch);
        $estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $datos = json_decode((string) $respuesta, true);

        if ($estado < 200 || $estado >= 300) {
            $detalle = is_array($datos)
                ? ($datos['error']['message'] ?? $datos['error'] ?? $respuesta)
                : $respuesta;
            throw new RuntimeException('Error de Google Drive: ' . $detalle);
        }

        return is_array($datos) ? $datos : [];
    }

    /**
     * Busca la carpeta de una categoría dentro de la raíz; si no existe, la crea.
     * Devuelve el ID de la carpeta.
     */
    public function obtenerOCrearCarpeta(string $nombre): string
    {
        $raiz = getenv('GOOGLE_DRIVE_ROOT_FOLDER_ID');
        if ($raiz === false || $raiz === '') {
            throw new RuntimeException('Google Drive no está configurado: falta GOOGLE_DRIVE_ROOT_FOLDER_ID en el archivo .env');
        }

        $token = $this->obtenerAccessToken();

        $consulta = sprintf(
            "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            str_replace("'", "\\'", $nombre),
            $raiz
        );

        $url = self::URL_DRIVE . '?' . http_build_query([
            'q'      => $consulta,
            'fields' => 'files(id,name)',
        ]);

        $resultado = $this->peticion('GET', $url, $token);

        if (!empty($resultado['files'][0]['id'])) {
            return (string) $resultado['files'][0]['id'];
        }

        $carpeta = $this->peticion(
            'POST',
            self::URL_DRIVE,
            $token,
            ['Content-Type: application/json'],
            json_encode([
                'name'     => $nombre,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$raiz],
            ])
        );

        if (empty($carpeta['id'])) {
            throw new RuntimeException("No se pudo crear la carpeta '$nombre' en Google Drive");
        }

        return (string) $carpeta['id'];
    }

    /**
     * Hace público un archivo (lectura para cualquiera que tenga el enlace).
     */
    private function hacerPublico(string $idArchivo): void
    {
        $token = $this->obtenerAccessToken();

        $this->peticion(
            'POST',
            self::URL_DRIVE . "/$idArchivo/permissions",
            $token,
            ['Content-Type: application/json'],
            json_encode([
                'role' => 'reader',
                'type' => 'anyone',
            ])
        );
    }

    /**
     * Sube un archivo de imagen a la carpeta indicada.
     *
     * @param string $rutaTemporal  Ruta temporal del archivo subido (tmp_name).
     * @param string $nombreArchivo Nombre original del archivo.
     * @param string $idCarpeta     ID de la carpeta de la categoría en Drive.
     * @param string $carpetaRuta   Nombre de la carpeta (para la columna ruta_drive).
     * @param string $tipoMime      Tipo MIME real del archivo (image/jpeg, image/png).
     *
     * @return array{id: string, nombre_archivo: string, ruta_drive: string, url_publica: string}
     */
    public function subirImagen(string $rutaTemporal, string $nombreArchivo, string $idCarpeta, string $carpetaRuta, string $tipoMime = ''): array
    {
        if (!is_uploaded_file($rutaTemporal) && !is_file($rutaTemporal)) {
            throw new RuntimeException('El archivo de imagen no es válido');
        }

        $nombreLimpio = self::sanearNombre($nombreArchivo);
        $token = $this->obtenerAccessToken();

        $metadatos = json_encode([
            'name'    => $nombreLimpio,
            'parents' => [$idCarpeta],
        ]);

        $mime = $tipoMime !== ''
            ? $tipoMime
            : (self::tipoMimePara($nombreArchivo) ?: (string) mime_content_type($rutaTemporal));

        // Google Drive exige multipart/related (no multipart/form-data que
        // genera PHP automáticamente), así que construimos el cuerpo a mano.
        $limite = 'marketplace_' . bin2hex(random_bytes(8));
        $cuerpo = "--$limite\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadatos . "\r\n"
            . "--$limite\r\n"
            . "Content-Type: $mime\r\n"
            . 'Content-Disposition: form-data; name="file"; filename="' . $nombreLimpio . "\"\r\n"
            . "Content-Transfer-Encoding: binary\r\n\r\n"
            . (string) file_get_contents($rutaTemporal) . "\r\n"
            . "--$limite--\r\n";

        $ch = curl_init(self::URL_SUBIDA . '?uploadType=multipart');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $cuerpo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                "Content-Type: multipart/related; boundary=$limite",
            ],
        ]);

        $respuesta = curl_exec($ch);
        $estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $datos = json_decode((string) $respuesta, true);

        if ($estado < 200 || $estado >= 300 || empty($datos['id'])) {
            $detalle = is_array($datos)
                ? ($datos['error']['message'] ?? $respuesta)
                : $respuesta;
            throw new RuntimeException('No se pudo subir la imagen a Google Drive: ' . $detalle . " [mime=$mime]");
        }

        $idArchivo = (string) $datos['id'];

        $this->hacerPublico($idArchivo);

        return [
            'id'             => $idArchivo,
            'nombre_archivo' => $nombreLimpio,
            'ruta_drive'     => trim($carpetaRuta, '/') . '/' . $nombreLimpio,
            'url_publica'    => self::urlPublica($idArchivo),
        ];
    }

    public static function urlPublica(string $idArchivo): string
    {
        // /thumbnail sirve la imagen directamente al navegador; /uc?export=view
        // devuelve HTML/redirecciones que el navegador bloquea (ERR_BLOCKED_BY_ORB).
        return "https://drive.google.com/thumbnail?id=" . urlencode($idArchivo) . "&sz=w1000";
    }

    /**
     * Resuelve el tipo MIME de una imagen a partir de su extensión.
     * Útil cuando el navegador reporta "application/octet-stream".
     */
    public static function tipoMimePara(string $nombreArchivo): string
    {
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg'           => 'image/jpeg',
            'png'                   => 'image/png',
            'gif'                   => 'image/gif',
            'webp'                  => 'image/webp',
            'bmp'                   => 'image/bmp',
            'svg'                   => 'image/svg+xml',
            'avif'                  => 'image/avif',
            'tiff', 'tif'           => 'image/tiff',
            'ico'                   => 'image/x-icon',
            default                 => '',
        };
    }

    /**
     * Descarga el contenido de un archivo desde Google Drive.
     * Útil para servir imágenes con headers CORS correctos desde el backend.
     *
     * @param string $idArchivo El ID del archivo en Google Drive
     * @return string El contenido binario del archivo
     */
    public function descargarArchivo(string $idArchivo): string
    {
        $token = $this->obtenerAccessToken();

        $url = self::URL_DRIVE . '/' . urlencode($idArchivo) . '?alt=media';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer $token"],
        ]);

        $contenido = curl_exec($ch);
        $estado = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($estado < 200 || $estado >= 300) {
            throw new RuntimeException("No se pudo descargar la imagen desde Google Drive (HTTP $estado)");
        }

        return (string) $contenido;
    }

    /**
     * Obtiene los metadatos de un archivo (incluyendo tipo MIME).
     */
    public function obtenerMetadatos(string $idArchivo): array
    {
        $token = $this->obtenerAccessToken();

        $url = self::URL_DRIVE . '/' . urlencode($idArchivo) . '?fields=id,name,mimeType,size';

        $resultado = $this->peticion('GET', $url, $token);

        return $resultado;
    }

    /**
     * Quita caracteres que puedan romper la ruta en Drive o la URL.
     */
    public static function sanearNombre(string $nombre): string
    {
        $info = pathinfo($nombre);
        $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $info['filename']) ?? 'imagen';
        $base = trim($base, '.-') !== '' ? trim($base, '.-') : 'imagen';
        $extension = strtolower((string) ($info['extension'] ?? ''));

        return "$base.$extension";
    }
}
