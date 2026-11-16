<?php

require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';
require_once dirname(__DIR__) . '/src/Bootstrap/Env.php';
require_once dirname(__DIR__) . '/src/Support/MpdfFactory.php';

use Bio\Api\Bootstrap\Env;
use Bio\Api\Services\ReportPdfService;

Env::load(dirname(__DIR__));

try {
    $pdf = (new ReportPdfService())->personalRegistrado([]);
    $bin = $pdf->Output('', 'S');
    echo 'OK bytes=' . strlen($bin) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
