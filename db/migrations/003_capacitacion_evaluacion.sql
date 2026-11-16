ALTER TABLE `bio_personal_capacitacion`
  ADD COLUMN `resultado` enum('APROBADO','DESAPROBADO','PENDIENTE') NOT NULL DEFAULT 'APROBADO' AFTER `fecha_vence`,
  ADD COLUMN `fecha_evaluacion` date DEFAULT NULL AFTER `resultado`;

INSERT IGNORE INTO `bio_config` (`clave`, `valor`) VALUES
('capacitacion_catalogo_gestor', 'ambos'),
('capacitacion_registro_gestor', 'ambos');
