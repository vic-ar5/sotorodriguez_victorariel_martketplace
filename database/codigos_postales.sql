-- =====================================================================
-- Catálogo de códigos postales MX (autocompletado de direcciones sin API)
-- Ejecutar después de database/schema.sql:
--   psql -U postgres -d marketplace -f database/codigos_postales.sql
-- =====================================================================

CREATE TABLE IF NOT EXISTS codigos_postales (
    id_codigo_postal SERIAL PRIMARY KEY,
    codigo_postal   CHAR(5)      NOT NULL
        CHECK (codigo_postal ~ '^[0-9]{5}$'),
    colonia         VARCHAR(120) NOT NULL,
    municipio       VARCHAR(120) NOT NULL,
    id_estado       INT          NOT NULL
        REFERENCES estados_mexico (id_estado) ON UPDATE CASCADE ON DELETE RESTRICT,
    UNIQUE (codigo_postal, colonia)
);

CREATE INDEX IF NOT EXISTS idx_codigos_postales_cp      ON codigos_postales (codigo_postal);
CREATE INDEX IF NOT EXISTS idx_codigos_postales_estado  ON codigos_postales (id_estado);

-- ---------------------------------------------------------------------
-- Datos semilla de ejemplo.
-- Agrega más filas con el mismo formato; el estado debe coincidir con el
-- catálogo estados_mexico. Ejemplo:
--   INSERT INTO codigos_postales (codigo_postal, colonia, municipio, id_estado)
--   VALUES ('00000', 'Mi Colonia', 'Mi Municipio',
--           (SELECT id_estado FROM estados_mexico WHERE nombre = 'Estado'));
-- ---------------------------------------------------------------------
INSERT INTO codigos_postales (codigo_postal, colonia, municipio, id_estado) VALUES
    -- Nuevo León · Monterrey y San Pedro Garza García
    ('66220', 'Del Valle',            'San Pedro Garza García',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('66230', 'Fuentes del Valle',    'San Pedro Garza García',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('66260', 'Los Ángeles',          'San Pedro Garza García',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64040', 'Deportivo Obispado',   'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64040', 'Mitras Norte',         'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64000', 'Centro',               'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64710', 'Cumbres 1er Sector',   'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64720', 'Cumbres 2do Sector',   'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64890', 'Linda Vista',          'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64900', 'Colinas de San Jerónimo', 'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),
    ('64290', 'San Bernabé',          'Monterrey',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Nuevo León')),

    -- Ciudad de México
    ('01000', 'San Ángel',            'Álvaro Obregón',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),
    ('01030', 'Lomas de San Ángel Inn', 'Álvaro Obregón',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),
    ('11000', 'Lomas de Chapultepec', 'Miguel Hidalgo',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),
    ('11520', 'Polanco',              'Miguel Hidalgo',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),
    ('06000', 'Centro Histórico',     'Cuauhtémoc',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),
    ('03100', 'Del Valle',            'Benito Juárez',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),
    ('03200', 'Nápoles',              'Benito Juárez',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Ciudad de México')),

    -- Jalisco
    ('44100', 'Centro',               'Guadalajara',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Jalisco')),
    ('44600', 'Americana',            'Guadalajara',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Jalisco')),

    -- Yucatán
    ('97000', 'Centro',               'Mérida',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Yucatán')),

    -- Estado de México
    ('54000', 'Centro',               'Tlalnepantla de Baz',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'México')),
    ('53340', 'Lomas Verdes',         'Naucalpan de Juárez',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'México')),

    -- Puebla
    ('72000', 'Centro Histórico',     'Puebla',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Puebla')),

    -- Baja California
    ('22100', 'Centro',               'Tijuana',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Baja California')),

    -- Chihuahua
    ('31000', 'Centro',               'Chihuahua',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Chihuahua')),
    ('31110', 'Arboledas',            'Chihuahua',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Chihuahua')),

    -- Querétaro
    ('76000', 'Centro',               'Querétaro',
        (SELECT id_estado FROM estados_mexico WHERE nombre = 'Querétaro'));
