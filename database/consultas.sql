-- =====================================================================
--  MARKETPLACE MÉXICO - Consultas SQL
--  Examen Ordinario Unidad 3 - Desarrollo Web Integral
--  Autor: Victor Ariel Soto Rodriguez
--
--  USO: parámetros estilo PDO (:nombre, :id_usuario, ...) compatibles
--  con el backend PHP (Flight + PDO).
--
--  REGLA GENERAL: las consultas públicas devuelven SOLO productos
--  con estado = 'activo', a menos que el administrador lo indique.
--  El trigger fn_actualizar_fecha_producto ya vive en schema.sql.
-- =====================================================================

-- =====================================================================
--  COMPRADOR
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Registrarse (credenciales en `usuario`, datos en `persona`)
--    Ejecutar dentro de una transacción (BEGIN / COMMIT).
-- ---------------------------------------------------------------------
INSERT INTO usuario (id_rol, nombre_usuario, correo, contrasena_hash)
VALUES (3, :nombre_usuario, :correo, :contrasena_hash);

INSERT INTO persona (id_usuario, nombre, apellido_paterno, apellido_materno, telefono)
VALUES (currval('usuario_id_usuario_seq'), :nombre, :apellido_paterno, :apellido_materno, :telefono);

-- ---------------------------------------------------------------------
-- 2. Iniciar sesión (verifica correo y contraseña; devuelve rol y estado)
--    La verificación de la contraseña se hace en PHP con password_verify()
--    contra u.contrasena_hash.
-- ---------------------------------------------------------------------
SELECT u.id_usuario,
       u.contrasena_hash,
       r.nombre AS rol,
       u.activo
FROM usuario u
JOIN roles r ON u.id_rol = r.id_rol
WHERE u.correo = :correo;

-- ---------------------------------------------------------------------
-- 3. Consultar productos (catálogo público: solo activos)
-- ---------------------------------------------------------------------
SELECT p.id_producto,
       p.nombre,
       p.descripcion,
       p.precio,
       p.existencia,
       p.moneda,
       v.id_usuario      AS id_vendedor,
       c.nombre          AS categoria,
       i.url_publica     AS imagen
FROM productos p
JOIN usuario v ON p.id_vendedor = v.id_usuario
JOIN categorias c ON p.id_categoria = c.id_categoria
LEFT JOIN imagenes i ON i.id_producto = p.id_producto AND i.es_principal = TRUE
WHERE p.estado = 'activo'
ORDER BY p.fecha_registro DESC;

-- ---------------------------------------------------------------------
-- 4. Buscar productos por nombre (solo activos)
-- ---------------------------------------------------------------------
SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.existencia, p.moneda,
       v.id_usuario AS id_vendedor,
       c.nombre AS categoria, i.url_publica AS imagen
FROM productos p
JOIN usuario v ON p.id_vendedor = v.id_usuario
JOIN categorias c ON p.id_categoria = c.id_categoria
LEFT JOIN imagenes i ON i.id_producto = p.id_producto AND i.es_principal = TRUE
WHERE p.estado = 'activo'
  AND p.nombre ILIKE '%' || :nombre || '%'
ORDER BY p.nombre;

-- ---------------------------------------------------------------------
-- 5. Filtrar productos (varios filtros a la vez, todos opcionales)
--    :id_categoria       INT       -> 0 o NULL = todas
--    :precio_min         NUMERIC   -> NULL = sin mínimo
--    :precio_max         NUMERIC   -> NULL = sin máximo
--    :nombre             VARCHAR   -> NULL = sin búsqueda
--    :disponibilidad     VARCHAR   -> NULL = todas, 'disponible' o 'agotado'
--    :orden              VARCHAR   -> NULL/'nombre' | 'precio_desc' | 'precio_asc'
-- ---------------------------------------------------------------------
SELECT p.id_producto,
       p.nombre,
       p.descripcion,
       p.precio,
       p.existencia,
       p.moneda,
       v.id_usuario      AS id_vendedor,
       c.nombre AS categoria,
       i.url_publica AS imagen
