-- EcoLact BD v1 - MySQL 8.x
-- 37 tablas funcionales; Laravel/Sanctum agrega tablas técnicas aparte.
CREATE DATABASE IF NOT EXISTS `ecolact` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ecolact`;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(50) NOT NULL,
  `descripcion` VARCHAR(180),
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `username` VARCHAR(60) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `debe_cambiar_password` BOOLEAN NOT NULL DEFAULT 0,
  `ultimo_acceso` DATETIME,
  `remember_token` VARCHAR(100),
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zonas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_zonas_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proveedores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `zona_id` BIGINT UNSIGNED NOT NULL,
  `dni` CHAR(8) NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20),
  `qr_token` CHAR(36) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `advertencias_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `fecha_registro` DATE NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_proveedores_user_id` (`user_id`),
  UNIQUE KEY `uq_proveedores_dni` (`dni`),
  UNIQUE KEY `uq_proveedores_qr_token` (`qr_token`),
  KEY `idx_proveedores_zona_id_estado` (`zona_id`, `estado`),
  CONSTRAINT `fk_proveedores_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_proveedores_zona_id` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acopiadores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `zona_id` BIGINT UNSIGNED NOT NULL,
  `dni` CHAR(8) NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20),
  `estado` VARCHAR(20) NOT NULL,
  `fecha_ingreso` DATE,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_acopiadores_user_id` (`user_id`),
  UNIQUE KEY `uq_acopiadores_dni` (`dni`),
  KEY `idx_acopiadores_zona_id_estado` (`zona_id`, `estado`),
  CONSTRAINT `fk_acopiadores_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_acopiadores_zona_id` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proveedor_zona_historial` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proveedor_id` BIGINT UNSIGNED NOT NULL,
  `zona_id` BIGINT UNSIGNED NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE,
  `motivo` VARCHAR(255),
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_proveedor_zona_historial_proveedor_id` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_proveedor_zona_historial_zona_id` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acopiador_zona_historial` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `acopiador_id` BIGINT UNSIGNED NOT NULL,
  `zona_id` BIGINT UNSIGNED NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE,
  `motivo` VARCHAR(255),
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_acopiador_zona_historial_acopiador_id` FOREIGN KEY (`acopiador_id`) REFERENCES `acopiadores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_acopiador_zona_historial_zona_id` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `entregas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proveedor_id` BIGINT UNSIGNED NOT NULL,
  `acopiador_id` BIGINT UNSIGNED,
  `zona_id` BIGINT UNSIGNED NOT NULL,
  `registrado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `fecha_hora` DATETIME NOT NULL,
  `litros` DECIMAL(10,2) NOT NULL,
  `metodo_identificacion` VARCHAR(10) NOT NULL,
  `estado` VARCHAR(25) NOT NULL,
  `observacion` TEXT,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entregas_proveedor_id_fecha_hora` (`proveedor_id`, `fecha_hora`),
  KEY `idx_entregas_acopiador_id_fecha_hora` (`acopiador_id`, `fecha_hora`),
  KEY `idx_entregas_zona_id_fecha_hora` (`zona_id`, `fecha_hora`),
  CONSTRAINT `fk_entregas_proveedor_id` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_entregas_acopiador_id` FOREIGN KEY (`acopiador_id`) REFERENCES `acopiadores` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_entregas_zona_id` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_entregas_registrado_por_user_id` FOREIGN KEY (`registrado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `parametros_calidad` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(40) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `unidad` VARCHAR(30),
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parametros_calidad_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reglas_calidad` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parametro_id` BIGINT UNSIGNED NOT NULL,
  `valor_minimo` DECIMAL(12,4),
  `valor_maximo` DECIMAL(12,4),
  `resultado_incumplimiento` VARCHAR(20) NOT NULL,
  `prioridad` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_reglas_calidad_parametro_id` FOREIGN KEY (`parametro_id`) REFERENCES `parametros_calidad` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analisis_calidad` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entrega_id` BIGINT UNSIGNED NOT NULL,
  `imagen_path` VARCHAR(500),
  `ocr_texto_original` LONGTEXT,
  `ocr_confianza` DECIMAL(5,2),
  `estado` VARCHAR(20) NOT NULL,
  `resultado` VARCHAR(20),
  `fecha_analisis` DATETIME NOT NULL,
  `confirmado_por_user_id` BIGINT UNSIGNED,
  `observacion` TEXT,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_analisis_calidad_entrega_id` (`entrega_id`),
  KEY `idx_analisis_calidad_resultado_fecha_analisis` (`resultado`, `fecha_analisis`),
  CONSTRAINT `fk_analisis_calidad_entrega_id` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_analisis_calidad_confirmado_por_user_id` FOREIGN KEY (`confirmado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analisis_calidad_detalles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `analisis_id` BIGINT UNSIGNED NOT NULL,
  `parametro_id` BIGINT UNSIGNED NOT NULL,
  `valor` DECIMAL(12,4) NOT NULL,
  `origen` VARCHAR(10) NOT NULL,
  `confianza_ocr` DECIMAL(5,2),
  `cumple` BOOLEAN,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_analisis_calidad_detalles_analisis_id_parametro_id` (`analisis_id`, `parametro_id`),
  CONSTRAINT `fk_analisis_calidad_detalles_analisis_id` FOREIGN KEY (`analisis_id`) REFERENCES `analisis_calidad` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_analisis_calidad_detalles_parametro_id` FOREIGN KEY (`parametro_id`) REFERENCES `parametros_calidad` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sanciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proveedor_id` BIGINT UNSIGNED NOT NULL,
  `analisis_id` BIGINT UNSIGNED,
  `tipo` VARCHAR(40) NOT NULL,
  `nivel_advertencia` TINYINT UNSIGNED,
  `descripcion` TEXT NOT NULL,
  `tipo_penalizacion` VARCHAR(30) NOT NULL,
  `valor_penalizacion` DECIMAL(12,2),
  `fecha` DATE NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `creado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_sanciones_proveedor_id` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_sanciones_analisis_id` FOREIGN KEY (`analisis_id`) REFERENCES `analisis_calidad` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_sanciones_creado_por_user_id` FOREIGN KEY (`creado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comunicados` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(180) NOT NULL,
  `contenido` TEXT NOT NULL,
  `tipo` VARCHAR(30) NOT NULL,
  `alcance` VARCHAR(20) NOT NULL,
  `zona_id` BIGINT UNSIGNED,
  `fecha_publicacion` DATETIME NOT NULL,
  `fecha_evento` DATETIME,
  `creado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_comunicados_zona_id` FOREIGN KEY (`zona_id`) REFERENCES `zonas` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_comunicados_creado_por_user_id` FOREIGN KEY (`creado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comunicado_destinatarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comunicado_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `leido` BOOLEAN NOT NULL DEFAULT 0,
  `fecha_lectura` DATETIME,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comunicado_destinatarios_comunicado_id_user_id` (`comunicado_id`, `user_id`),
  KEY `idx_comunicado_destinatarios_user_id_leido` (`user_id`, `leido`),
  CONSTRAINT `fk_comunicado_destinatarios_comunicado_id` FOREIGN KEY (`comunicado_id`) REFERENCES `comunicados` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_comunicado_destinatarios_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(40) NOT NULL,
  `titulo` VARCHAR(180) NOT NULL,
  `mensaje` TEXT NOT NULL,
  `referencia_tipo` VARCHAR(50),
  `referencia_id` BIGINT UNSIGNED,
  `leido` BOOLEAN NOT NULL DEFAULT 0,
  `fecha_lectura` DATETIME,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notificaciones_user_id_leido` (`user_id`, `leido`),
  CONSTRAINT `fk_notificaciones_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `precios_leche` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `precio_litro` DECIMAL(10,4) NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE,
  `estado` VARCHAR(20) NOT NULL,
  `creado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_precios_leche_creado_por_user_id` FOREIGN KEY (`creado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reglas_pago_calidad` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resultado_calidad` VARCHAR(20) NOT NULL,
  `tipo_ajuste` VARCHAR(30) NOT NULL,
  `valor_ajuste` DECIMAL(10,4) NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `valorizaciones_entrega` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entrega_id` BIGINT UNSIGNED NOT NULL,
  `analisis_id` BIGINT UNSIGNED NOT NULL,
  `precio_leche_id` BIGINT UNSIGNED NOT NULL,
  `regla_pago_id` BIGINT UNSIGNED NOT NULL,
  `litros_entregados` DECIMAL(10,2) NOT NULL,
  `litros_pagables` DECIMAL(10,2) NOT NULL,
  `precio_base_litro` DECIMAL(10,4) NOT NULL,
  `ajuste_por_litro` DECIMAL(10,4) NOT NULL,
  `precio_final_litro` DECIMAL(10,4) NOT NULL,
  `importe_total` DECIMAL(12,2) NOT NULL,
  `fecha_calculo` DATETIME NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_valorizaciones_entrega_entrega_id` (`entrega_id`),
  CONSTRAINT `fk_valorizaciones_entrega_entrega_id` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_valorizaciones_entrega_analisis_id` FOREIGN KEY (`analisis_id`) REFERENCES `analisis_calidad` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_valorizaciones_entrega_precio_leche_id` FOREIGN KEY (`precio_leche_id`) REFERENCES `precios_leche` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_valorizaciones_entrega_regla_pago_id` FOREIGN KEY (`regla_pago_id`) REFERENCES `reglas_pago_calidad` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `periodos_liquidacion` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liquidaciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periodo_id` BIGINT UNSIGNED NOT NULL,
  `proveedor_id` BIGINT UNSIGNED NOT NULL,
  `total_litros_entregados` DECIMAL(12,2) NOT NULL,
  `total_litros_pagables` DECIMAL(12,2) NOT NULL,
  `precio_litro_aplicado` DECIMAL(10,4) NOT NULL,
  `tuvo_incidencia` BOOLEAN NOT NULL DEFAULT 0,
  `sancion_id` BIGINT UNSIGNED,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `total_ajustes` DECIMAL(12,2) NOT NULL,
  `total_pagar` DECIMAL(12,2) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `fecha_calculo` DATETIME NOT NULL,
  `generado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidaciones_periodo_id_proveedor_id` (`periodo_id`, `proveedor_id`),
  KEY `idx_liquidaciones_proveedor_id_estado` (`proveedor_id`, `estado`),
  CONSTRAINT `fk_liquidaciones_periodo_id` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_liquidacion` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_liquidaciones_proveedor_id` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_liquidaciones_generado_por_user_id` FOREIGN KEY (`generado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_liquidaciones_sancion_id` FOREIGN KEY (`sancion_id`) REFERENCES `sanciones` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `liquidacion_detalles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liquidacion_id` BIGINT UNSIGNED NOT NULL,
  `valorizacion_entrega_id` BIGINT UNSIGNED NOT NULL,
  `importe` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_liquidacion_detalles_valorizacion_entrega_id` (`valorizacion_entrega_id`),
  CONSTRAINT `fk_liquidacion_detalles_liquidacion_id` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_liquidacion_detalles_valorizacion_entrega_id` FOREIGN KEY (`valorizacion_entrega_id`) REFERENCES `valorizaciones_entrega` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pagos_proveedor` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liquidacion_id` BIGINT UNSIGNED NOT NULL,
  `fecha_pago` DATETIME NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `observacion` TEXT,
  `registrado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pagos_proveedor_liquidacion_id` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_pagos_proveedor_registrado_por_user_id` FOREIGN KEY (`registrado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `productos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `unidad_medida` VARCHAR(30) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_productos_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lotes_produccion` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(50) NOT NULL,
  `fecha_hora_inicio` DATETIME NOT NULL,
  `fecha_hora_fin` DATETIME,
  `litros_leche_utilizados` DECIMAL(12,2) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `observacion` TEXT,
  `registrado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lotes_produccion_codigo` (`codigo`),
  CONSTRAINT `fk_lotes_produccion_registrado_por_user_id` FOREIGN KEY (`registrado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lote_produccion_entregas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lote_produccion_id` BIGINT UNSIGNED NOT NULL,
  `entrega_id` BIGINT UNSIGNED NOT NULL,
  `litros_utilizados` DECIMAL(10,2) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lote_produccion_entregas_lote_produccion_id_entrega_id` (`lote_produccion_id`, `entrega_id`),
  CONSTRAINT `fk_lote_produccion_entregas_lote_produccion_id` FOREIGN KEY (`lote_produccion_id`) REFERENCES `lotes_produccion` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_lote_produccion_entregas_entrega_id` FOREIGN KEY (`entrega_id`) REFERENCES `entregas` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `produccion_detalles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lote_produccion_id` BIGINT UNSIGNED NOT NULL,
  `producto_id` BIGINT UNSIGNED NOT NULL,
  `cantidad_producida` DECIMAL(12,2) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_produccion_detalles_lote_produccion_id_producto_id` (`lote_produccion_id`, `producto_id`),
  CONSTRAINT `fk_produccion_detalles_lote_produccion_id` FOREIGN KEY (`lote_produccion_id`) REFERENCES `lotes_produccion` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_produccion_detalles_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compradores` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(150) NOT NULL,
  `documento` VARCHAR(20),
  `telefono` VARCHAR(20),
  `tipo_cliente` VARCHAR(20) NOT NULL,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `producto_precios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` BIGINT UNSIGNED NOT NULL,
  `tipo_cliente` VARCHAR(20) NOT NULL,
  `precio` DECIMAL(10,2) NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE,
  `estado` VARCHAR(20) NOT NULL,
  `creado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_producto_precios_producto_id_tipo_cliente_estado` (`producto_id`, `tipo_cliente`, `estado`),
  CONSTRAINT `fk_producto_precios_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_producto_precios_creado_por_user_id` FOREIGN KEY (`creado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ventas_producto` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` BIGINT UNSIGNED NOT NULL,
  `proveedor_id` BIGINT UNSIGNED,
  `comprador_id` BIGINT UNSIGNED,
  `tipo_cliente` VARCHAR(20) NOT NULL,
  `cantidad` DECIMAL(10,2) NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  `importe_total` DECIMAL(12,2) NOT NULL,
  `forma_pago` VARCHAR(20) NOT NULL,
  `periodo_id` BIGINT UNSIGNED,
  `estado` VARCHAR(20) NOT NULL,
  `fecha` DATETIME NOT NULL,
  `registrado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ventas_producto_proveedor_id_periodo_id` (`proveedor_id`, `periodo_id`),
  CONSTRAINT `fk_ventas_producto_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ventas_producto_proveedor_id` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ventas_producto_comprador_id` FOREIGN KEY (`comprador_id`) REFERENCES `compradores` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ventas_producto_periodo_id` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_liquidacion` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ventas_producto_registrado_por_user_id` FOREIGN KEY (`registrado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `insumos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `unidad_medida` VARCHAR(30) NOT NULL,
  `stock_actual` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `estado` VARCHAR(20) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_insumos_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `insumo_movimientos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `insumo_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(20) NOT NULL,
  `motivo` VARCHAR(30) NOT NULL,
  `cantidad` DECIMAL(12,2) NOT NULL,
  `lote_produccion_id` BIGINT UNSIGNED,
  `fecha` DATETIME NOT NULL,
  `observacion` TEXT,
  `registrado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_insumo_movimientos_insumo_id_fecha` (`insumo_id`, `fecha`),
  CONSTRAINT `fk_insumo_movimientos_insumo_id` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_insumo_movimientos_lote_produccion_id` FOREIGN KEY (`lote_produccion_id`) REFERENCES `lotes_produccion` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_insumo_movimientos_registrado_por_user_id` FOREIGN KEY (`registrado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lote_produccion_insumos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `lote_produccion_id` BIGINT UNSIGNED NOT NULL,
  `insumo_id` BIGINT UNSIGNED NOT NULL,
  `cantidad_usada` DECIMAL(12,4) NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lote_produccion_insumos_lote_produccion_id_insumo_id` (`lote_produccion_id`, `insumo_id`),
  CONSTRAINT `fk_lote_produccion_insumos_lote_produccion_id` FOREIGN KEY (`lote_produccion_id`) REFERENCES `lotes_produccion` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_lote_produccion_insumos_insumo_id` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `producto_stock` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` BIGINT UNSIGNED NOT NULL,
  `cantidad_actual` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_producto_stock_producto_id` (`producto_id`),
  CONSTRAINT `fk_producto_stock_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `producto_movimientos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` BIGINT UNSIGNED NOT NULL,
  `tipo` VARCHAR(20) NOT NULL,
  `cantidad` DECIMAL(12,2) NOT NULL,
  `lote_produccion_id` BIGINT UNSIGNED,
  `venta_producto_id` BIGINT UNSIGNED,
  `fecha` DATETIME NOT NULL,
  `observacion` TEXT,
  `registrado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_producto_movimientos_producto_id_fecha` (`producto_id`, `fecha`),
  CONSTRAINT `fk_producto_movimientos_producto_id` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_producto_movimientos_lote_produccion_id` FOREIGN KEY (`lote_produccion_id`) REFERENCES `lotes_produccion` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_producto_movimientos_venta_producto_id` FOREIGN KEY (`venta_producto_id`) REFERENCES `ventas_producto` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_producto_movimientos_registrado_por_user_id` FOREIGN KEY (`registrado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ajustes_liquidacion` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `liquidacion_id` BIGINT UNSIGNED NOT NULL,
  `sancion_id` BIGINT UNSIGNED,
  `venta_producto_id` BIGINT UNSIGNED,
  `tipo` VARCHAR(20) NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `monto` DECIMAL(12,2) NOT NULL,
  `creado_por_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ajustes_liquidacion_liquidacion_id` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ajustes_liquidacion_sancion_id` FOREIGN KEY (`sancion_id`) REFERENCES `sanciones` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ajustes_liquidacion_creado_por_user_id` FOREIGN KEY (`creado_por_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_ajustes_liquidacion_venta_producto_id` FOREIGN KEY (`venta_producto_id`) REFERENCES `ventas_producto` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auditorias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED,
  `accion` VARCHAR(30) NOT NULL,
  `tabla_afectada` VARCHAR(100) NOT NULL,
  `registro_id` BIGINT UNSIGNED,
  `datos_anteriores` JSON,
  `datos_nuevos` JSON,
  `motivo` VARCHAR(255),
  `ip` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_auditorias_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `encargados_planta` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `dni` CHAR(8) NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20),
  `estado` VARCHAR(20) NOT NULL,
  `fecha_ingreso` DATE,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_encargados_planta_user_id` (`user_id`),
  UNIQUE KEY `uq_encargados_planta_dni` (`dni`),
  CONSTRAINT `fk_encargados_planta_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_ventas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `dni` CHAR(8) NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20),
  `estado` VARCHAR(20) NOT NULL,
  `fecha_ingreso` DATE,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personal_ventas_user_id` (`user_id`),
  UNIQUE KEY `uq_personal_ventas_dni` (`dni`),
  CONSTRAINT `fk_personal_ventas_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `analistas_calidad` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `dni` CHAR(8) NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(150) NOT NULL,
  `telefono` VARCHAR(20),
  `estado` VARCHAR(20) NOT NULL,
  `fecha_ingreso` DATE,
  `created_at` TIMESTAMP,
  `updated_at` TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_analistas_calidad_user_id` (`user_id`),
  UNIQUE KEY `uq_analistas_calidad_dni` (`dni`),
  CONSTRAINT `fk_analistas_calidad_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`nombre`,`descripcion`,`created_at`,`updated_at`) VALUES
('ADMINISTRADOR','Acceso completo al sistema',NOW(),NOW()),
('ACOPIADOR','Registra entregas (recolección física) de su zona',NOW(),NOW()),
('PROVEEDOR','Consulta su QR, entregas, análisis, liquidaciones y comunicaciones',NOW(),NOW()),
('ENCARGADO_PLANTA','Gestiona producción e insumos de planta',NOW(),NOW()),
('VENTAS','Gestiona inventario de productos terminados y ventas',NOW(),NOW()),
('CALIDAD','Realiza el control de calidad técnico de cualquier zona y propone capacitaciones',NOW(),NOW())
ON DUPLICATE KEY UPDATE `descripcion`=VALUES(`descripcion`), `updated_at`=NOW();
