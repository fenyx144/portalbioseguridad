-- Almacén por granja (kardex). No usa imov ni movi_zonas.
-- Dos depósitos lógicos por granja: LIMPIO (Almacén limpio) y LAVADERO (Lavadero).
-- Ropa: 1 tránsito, 2 proceso. El control de stock es la ropa de proceso.
-- tipo_mov: SI saldo inicial, E entrada, S salida. Stock = SI + E - S.
-- No ejecutar desde la aplicación: aplicarla a mano en el servidor.

CREATE TABLE IF NOT EXISTS `bio_ropa_tipo` (
  `codigo` tinyint unsigned NOT NULL,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_logico` (
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_uniforme_mov` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `granja_cod` varchar(20) NOT NULL,
  `ropa_tipo` tinyint unsigned NOT NULL DEFAULT 2,
  `almacen` varchar(20) NOT NULL,
  `tipo_mov` enum('SI','E','S') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `concepto` enum('CARGA','ENTREGA','RECEPCION','LAVADO') NOT NULL,
  `personal_id` int unsigned DEFAULT NULL,
  `dni` varchar(15) NOT NULL DEFAULT '',
  `trabajador` varchar(120) NOT NULL DEFAULT '',
  `fecha` date NOT NULL,
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `par_id` int unsigned DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_umov_granja` (`granja_cod`, `ropa_tipo`, `almacen`, `id`),
  KEY `idx_bio_umov_fecha` (`fecha`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_insumo_mov` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `granja_cod` varchar(20) NOT NULL,
  `insumo_id` int unsigned NOT NULL,
  `tipo_mov` enum('SI','E','S') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `concepto` enum('CARGA','CONSUMO') NOT NULL,
  `fecha` date NOT NULL,
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_insmov_granja` (`granja_cod`, `insumo_id`, `id`),
  KEY `idx_bio_insmov_fecha` (`fecha`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT IGNORE INTO `bio_ropa_tipo` (`codigo`, `nombre`) VALUES
(1, 'Ropa de transito'),
(2, 'Ropa para el proceso');

INSERT IGNORE INTO `bio_almacen_logico` (`codigo`, `nombre`) VALUES
('LIMPIO', 'Almacen limpio'),
('LAVADERO', 'Lavadero');
