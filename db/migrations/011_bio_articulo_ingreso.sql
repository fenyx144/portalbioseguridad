CREATE TABLE IF NOT EXISTS `bio_articulo_ingreso` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `regla` enum('PERMITIDO','PROHIBIDO','CRITERIO') NOT NULL DEFAULT 'CRITERIO',
  `nota` varchar(255) NOT NULL DEFAULT '',
  `orden` smallint unsigned NOT NULL DEFAULT 0,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bio_articulo_ingreso_codigo` (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '1', 'Celular / smartphone', 'PERMITIDO', 'Registrar ingreso con foto', 1
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '1');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '2', 'Audífonos', 'PROHIBIDO', '', 2
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '2');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '3', 'Reloj u otros accesorios metálicos', 'PROHIBIDO', '', 3
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '3');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '4', 'Alimentos', 'PROHIBIDO', '', 4
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '4');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '5', 'Bebidas', 'PROHIBIDO', '', 5
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '5');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '6', 'Herramienta portátil', 'PERMITIDO', 'Con desinfección previa', 6
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '6');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '7', 'EPP autorizado (casco, guantes…)', 'PERMITIDO', '', 7
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '7');
INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden) SELECT '99', 'Otro', 'CRITERIO', 'El guardia documenta según política', 99
WHERE NOT EXISTS (SELECT 1 FROM bio_articulo_ingreso WHERE codigo = '99');