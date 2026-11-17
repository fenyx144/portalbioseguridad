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
| Kardex | GET | `/almacen/granja/kardex?granja_cod=&almacen=&desde=&hasta=&clase=` |
| Registrar movimiento | POST | `/almacen/granja/movimientos` |

`clase`: `uniforme`, `insumo` o `todos`. `almacen`: `LIMPIO` o `LAVADERO`.

POST `/almacen/granja/movimientos`:

```json
{
  "operacion": "ENTREGA",
  "granja_cod": "001",
  "fecha": "2026-11-17",
  "cantidad": 1,
  "ropa_tipo": 2,
  "almacen": "LIMPIO",
  "insumo_id": 0,
  "dni": "",
  "trabajador": "",
  "observacion": ""
}
```

`operacion`: `SI_UNIFORME`, `CARGA_UNIFORME`, `ENTREGA`, `RECEPCION`, `LAVADO`, `SI_INSUMO`, `CARGA_INSUMO`, `CONSUMO`.

- `ENTREGA`: salida del Almacén limpio (uniforme al trabajador que llega).
- `RECEPCION`: entrada al Lavadero (uniforme al retirarse).
- `LAVADO`: salida del Lavadero y entrada al Almacén limpio, misma cantidad.
- Ropa `1` tránsito, `2` proceso. El stock de la pantalla es el de proceso.
- `campo-context` trae `resumen[]` con `limpio` y `lavadero`, `insumos[]`, `movimientos[]` (kardex con `tipo_mov` SI/E/S y `stock`), `ropa_tipos` y `almacenes`.

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
