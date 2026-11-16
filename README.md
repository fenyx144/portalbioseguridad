# Bioseguridad — módulo web (Fase 1)

Monorepo: `web` (React + Vite), `api` (PHP REST), `db` (schema + seed), `app` (móvil futuro).

## Requisitos

- XAMPP (Apache + PHP 8)
- Node.js para desarrollo del front
- Base `ciajoya` (compartida con Sanidad)

## Base de datos

```powershell
Get-Content db\schema.sql -Raw | & c:\xampp\mysql\bin\mysql.exe -h HOST -u USER -pPASS ciajoya
Get-Content db\seed.sql -Raw | & c:\xampp\mysql\bin\mysql.exe -h HOST -u USER -pPASS ciajoya
c:\xampp\php\php.exe api\scripts\apply_migration.php db\migrations\002_bio_empresa.sql
```

Copiar `api/.env.example` → `api/.env` o usar credenciales de `conexion_grs`.

## API

URL: `http://localhost/bioseguridad/api/public/`

Login: mismas credenciales que Sanidad (`usuario` + AES).

## Frontend (desarrollo)

```powershell
cd web
npm install
npm run dev
```

Proxy Vite → API en Apache. Producción: `npm run build` y servir `web/dist` bajo `/bioseguridad/web/`.

## Acceso

1. `http://localhost/bioseguridad/web/` (build) o `http://localhost:5173/bioseguridad/web/` (dev)
2. Iniciar sesión con usuario activo de Sanidad

### Actores

- **GRS** (ej. usuario `DERR`): planificación por **cargo**, gestión de puntos y mantenimiento de **empresas**.
- **Empresa contratista**: usuario SIP creado en *Empresas* (código 6 + RUC; clave inicial = RUC). Solo ve su personal y planifica en los puntos.
- **Operarios contratista** (app móvil, p. ej. `EMPT01`): `libtri` = DNI en `bio_personal`; no usa el portal web como GRS.

Logo en login: `web/public/logo-grs.png` (ruta con base `/bioseguridad/web/`).
