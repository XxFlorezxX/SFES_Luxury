-- ============================================================
--  SFES Luxury — Esquema de base de datos  ·  v3
--  Joyería en oro y plata · e-commerce completo
--
--  Instalación limpia:
--      mysql -u root -p < database/sfes.sql
--  o en XAMPP: phpMyAdmin → Importar → este archivo.
--  Se puede volver a ejecutar sin errores (no duplica los datos semilla).
--
--  Si YA tienes datos de la v2, NO ejecutes este archivo:
--  usa database/migracion_v2_a_v3.sql (conserva tus registros).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE DATABASE IF NOT EXISTS sfes_luxury
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sfes_luxury;

-- ============================================================
--  1. IDENTIDAD Y ACCESO
-- ============================================================

-- ------------------------------------------------------------
--  usuarios — administradores y clientes registrados
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(120) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password_hash  VARCHAR(255) NOT NULL,
    telefono       VARCHAR(30)  NULL,
    rol            ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_acceso  DATETIME     NULL,
    creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rol (rol)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  intentos_login — freno de fuerza bruta (bloqueo temporal)
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

-- ------------------------------------------------------------
--  direcciones — libreta de envío del cliente
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS direcciones (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT NOT NULL,
    nombres          VARCHAR(150) NOT NULL,
    telefono         VARCHAR(30)  NOT NULL,
    direccion        VARCHAR(300) NOT NULL,
    ciudad           VARCHAR(100) NOT NULL DEFAULT '',
    especificaciones TEXT NULL,
    principal        TINYINT(1) NOT NULL DEFAULT 1,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB;

-- ============================================================
--  2. CATÁLOGO
-- ============================================================

-- ------------------------------------------------------------
--  categorias — normaliza lo que antes era texto libre
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(60)  NOT NULL UNIQUE,
    slug        VARCHAR(80)  NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    icono       VARCHAR(40)  NULL,
    orden       SMALLINT     NOT NULL DEFAULT 0,
    activa      TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  productos
--  costo  = lo que le cuesta la pieza al negocio  → margen y utilidad
--  precio = lo que paga el cliente
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS productos (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    sku           VARCHAR(40)   NULL UNIQUE,
    nombre        VARCHAR(150)  NOT NULL,
    slug          VARCHAR(180)  NULL UNIQUE,
    descripcion   TEXT          NOT NULL,
    precio        DECIMAL(10,2) NOT NULL,
    precio_antes  DECIMAL(10,2) NULL,
    costo         DECIMAL(10,2) NOT NULL DEFAULT 0,
    categoria     VARCHAR(60)   NOT NULL,
    material      VARCHAR(60)   NOT NULL DEFAULT 'Baño de oro',
    peso_gramos   DECIMAL(6,2)  NULL,
    stock         INT           NOT NULL DEFAULT 0,
    stock_minimo  INT           NOT NULL DEFAULT 3,
    imagen        VARCHAR(255)  NOT NULL,
    destacado     TINYINT(1)    NOT NULL DEFAULT 0,
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    vistas        INT           NOT NULL DEFAULT 0,
    creado_en     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_categoria (categoria),
    INDEX idx_material (material),
    INDEX idx_precio (precio),
    INDEX idx_activo_destacado (activo, destacado),
    FULLTEXT KEY ft_busqueda (nombre, descripcion)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  producto_imagenes — galería adicional de cada pieza
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS producto_imagenes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    producto_id INT NOT NULL,
    url         VARCHAR(255) NOT NULL,
    alt         VARCHAR(150) NULL,
    orden       SMALLINT NOT NULL DEFAULT 0,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto (producto_id, orden)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  resenas — calificaciones de clientes
--  Solo se muestran las aprobadas; "verificada" = el cliente
--  realmente compró la pieza (se cruza contra detalle_pedidos).
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
--  favoritos — lista de deseos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favoritos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT NOT NULL,
    producto_id INT NOT NULL,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)  ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_favorito (usuario_id, producto_id)
) ENGINE=InnoDB;

-- ============================================================
--  3. VENTAS
-- ============================================================

