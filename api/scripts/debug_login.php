<?php

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Bio\\Api\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/src/' . $rel . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use Bio\Api\Bootstrap\Env;
use Bio\Api\Bootstrap\Database;
use Bio\Api\Repositories\EmpresaRepository;
use Bio\Api\Support\SanidadAuthBridge;
use Bio\Api\Support\UserContext;

Env::load(dirname(__DIR__));

$usuario = $argv[1] ?? 'DERR';
$clave = $argv[2] ?? '';

try {
    echo "DB test...\n";
    $pdo = Database::pdo();
    $pdo->query('SELECT 1')->fetch();
    echo "DB OK\n";
} catch (Throwable $e) {
    echo "DB FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "bio_empresa test...\n";
    $repo = new EmpresaRepository();
    $repo->listar();
    echo "bio_empresa OK\n";
} catch (Throwable $e) {
    echo "bio_empresa FAIL: " . $e->getMessage() . "\n";
}

try {
    $lib = dirname(__DIR__, 3) . '/../sanidad/includes/usuario_auth_lib.php';
    $connFile = dirname(__DIR__, 3) . '/../conexion_grs/conexion.php';
    echo "sanidad lib (manual): $lib exists=" . (is_file($lib) ? 'yes' : 'no') . "\n";
    echo "bridge lib: ";
    $bridgeLib = dirname(__DIR__) . '/src/Support/SanidadAuthBridge.php';
    $ref = new ReflectionClass(\Bio\Api\Support\SanidadAuthBridge::class);
    // path from bridge source
    $support = dirname(__DIR__) . '/src/Support';
    $bLib = dirname($support, 4) . '/sanidad/includes/usuario_auth_lib.php';
    echo "$bLib exists=" . (is_file($bLib) ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo "path check: " . $e->getMessage() . "\n";
}

if ($clave !== '') {
    try {
        $result = SanidadAuthBridge::authenticate($usuario, $clave);
        echo "auth: " . ($result === null ? 'null' : json_encode($result)) . "\n";
        if ($result !== null) {
            $repo = new EmpresaRepository();
            $emp = $repo->findByUsuarioCod($result['codigo']);
            echo "empresa row: " . ($emp === null ? 'null' : json_encode($emp)) . "\n";
            $profile = UserContext::enrichUser($result);
            echo "actor: " . ($profile['actor'] ?? '?') . "\n";
        }
    } catch (Throwable $e) {
        echo "auth FAIL: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Pass clave as argv[2] to test authenticate\n";
}
