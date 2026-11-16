<?php

declare(strict_types=1);

namespace Bio\Api\Support;

use Bio\Api\Bootstrap\Database;
use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Repositories\PersonalRepository;
use PDO;
use Throwable;

/**
 * Misma nómina que Sanidad (`san_personal_proveedor`, RUC + DNI).
 * bio_personal guarda solo lo que esa tabla no tiene (cargo, contratación, foto, usuario).
 */
final class PersonalProveedorSync
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function asegurarTabla(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `san_personal_proveedor` (
              `ruc` varchar(11) NOT NULL DEFAULT \'\',
              `dni` varchar(15) NOT NULL DEFAULT \'\',
              `nombre` varchar(60) NOT NULL DEFAULT \'\',
              `activo` tinyint(1) NOT NULL DEFAULT 1,
              `usuarioModificacion` varchar(25) NOT NULL DEFAULT \'\',
              `fechaHoraModificacion` datetime NULL DEFAULT NULL,
              PRIMARY KEY (`ruc`, `dni`)
            ) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci'
        );
    }

    /** Copia al portal las personas que el proveedor ya cargó en Sanidad. */
    public function importarEmpresa(int $empresaId): int
    {
        $emp = (new EmpresaRepository())->find($empresaId);
        if ($emp === null) {
            return 0;
        }
        $ruc = preg_replace('/\D/', '', (string) ($emp['ruc'] ?? '')) ?? '';
        if (strlen($ruc) !== 11) {
            return 0;
        }
        try {
            $this->asegurarTabla();
            $st = $this->pdo->prepare(
                'SELECT TRIM(dni) AS dni, TRIM(nombre) AS nombre, activo, fechaHoraModificacion
                   FROM san_personal_proveedor WHERE TRIM(ruc) = ?'
            );
            $st->execute([$ruc]);
            $rows = $st->fetchAll() ?: [];
        } catch (Throwable $e) {
            return 0;
        }
        $fotoCol = $this->columnaFoto();
        $repo = new PersonalRepository();
        $n = 0;
        foreach ($rows as $row) {
            $dni = trim((string) ($row['dni'] ?? ''));
            if ($dni === '') {
                continue;
            }
            $activo = (int) ($row['activo'] ?? 1) === 1;
            $nombre = trim((string) ($row['nombre'] ?? ''));
            [$nombres, $apellidos] = self::partirNombre($nombre !== '' ? $nombre : $dni);
            $existente = $repo->findByDni($dni);
            $foto = '';
            if ($fotoCol !== null) {
                $foto = $this->fotoDe($ruc, $dni, $fotoCol);
            }
            if ($existente === null) {
                $fecha = substr((string) ($row['fechaHoraModificacion'] ?? ''), 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    $fecha = date('Y-m-d');
                }
                $id = $repo->crear([
                    'dni' => $dni,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'cargo' => 'Personal',
                    'empresa' => 'TERCERO',
                    'empresa_id' => $empresaId,
                    'fecha_contratacion' => $fecha,
                    'estado' => $activo ? 'ACTIVO' : 'CESADO',
                    'granja_base_cod' => '',
                    'foto_ruta' => $foto,
                ]);
                $n++;
                if ($activo) {
                    $this->asegurarUsuario($id, $dni, trim($nombres . ' ' . $apellidos), $empresaId);
                }
                continue;
            }
            $id = (int) $existente['id'];
            $ficha = $repo->find($id);
            if ($ficha === null) {
                continue;
            }
            if ((int) ($ficha['empresa_id'] ?? 0) !== $empresaId && ($ficha['empresa'] ?? '') === 'TERCERO') {
                continue;
            }
            $cambiaNombre = $nombre !== '' && ($ficha['nombres'] !== $nombres || $ficha['apellidos'] !== $apellidos);
            $estado = $activo ? 'ACTIVO' : 'CESADO';
            $fotoRuta = (string) ($ficha['foto_ruta'] ?? '');
            if ($fotoRuta === '' && $foto !== '') {
                $fotoRuta = $foto;
            }
            if ($cambiaNombre || $estado !== ($ficha['estado'] ?? '') || $fotoRuta !== ($ficha['foto_ruta'] ?? '')) {
                $repo->actualizar($id, [
                    'dni' => $ficha['dni'],
                    'nombres' => $cambiaNombre ? $nombres : $ficha['nombres'],
                    'apellidos' => $cambiaNombre ? $apellidos : $ficha['apellidos'],
                    'cargo' => $ficha['cargo'],
                    'empresa' => 'TERCERO',
                    'empresa_id' => $empresaId,
                    'fecha_contratacion' => $ficha['fecha_contratacion'],
                    'estado' => $estado,
                    'granja_base_cod' => $ficha['granja_base_cod'] ?? '',
                    'foto_ruta' => $fotoRuta,
                ]);
            }
            if ($activo && trim((string) ($ficha['usuario_cod'] ?? '')) === '') {
                $this->asegurarUsuario($id, $dni, trim($ficha['nombres'] . ' ' . $ficha['apellidos']), $empresaId);
            }
        }

        return $n;
    }

    /** Publica en la nómina de Sanidad lo que se registró en el portal. */
    public function publicar(array $personal, string $usuario): void
    {
        if (($personal['empresa'] ?? '') !== 'TERCERO') {
            return;
        }
        $empresaId = (int) ($personal['empresa_id'] ?? 0);
        $emp = $empresaId > 0 ? (new EmpresaRepository())->find($empresaId) : null;
        $ruc = preg_replace('/\D/', '', (string) ($emp['ruc'] ?? '')) ?? '';
        if (strlen($ruc) !== 11) {
            return;
        }
        $dni = trim((string) ($personal['dni'] ?? ''));
        if ($dni === '') {
            return;
        }
        $nombre = trim((string) ($personal['apellidos'] ?? '') . ' ' . (string) ($personal['nombres'] ?? ''));
        $nombre = substr(preg_replace('/\s+/', ' ', $nombre) ?? $nombre, 0, 60);
        $activo = ($personal['estado'] ?? '') === 'ACTIVO' ? 1 : 0;
        try {
            $this->asegurarTabla();
            $this->pdo->prepare(
                'INSERT INTO san_personal_proveedor (ruc, dni, nombre, activo, usuarioModificacion, fechaHoraModificacion)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                   nombre = IF(VALUES(nombre) <> \'\', VALUES(nombre), nombre),
                   activo = VALUES(activo),
                   usuarioModificacion = VALUES(usuarioModificacion),
                   fechaHoraModificacion = NOW()'
            )->execute([$ruc, $dni, $nombre, $activo, substr($usuario, 0, 25)]);
            $this->escribirFoto($ruc, $dni, (string) ($personal['foto_ruta'] ?? ''));
        } catch (Throwable $e) {
            // La ficha del portal queda guardada aunque la nómina compartida no esté.
        }
    }

    /** @return array{0:string,1:string} */
    public static function partirNombre(string $nombre): array
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? '');
        if ($nombre === '') {
            return ['', '-'];
        }
        $parts = explode(' ', $nombre);
        if (count($parts) === 1) {
            return [substr($parts[0], 0, 80), '-'];
        }
        $nombres = substr(array_shift($parts), 0, 80);
        $apellidos = substr(implode(' ', $parts), 0, 80);

        return [$nombres, $apellidos !== '' ? $apellidos : '-'];
    }

    private function columnaFoto(): ?string
    {
        try {
            $st = $this->pdo->query('SHOW COLUMNS FROM san_personal_proveedor');
            foreach ($st ? $st->fetchAll() : [] as $c) {
                $f = strtolower((string) $c['Field']);
                if (in_array($f, ['foto_ruta', 'foto', 'ruta_foto', 'imagen'], true)) {
                    return (string) $c['Field'];
                }
            }
        } catch (Throwable $e) {
            return null;
        }

        return null;
    }

    private function fotoDe(string $ruc, string $dni, string $col): string
    {
        $st = $this->pdo->prepare(
            'SELECT `' . str_replace('`', '', $col) . '` AS foto FROM san_personal_proveedor WHERE TRIM(ruc) = ? AND TRIM(dni) = ? LIMIT 1'
        );
        $st->execute([$ruc, $dni]);
        $row = $st->fetch();

        return trim((string) ($row['foto'] ?? ''));
    }

    private function escribirFoto(string $ruc, string $dni, string $ruta): void
    {
        $col = $this->columnaFoto();
        if ($col === null || $ruta === '') {
            return;
        }
        $this->pdo->prepare(
            'UPDATE san_personal_proveedor SET `' . str_replace('`', '', $col) . '` = ? WHERE TRIM(ruc) = ? AND TRIM(dni) = ?'
        )->execute([substr($ruta, 0, 255), $ruc, $dni]);
    }

    private function asegurarUsuario(int $personalId, string $dni, string $nombre, int $empresaId): void
    {
        $repo = new PersonalRepository();
        $r = SanidadTrabajadorBridge::asegurar($dni, $nombre, $personalId, $empresaId);
        if (!empty($r['ok']) && !empty($r['usuario_cod'])) {
            $repo->setUsuarioCod($personalId, (string) $r['usuario_cod']);
        }
    }
}
