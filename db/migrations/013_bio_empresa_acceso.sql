-- Operarios de empresa contratista: varios usuarios Sanidad → misma bio_empresa (consumo / recepción).
CREATE TABLE IF NOT EXISTS `bio_empresa_acceso` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` int unsigned NOT NULL,
  `usuario_cod` varchar(10) NOT NULL,
  `personal_id` int unsigned DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_empresa_acceso_usuario` (`usuario_cod`),
  KEY `idx_bio_empresa_acceso_emp` (`empresa_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;