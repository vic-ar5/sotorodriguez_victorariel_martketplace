-- =====================================================================
--  MARKETPLACE MÉXICO - Datos semilla
--  Examen Ordinario Unidad 3 - Desarrollo Web Integral
--  Autor: Victor Ariel Soto Rodriguez
--
--  EJECUTAR DESPUÉS DE schema.sql:
--    psql -U postgres -d marketplace -f database/schema.sql
--    psql -U postgres -d marketplace -f database/semillas.sql
--
--  La contraseña de TODOS los usuarios de ejemplo es:  password
--  (los hash son bcrypt válidos para password_hash()/password_verify()).
--  Ningún campo se inserta como NULL.
-- =====================================================================

-- ---------------------------------------------------------------------
-- ROLES (solo administrador y comprador)
-- ---------------------------------------------------------------------
INSERT INTO roles (nombre, descripcion) VALUES
    ('administrador', 'Administra la plataforma: productos, categorías, usuarios y pedidos'),
    ('comprador',     'Consulta el catálogo, arma su carrito y genera pedidos');

-- ---------------------------------------------------------------------
-- ESTADOS DE LA REPÚBLICA MEXICANA
-- ---------------------------------------------------------------------
INSERT INTO estados_mexico (nombre, abreviatura) VALUES
    ('Aguascalientes', 'AGS'), ('Baja California', 'BC'),
    ('Baja California Sur', 'BCS'), ('Campeche', 'CAMP'),
    ('Coahuila de Zaragoza', 'COAH'), ('Colima', 'COL'),
    ('Chiapas', 'CHIS'), ('Chihuahua', 'CHIH'),
    ('Ciudad de México', 'CDMX'), ('Durango', 'DGO'),
    ('Guanajuato', 'GTO'), ('Guerrero', 'GRO'),
    ('Hidalgo', 'HGO'), ('Jalisco', 'JAL'),
    ('México', 'MEX'), ('Michoacán de Ocampo', 'MICH'),
    ('Morelos', 'MOR'), ('Nayarit', 'NAY'),
    ('Nuevo León', 'NL'), ('Oaxaca', 'OAX'),
    ('Puebla', 'PUE'), ('Querétaro', 'QRO'),
    ('Quintana Roo', 'QROO'), ('San Luis Potosí', 'SLP'),
    ('Sinaloa', 'SIN'), ('Sonora', 'SON'),
    ('Tabasco', 'TAB'), ('Tamaulipas', 'TAMPS'),
    ('Tlaxcala', 'TLAX'), ('Veracruz de Ignacio de la Llave', 'VER'),
    ('Yucatán', 'YUC'), ('Zacatecas', 'ZAC');

-- ---------------------------------------------------------------------
-- ESTADOS DE PEDIDO
-- ---------------------------------------------------------------------
INSERT INTO estados_pedido (nombre) VALUES
    ('Pendiente'), ('Confirmado'), ('Preparando'),
    ('Enviado'), ('Entregado'), ('Cancelado');

-- ---------------------------------------------------------------------
-- CATEGORÍAS
-- ---------------------------------------------------------------------
INSERT INTO categorias (nombre, descripcion) VALUES
    ('Electrónica',  'Dispositivos y equipos electrónicos'),
    ('Computación',  'Equipos de cómputo y accesorios'),
    ('Telefonía',    'Celulares y accesorios de telefonía'),
    ('Hogar',        'Artículos para el hogar'),
    ('Ropa',         'Prendas de vestir'),
    ('Deportes',     'Artículos deportivos'),
    ('Videojuegos',  'Consolas, juegos y accesorios'),
    ('Automóviles',  'Autopartes y accesorios automotrices'),
    ('Libros',       'Libros y material educativo'),
    ('Otros',        'Artículos que no clasifican en otra categoría');

-- ---------------------------------------------------------------------
-- USUARIOS (credenciales)  |  contraseña de todos: password
-- ---------------------------------------------------------------------
INSERT INTO usuario (id_rol, nombre_usuario, correo, contrasena_hash, activo) VALUES
    ((SELECT id_rol FROM roles WHERE nombre = 'administrador'),
     'admin', 'admin@marketplace.mx',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE),
    ((SELECT id_rol FROM roles WHERE nombre = 'comprador'),
     'maria', 'maria@correo.mx',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE),
    ((SELECT id_rol FROM roles WHERE nombre = 'comprador'),
     'jose', 'jose@correo.mx',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE),
    ((SELECT id_rol FROM roles WHERE nombre = 'comprador'),
     'ana', 'ana@correo.mx',
     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', TRUE);

-- ---------------------------------------------------------------------
-- PERSONAS (datos personales de cada usuario)
-- ---------------------------------------------------------------------
INSERT INTO persona (id_usuario, nombre, apellido_paterno, apellido_materno, telefono) VALUES
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     'Administrador', 'Raiz', 'Sistema', '8112345670'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'maria'),
     'María', 'Fernanda', 'López', '8123456789'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'jose'),
     'José', 'Luis', 'Ramírez', '4612345678'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'ana'),
     'Ana', 'Paola', 'Torres', '5512345678');

