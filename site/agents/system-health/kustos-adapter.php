<?php
declare(strict_types=1);

/*
 * Адаптер је само за касније укључивање у Кустоса.
 * Овај пакет НЕ мења постојећи command agent.
 */
function rajac_kustos_system_health_snapshot(): array {
    $candidate = '/home/piorajac/rajac-ai/data/system-health/latest.json';
    if (!is_file($candidate)) {
        return [
            'agent' => 'system-health',
            'name' => 'Агент надзора система',
            'status' => 'nepoznato',
            'status_label' => 'НЕПОЗНАТО',
            'analysis' => 'Нема снимка Агентa надзора система.',
            'services' => [],
            'alerts' => [],
            'recommended_checks' => [],
        ];
    }
    $raw = @file_get_contents($candidate);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [
        'agent' => 'system-health',
        'name' => 'Агент надзора система',
        'status' => 'greska',
        'status_label' => 'ГРЕШКА',
        'analysis' => 'latest.json није валидан.',
        'services' => [],
        'alerts' => [],
        'recommended_checks' => [],
    ];
}
