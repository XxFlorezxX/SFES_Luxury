-- ============================================================
--  SFES Luxury — Migración v2 → v3
--
--  Usa este archivo SOLO si ya tienes la base `sfes_luxury`
--  con datos (productos, clientes o pedidos) que quieres conservar.
--  Para una instalación limpia usa database/sfes.sql.
--
--      mysql -u root -p sfes_luxury < database/migracion_v2_a_v3.sql
--
--  Qué hace:
--   · Agrega columnas nuevas SIN borrar ninguna existente.
--   · Crea las tablas nuevas (reseñas, favoritos, cupones, gastos,
--     kardex de inventario, tarifas de envío, categorías, config).
--   · Rellena costo/slug/SKU de los productos que ya tenías.
--   · Recalcula el subtotal de los pedidos históricos.
--
--  Nada de esto elimina datos. Es seguro ejecutarlo una sola vez;
--  si lo ejecutas dos veces verás avisos de "Duplicate column",
--  que puedes ignorar (MariaDB los omite con IF NOT EXISTS).
-- ============================================================

SET NAMES utf8mb4;
USE sfes_luxury;

-- ------------------------------------------------------------
--  1. usuarios
-- ------------------------------------------------------------
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS telefono      VARCHAR(30) NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS activo        TINYINT(1) NOT NULL DEFAULT 1 AFTER rol,
    ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL AFTER activo;

-- ------------------------------------------------------------
--  2. productos — columnas comerciales y contables
-- ------------------------------------------------------------
ALTER TABLE productos
    ADD COLUMN IF NOT EXISTS sku            VARCHAR(40)   NULL AFTER id,
    ADD COLUMN IF NOT EXISTS slug           VARCHAR(180)  NULL AFTER nombre,
    ADD COLUMN IF NOT EXISTS precio_antes   DECIMAL(10,2) NULL AFTER precio,
    ADD COLUMN IF NOT EXISTS costo          DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER precio_antes,
    ADD COLUMN IF NOT EXISTS material       VARCHAR(60)   NOT NULL DEFAULT 'Baño de oro' AFTER categoria,
    ADD COLUMN IF NOT EXISTS peso_gramos    DECIMAL(6,2)  NULL AFTER material,
    ADD COLUMN IF NOT EXISTS stock_minimo   INT           NOT NULL DEFAULT 3 AFTER stock,
    ADD COLUMN IF NOT EXISTS destacado      TINYINT(1)    NOT NULL DEFAULT 0 AFTER imagen,
    ADD COLUMN IF NOT EXISTS activo         TINYINT(1)    NOT NULL DEFAULT 1 AFTER destacado,
    ADD COLUMN IF NOT EXISTS vistas         INT           NOT NULL DEFAULT 0 AFTER activo,
    ADD COLUMN IF NOT EXISTS actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Costo estimado en 45% del precio para las piezas que ya existían
-- (el administrador puede corregirlo pieza por pieza desde el panel).
UPDATE productos SET costo = ROUND(precio * 0.45) WHERE costo = 0;

-- Slug legible a partir del nombre, para las URLs de la ficha de producto.
UPDATE productos
SET slug = LOWER(
      REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
      TRIM(nombre),' ','-'),'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ñ','n'),',',''),'.','')
    )
WHERE slug IS NULL OR slug = '';

-- SKU secuencial para las piezas que no lo tenían.
UPDATE productos SET sku = CONCAT('SFS-', UPPER(LEFT(categoria,3)), '-', LPAD(id,3,'0'))
WHERE sku IS NULL OR sku = '';

ALTER TABLE productos
    ADD UNIQUE KEY IF NOT EXISTS uq_sku (sku),
    ADD UNIQUE KEY IF NOT EXISTS uq_slug (slug),
    ADD INDEX  IF NOT EXISTS idx_categoria (categoria),
    ADD INDEX  IF NOT EXISTS idx_material (material),
    ADD INDEX  IF NOT EXISTS idx_precio (precio),
    ADD INDEX  IF NOT EXISTS idx_activo_destacado (activo, destacado);