-- ---------------------------------------------------------------------
-- DIRECCIONES (ningún campo NULL, país = México)
-- ---------------------------------------------------------------------
INSERT INTO direcciones
    (id_persona, nombre, calle, numero_exterior, numero_interior, colonia,
     codigo_postal, municipio, id_estado, pais, es_principal)
VALUES
    ((SELECT p.id_persona FROM persona p JOIN usuario u ON p.id_usuario = u.id_usuario
      WHERE u.nombre_usuario = 'admin'),
     'Oficina', 'Av. Principal', '123', 'A', 'Centro',
     '64000', 'Monterrey',
     (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León'),
     'México', TRUE),
    ((SELECT p.id_persona FROM persona p JOIN usuario u ON p.id_usuario = u.id_usuario
      WHERE u.nombre_usuario = 'maria'),
     'Casa', 'Av. Universidad', '500', '12', 'Del Valle',
     '66220', 'San Pedro Garza García',
     (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León'),
     'México', TRUE),
    ((SELECT p.id_persona FROM persona p JOIN usuario u ON p.id_usuario = u.id_usuario
      WHERE u.nombre_usuario = 'jose'),
     'Casa', 'Calle Hidalgo', '45', 'B', 'Centro',
     '38600', 'Acámbaro',
     (SELECT id_estado FROM estados_mexico WHERE nombre = 'Guanajuato'),
     'México', TRUE),
    ((SELECT p.id_persona FROM persona p JOIN usuario u ON p.id_usuario = u.id_usuario
      WHERE u.nombre_usuario = 'ana'),
     'Departamento', 'Av. Revolución', '890', '4', 'Roma Norte',
     '06700', 'Cuauhtémoc',
     (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México'),
     'México', TRUE);

-- ---------------------------------------------------------------------
-- PRODUCTOS (los publica el administrador, moneda MXN)
-- ---------------------------------------------------------------------
INSERT INTO productos
    (id_vendedor, id_categoria, nombre, descripcion, precio, existencia, estado, moneda)
VALUES
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Computación'),
     'Laptop Lenovo IdeaPad', 'Laptop de 15.6 pulgadas con 16 GB de RAM y SSD de 512 GB.',
     14999.00, 10, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Computación'),
     'Mouse Logitech MX', 'Mouse inalámbrico ergonómico con precisión para oficina.',
     799.00, 25, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Hogar'),
     'Licuadora Oster', 'Licuadora de 10 velocidades con vaso de vidrio de 1.5 L.',
     899.00, 15, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Hogar'),
     'Ventilador de Torre', 'Ventilador de torre de 40 cm con control remoto y temporizador.',
     1299.00, 8, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Ropa'),
     'Camisa Polo', 'Camisa polo de algodón manga corta, talla M.',
     299.00, 40, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Deportes'),
     'Zapatos Deportivos', 'Tenis deportivos ligeros para correr, talla 27.',
     1200.00, 12, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Telefonía'),
     'iPhone 15', 'Smartphone Apple de 128 GB con pantalla OLED de 6.1 pulgadas.',
     19999.00, 5, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Electrónica'),
     'Smart TV 55"', 'Televisor 4K UHD de 55 pulgadas con sistema operativo Android TV.',
     15999.00, 7, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Videojuegos'),
     'Consola PS5', 'Consola de videojuegos PlayStation 5 con mando inalámbrico.',
     10999.00, 6, 'activo', 'MXN'),
    ((SELECT id_usuario FROM usuario WHERE nombre_usuario = 'admin'),
     (SELECT id_categoria FROM categorias WHERE nombre = 'Libros'),
     'Cien años de soledad', 'Novela clásica de Gabriel García Márquez, edición conmemorativa.',
     250.00, 30, 'activo', 'MXN');

-- ---------------------------------------------------------------------
-- IMÁGENES (solo metadatos; las imágenes viven en Google Drive)
-- ---------------------------------------------------------------------
INSERT INTO imagenes (id_producto, nombre_archivo, ruta_drive, url_publica, es_principal)
VALUES
    ((SELECT id_producto FROM productos WHERE nombre = 'Laptop Lenovo IdeaPad'),
     'laptop-lenovo.jpg', 'computacion/laptop-lenovo.jpg',
     'https://drive.google.com/uc?export=view&id=laptop-lenovo-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Mouse Logitech MX'),
     'mouse-logitech.jpg', 'computacion/mouse-logitech.jpg',
     'https://drive.google.com/uc?export=view&id=mouse-logitech-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Licuadora Oster'),
     'licuadora-oster.jpg', 'hogar/licuadora-oster.jpg',
     'https://drive.google.com/uc?export=view&id=licuadora-oster-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Ventilador de Torre'),
     'ventilador-torre.jpg', 'hogar/ventilador-torre.jpg',
     'https://drive.google.com/uc?export=view&id=ventilador-torre-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Camisa Polo'),
     'camisa-polo.jpg', 'ropa/camisa-polo.jpg',
     'https://drive.google.com/uc?export=view&id=camisa-polo-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Zapatos Deportivos'),
     'zapatos-deportivos.jpg', 'ropa/zapatos-deportivos.jpg',
     'https://drive.google.com/uc?export=view&id=zapatos-deportivos-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'iPhone 15'),
     'iphone-15.jpg', 'telefonia/iphone-15.jpg',
     'https://drive.google.com/uc?export=view&id=iphone-15-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Smart TV 55"'),
     'smart-tv-55.jpg', 'electronica/smart-tv-55.jpg',
     'https://drive.google.com/uc?export=view&id=smart-tv-55-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Consola PS5'),
     'consola-ps5.jpg', 'videojuegos/consola-ps5.jpg',
     'https://drive.google.com/uc?export=view&id=consola-ps5-id', TRUE),
    ((SELECT id_producto FROM productos WHERE nombre = 'Cien años de soledad'),
     'cien-anos-de-soledad.jpg', 'libros/cien-anos-de-soledad.jpg',
     'https://drive.google.com/uc?export=view&id=cien-anos-soledad-id', TRUE);

