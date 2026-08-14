# Base de datos del Marketplace

Esquema PostgreSQL para el marketplace mexicano (Examen Ordinario Unidad 3).

## Archivo

- `schema.sql` — crea tablas, restricciones, índices, triggers y datos semilla.
- `semillas.sql` — datos de ejemplo (roles, estados, categorías, usuarios, productos, carrito y pedidos).
- `codigos_postales.sql` — catálogo de códigos postales MX para autocompletar direcciones (sin API externa).
- `consultas.sql` — todas las consultas del sistema (comprador y administrador) con filtros combinados.

## Tablas

| Tabla | Propósito |
|---|---|
| `roles` | Tipos de usuario: administrador, vendedor, comprador |
| `estados_mexico` | 32 estados de la República Mexicana |
| `estados_pedido` | Pendiente, Confirmado, Preparando, Enviado, Entregado, Cancelado |
| `categorias` | Catálogo de categorías de productos |
| `usuario` | Credenciales de acceso (nombre_usuario, correo, hash bcrypt) |
| `persona` | Datos personales del usuario (1:1); llama a sus direcciones |
| `direcciones` | Direcciones de entrega de la persona (CP MX, estado, país = México) |
| `codigos_postales` | Catálogo CP MX → colonia, municipio, estado (autocompletado sin API) |
| `productos` | Productos (precio MXN, existencia, estado, vendedor) |
| `imagenes` | Metadatos de la imagen en Google Drive (nunca el archivo) |
| `carrito` | Carrito activo por comprador |
| `detalle_carrito` | Productos y cantidades del carrito |
| `pedidos` | Solicitud de compra (número, usuario, dirección, total) |
| `detalle_pedido` | Productos y cantidades del pedido |

## Diseño de usuario

- `usuario` — solo credenciales: `id_usuario`, `id_rol`, `nombre_usuario`, `correo`, `contrasena_hash`, `activo`.
- `persona` — datos personales: `id_persona`, `id_usuario` (UNIQUE, 1:1), nombre/apellidos, teléfono.
- `direcciones` — pertenece a `persona` (una persona puede tener varias direcciones).

Todos los IDs usan el prefijo de su tabla (`id_usuario`, `id_persona`, `id_producto`, `id_pedido`, etc.).

## Cargar la BD

```bash
# Con psql (crea la BD primero si no existe)
createdb -U postgres marketplace

# Aplica el esquema y luego los datos de ejemplo
psql -U postgres -d marketplace -f database/schema.sql
psql -U postgres -d marketplace -f database/semillas.sql
psql -U postgres -d marketplace -f database/codigos_postales.sql
```

Los usuarios de ejemplo usan la contraseña `password` (hash bcrypt válido).

El `.env` ya apunta a `pgsql`, host `localhost`, puerto `5432`, BD `marketplace`, usuario `postgres`.

## Reglas clave

- Las **imágenes viven en Google Drive** (`Marketplace-Mexico/{categoria}/archivo.jpg`); en la BD solo se guarda `ruta_drive` y `url_publica`.
- Moneda fija **MXN**, país fijo **México**, teléfono de 10 dígitos y CP de 5 dígitos.
- Un único carrito `activo` por usuario (índice único parcial).
- `numero_pedido` se genera solo con el formato `PED-####`.
- Las fechas de creación y actualización son automáticas.
