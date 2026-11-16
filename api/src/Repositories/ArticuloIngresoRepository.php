<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Support\Database;

final class ArticuloIngresoRepository
{
    /** @return list<array<string, mixed>> */
    public function listar(bool $soloActivos): array
    {
        $pdo = Database::pdo();
        $sql = 'SELECT id, codigo, nombre, regla, nota, orden, estado FROM bio_articulo_ingreso';
        if ($soloActivos) {
            $sql .= " WHERE estado = 'ACTIVO'";
        }
        $sql .= ' ORDER BY orden, codigo';
        $st = $pdo->query($sql);
        if ($st === false) {
            return [];
        }

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('SELECT * FROM bio_articulo_ingreso WHERE id = ? LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @param array{codigo:string,nombre:string,regla:string,nota:string,orden:int,estado:string} $data */
    public function crear(array $data): int
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO bio_articulo_ingreso (codigo, nombre, regla, nota, orden, estado) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $data['codigo'],
            $data['nombre'],
            $data['regla'],
            $data['nota'],
            $data['orden'],
            $data['estado'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array{codigo:string,nombre:string,regla:string,nota:string,orden:int,estado:string} $data */
    public function actualizar(int $id, array $data): void
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'UPDATE bio_articulo_ingreso SET codigo = ?, nombre = ?, regla = ?, nota = ?, orden = ?, estado = ? WHERE id = ?'
        );
        $st->execute([
            $data['codigo'],
            $data['nombre'],
            $data['regla'],
            $data['nota'],
            $data['orden'],
            $data['estado'],
            $id,
        ]);
    }
}