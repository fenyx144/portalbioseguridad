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
| Pantalla | GET | `/almacen/campo-context?granja_cod=` |
| Stock | GET | `/almacen/granja/stock?granja_cod=` |
| Kardex | GET | `/almacen/granja/kardex?granja_cod=&categoria=&desde=&hasta=` |
| Registrar | POST | `/almacen/granja/movimientos` |

Tabla `bio_almacen_mov`. `frente`: `LIMPIO`, `LAVADERO`, `IMPLEMENTOS`, `INSUMOS`. `tipo`: `SI`, `E`, `S`. `categoria`: `UNIFORME` (ítem `1` tránsito o `2` proceso), `IMPLEMENTO` (id de `bio_insumo` EPP/INDUMENTARIA), `INSUMO` (id DESINFECTANTE/OTRO).

El rol sale de la sesión (`actor` grs o empresa). GRS solo `ENVIO` y `SI`, todas las granjas. Empresa solo sus granjas y `CONSUMO`, `ENTREGA`, `DEVOLUCION`, `LAVADO`, `RELEVO`. Otro rol responde 403.

POST:

```json
{
  "operacion": "ENTREGA",
  "granja_cod": "001",
  "categoria": "UNIFORME",
  "item": "2",
  "cantidad": 1,
  "fecha": "2026-11-17",
  "personal_id": 0,
  "dni": "",
  "personal_id_sale": 0,
  "dni_sale": "",
  "personal_id_entra": 0,
  "dni_entra": "",
  "origen": "app"
}
```

- `ENVIO` / `SI` (GRS): entrada o saldo en limpio, implementos o insumos.
- `CONSUMO`: `S` en `INSUMOS`.
- `ENTREGA`: `S` en `LIMPIO` (uniforme) o `IMPLEMENTOS`.
- `DEVOLUCION`: `E` en `LAVADERO` (uniforme) o `IMPLEMENTOS` (vuelve al stock).
- `LAVADO`: `S` en `LAVADERO` y `E` en `LIMPIO`, mismo `par_id`.
- `RELEVO`: un `par_id`. Uniforme del saliente `E` en lavadero, uniforme limpio `S` al entrante. Cada implemento con stock: `S` del saliente y `E` del entrante (el stock de la granja no cambia).

`campo-context` y stock traen `rol`, `acciones`, `granjas`, `resumen` (limpio/lavadero), `implementos`, `insumos`. Kardex filtra con `categoria`.

Si `015` ya se aplicó, hace falta `db/migrations/016_almacen_frente_implementos.sql` (no ejecutada). No usa `imov` ni `movi_zonas`.

<!--
Entrega GRS → proveedor/tercero, fuera de uso. Rutas comentadas, código conservado:
GET/POST /almacen/entregas, POST /almacen/entregas/{id}/recepcion, POST /almacen/entregas/{id}/anular,
POST /almacen/saldos, GET/POST/DELETE /almacen/consumos, GET /almacen/stock, GET/POST /almacen/relevos.
-->

## Configuración

En `gc_bioseguridad/lib/utils/constants.dart`:

- `bioEmpresaApiBase` — URL pública de la API (misma red que el servidor).
- `mostrarModuloEmpresaBio` — oculta el bloque del drawer si hace falta.