-- ------------------------------------------------------------
--  3. pedidos — desglose financiero
-- ------------------------------------------------------------
ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS codigo        VARCHAR(20) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER especificaciones,
    ADD COLUMN IF NOT EXISTS descuento     DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN IF NOT EXISTS cupon_codigo  VARCHAR(40) NULL AFTER descuento,
    ADD COLUMN IF NOT EXISTS envio         DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cupon_codigo,
    ADD COLUMN IF NOT EXISTS costo_total   DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total,
    ADD COLUMN IF NOT EXISTS metodo_pago   VARCHAR(40) NOT NULL DEFAULT 'contraentrega' AFTER costo_total,
    ADD COLUMN IF NOT EXISTS actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD INDEX IF NOT EXISTS idx_fecha (creado_en);

ALTER TABLE detalle_pedidos
    ADD COLUMN IF NOT EXISTS costo DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER precio;

-- Los pedidos históricos no tenían subtotal separado: el total era el subtotal.
UPDATE pedidos SET subtotal = total WHERE subtotal = 0 AND total > 0;
UPDATE pedidos SET codigo = CONCAT('SFS-', LPAD(id, 5, '0')) WHERE codigo IS NULL;

-- Recupera el costo histórico de cada línea a partir del costo actual del producto.
UPDATE detalle_pedidos dp
JOIN productos p ON p.id = dp.producto_id
SET dp.costo = p.costo
WHERE dp.costo = 0;

UPDATE pedidos p
SET costo_total = COALESCE((SELECT SUM(dp.costo * dp.cantidad) FROM detalle_pedidos dp WHERE dp.pedido_id = p.id), 0)
WHERE costo_total = 0;

-- ------------------------------------------------------------
--  4. mensajes de contacto
-- ------------------------------------------------------------
ALTER TABLE mensajes_contacto
    ADD COLUMN IF NOT EXISTS asunto VARCHAR(160) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS leido  TINYINT(1) NOT NULL DEFAULT 0 AFTER mensaje;

