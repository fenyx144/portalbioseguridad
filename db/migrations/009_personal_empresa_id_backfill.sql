-- Vincula personal TERCERO sin contratista al único registro en bio_empresa (solo si hay exactamente una).
-- Ejecutar manualmente en entornos con datos legacy; no es destructivo.

SET @cnt = (SELECT COUNT(*) FROM bio_empresa WHERE estado = 'ACTIVO');
SET @eid = (SELECT id FROM bio_empresa WHERE estado = 'ACTIVO' ORDER BY id LIMIT 1);

UPDATE bio_personal
SET empresa_id = @eid
WHERE empresa = 'TERCERO'
  AND (empresa_id IS NULL OR empresa_id = 0)
  AND @cnt = 1;
