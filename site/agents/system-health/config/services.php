<?php
declare(strict_types=1);

/*
 * Само конфигурација провера.
 * Не мења ниједан постојећи сервис.
 *
 * freshness:
 *   null       = не процењује свежину, само доступност
 *   max_age_s  = максимална прихватљива старост извора
 *
 * date_keys су могућа поља из JSON-а. Агент их тражи рекурзивно.
 */
return [
    [
        'id' => 'kustos',
        'name' => 'Кустос — главни координатор система',
        'url' => 'https://piorajac.rs/rajac-ai/agents/command/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => true,
    ],
    [
        'id' => 'ams',
        'name' => 'Метеоролошки агент / АМС Рајац',
        'url' => 'https://piorajac.rs/amsrajac/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => true,
    ],
    [
        'id' => 'fire',
        'name' => 'Пожарни агент',
        'url' => 'https://piorajac.rs/pozarna-opasnost/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => true,
    ],
    [
        'id' => 'vegetation',
        'name' => 'Агент сателитског мониторинга — вегетација',
        'url' => 'https://piorajac.rs/vegetacija-kopernikus/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
    ],
    [
        'id' => 'vegetation-data',
        'name' => 'Copernicus — индекс резултата',
        'url' => 'https://raw.githubusercontent.com/suvoborskirajac/rajac-vegetation-data/main/public/results/index.json',
        'kind' => 'json',
        'timeout_s' => 10,
        'critical' => true,
        'freshness' => [
            'max_age_s' => 45 * 86400,
            'date_keys' => ['updated_at','generated_at','latest','date','timestamp'],
        ],
    ],
    [
        'id' => 'forest',
        'name' => 'Агент мониторинга шума',
        'url' => 'https://piorajac.rs/kopindeksi/forest-monitor/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
    ],
    [
        'id' => 'forest-data',
        'name' => 'Forest Monitor — индекс резултата',
        'url' => 'https://raw.githubusercontent.com/suvoborskirajac/rajac-vegetation-data/main/public/results-forests/index.json',
        'kind' => 'json',
        'timeout_s' => 10,
        'critical' => false,
        'freshness' => [
            'max_age_s' => 45 * 86400,
            'date_keys' => ['updated_at','generated_at','latest','date','timestamp'],
        ],
    ],
    [
        'id' => 'agro',
        'name' => 'Agro Copernicus',
        'url' => 'https://piorajac.rs/agro-kopernikus/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
    ],
    [
        'id' => 'agro-data',
        'name' => 'Agro Copernicus — дневни индекс',
        'url' => 'https://raw.githubusercontent.com/suvoborskirajac/rajac-agro-data/main/public/daily/index.json',
        'kind' => 'json',
        'timeout_s' => 10,
        'critical' => false,
        'freshness' => [
            'max_age_s' => 10 * 86400,
            'date_keys' => ['updated_at','generated_at','latest','date','timestamp'],
        ],
    ],
    [
        'id' => 'dark-sky',
        'name' => 'Агент природног мрака и светлосног загађења',
        'url' => 'https://piorajac.rs/monitoring-svetlosno-zagadjenje/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
    ],
    [
        'id' => 'biodiversity',
        'name' => 'Агент биодиверзитета — извештаји',
        'url' => 'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
    ],
    [
        'id' => 'reports',
        'name' => 'Агент извештавања — архива',
        'url' => 'https://piorajac.rs/monitoring-izvestaji/',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
    ],
    [
        'id' => 'vision',
        'name' => 'Агент визуелног надзора',
        'url' => 'https://piorajac.rs/ai-nadzor/home.php',
        'kind' => 'page',
        'timeout_s' => 8,
        'critical' => false,
        'allow_auth_redirect' => true,
    ],
];
