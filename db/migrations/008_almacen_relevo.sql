-- Relevo de turno en almacén (conteo de insumos al cambio de guardia, uso móvil empresa)
CREATE TABLE IF NOT EXISTS `bio_almacen_relevo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `granja_cod` varchar(20) NOT NULL DEFAULT '',
  `fecha` date NOT NULL,
  `turno_cod` varchar(20) NOT NULL DEFAULT '',
  `responsable_salida` varchar(80) NOT NULL DEFAULT '',
  `responsable_entrada` varchar(80) NOT NULL DEFAULT '',
  `observacion` varchar(500) NOT NULL DEFAULT '',
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_ar_empresa_fecha` (`empresa_id`,`fecha`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_relevo_det` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `relevo_id` int unsigned NOT NULL,
  `insumo_id` int unsigned NOT NULL,
  `cantidad_sistema` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cantidad_contada` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_bio_ard_relevo` (`relevo_id`),
  KEY `idx_bio_ard_insumo` (`insumo_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
