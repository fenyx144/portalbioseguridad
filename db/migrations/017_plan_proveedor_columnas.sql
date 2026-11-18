-- Referencia. NO ejecutar si ciajoya ya tiene estas tablas y columnas
-- (las crea Sanidad). Solo aplica en una base donde falten.
-- No se ejecutó desde el portal.

CREATE TABLE IF NOT EXISTS `san_personal_proveedor` (
  `ruc` varchar(11) NOT NULL DEFAULT '',
  `dni` varchar(15) NOT NULL DEFAULT '',
  `nombre` varchar(60) NOT NULL DEFAULT '',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `usuarioModificacion` varchar(25) NOT NULL DEFAULT '',
  `fechaHoraModificacion` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`ruc`, `dni`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `san_vehiculo_proveedor` (
  `ruc` varchar(11) NOT NULL DEFAULT '',
  `placa` varchar(15) NOT NULL DEFAULT '',
  `descripcion` varchar(60) NOT NULL DEFAULT '',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `usuarioModificacion` varchar(25) NOT NULL DEFAULT '',
  `fechaHoraModificacion` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`ruc`, `placa`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `san_usuario_proveedor` (
  `codigo` varchar(30) NOT NULL DEFAULT '',
  `ruc` varchar(11) NOT NULL DEFAULT '',
  `razonsocial` varchar(120) NOT NULL DEFAULT '',
  `proceso` varchar(4000) NOT NULL DEFAULT '',
  `usuarioModificacion` varchar(25) NOT NULL DEFAULT '',
  `fechaHoraModificacion` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Ejecutar cada ALTER solo si la columna no existe.
-- ALTER TABLE `san_fact_planvisita_cab` ADD COLUMN `proveedorRuc` varchar(15) NOT NULL DEFAULT '' AFTER `numPersonas`;
-- ALTER TABLE `san_fact_planvisita_cab` ADD COLUMN `proveedor` varchar(120) NOT NULL DEFAULT '' AFTER `proveedorRuc`;
-- ALTER TABLE `san_fact_planvisita_cab` ADD COLUMN `areaProveedor` varchar(20) NOT NULL DEFAULT '' AFTER `proveedor`;
-- ALTER TABLE `san_fact_planvisita_cab` ADD COLUMN `motivo` varchar(60) NOT NULL DEFAULT '' AFTER `areaProveedor`;
-- ALTER TABLE `san_fact_planvisita_cab` ADD COLUMN `observacion` varchar(200) NOT NULL DEFAULT '' AFTER `motivo`;
