-- Sistema de Farmacias (multi-farmacia) - Esquema MySQL 5.7+/MariaDB 10.2+
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS farmacias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  nit VARCHAR(30) NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  direccion VARCHAR(255) NULL,
  responsable VARCHAR(150) NULL,
  plan VARCHAR(60) NOT NULL DEFAULT 'Básico',
  precio_suscripcion DECIMAL(10,2) NOT NULL DEFAULT 0,
  ciclo ENUM('mensual','trimestral','semestral','anual') NOT NULL DEFAULT 'mensual',
  fecha_inicio DATE NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  estado ENUM('activa','suspendida') NOT NULL DEFAULT 'activa',
  dias_alerta_vencimiento INT NOT NULL DEFAULT 90,
  moneda VARCHAR(5) NOT NULL DEFAULT 'Q',
  ticket_pie VARCHAR(255) NULL,
  notas TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos_suscripcion (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  monto DECIMAL(10,2) NOT NULL,
  fecha_pago DATE NOT NULL,
  periodo_desde DATE NOT NULL,
  periodo_hasta DATE NOT NULL,
  metodo VARCHAR(40) NOT NULL DEFAULT 'Transferencia',
  referencia VARCHAR(100) NULL,
  notas VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pago_farmacia (farmacia_id, fecha_pago),
  CONSTRAINT fk_pago_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NULL,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('superadmin','admin','vendedor') NOT NULL DEFAULT 'vendedor',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_acceso DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuario_email (email),
  KEY idx_usuario_farmacia (farmacia_id),
  CONSTRAINT fk_usuario_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  KEY idx_cat_farmacia (farmacia_id),
  CONSTRAINT fk_cat_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NULL,
  codigo VARCHAR(60) NULL,
  nombre VARCHAR(200) NOT NULL,
  principio_activo VARCHAR(200) NULL,
  presentacion VARCHAR(120) NULL,
  laboratorio VARCHAR(120) NULL,
  unidad VARCHAR(30) NOT NULL DEFAULT 'Unidad',
  precio_compra DECIMAL(12,2) NOT NULL DEFAULT 0,
  precio_venta DECIMAL(12,2) NOT NULL DEFAULT 0,
  stock_minimo INT NOT NULL DEFAULT 5,
  requiere_receta TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_prod_farmacia (farmacia_id, activo),
  KEY idx_prod_codigo (farmacia_id, codigo),
  KEY idx_prod_nombre (farmacia_id, nombre),
  CONSTRAINT fk_prod_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE,
  CONSTRAINT fk_prod_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proveedores (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  nit VARCHAR(30) NULL,
  contacto VARCHAR(150) NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  direccion VARCHAR(255) NULL,
  notas VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_prov_farmacia (farmacia_id),
  CONSTRAINT fk_prov_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clientes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  nit VARCHAR(30) NULL,
  dpi VARCHAR(20) NULL,
  telefono VARCHAR(30) NULL,
  email VARCHAR(150) NULL,
  direccion VARCHAR(255) NULL,
  notas VARCHAR(255) NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cli_farmacia (farmacia_id),
  CONSTRAINT fk_cli_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compras (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  proveedor_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  numero_factura VARCHAR(60) NULL,
  fecha DATE NOT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('registrada','anulada') NOT NULL DEFAULT 'registrada',
  notas VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_compra_farmacia (farmacia_id, fecha),
  CONSTRAINT fk_compra_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE,
  CONSTRAINT fk_compra_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  compra_id INT UNSIGNED NULL,
  numero_lote VARCHAR(60) NULL,
  fecha_vencimiento DATE NULL,
  cantidad_inicial INT NOT NULL DEFAULT 0,
  cantidad_actual INT NOT NULL DEFAULT 0,
  costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lote_producto (producto_id, cantidad_actual),
  KEY idx_lote_venc (farmacia_id, fecha_vencimiento),
  CONSTRAINT fk_lote_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE,
  CONSTRAINT fk_lote_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_lote_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS compra_detalle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  compra_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  lote_id INT UNSIGNED NULL,
  cantidad INT NOT NULL,
  costo_unitario DECIMAL(12,2) NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  KEY idx_cd_compra (compra_id),
  CONSTRAINT fk_cd_compra FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
  CONSTRAINT fk_cd_lote FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ventas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  cliente_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  fecha DATETIME NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  costo_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  metodo_pago ENUM('efectivo','tarjeta','transferencia') NOT NULL DEFAULT 'efectivo',
  monto_recibido DECIMAL(12,2) NOT NULL DEFAULT 0,
  cambio DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado ENUM('completada','anulada') NOT NULL DEFAULT 'completada',
  notas VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_venta_numero (farmacia_id, numero),
  KEY idx_venta_fecha (farmacia_id, fecha, estado),
  KEY idx_venta_cliente (cliente_id),
  CONSTRAINT fk_venta_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE,
  CONSTRAINT fk_venta_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venta_detalle (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  costo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL,
  KEY idx_vd_venta (venta_id),
  KEY idx_vd_producto (producto_id),
  CONSTRAINT fk_vd_venta FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
  CONSTRAINT fk_vd_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS venta_detalle_lotes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  venta_detalle_id INT UNSIGNED NOT NULL,
  lote_id INT UNSIGNED NOT NULL,
  cantidad INT NOT NULL,
  KEY idx_vdl_det (venta_detalle_id),
  CONSTRAINT fk_vdl_det FOREIGN KEY (venta_detalle_id) REFERENCES venta_detalle(id) ON DELETE CASCADE,
  CONSTRAINT fk_vdl_lote FOREIGN KEY (lote_id) REFERENCES lotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gastos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  categoria VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha DATE NOT NULL,
  metodo_pago VARCHAR(30) NOT NULL DEFAULT 'efectivo',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_gasto_farmacia (farmacia_id, fecha),
  CONSTRAINT fk_gasto_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS movimientos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  farmacia_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NOT NULL,
  lote_id INT UNSIGNED NULL,
  tipo ENUM('compra','venta','ajuste_entrada','ajuste_salida','anulacion_venta','anulacion_compra') NOT NULL,
  cantidad INT NOT NULL,
  referencia VARCHAR(120) NULL,
  usuario_id INT UNSIGNED NULL,
  fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_prod (farmacia_id, producto_id, fecha),
  CONSTRAINT fk_mov_farmacia FOREIGN KEY (farmacia_id) REFERENCES farmacias(id) ON DELETE CASCADE,
  CONSTRAINT fk_mov_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
