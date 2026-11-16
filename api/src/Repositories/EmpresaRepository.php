<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class EmpresaRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function listar(): array
    {
        $st = $this->pdo->query(
            "SELECT e.id, e.codigo, e.ruc, e.razon_social, e.usuario_cod, e.estado, e.creado_en,
                    (SELECT COUNT(*) FROM bio_personal p WHERE p.empresa_id = e.id AND p.estado = 'ACTIVO') AS personal_activo,
                    (SELECT COUNT(*) FROM bio_personal p WHERE p.empresa_id = e.id) AS personal_total
             FROM bio_empresa e ORDER BY e.razon_social"
        );

        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_empresa WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function findByUsuarioCod(string $codigo): ?array
    {
        $cod = strtoupper(trim($codigo));
        $st = $this->pdo->prepare(
            "SELECT * FROM bio_empresa WHERE usuario_cod = ? AND estado = 'ACTIVO' LIMIT 1"
        );
        $st->execute([$cod]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }

        // Operarios de contratista (p. ej. EMPT01): no aplica a cuentas GRS (DERR, etc.).
        if (!self::esCodigoOperarioEmpresa($cod)) {
            return null;
        }

        return $this->findByTrabajadorUsuario($cod);
    }

    /** Cuentas móvil/web de operario, no administradores GRS ni usuario portal EMP00x. */
    public static function esCodigoOperarioEmpresa(string $codigo): bool
    {
        $cod = strtoupper(trim($codigo));

        return (bool) preg_match('/^EMPT[A-Z0-9]*$/', $cod);
    }

    /**
     * Trabajador contratista: usuario en `usuario` con libtri = DNI y ficha en bio_personal.empresa_id.
     */
    public function findByTrabajadorUsuario(string $codigoUsuario): ?array
    {
        $cod = strtoupper(trim($codigoUsuario));
        if (!self::esCodigoOperarioEmpresa($cod)) {
            return null;
        }
        $st = $this->pdo->prepare(
            "SELECT e.*
             FROM bio_empresa e
             INNER JOIN bio_personal p ON p.empresa_id = e.id AND p.estado = 'ACTIVO'
             INNER JOIN usuario u ON UPPER(TRIM(u.codigo)) = ?
               AND u.estado = 'A'
               AND TRIM(u.libtri) <> ''
               AND TRIM(u.libtri) = TRIM(p.dni)
             WHERE e.estado = 'ACTIVO'
             LIMIT 1"
        );
        $st->execute([$cod]);
        $row = $st->fetch();

        return $row ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_empresa WHERE codigo = ?');
        $st->execute([strtoupper(trim($codigo))]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @param array{codigo: string, ruc: string, razon_social: string, usuario_cod: string} $data */
    public function crear(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_empresa (codigo, ruc, razon_social, usuario_cod, estado) VALUES (?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['codigo'],
            $data['ruc'],
            $data['razon_social'],
            $data['usuario_cod'],
            'ACTIVO',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function patchEstado(int $id, string $estado): bool
    {
        if (!in_array($estado, ['ACTIVO', 'INACTIVO'], true)) {
            return false;
        }
        $st = $this->pdo->prepare('UPDATE bio_empresa SET estado = ? WHERE id = ?');
        $st->execute([$estado, $id]);

        return $st->rowCount() > 0;
    }
}