-- ------------------------------------------------------------
--  5. Tablas nuevas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS intentos_login (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    email     VARCHAR(150) NOT NULL,
    ip        VARCHAR(45)  NOT NULL,
    exitoso   TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_fecha (email, creado_en),
    INDEX idx_ip_fecha (ip, creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(60)  NOT NULL UNIQUE,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    icono       VARCHAR(40)  NULL,
    orden       SMALLINT     NOT NULL DEFAULT 0,
    activa      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS producto_imagenes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    url         VARCHAR(255) NOT NULL,
    alt         VARCHAR(150) NULL,
    orden       SMALLINT NOT NULL DEFAULT 0,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto (producto_id, orden)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resenas (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    producto_id  INT NOT NULL,
    usuario_id   INT NOT NULL,
    calificacion TINYINT NOT NULL,
    titulo       VARCHAR(120) NULL,
    comentario   TEXT NOT NULL,
    verificada   TINYINT(1) NOT NULL DEFAULT 0,
    aprobada     TINYINT(1) NOT NULL DEFAULT 1,
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    UNIQUE KEY uq_resena_cliente (producto_id, usuario_id),
    INDEX idx_producto (producto_id, aprobada)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favoritos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    producto_id INT NOT NULL,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_favorito (usuario_id, producto_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cupones (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    codigo        VARCHAR(40) NOT NULL UNIQUE,
    descripcion   VARCHAR(160) NULL,
    tipo          ENUM('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje',
    valor         DECIMAL(10,2) NOT NULL,
    minimo_compra DECIMAL(10,2) NOT NULL DEFAULT 0,
    usos_maximos  INT NULL,
    usos_actuales INT NOT NULL DEFAULT 0,
    inicia_en     DATE NULL,
    vence_en      DATE NULL,
    activo        TINYINT(1) NOT NULL DEFAULT 1,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activo (activo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tarifas_envio (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ciudad         VARCHAR(100) NOT NULL UNIQUE,
    costo          DECIMAL(10,2) NOT NULL DEFAULT 0,
    dias_estimados VARCHAR(30) NOT NULL DEFAULT '2 a 4 días',
    activo         TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    producto_id      INT NOT NULL,
    tipo             ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad         INT NOT NULL,
    stock_resultante INT NOT NULL,
    motivo           VARCHAR(120) NOT NULL,
    pedido_id        INT NULL,
    usuario_id       INT NULL,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto_fecha (producto_id, creado_en),
    INDEX idx_fecha (creado_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gastos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    concepto   VARCHAR(160) NOT NULL,
    categoria  ENUM('inventario','nomina','marketing','logistica','servicios','impuestos','otros')
               NOT NULL DEFAULT 'otros',
    monto      DECIMAL(12,2) NOT NULL,
    fecha      DATE NOT NULL,
    nota       TEXT NULL,
    usuario_id INT NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS configuracion (
    clave       VARCHAR(60) PRIMARY KEY,
    valor       TEXT NOT NULL,
    descripcion VARCHAR(200) NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  6. Semillas de las tablas nuevas (solo si están vacías)
-- ------------------------------------------------------------
INSERT IGNORE INTO categorias (nombre, slug, descripcion, icono, orden)
SELECT DISTINCT categoria,
       LOWER(REPLACE(categoria,' ','-')),
       CONCAT('Piezas de la línea ', categoria, '.'),
       'fa-gem', 0
FROM productos;

INSERT IGNORE INTO tarifas_envio (ciudad, costo, dias_estimados) VALUES
('Medellín', 8000, '1 a 2 días'), ('Bogotá', 12000, '2 a 3 días'),
('Cali', 12000, '2 a 3 días'), ('Barranquilla', 15000, '3 a 5 días'),
('Cartagena', 15000, '3 a 5 días'), ('Bucaramanga', 13000, '2 a 4 días'),
('Pereira', 10000, '2 a 3 días'), ('Otra ciudad', 18000, '4 a 7 días');

INSERT IGNORE INTO cupones (codigo, descripcion, tipo, valor, minimo_compra, usos_maximos, vence_en) VALUES
('BIENVENIDA10', 'Descuento de bienvenida para tu primera compra', 'porcentaje', 10, 150000, 500, '2027-12-31'),
('ORO20',        'Temporada de oro: 20% en compras superiores a $400.000', 'porcentaje', 20, 400000, 200, '2027-12-31'),
('ENVIOGRATIS',  'Descuento fijo equivalente al envío nacional', 'fijo', 15000, 200000, NULL, '2027-12-31');

INSERT IGNORE INTO configuracion (clave, valor, descripcion) VALUES
('tienda_nombre',      'SFES Luxury',            'Nombre comercial'),
('tienda_email',       'contacto@sfesluxury.com','Correo de contacto'),
('tienda_telefono',    '+57 300 000 0000',       'Teléfono / WhatsApp'),
('envio_gratis_desde', '200000',                 'Monto desde el cual el envío es gratis'),
('moneda',             'COP',                    'Moneda de la tienda'),
('iva_porcentaje',     '0',                      'IVA aplicado (0 = precios ya incluyen impuestos)');

-- ------------------------------------------------------------
--  7. Kardex inicial: deja registrado el stock actual como entrada
-- ------------------------------------------------------------
INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, stock_resultante, motivo)
SELECT p.id, 'entrada', p.stock, p.stock, 'Saldo inicial al migrar a v3'
FROM productos p
WHERE NOT EXISTS (SELECT 1 FROM movimientos_inventario m WHERE m.producto_id = p.id);
