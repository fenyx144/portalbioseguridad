<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class GranjaRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return list<array{codigo: string, nombre: string, resumen: string}> */
    public function listar(): array
    {
        $sql = 'SELECT TRIM(codgranja) AS codigo, TRIM(nomgranja) AS nombre, TRIM(IFNULL(resumen, "")) AS resumen
                FROM lk_granjas ORDER BY codgranja';
        $st = $this->pdo->query($sql);
        if ($st === false) {
            return [];
        }

        return array_map(function (array $r) {
            return [
                'codigo' => (string) $r['codigo'],
                'nombre' => (string) $r['nombre'],
                'resumen' => (string) $r['resumen'],
            ];
        }, $st->fetchAll());
    }

    public function existe(string $codigo): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM lk_granjas WHERE TRIM(codgranja) = ? LIMIT 1');
        $st->execute([trim($codigo)]);

        return (bool) $st->fetchColumn();
    }
}
