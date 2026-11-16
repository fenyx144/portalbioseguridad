<?php

require_once dirname(__DIR__) . '/src/Bootstrap/compat.php';
$autoload = dirname(__DIR__, 3) . '/sanidad/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "No autoload: $autoload\n");
    exit(1);
}
require_once $autoload;
$tmp = dirname(__DIR__) . '/storage/pdf_tmp';
if (!is_dir($tmp)) {
    mkdir($tmp, 0775, true);
}
$m = new \Mpdf\Mpdf(['tempDir' => $tmp, 'mode' => 'utf-8']);
$m->WriteHTML('<p>ok</p>');
echo strlen($m->Output('', 'S'));