FROM productos p
JOIN usuario v ON p.id_vendedor = v.id_usuario
JOIN categorias c ON p.id_categoria = c.id_categoria
LEFT JOIN imagenes i ON i.id_producto = p.id_producto AND i.es_principal = TRUE
WHERE p.estado = 'activo'
  AND (:id_categoria IS NULL OR p.id_categoria = :id_categoria)
  AND (:precio_min IS NULL OR p.precio >= :precio_min)
  AND (:precio_max IS NULL OR p.precio <= :precio_max)
  AND (:nombre IS NULL OR p.nombre ILIKE '%' || :nombre || '%')
  AND (:disponibilidad IS NULL
       OR (:disponibilidad = 'disponible' AND p.existencia > 0)
       OR (:disponibilidad = 'agotado'    AND p.existencia = 0))
ORDER BY CASE :orden
           WHEN 'precio_desc' THEN p.precio END DESC,
         CASE :orden
           WHEN 'precio_asc'  THEN p.precio END ASC,
         p.nombre ASC;

-- ---------------------------------------------------------------------
-- 6. Consultar detalle de un producto (solo activo, con vendedor)
-- ---------------------------------------------------------------------
SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.existencia, p.moneda,
       p.fecha_registro, p.fecha_actualizacion,
       c.nombre AS categoria,
       v.nombre_usuario AS vendedor,
       pv.nombre || ' ' || pv.apellido_paterno AS nombre_vendedor
FROM productos p
JOIN categorias c  ON p.id_categoria = c.id_categoria
JOIN usuario  v    ON p.id_vendedor = v.id_usuario
LEFT JOIN persona pv ON v.id_usuario = pv.id_usuario
WHERE p.id_producto = :id_producto
  AND p.estado = 'activo';

-- Todas las imágenes del producto (metadatos de Google Drive)
SELECT id_imagen, nombre_archivo, ruta_drive, url_publica, es_principal
FROM imagenes
WHERE id_producto = :id_producto
ORDER BY es_principal DESC;

-- ---------------------------------------------------------------------
-- 7. Carrito de compras
-- ---------------------------------------------------------------------
-- 7a. Obtener el carrito activo del usuario (si no existe, insertar en 7b)
SELECT id_carrito, fecha_creacion
FROM carrito
WHERE id_usuario = :id_usuario AND estado = 'activo';

-- 7b. Crear carrito si no existe
INSERT INTO carrito (id_usuario)
VALUES (:id_usuario)
RETURNING id_carrito;

-- 7c. Precio del producto (para guardar precio_unitario congelado)
SELECT id_producto, precio
FROM productos
WHERE id_producto = :id_producto AND estado = 'activo';

-- 7d. Agregar producto (o sumar cantidad si ya está en el carrito)
INSERT INTO detalle_carrito (id_carrito, id_producto, cantidad, precio_unitario)
VALUES (:id_carrito, :id_producto, :cantidad, :precio_unitario)
ON CONFLICT (id_carrito, id_producto)
DO UPDATE SET cantidad = detalle_carrito.cantidad + EXCLUDED.cantidad;

-- 7e. Modificar cantidad de un producto en el carrito
UPDATE detalle_carrito
SET cantidad = :cantidad
WHERE id_carrito = :id_carrito AND id_producto = :id_producto;

-- 7f. Eliminar un producto del carrito
DELETE FROM detalle_carrito
WHERE id_carrito = :id_carrito AND id_producto = :id_producto;

-- 7g. Consultar el carrito (con subtotal por línea y total)
SELECT dc.id_detalle_carrito,
       dc.id_producto,
       p.nombre,
       dc.cantidad,
       dc.precio_unitario,
       dc.subtotal,
       p.existencia
FROM detalle_carrito dc
JOIN productos p ON dc.id_producto = p.id_producto
WHERE dc.id_carrito = :id_carrito
ORDER BY dc.fecha_agregado;

-- Total del carrito (formato: cantidades y total, ejemplo del examen)
SELECT SUM(dc.cantidad)      AS productos,
       SUM(dc.subtotal)      AS total
FROM detalle_carrito dc
WHERE dc.id_carrito = :id_carrito;

-- ---------------------------------------------------------------------
-- 8. Generar pedido (transacción: BEGIN ... COMMIT)
-- ---------------------------------------------------------------------
-- 8a. Insertar el pedido (estado 1 = Pendiente). id_direccion = la elegida
INSERT INTO pedidos (id_usuario, id_direccion, id_estado_pedido, total)
SELECT c.id_usuario, :id_direccion, 1, SUM(dc.subtotal)
FROM carrito c
JOIN detalle_carrito dc ON dc.id_carrito = c.id_carrito
WHERE c.id_usuario = :id_usuario AND c.estado = 'activo'
GROUP BY c.id_usuario;

