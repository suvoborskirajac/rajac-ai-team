<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/lib.php';

try {
    $snapshot = rajac_sh_run();
    $paths = rajac_sh_save($snapshot);
    if (PHP_SAPI === 'cli') {
        echo '[' . date('Y-m-d H:i:s') . '] '
           . $snapshot['status_label']
           . ' | ради=' . $snapshot['counts']['radi']
           . ' | касни=' . $snapshot['counts']['radi_podatak_kasni']
           . ' | делимично=' . $snapshot['counts']['delimicno_radi']
           . ' | грешка=' . $snapshot['counts']['greska']
           . PHP_EOL;
    }
} catch (Throwable $e) {
    error_log('RAJAC System Health: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'ГРЕШКА: ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}
