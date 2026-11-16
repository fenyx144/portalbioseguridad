<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class CapacitacionRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array<string, mixed>> */
    public function listar(bool $soloActivos = true): array
    {
        $sql = 'SELECT id, codigo, nombre, vigencia_meses, estado FROM bio_capacitacion';
        if ($soloActivos) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY nombre';
        $st = $this->pdo->query($sql);

        return $st ? $st->fetchAll() : [];
    }

    public function find(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM bio_capacitacion WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function crear(array $data): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_capacitacion (codigo, nombre, vigencia_meses, estado) VALUES (?, ?, ?, ?)'
        );
        $st->execute([
            $data['codigo'],
            $data['nombre'],
            (int) $data['vigencia_meses'],
            $data['estado'] ?? 'ACTIVO',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function actualizar(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            'UPDATE bio_capacitacion SET codigo = ?, nombre = ?, vigencia_meses = ?, estado = ? WHERE id = ?'
        );
        $st->execute([
            $data['codigo'],
            $data['nombre'],
            (int) $data['vigencia_meses'],
            $data['estado'],
            $id,
        ]);
    }
}
