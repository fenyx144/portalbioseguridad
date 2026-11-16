CREATE TABLE IF NOT EXISTS `bio_capacitacion_sesion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `capacitacion_id` int unsigned NOT NULL,
  `fecha` date NOT NULL,
  `lugar` varchar(120) NOT NULL DEFAULT '',
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `estado` enum('PROGRAMADA','REALIZADA','CANCELADA') NOT NULL DEFAULT 'PROGRAMADA',
  `usuario_registro` varchar(10) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_cs_emp_fecha` (`empresa_id`,`fecha`),
  KEY `idx_bio_cs_cap` (`capacitacion_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_capacitacion_sesion_personal` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sesion_id` int unsigned NOT NULL,
  `personal_id` int unsigned NOT NULL,
  `asistio` tinyint(1) NOT NULL DEFAULT 0,
  `registro_cap_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_csp_sesion_personal` (`sesion_id`,`personal_id`),
  KEY `idx_bio_csp_personal` (`personal_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
