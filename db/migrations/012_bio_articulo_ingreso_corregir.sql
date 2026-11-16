-- Tildes UTF-8 correctas y celular permitido (política GRS).
UPDATE `bio_articulo_ingreso`
SET `nombre` = 'Celular / smartphone',
    `regla` = 'PERMITIDO',
    `nota` = 'Registrar ingreso con foto'
WHERE `codigo` = '1';

UPDATE `bio_articulo_ingreso`
SET `nombre` = 'Audífonos'
WHERE `codigo` = '2';

UPDATE `bio_articulo_ingreso`
SET `nombre` = 'Reloj u otros accesorios metálicos'
WHERE `codigo` = '3';

UPDATE `bio_articulo_ingreso`
SET `nombre` = 'Herramienta portátil'
WHERE `codigo` = '6';

UPDATE `bio_articulo_ingreso`
SET `nombre` = 'EPP autorizado (casco, guantes…)'
WHERE `codigo` = '7';

UPDATE `bio_articulo_ingreso`
SET `nota` = 'El guardia documenta según política'
WHERE `codigo` = '99';