-- ------------------------------------------------------------
--  cupones — descuentos promocionales
-- ------------------------------------------------------------
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

-- ------------------------------------------------------------
--  tarifas_envio — costo de envío por ciudad
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tarifas_envio (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ciudad          VARCHAR(100) NOT NULL UNIQUE,
    costo           DECIMAL(10,2) NOT NULL DEFAULT 0,
    dias_estimados  VARCHAR(30) NOT NULL DEFAULT '2 a 4 días',
    activo          TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  pedidos
--  Desglose financiero completo: subtotal → descuento → envío → total.
--  costo_total guarda el costo de la mercancía vendida (CMV) en el
--  momento de la venta, para que la contabilidad sea histórica y no
--  cambie si después se edita el costo del producto.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pedidos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    codigo           VARCHAR(20) NULL UNIQUE,
    usuario_id       INT NULL,
    nombres          VARCHAR(150) NOT NULL,
    email            VARCHAR(150) NULL,
    telefono         VARCHAR(30)  NOT NULL,
    direccion        VARCHAR(300) NOT NULL,
    ciudad           VARCHAR(100) NOT NULL DEFAULT '',
    especificaciones TEXT NULL,
    subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
    descuento        DECIMAL(12,2) NOT NULL DEFAULT 0,
    cupon_codigo     VARCHAR(40) NULL,
    envio            DECIMAL(12,2) NOT NULL DEFAULT 0,
    total            DECIMAL(12,2) NOT NULL DEFAULT 0,
    costo_total      DECIMAL(12,2) NOT NULL DEFAULT 0,
    metodo_pago      VARCHAR(40) NOT NULL DEFAULT 'contraentrega',
    estado           ENUM('pendiente','confirmado','procesando','enviado','entregado','cancelado')
                     NOT NULL DEFAULT 'pendiente',
    notas_admin      TEXT NULL,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_estado  (estado),
    INDEX idx_fecha   (creado_en)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  detalle_pedidos — items del pedido (con costo histórico)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS detalle_pedidos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id   INT NOT NULL,
    producto_id INT NOT NULL,
    nombre      VARCHAR(150)  NOT NULL,
    precio      DECIMAL(12,2) NOT NULL,
    costo       DECIMAL(12,2) NOT NULL DEFAULT 0,
    cantidad    SMALLINT      NOT NULL DEFAULT 1,
    imagen      VARCHAR(255)  NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    INDEX idx_producto (producto_id)
) ENGINE=InnoDB;

-- ============================================================
--  4. INVENTARIO Y CONTABILIDAD
-- ============================================================