-- ---------------------------------------------------------------------
-- CARRITO de María (activo, con 2 productos)
-- ---------------------------------------------------------------------
INSERT INTO carrito (id_usuario, estado)
SELECT u.id_usuario, 'activo'
FROM usuario u WHERE u.nombre_usuario = 'maria';

INSERT INTO detalle_carrito (id_carrito, id_producto, cantidad, precio_unitario)
SELECT c.id_carrito, pr.id_producto, 1, pr.precio
FROM carrito c, productos pr
WHERE c.id_usuario = (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'maria')
  AND c.estado = 'activo'
  AND pr.nombre = 'Laptop Lenovo IdeaPad';

INSERT INTO detalle_carrito (id_carrito, id_producto, cantidad, precio_unitario)
SELECT c.id_carrito, pr.id_producto, 2, pr.precio
FROM carrito c, productos pr
WHERE c.id_usuario = (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'maria')
  AND c.estado = 'activo'
  AND pr.nombre = 'Mouse Logitech MX';

-- ---------------------------------------------------------------------
-- PEDIDO 1: José (Entregado) - Laptop x1 + Mouse x1 = $15,798.00
-- ---------------------------------------------------------------------
INSERT INTO pedidos (id_usuario, id_direccion, id_estado_pedido, total)
VALUES (
    (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'jose'),
    (SELECT d.id_direccion FROM direcciones d
     JOIN persona p ON d.id_persona = p.id_persona
     JOIN usuario u ON p.id_usuario = u.id_usuario
     WHERE u.nombre_usuario = 'jose' AND d.es_principal = TRUE),
    (SELECT id_estado_pedido FROM estados_pedido WHERE nombre = 'Entregado'),
    15798.00
);

INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
SELECT (SELECT id_pedido FROM pedidos WHERE numero_pedido = 'PED-1000'),
       pr.id_producto, 1, pr.precio
FROM productos pr WHERE pr.nombre = 'Laptop Lenovo IdeaPad';

INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
SELECT (SELECT id_pedido FROM pedidos WHERE numero_pedido = 'PED-1000'),
       pr.id_producto, 1, pr.precio
FROM productos pr WHERE pr.nombre = 'Mouse Logitech MX';

-- ---------------------------------------------------------------------
-- PEDIDO 2: Ana (Pendiente) - Licuadora x1 + Ventilador x2 = $3,497.00
-- ---------------------------------------------------------------------
INSERT INTO pedidos (id_usuario, id_direccion, id_estado_pedido, total)
VALUES (
    (SELECT id_usuario FROM usuario WHERE nombre_usuario = 'ana'),
    (SELECT d.id_direccion FROM direcciones d
     JOIN persona p ON d.id_persona = p.id_persona
     JOIN usuario u ON p.id_usuario = u.id_usuario
     WHERE u.nombre_usuario = 'ana' AND d.es_principal = TRUE),
    (SELECT id_estado_pedido FROM estados_pedido WHERE nombre = 'Pendiente'),
    3497.00
);

INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
SELECT (SELECT id_pedido FROM pedidos WHERE numero_pedido = 'PED-1001'),
       pr.id_producto, 1, pr.precio
FROM productos pr WHERE pr.nombre = 'Licuadora Oster';

INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
SELECT (SELECT id_pedido FROM pedidos WHERE numero_pedido = 'PED-1001'),
       pr.id_producto, 2, pr.precio
FROM productos pr WHERE pr.nombre = 'Ventilador de Torre';
