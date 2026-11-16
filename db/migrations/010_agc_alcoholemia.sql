-- Mismo contenido que sanidad/flutter/bioseguridad/sql/migracion_agc_alcoholemia.sql
SOURCE is manual: copiar desde migracion_agc_alcoholemia.sql en el módulo móvil.

INSERT INTO san_agc_indicador (nombre, orden)
SELECT 'Bioseguridad', 2
WHERE NOT EXISTS (SELECT 1 FROM san_agc_indicador WHERE nombre = 'Bioseguridad');
UPDATE san_agc_indicador SET activo = 1, orden = 2 WHERE nombre = 'Bioseguridad';

INSERT INTO san_agc_proceso (indicador_id, grupo, codigo, nombre, orden)
SELECT (SELECT MIN(id) FROM san_agc_indicador WHERE nombre = 'Bioseguridad'),
       'Bioseguridad', 'BF', 'Control de Alcoholemia', 10
WHERE NOT EXISTS (SELECT 1 FROM san_agc_proceso WHERE nombre = 'Control de Alcoholemia');

UPDATE san_agc_proceso
SET indicador_id = (SELECT MIN(id) FROM san_agc_indicador WHERE nombre = 'Bioseguridad'),
    grupo = 'Bioseguridad',
    codigo = 'BF',
    activo = 1
WHERE nombre = 'Control de Alcoholemia';