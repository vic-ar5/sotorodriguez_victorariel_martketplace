# Estructura de carpetas del Backend

Diagrama de la arquitectura del backend PHP (framework **FlightPHP**), con el propósito de cada carpeta y las dependencias entre ellas.

## Diagrama general

```
backend/
│
├── public/                          # Raíz pública del servidor (única carpeta expuesta)
│   └── index.php                    # Punto de entrada: carga .env, conexión DB, CORS, rutas
│
├── ruta/                            # Definición de rutas (endpoints de la API)
│   └── rutas.php                    # Mapea URL → Controlador@método
│
├── Controladores/                   # Capa de presentación HTTP (recibe la petición)
│   └── ProductoController.php       # Atiende GET/POST/PUT/DELETE de productos
│
├── modelos/                         # Capa de datos (acceso a la base de datos)
│   └── ProductoModel.php            # Consultas PDO contra PostgreSQL
│
├── docs/                            # Documentación del backend
│   └── estructura-carpetas.md       # Este documento
│
├── vendor/                          # Dependencias de Composer (FlightPHP, autoload)
│   ├── autoload.php                 # Autoloader PSR-4 generado por Composer
│   └── flightphp/                   # Framework FlightPHP
│
├── .htaccess                        # Redirige todo al /public (Apache)
├── composer.json                    # Dependencias y autoload PSR-4 (App\ → raíz backend)
└── composer.lock                    # Versiones exactas de las dependencias
```

## Capas del proyecto

| Carpeta | Para qué sirve |
|---|---|
| `public/` | **Punto de entrada único**. Apache solo expone esta carpeta (`.htaccess` redirige todo a `public/`). Aquí se configura la app: variables de entorno, conexión PDO y CORS. |
| `ruta/` | Define los **endpoints** de la API y los conecta con su controlador. Ej. `GET /productos → ProductoController::index`. |
| `Controladores/` | Capa de presentación HTTP: recibe la petición, orquesta el modelo y responde en JSON (`Flight::json()`). |
| `modelos/` | Capa de datos: consultas SQL contra la base de datos (PostgreSQL) usando PDO. |
| `vendor/` | Dependencias de Composer (FlightPHP). **No se edita a mano**; se regenera con `composer install`. |
| `docs/` | Documentación del backend. |

## ¿Qué carpeta llama a cuál?

```
Solicitud HTTP  →  .htaccess  →  public/  →  ruta/  →  Controladores/  →  modelos/  →  PostgreSQL
                                                                              ▲
                                                    .env (credenciales DB) ──┘
```

### Flujo explicado

1. **`.htaccess`** redirige toda petición que no empiece por `/public/` hacia `public/`.
2. **`public/index.php`** es el único punto de entrada. Hace:
   - Carga el `.env` (raíz del repositorio) con `cargarEnv()` → credenciales de la BD.
   - Registra la conexión **PDO** en `Flight::db()` (DSN `pgsql` o `mysql` según `.env`).
   - Configura cabeceras **CORS** para el frontend.
   - Incluye `ruta/rutas.php` y arranca el framework (`Flight::start()`).
3. **`ruta/rutas.php`** mapea cada URL a un método del controlador.
   ```
   GET    /productos      → ProductoController::index
   GET    /productos/@id  → ProductoController::show
   POST   /productos      → ProductoController::store
   PUT    /productos/@id  → ProductoController::update
   DELETE /productos/@id  → ProductoController::destroy
   ```
4. **`Controladores/ProductoController.php`** recibe la petición, instancia el modelo y devuelve JSON.
5. **`modelos/ProductoModel.php`** obtiene la conexión con `Flight::db()` y ejecuta las consultas.

## Reglas de dependencia

- `public/index.php` orquesta todo: carga `.env`, registra la BD e incluye `ruta/`.
- `ruta/` importa **Controladores/** (no toca la BD directamente).
- `Controladores/` usan **modelos/**.
- `modelos/` solo tocan la base de datos (vía `Flight::db()`).
- `vendor/` se genera con `composer install`; no se modifica manualmente.
- La capa `modelos/` no conoce la HTTP; la capa `Controladores/` no conoce SQL.

## Comandos útiles

```bash
# Instalar dependencias (primera vez o tras clonar)
composer install

# Servir en desarrollo (desde backend/)
php -S localhost:8000 -t public
```