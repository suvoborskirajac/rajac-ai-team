<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$run = isset($_GET['run']) && $_GET['run'] === '1';

try {
    if ($run) {
        $snapshot = rajac_sh_run();
        rajac_sh_save($snapshot);
    } else {
        $snapshot = rajac_sh_load_latest();
        if (!$snapshot) {
            $snapshot = [
                'schema_version' => '1.0',
                'agent' => 'system-health',
                'name' => 'Агент надзора система',
                'status' => 'nepoznato',
                'status_label' => 'НЕПОЗНАТО',
                'timestamp' => gmdate('c'),
                'analysis' => 'Још нема снимка. Покрените cron.php или api.php?run=1.',
                'services' => [],
                'alerts' => [],
                'recommended_checks' => [],
            ];
        }
    }
    echo json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'agent' => 'system-health',
        'name' => 'Агент надзора система',
        'status' => 'greska',
        'status_label' => 'ГРЕШКА',
        'timestamp' => gmdate('c'),
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
