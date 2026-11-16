-- Capacitaciones: sesiones con hora y nota mínima; asistencia y evaluación por participante
ALTER TABLE `bio_capacitacion_sesion`
  ADD COLUMN `hora_inicio` time DEFAULT NULL AFTER `fecha`,
  ADD COLUMN `nota_minima` decimal(4,1) NOT NULL DEFAULT 14.0 AFTER `lugar`;

ALTER TABLE `bio_capacitacion_sesion_personal`
  ADD COLUMN `nota` decimal(4,1) DEFAULT NULL AFTER `asistio`,
  ADD COLUMN `resultado` enum('PENDIENTE','APROBADO','DESAPROBADO') NOT NULL DEFAULT 'PENDIENTE' AFTER `nota`,
  ADD COLUMN `fecha_evaluacion` date DEFAULT NULL AFTER `resultado`,
  ADD COLUMN `observacion` varchar(255) NOT NULL DEFAULT '' AFTER `fecha_evaluacion`;

ALTER TABLE `bio_personal_capacitacion`
  ADD COLUMN `nota` decimal(4,1) DEFAULT NULL AFTER `resultado`;
