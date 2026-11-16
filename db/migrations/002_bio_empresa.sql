-- Empresas contratistas y vínculo con personal / usuario SIP
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

ALTER TABLE `bio_personal`
  ADD COLUMN `empresa_id` int unsigned DEFAULT NULL AFTER `empresa`,
  ADD KEY `idx_bio_personal_empresa_id` (`empresa_id`);
