-- =====================================================================
--  MARKETPLACE MÉXICO - Esquema de base de datos (PostgreSQL)
--  Examen Ordinario Unidad 3 - Desarrollo Web Integral
--  Autor: Victor Ariel Soto Rodriguez
--
--  NOTA: Las imágenes NO se guardan en la base de datos. Solo se
--  almacena la información para localizarlas en Google Drive
--  (carpeta Marketplace-Mexico/{categoria}/archivo.jpg).
--  La ruta y el enlace público se guardan en la tabla `imagenes`.
--
--  Diseño de usuarios:
--    usuario  -> credenciales (nombre_usuario, correo, contraseña hash)
--    persona  -> datos personales, asociada a su usuario y sus direcciones
--  Todos los IDs llevan el prefijo de su tabla (id_usuario, id_persona...).
-- =====================================================================

-- Extension opcional: búsqueda de texto difusa en productos (LIKE / ILIKE)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- =====================================================================
-- 1. CATÁLOGOS BASE
-- =====================================================================

-- Roles de usuario (comprador, vendedor, administrador)
CREATE TABLE roles (
    id_rol         SERIAL PRIMARY KEY,
    nombre         VARCHAR(50)  NOT NULL UNIQUE,
    descripcion    VARCHAR(200),
    fecha_registro TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Estados de la República Mexicana (consideración México)
CREATE TABLE estados_mexico (
    id_estado   SERIAL PRIMARY KEY,
    nombre      VARCHAR(60) NOT NULL UNIQUE,
    abreviatura VARCHAR(5)  NOT NULL UNIQUE
);

-- Catálogo de estados por los que pasa un pedido
CREATE TABLE estados_pedido (
    id_estado_pedido SERIAL PRIMARY KEY,
    nombre           VARCHAR(30) NOT NULL UNIQUE
        CHECK (nombre IN ('Pendiente', 'Confirmado', 'Preparando',
                          'Enviado', 'Entregado', 'Cancelado'))
);

-- Categorías para organizar los productos
CREATE TABLE categorias (
    id_categoria   SERIAL PRIMARY KEY,
    nombre         VARCHAR(80)  NOT NULL UNIQUE,
    descripcion    VARCHAR(200),
    activo         BOOLEAN      NOT NULL DEFAULT TRUE,
    fecha_registro TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- 2. USUARIOS (credenciales) Y PERSONAS (datos personales)
-- =====================================================================

-- Cuenta de acceso: solo identificación y credenciales
CREATE TABLE usuario (
    id_usuario      SERIAL PRIMARY KEY,
    id_rol          INT          NOT NULL
        REFERENCES roles (id_rol) ON UPDATE CASCADE ON DELETE RESTRICT,
    nombre_usuario  VARCHAR(50)  NOT NULL UNIQUE,
    correo          VARCHAR(150) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    activo          BOOLEAN      NOT NULL DEFAULT TRUE,
    fecha_registro  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_usuario_correo
        CHECK (correo ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')
);

-- Datos personales del usuario (1:1 con usuario)
CREATE TABLE persona (
    id_persona        SERIAL PRIMARY KEY,
    id_usuario        INT          NOT NULL UNIQUE
        REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    nombre            VARCHAR(80)  NOT NULL,
    apellido_paterno  VARCHAR(80)  NOT NULL,
    apellido_materno  VARCHAR(80),
    telefono          VARCHAR(10)
        CHECK (telefono ~ '^[0-9]{10}$'),          -- formato mexicano (10 dígitos)
    fecha_registro    TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Direcciones de entrega de la persona (consideración México)
CREATE TABLE direcciones (
    id_direccion    SERIAL PRIMARY KEY,
    id_persona      INT          NOT NULL
        REFERENCES persona (id_persona) ON DELETE CASCADE,
    nombre          VARCHAR(100) NOT NULL,
    calle           VARCHAR(150) NOT NULL,
    numero_exterior VARCHAR(10)  NOT NULL,
    numero_interior VARCHAR(10),
    colonia         VARCHAR(120) NOT NULL,
    codigo_postal   CHAR(5)      NOT NULL
        CHECK (codigo_postal ~ '^[0-9]{5}$'),      -- CP mexicano
    municipio       VARCHAR(120) NOT NULL,
    id_estado       INT          NOT NULL
        REFERENCES estados_mexico (id_estado) ON UPDATE CASCADE ON DELETE RESTRICT,
    pais            VARCHAR(40)  NOT NULL DEFAULT 'México'
        CHECK (pais = 'México'),                   -- país fijo
    es_principal    BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_registro  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Catálogo de códigos postales (autocompletado de direcciones sin API)
CREATE TABLE codigos_postales (
    id_codigo_postal SERIAL PRIMARY KEY,
    codigo_postal   CHAR(5)      NOT NULL
        CHECK (codigo_postal ~ '^[0-9]{5}$'),        -- CP mexicano
    colonia         VARCHAR(120) NOT NULL,
    municipio       VARCHAR(120) NOT NULL,
    id_estado       INT          NOT NULL
        REFERENCES estados_mexico (id_estado) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE (codigo_postal, colonia)
);

CREATE INDEX idx_codigos_postales_cp      ON codigos_postales (codigo_postal);
CREATE INDEX idx_codigos_postales_estado  ON codigos_postales (id_estado);

-- =====================================================================
-- 3. PRODUCTOS E IMÁGENES
-- =====================================================================

CREATE TABLE productos (
    id_producto        SERIAL PRIMARY KEY,
    identificador      VARCHAR(20)   NOT NULL UNIQUE,  -- SKU o código interno
    id_vendedor        INT           NOT NULL
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT,
    id_categoria       INT           NOT NULL
        REFERENCES categorias (id_categoria) ON UPDATE CASCADE ON DELETE RESTRICT,
    nombre             VARCHAR(150)  NOT NULL,
    descripcion        TEXT          NOT NULL,
    precio             NUMERIC(12,2) NOT NULL
        CHECK (precio >= 0),                        -- moneda MXN
    existencia         INT           NOT NULL DEFAULT 0
        CHECK (existencia >= 0),
    estado             VARCHAR(20)   NOT NULL DEFAULT 'activo'
        CHECK (estado IN ('activo', 'inactivo')),
    moneda             CHAR(3)       NOT NULL DEFAULT 'MXN'
        CHECK (moneda = 'MXN'),                     -- consideración México
    fecha_registro     TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Imágenes de productos: SOLO METADATOS (nunca el archivo en la BD).
-- ruta_drive guarda la ubicación en Google Drive (ej. 'electronica/laptop.jpg')
-- y url_publica el enlace/identificador del archivo en Google Drive.
CREATE TABLE imagenes (
    id_imagen       SERIAL PRIMARY KEY,
    id_producto     INT           NOT NULL
        REFERENCES productos (id_producto) ON DELETE CASCADE,
    nombre_archivo  VARCHAR(255) NOT NULL,
    ruta_drive      VARCHAR(500) NOT NULL,
    url_publica     TEXT         NOT NULL,
    es_principal    BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_registro  TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================================
-- 4. CARRITO DE COMPRAS
-- =====================================================================

CREATE TABLE carrito (
    id_carrito     SERIAL PRIMARY KEY,
    id_usuario     INT         NOT NULL
        REFERENCES usuario (id_usuario) ON DELETE CASCADE,
    estado         VARCHAR(20) NOT NULL DEFAULT 'activo'
        CHECK (estado IN ('activo', 'convertido', 'eliminado')),
    fecha_creacion TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Un solo carrito activo por usuario
CREATE UNIQUE INDEX idx_carrito_activo_por_usuario
    ON carrito (id_usuario) WHERE estado = 'activo';

CREATE TABLE detalle_carrito (
    id_detalle_carrito SERIAL PRIMARY KEY,
    id_carrito         INT           NOT NULL
        REFERENCES carrito (id_carrito) ON DELETE CASCADE,
    id_producto        INT           NOT NULL
        REFERENCES productos (id_producto) ON DELETE CASCADE,
    cantidad           INT           NOT NULL
        CHECK (cantidad >= 1),
    precio_unitario    NUMERIC(12,2) NOT NULL
        CHECK (precio_unitario >= 0),
    subtotal           NUMERIC(12,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED,
    fecha_agregado     TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE (id_carrito, id_producto)
);

-- =====================================================================
-- 5. PEDIDOS
-- =====================================================================

-- Secuencia para generar el número de pedido de forma automática
CREATE SEQUENCE seq_numero_pedido START 1000;

CREATE TABLE pedidos (
    id_pedido      SERIAL PRIMARY KEY,
    numero_pedido  VARCHAR(20)   NOT NULL UNIQUE
        DEFAULT 'PED-' || nextval('seq_numero_pedido'),
    id_usuario     INT           NOT NULL
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT,
    id_direccion   INT           NOT NULL
        REFERENCES direcciones (id_direccion) ON DELETE RESTRICT,
    id_estado_pedido INT         NOT NULL
        REFERENCES estados_pedido (id_estado_pedido) ON DELETE RESTRICT,
    fecha_pedido   TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total          NUMERIC(12,2) NOT NULL
        CHECK (total >= 0),
    moneda         CHAR(3)       NOT NULL DEFAULT 'MXN'
        CHECK (moneda = 'MXN')
);

CREATE TABLE detalle_pedido (
    id_detalle_pedido SERIAL PRIMARY KEY,
    id_pedido         INT           NOT NULL
        REFERENCES pedidos (id_pedido) ON DELETE CASCADE,
    id_producto       INT           NOT NULL
        REFERENCES productos (id_producto) ON DELETE RESTRICT,
    cantidad          INT           NOT NULL
        CHECK (cantidad >= 1),
    precio_unitario   NUMERIC(12,2) NOT NULL
        CHECK (precio_unitario >= 0),
    subtotal          NUMERIC(12,2) GENERATED ALWAYS AS (cantidad * precio_unitario) STORED
);

-- =====================================================================
-- 6. ÍNDICES (búsquedas y llaves foráneas)
-- =====================================================================

CREATE INDEX idx_persona_usuario     ON persona (id_usuario);
CREATE INDEX idx_direcciones_persona ON direcciones (id_persona);
CREATE INDEX idx_direcciones_estado  ON direcciones (id_estado);
CREATE INDEX idx_productos_vendedor  ON productos (id_vendedor);
CREATE INDEX idx_productos_categoria ON productos (id_categoria);
CREATE INDEX idx_productos_nombre    ON productos USING GIN (nombre gin_trgm_ops);
CREATE INDEX idx_imagenes_producto   ON imagenes (id_producto);
CREATE INDEX idx_carrito_usuario     ON carrito (id_usuario);
CREATE INDEX idx_detalle_carrito     ON detalle_carrito (id_carrito);
CREATE INDEX idx_detalle_carrito_prod ON detalle_carrito (id_producto);
CREATE INDEX idx_pedidos_usuario     ON pedidos (id_usuario);
CREATE INDEX idx_pedidos_estado      ON pedidos (id_estado_pedido);
CREATE INDEX idx_detalle_pedido      ON detalle_pedido (id_pedido);

-- =====================================================================
-- 7. TRIGGER: actualiza fecha_actualizacion del producto
-- =====================================================================

CREATE OR REPLACE FUNCTION fn_actualizar_fecha_producto()
RETURNS TRIGGER AS $$
BEGIN
    NEW.fecha_actualizacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_productos_actualizacion
    BEFORE UPDATE ON productos
    FOR EACH ROW
    EXECUTE FUNCTION fn_actualizar_fecha_producto();

-- =====================================================================
-- 8. DATOS SEMILLA
--    Los datos de ejemplo viven en database/semillas.sql.
--    Ejecutar:  psql -U postgres -d marketplace -f schema.sql
--               psql -U postgres -d marketplace -f semillas.sql
-- =====================================================================
