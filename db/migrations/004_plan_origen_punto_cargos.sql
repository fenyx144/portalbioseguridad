ALTER TABLE `bio_asignacion`
  ADD COLUMN `origen` enum('GRS','EMPRESA') NOT NULL DEFAULT 'EMPRESA' AFTER `turno_cod`;

CREATE TABLE IF NOT EXISTS `bio_punto_cargo_req` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `punto_id` int unsigned NOT NULL,
  `turno_cod` varchar(10) NOT NULL,
  `cargo` varchar(80) NOT NULL,
  `cantidad` tinyint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_pcr_punto_turno` (`punto_id`,`turno_cod`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
