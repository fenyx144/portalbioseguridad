-- Frente IMPLEMENTOS (botas, lentes, mascarillas: se entregan y se devuelven, no se lavan).
-- Aplicar solo si 015 ya creó bio_almacen_mov. No ejecutar desde la aplicación.

ALTER TABLE `bio_almacen_mov`
  MODIFY `frente` enum('LIMPIO','LAVADERO','INSUMOS','IMPLEMENTOS') NOT NULL;