-- 8b. Copiar el detalle al pedido (currval = id del pedido recién insertado)
-- NOTA: subtotal es GENERATED ALWAYS, no se inserta.
INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
SELECT currval('pedidos_id_pedido_seq'),
       dc.id_producto, dc.cantidad, dc.precio_unitario
FROM detalle_carrito dc
JOIN carrito c ON dc.id_carrito = c.id_carrito
WHERE c.id_usuario = :id_usuario AND c.estado = 'activo';

-- 8c. Descontar existencias
UPDATE productos pr
SET existencia = pr.existencia - dc.cantidad
FROM detalle_carrito dc
JOIN carrito c ON dc.id_carrito = c.id_carrito
WHERE dc.id_producto = pr.id_producto
  AND c.id_usuario = :id_usuario
  AND c.estado = 'activo'
  AND pr.estado = 'activo';

-- 8d. Cerrar el carrito (deja de estar activo)
UPDATE carrito
SET estado = 'convertido'
WHERE id_usuario = :id_usuario AND estado = 'activo';

-- ---------------------------------------------------------------------
-- 9. Consultar sus pedidos
-- ---------------------------------------------------------------------
SELECT p.id_pedido,
       p.numero_pedido,
       p.fecha_pedido,
       p.total,
       p.moneda,
       ep.nombre AS estado
FROM pedidos p
JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
WHERE p.id_usuario = :id_usuario
ORDER BY p.fecha_pedido DESC;

-- Detalle de un pedido (para ver productos y cantidades)
SELECT dp.id_producto, p.nombre, dp.cantidad, dp.precio_unitario, dp.subtotal
FROM detalle_pedido dp
JOIN productos p ON dp.id_producto = p.id_producto
WHERE dp.id_pedido = :id_pedido;

-- =====================================================================
--  ADMINISTRADOR
-- =====================================================================

-- ---------------------------------------------------------------------
-- 10. Registrar producto (y su imagen en Google Drive)
-- ---------------------------------------------------------------------
INSERT INTO productos (identificador, id_vendedor, id_categoria, nombre, descripcion, precio, existencia)
VALUES (:identificador, :id_vendedor, :id_categoria, :nombre, :descripcion, :precio, :existencia)
RETURNING id_producto;

INSERT INTO imagenes (id_producto, nombre_archivo, ruta_drive, url_publica, es_principal)
VALUES (:id_producto, :nombre_archivo, :ruta_drive, :url_publica, TRUE);

-- ---------------------------------------------------------------------
-- 11. Modificar producto (el trigger actualiza fecha_actualizacion)
-- ---------------------------------------------------------------------
UPDATE productos
SET id_categoria  = :id_categoria,
    nombre        = :nombre,
    descripcion   = :descripcion,
    precio        = :precio,
    existencia    = :existencia
WHERE id_producto = :id_producto;

-- Reemplazar imagen principal del producto
UPDATE imagenes
SET nombre_archivo = :nombre_archivo,
    ruta_drive     = :ruta_drive,
    url_publica    = :url_publica
WHERE id_producto = :id_producto AND es_principal = TRUE;

-- ---------------------------------------------------------------------
-- 12. Desactivar / reactivar producto (desactivación recomendada)
-- ---------------------------------------------------------------------
UPDATE productos
SET estado = 'inactivo'          -- o 'activo' para reactivar
WHERE id_producto = :id_producto;

-- Consultar productos ACTIVOS
SELECT p.id_producto, p.nombre, p.precio, p.existencia, p.estado,
       c.nombre AS categoria, p.fecha_registro, p.fecha_actualizacion
FROM productos p
JOIN categorias c ON p.id_categoria = c.id_categoria
WHERE p.estado = 'activo'
ORDER BY p.fecha_registro DESC;

-- Consultar productos INACTIVOS
SELECT p.id_producto, p.nombre, p.precio, p.existencia, p.estado,
       c.nombre AS categoria, p.fecha_registro, p.fecha_actualizacion
