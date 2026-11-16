-- Control de almacén: catálogo de insumos, saldo inicial por empresa, entregas GRS -> tercero, consumos
CREATE TABLE IF NOT EXISTS `bio_insumo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `categoria` enum('INDUMENTARIA','DESINFECTANTE','EPP','OTRO') NOT NULL DEFAULT 'INDUMENTARIA',
  `unidad` varchar(20) NOT NULL DEFAULT 'UND',
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_insumo_codigo` (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_saldo` (
  `empresa_id` int unsigned NOT NULL,
  `insumo_id` int unsigned NOT NULL,
  `saldo_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`empresa_id`,`insumo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_entrega` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `granja_cod` varchar(20) NOT NULL DEFAULT '',
  `fecha` date NOT NULL,
  `estado` enum('PENDIENTE','RECIBIDA','OBSERVADA','ANULADA') NOT NULL DEFAULT 'PENDIENTE',
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `recibido_en` datetime DEFAULT NULL,
  `recibido_por` varchar(30) NOT NULL DEFAULT '',
  `anomalia` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_bio_ae_empresa_fecha` (`empresa_id`,`fecha`),
  KEY `idx_bio_ae_estado` (`estado`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_entrega_det` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entrega_id` int unsigned NOT NULL,
  `insumo_id` int unsigned NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cantidad_recibida` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bio_aed_entrega` (`entrega_id`),
  KEY `idx_bio_aed_insumo` (`insumo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_consumo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `insumo_id` int unsigned NOT NULL,
  `granja_cod` varchar(20) NOT NULL DEFAULT '',
  `fecha` date NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 0.00,
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_ac_empresa_fecha` (`empresa_id`,`fecha`),
  KEY `idx_bio_ac_insumo` (`insumo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT IGNORE INTO `bio_insumo` (`codigo`, `nombre`, `categoria`, `unidad`) VALUES
('IND-001', 'Overol', 'INDUMENTARIA', 'UND'),
('IND-002', 'Botas de jebe', 'INDUMENTARIA', 'PAR'),
('IND-003', 'Gorro descartable', 'INDUMENTARIA', 'UND'),
('EPP-001', 'Mascarilla', 'EPP', 'UND'),
('EPP-002', 'Guantes de nitrilo', 'EPP', 'PAR'),
('DES-001', 'Desinfectante (amonio cuaternario)', 'DESINFECTANTE', 'L'),
('DES-002', 'Alcohol 70%', 'DESINFECTANTE', 'L');
