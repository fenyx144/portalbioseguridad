SET NAMES latin1;

CREATE TABLE IF NOT EXISTS `bio_turno_catalogo` (
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `cruza_medianoche` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_capacitacion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `vigencia_meses` smallint unsigned NOT NULL DEFAULT 0,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_cap_codigo` (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_punto_tipo` (
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_config` (
  `clave` varchar(60) NOT NULL,
  `valor` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`clave`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_empresa` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(10) NOT NULL,
  `ruc` varchar(11) NOT NULL,
  `razon_social` varchar(120) NOT NULL,
  `usuario_cod` varchar(10) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_empresa_codigo` (`codigo`),
  UNIQUE KEY `uk_bio_empresa_ruc` (`ruc`),
  UNIQUE KEY `uk_bio_empresa_usuario` (`usuario_cod`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_personal` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dni` varchar(15) NOT NULL,
  `nombres` varchar(80) NOT NULL,
  `apellidos` varchar(80) NOT NULL,
  `cargo` varchar(80) NOT NULL DEFAULT '',
  `empresa` enum('GRS','TERCERO') NOT NULL DEFAULT 'GRS',
  `empresa_id` int unsigned DEFAULT NULL,
  `fecha_contratacion` date NOT NULL,
  `estado` enum('ACTIVO','CESADO') NOT NULL DEFAULT 'ACTIVO',
  `granja_base_cod` varchar(20) NOT NULL DEFAULT '',
  `foto_ruta` varchar(255) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_personal_dni` (`dni`),
  KEY `idx_bio_personal_estado` (`estado`),
  KEY `idx_bio_personal_granja` (`granja_base_cod`),
  KEY `idx_bio_personal_empresa_id` (`empresa_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_personal_capacitacion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `personal_id` int unsigned NOT NULL,
  `capacitacion_id` int unsigned NOT NULL,
  `fecha_realizada` date NOT NULL,
  `fecha_vence` date DEFAULT NULL,
  `resultado` enum('APROBADO','DESAPROBADO','PENDIENTE') NOT NULL DEFAULT 'APROBADO',
  `nota` decimal(4,1) DEFAULT NULL,
  `fecha_evaluacion` date DEFAULT NULL,
  `observacion` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_bpc_personal` (`personal_id`),
  KEY `idx_bpc_cap` (`capacitacion_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_capacitacion_sesion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `capacitacion_id` int unsigned NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time DEFAULT NULL,
  `lugar` varchar(120) NOT NULL DEFAULT '',
  `nota_minima` decimal(4,1) NOT NULL DEFAULT 14.0,
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
  `nota` decimal(4,1) DEFAULT NULL,
  `resultado` enum('PENDIENTE','APROBADO','DESAPROBADO') NOT NULL DEFAULT 'PENDIENTE',
  `fecha_evaluacion` date DEFAULT NULL,
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `registro_cap_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_csp_sesion_personal` (`sesion_id`,`personal_id`),
  KEY `idx_bio_csp_personal` (`personal_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_puntos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `granja_cod` varchar(20) NOT NULL,
  `codigo` varchar(30) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `tipo_cod` varchar(20) NOT NULL,
  `ubicacion` varchar(255) NOT NULL DEFAULT '',
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_punto_cod` (`granja_cod`,`codigo`),
  UNIQUE KEY `uk_bio_punto_nom` (`granja_cod`,`nombre`),
  KEY `idx_bio_puntos_granja` (`granja_cod`),
  KEY `idx_bio_puntos_estado` (`estado`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_punto_turno` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `punto_id` int unsigned NOT NULL,
  `turno_cod` varchar(10) NOT NULL,
  `personal_requerido` tinyint unsigned NOT NULL DEFAULT 1,
  `hora_inicio_override` time DEFAULT NULL,
  `hora_fin_override` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bpt_punto_turno` (`punto_id`,`turno_cod`),
  KEY `idx_bpt_turno` (`turno_cod`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_punto_cargo_req` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `punto_id` int unsigned NOT NULL,
  `turno_cod` varchar(10) NOT NULL,
  `cargo` varchar(80) NOT NULL,
  `cantidad` tinyint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_pcr_punto_turno` (`punto_id`,`turno_cod`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_punto_capacitacion` (
  `punto_id` int unsigned NOT NULL,
  `capacitacion_id` int unsigned NOT NULL,
  PRIMARY KEY (`punto_id`,`capacitacion_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_asignacion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `personal_id` int unsigned NOT NULL,
  `punto_id` int unsigned NOT NULL,
  `fecha` date NOT NULL,
  `turno_cod` varchar(10) NOT NULL,
  `origen` enum('GRS','EMPRESA') NOT NULL DEFAULT 'EMPRESA',
  `es_reemplazo` tinyint(1) NOT NULL DEFAULT 0,
  `reemplazo_id` int unsigned DEFAULT NULL,
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ba_fecha` (`fecha`),
  KEY `idx_ba_personal` (`personal_id`),
  KEY `idx_ba_punto` (`punto_id`),
  KEY `idx_ba_punto_fecha` (`punto_id`,`fecha`,`turno_cod`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_reemplazo` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `asignacion_id` int unsigned NOT NULL,
  `personal_original_id` int unsigned NOT NULL,
  `personal_reemplazo_id` int unsigned NOT NULL,
  `motivo` varchar(500) NOT NULL,
  `registrado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_br_asignacion` (`asignacion_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_plan_semana_copia` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `granja_cod` varchar(20) NOT NULL,
  `semana_origen` date NOT NULL,
  `semana_destino` date NOT NULL,
  `usuario` varchar(30) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_asistencia` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `asignacion_id` int unsigned NOT NULL,
  `marcado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_asist_asig` (`asignacion_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Almacén de insumos (ver migración 007)
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