FROM productos p
JOIN categorias c ON p.id_categoria = c.id_categoria
WHERE p.estado = 'inactivo'
ORDER BY p.fecha_registro DESC;

-- Consultar TODOS los productos (activos e inactivos)
SELECT p.id_producto, p.nombre, p.precio, p.existencia, p.estado,
       c.nombre AS categoria, p.fecha_registro, p.fecha_actualizacion
FROM productos p
JOIN categorias c ON p.id_categoria = c.id_categoria
ORDER BY p.fecha_registro DESC;

-- ---------------------------------------------------------------------
-- 13. Administrar categorías
-- ---------------------------------------------------------------------
-- Listar categorías activas
SELECT id_categoria, nombre, descripcion, activo
FROM categorias
ORDER BY nombre;

-- Crear categoría
INSERT INTO categorias (nombre, descripcion)
VALUES (:nombre, :descripcion);

-- Modificar categoría
UPDATE categorias
SET nombre      = :nombre,
    descripcion = :descripcion
WHERE id_categoria = :id_categoria;

-- Desactivar / reactivar categoría
UPDATE categorias
SET activo = FALSE               -- o TRUE para reactivar
WHERE id_categoria = :id_categoria;

-- ---------------------------------------------------------------------
-- 14. Consultar usuarios
-- ---------------------------------------------------------------------
-- 14a. Usuarios ACTIVOS
SELECT u.id_usuario,
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
LEFT JOIN persona p ON u.id_usuario = p.id_usuario
WHERE u.activo = TRUE
ORDER BY u.fecha_registro DESC;

-- 14b. Usuarios INACTIVOS
SELECT u.id_usuario,
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
LEFT JOIN persona p ON u.id_usuario = p.id_usuario
WHERE u.activo = FALSE
ORDER BY u.fecha_registro DESC;

-- 14c. TODOS los usuarios (activos e inactivos)
SELECT u.id_usuario,
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
LEFT JOIN persona p ON u.id_usuario = p.id_usuario
ORDER BY u.fecha_registro DESC;

-- Direcciones de un usuario (para elegir dirección de envío / admin)
SELECT d.id_direccion, d.nombre, d.calle, d.numero_exterior, d.colonia,
       d.codigo_postal, d.municipio, e.nombre AS estado, d.pais, d.es_principal
FROM direcciones d
JOIN estados_mexico e ON d.id_estado = e.id_estado
WHERE d.id_persona = :id_persona;

-- ---------------------------------------------------------------------
-- 15. Consultar pedidos
--     Los pedidos no tienen activo/inactivo: se separan por ESTADO.
--     :id_estado_pedido -> NULL = todos, o 1..6 para un estado específico
--       (1 Pendiente, 2 Confirmado, 3 Preparando, 4 Enviado, 5 Entregado, 6 Cancelado)
-- ---------------------------------------------------------------------
SELECT p.id_pedido,
       p.numero_pedido,
       p.fecha_pedido,
       p.total,
       p.moneda,
       ep.nombre AS estado,
       u.nombre_usuario,
       d.nombre || ' - ' || d.calle || ' ' || d.numero_exterior AS direccion
FROM pedidos p
JOIN usuario u        ON p.id_usuario = u.id_usuario
JOIN estados_pedido ep ON p.id_estado_pedido = ep.id_estado_pedido
LEFT JOIN direcciones d ON p.id_direccion = d.id_direccion
WHERE (:id_estado_pedido IS NULL OR p.id_estado_pedido = :id_estado_pedido)
ORDER BY p.fecha_pedido DESC;

-- Ejemplos rápidos para separar por estado:
--   Pedidos pendientes:    WHERE p.id_estado_pedido = 1
--   Pedidos confirmados:   WHERE p.id_estado_pedido = 2
--   Pedidos cancelados:    WHERE p.id_estado_pedido = 6

-- ---------------------------------------------------------------------
-- 16. Cambiar el estado de un pedido
--     id_estado_pedido: 1 Pendiente, 2 Confirmado, 3 Preparando,
--                       4 Enviado, 5 Entregado, 6 Cancelado
-- ---------------------------------------------------------------------
UPDATE pedidos
SET id_estado_pedido = :id_estado_pedido
WHERE id_pedido = :id_pedido;
