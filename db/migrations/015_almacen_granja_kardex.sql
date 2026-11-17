-- Almacén por granja. Una sola tabla de kardex. No usa imov ni movi_zonas.
-- frente: LIMPIO (Almacén limpio), LAVADERO, INSUMOS.
-- tipo: SI saldo inicial, E entrada, S salida. Stock = SI + E - S.
-- item: ropa_tipo (1 tránsito, 2 proceso) si frente es LIMPIO o LAVADERO; id de bio_insumo si frente es INSUMOS.
-- LAVADO escribe dos filas (S en LAVADERO y E en LIMPIO) con el mismo par_id.
-- No ejecutar desde la aplicación: aplicarla a mano en el servidor.

CREATE TABLE IF NOT EXISTS `bio_ropa_tipo` (
  `codigo` tinyint unsigned NOT NULL,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `bio_almacen_mov` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `frente` enum('LIMPIO','LAVADERO','INSUMOS') NOT NULL,
  `tipo` enum('SI','E','S') NOT NULL,
  `granja_cod` varchar(20) NOT NULL,
  `item` varchar(20) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `operacion` varchar(20) NOT NULL,
  `par_id` int unsigned DEFAULT NULL,
  `personal_id` int unsigned DEFAULT NULL,
  `dni` varchar(15) NOT NULL DEFAULT '',
  `usuario_registro` varchar(30) NOT NULL DEFAULT '',
  `origen` enum('web','app') NOT NULL DEFAULT 'web',
  `fecha` date NOT NULL,
  `observacion` varchar(255) NOT NULL DEFAULT '',
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bio_amov_stock` (`granja_cod`, `frente`, `item`, `id`),
  KEY `idx_bio_amov_fecha` (`fecha`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT IGNORE INTO `bio_ropa_tipo` (`codigo`, `nombre`) VALUES
(1, 'Ropa de transito'),
(2, 'Ropa para el proceso');