-- ------------------------------------------------------------
--  movimientos_inventario — kardex
--  Toda variación de stock queda registrada y es auditable.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS movimientos_inventario (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    producto_id       INT NOT NULL,
    tipo              ENUM('entrada','salida','ajuste') NOT NULL,
    cantidad          INT NOT NULL,
    stock_resultante  INT NOT NULL,
    motivo            VARCHAR(120) NOT NULL,
    pedido_id         INT NULL,
    usuario_id        INT NULL,
    creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_producto_fecha (producto_id, creado_en),
    INDEX idx_fecha (creado_en)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
--  gastos — egresos operativos para el estado de resultados
-- ------------------------------------------------------------
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

-- ============================================================
--  5. SOPORTE Y CONFIGURACIÓN
-- ============================================================

CREATE TABLE IF NOT EXISTS mensajes_contacto (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(120) NOT NULL,
    email     VARCHAR(150) NOT NULL,
    asunto    VARCHAR(160) NULL,
    mensaje   TEXT NOT NULL,
    leido     TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leido (leido)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS configuracion (
    clave       VARCHAR(60) PRIMARY KEY,
    valor       TEXT NOT NULL,
    descripcion VARCHAR(200) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS disenos_personalizados (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT NULL,
    tipo          VARCHAR(100) NOT NULL,
    nombre_ia     VARCHAR(200) NULL,
    metal         VARCHAR(60)  NULL,
    piedra        VARCHAR(60)  NULL,
    estilo        VARCHAR(60)  NULL,
    descripcion   TEXT NULL,
    prompt_imagen TEXT NULL,
    imagen_url    TEXT NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB;

-- ============================================================
--  DATOS SEMILLA
-- ============================================================

-- Administrador · admin@sfesluxury.com / 1234
INSERT IGNORE INTO usuarios (nombre, email, password_hash, rol) VALUES
('Administrador SFES', 'admin@sfesluxury.com',
 '$2y$10$xstbMgkDUroTZpf/hlLqtuJp.9gwzM5xwJgJDOcMY1aEjr8lzLI5G', 'admin');

INSERT IGNORE INTO categorias (nombre, slug, descripcion, icono, orden) VALUES
('Anillos',  'anillos',  'Solitarios, bandas y piezas de compromiso.',   'fa-ring',       1),
('Aretes',   'aretes',   'Topos, argollas y candongas para cada ocasión.','fa-star',       2),
('Collares', 'collares', 'Cadenas y gargantillas en oro y plata.',        'fa-gem',        3),
('Pulseras', 'pulseras', 'Manillas tejidas, rígidas y con charms.',       'fa-circle-notch',4),
('Dijes',    'dijes',    'Charms y medallas para personalizar.',          'fa-heart',      5),
('Sets',     'sets',     'Conjuntos coordinados listos para regalar.',    'fa-box-open',   6);

-- Catálogo (se conservan las piezas originales del proyecto y se
-- amplían con referencias nuevas de oro y plata).
INSERT IGNORE INTO productos
 (sku, nombre, slug, descripcion, precio, precio_antes, costo, categoria, material, peso_gramos, stock, stock_minimo, imagen, destacado) VALUES
('SFS-COL-001','Cadena Elegante Oro Blanco','cadena-elegante-oro-blanco',
 'Cadena tejido veneciano en baño de oro blanco sobre base de plata 925. Cierre de mosquetón reforzado y acabado pulido espejo. Ideal para usar sola o en capas con otras cadenas.',
 350000, 420000, 168000,'Collares','Oro blanco', 8.40, 12, 3,'assets/img/productos/cadena-elegante.png',1),

('SFS-ARE-001','Aretes Abeja Dorado','aretes-abeja-dorado',
 'Topos con silueta de abeja bañados en oro de 18 quilates. Cierre de mariposa antialérgico, livianos y cómodos para uso diario.',
 180000, NULL, 79000,'Aretes','Baño de oro 18k', 2.10, 20, 5,'assets/img/productos/aretes-abeja.png',1),

('SFS-COL-002','Dije Dios Bañado en Oro','dije-dios-banado-en-oro',
 'Medalla religiosa con relieve tallado a mano, bañada en oro de 18 quilates. Se entrega con cadena de 45 cm en estuche de regalo.',
 220000, NULL, 96000,'Dijes','Baño de oro 18k', 3.60, 15, 4,'assets/img/productos/dije-dios.png',0),

('SFS-PUL-001','Manilla Perlas Azules','manilla-perlas-azules',
 'Manilla artesanal tejida a mano con perlas de cristal azul y separadores dorados. Ajustable de 16 a 19 cm con nudo corredizo.',
 150000, 185000, 62000,'Pulseras','Plata 925', 6.20, 18, 5,'assets/img/productos/manilla-perlas-azules.png',1),

('SFS-ANI-001','Anillo Solitario Clásico','anillo-solitario-clasico',
 'Solitario con circón de talla brillante engastado en garras, montado en plata 925 con baño de oro. Un diseño atemporal para pedir la mano o regalarse.',
 275000, NULL, 118000,'Anillos','Plata 925', 3.10, 10, 3,'assets/img/productos/no-image.png',1),

('SFS-ANI-002','Anillo Doble Aro','anillo-doble-aro',
 'Dos aros entrelazados con acabado mate y pulido en contraste. Minimalista, apilable y pensado para usar en cualquier dedo.',
 195000, NULL, 84000,'Anillos','Plata 925', 2.80, 14, 4,'assets/img/productos/no-image.png',0),

('SFS-COL-003','Gargantilla Riviera Plata 925','gargantilla-riviera-plata-925',
 'Gargantilla de circones en línea continua, montada en plata 925 rodinada. Brilla como una riviera de diamantes con un precio muy distinto.',
 420000, 490000, 195000,'Collares','Plata 925', 11.50, 7, 3,'assets/img/productos/no-image.png',1),

('SFS-ARE-002','Argollas Huggie Oro 18k','argollas-huggie-oro-18k',
 'Argollas pequeñas tipo huggie que abrazan el lóbulo. Baño de oro 18k sobre plata, cierre de bisagra con seguro a presión.',
 165000, NULL, 68000,'Aretes','Baño de oro 18k', 2.60, 24, 6,'assets/img/productos/no-image.png',0),

('SFS-PUL-002','Esclava Rígida Oro Mate','esclava-rigida-oro-mate',
 'Brazalete rígido de perfil ovalado con acabado mate satinado y apertura lateral. Pieza de declaración que funciona sola.',
 310000, NULL, 142000,'Pulseras','Baño de oro 18k', 14.00, 9, 3,'assets/img/productos/no-image.png',0),

('SFS-SET-001','Set Perla Nácar','set-perla-nacar',
 'Conjunto de collar y aretes con perla de nácar cultivada, montura en plata 925. Se entrega en estuche forrado, listo para regalar.',
 480000, 560000, 210000,'Sets','Plata 925', 9.80, 6, 2,'assets/img/productos/no-image.png',1),

('SFS-DIJ-001','Dije Corazón Grabable','dije-corazon-grabable',
 'Corazón liso en plata 925 preparado para grabado láser de iniciales o fecha. Incluye el grabado sin costo adicional.',
 135000, NULL, 52000,'Dijes','Plata 925', 2.40, 22, 6,'assets/img/productos/no-image.png',0),

('SFS-ANI-003','Alianza Confort Oro Rosa','alianza-confort-oro-rosa',
 'Banda de perfil confort con interior redondeado para uso permanente. Baño de oro rosa sobre plata 925, 4 mm de ancho.',
 240000, NULL, 105000,'Anillos','Oro rosa', 4.20, 11, 3,'assets/img/productos/no-image.png',0);

INSERT IGNORE INTO tarifas_envio (ciudad, costo, dias_estimados) VALUES
('Medellín',      8000, '1 a 2 días'),
('Bogotá',       12000, '2 a 3 días'),
('Cali',         12000, '2 a 3 días'),
('Barranquilla', 15000, '3 a 5 días'),
('Cartagena',    15000, '3 a 5 días'),
('Bucaramanga',  13000, '2 a 4 días'),
('Pereira',      10000, '2 a 3 días'),
('Otra ciudad',  18000, '4 a 7 días');

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

-- Gastos operativos de ejemplo (el módulo contable arranca con datos
-- reales que el administrador puede editar o borrar desde el panel).
-- Solo se insertan si la tabla está vacía (el script se puede volver a ejecutar).
INSERT INTO gastos (concepto, categoria, monto, fecha, nota)
SELECT * FROM (
              SELECT 'Compra de insumos y cadenas al proveedor' AS concepto, 'inventario' AS categoria, 1850000 AS monto, DATE_SUB(CURDATE(), INTERVAL 25 DAY) AS fecha, 'Lote mensual' AS nota
    UNION ALL SELECT 'Pauta en redes sociales',           'marketing',  420000, DATE_SUB(CURDATE(), INTERVAL 18 DAY), NULL
    UNION ALL SELECT 'Servicio de mensajería y empaques', 'logistica',  260000, DATE_SUB(CURDATE(), INTERVAL 12 DAY), NULL
    UNION ALL SELECT 'Arriendo y servicios del taller',   'servicios',  950000, DATE_SUB(CURDATE(), INTERVAL 8 DAY),  NULL
    UNION ALL SELECT 'Honorarios de diseño y fotografía', 'nomina',     600000, DATE_SUB(CURDATE(), INTERVAL 4 DAY),  NULL
) AS semilla
WHERE NOT EXISTS (SELECT 1 FROM gastos);
