# Móvil: embebido en `gc_bioseguridad` vs app propia

## Decisión actual

- **Campo GRS** (ingreso, cerco, fómites, etc.) sigue en `gc_bioseguridad` contra Sanidad Flutter.
- **Almacén / relevo** usan la API de este repo (`/bioseguridad/api/public`).
- Embebido en `gc_bioseguridad` (drawer **Almacén**).
- **Un solo login:** misma tabla `usuario` (GRS y terceros). La app llama la API con `Authorization: Bearer` + `X-Usuario-Codigo` (idéntico al login móvil Sanidad), sin segunda clave ni cookie aparte.
  - Misma paleta y `AppDrawer` que el resto de la app.

## Alternativa (futuro)

Carpeta `bioseguridad/app/` para una app Flutter independiente clonando tema/drawer de `gc_bioseguridad`, si el APK debe distribuirse solo a contratistas.

## Endpoints móvil

La app (`gc_bioseguridad`) tiene **una sola sección Almacén**. Auth: `Authorization: Bearer` + `X-Usuario-Codigo`. Respuesta `{ok, data}`.

| Uso | Método | Ruta |
|-----|--------|------|
| Login | POST | `/auth/login` |
| Pantalla (granjas, stock, insumos, últimos movimientos) | GET | `/almacen/campo-context?granja_cod=` |
| Stock limpio / lavadero e insumos | GET | `/almacen/granja/stock?granja_cod=` |
| Kardex | GET | `/almacen/granja/kardex?granja_cod=&frente=&desde=&hasta=` |
| Registrar movimiento | POST | `/almacen/granja/movimientos` |

Una tabla `bio_almacen_mov`. `frente`: `LIMPIO`, `LAVADERO` o `INSUMOS`. `tipo`: `SI`, `E` o `S`. `item`: `1` o `2` (ropa) en los frentes de uniforme; id de insumo si `frente` es `INSUMOS`.

POST `/almacen/granja/movimientos`:

```json
{
  "operacion": "ENTREGA",
  "granja_cod": "001",
  "fecha": "2026-11-17",
  "cantidad": 1,
  "frente": "LIMPIO",
  "item": "2",
  "dni": "",
  "personal_id": 0,
  "observacion": "",
  "origen": "app"
}
```

`operacion`: `SI_UNIFORME`, `CARGA_UNIFORME`, `ENTREGA`, `RECEPCION`, `LAVADO`, `SI_INSUMO`, `CARGA_INSUMO`, `CONSUMO`. `origen`: `web` o `app` (si se omite y hay Bearer, queda `app`).

- `ENTREGA`: `S` en `LIMPIO`.
- `RECEPCION`: `E` en `LAVADERO`.
- `LAVADO`: `S` en `LAVADERO` y `E` en `LIMPIO`, misma cantidad, mismo `par_id`.
- Ropa `1` tránsito, `2` proceso. El stock de la pantalla es el ítem `2`.
- `campo-context` trae `resumen[]` (`limpio`, `lavadero`), `insumos[]`, `frentes`, `ropa_tipos` y `movimientos[]` con `frente`, `tipo`, `item`, `operacion`, `origen`, `par_id` y `stock`.

Aplicar `db/migrations/015_almacen_granja_kardex.sql` antes de usar esta sección. No usa `imov` ni `movi_zonas`.

<!--
Entrega GRS → proveedor/tercero, fuera de uso. Rutas comentadas, código conservado:
GET/POST /almacen/entregas, POST /almacen/entregas/{id}/recepcion, POST /almacen/entregas/{id}/anular,
POST /almacen/saldos, GET/POST/DELETE /almacen/consumos, GET /almacen/stock, GET/POST /almacen/relevos.
-->

## Configuración

En `gc_bioseguridad/lib/utils/constants.dart`:

- `bioEmpresaApiBase` — URL pública de la API (misma red que el servidor).
- `mostrarModuloEmpresaBio` — oculta el bloque del drawer si hace falta.
