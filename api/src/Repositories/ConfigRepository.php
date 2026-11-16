<?php

declare(strict_types=1);

namespace Bio\Api\Repositories;

use Bio\Api\Bootstrap\Database;
use PDO;

final class ConfigRepository
{
    /** @var PDO */
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $st = $this->pdo->query('SELECT clave, valor FROM bio_config');
        if ($st === false) {
            return [];
        }
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(string) $row['clave']] = (string) $row['valor'];
        }

        return $out;
    }

    public function set(string $clave, string $valor): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO bio_config (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $st->execute([$clave, $valor]);
    }
}
