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

| Uso | Método | Ruta |
|-----|--------|------|
| Login empresa | POST | `/auth/login` |
| Contexto campo | GET | `/almacen/campo-context` |
| Relevos | GET/POST | `/almacen/relevos` |
| Recepción | POST | `/almacen/entregas/{id}/recepcion` |

Aplicar migración `db/migrations/008_almacen_relevo.sql` antes de usar relevos.

## Configuración

En `gc_bioseguridad/lib/utils/constants.dart`:

- `bioEmpresaApiBase` — URL pública de la API (misma red que el servidor).
- `mostrarModuloEmpresaBio` — oculta el bloque del drawer si hace falta.
