<?php
declare(strict_types=1);

const RAJAC_SH_VERSION = '1.0.0';

function rajac_sh_root(): string {
    return __DIR__;
}

function rajac_sh_private_dir(): string {
    $preferred = '/home/piorajac/rajac-ai/data/system-health';
    if (is_dir('/home/piorajac') || @mkdir($preferred, 0775, true)) {
        if (is_dir($preferred) && is_writable($preferred)) return $preferred;
    }
    $fallback = __DIR__ . '/data';
    if (!is_dir($fallback)) @mkdir($fallback, 0775, true);
    return $fallback;
}

function rajac_sh_now(): string {
    return gmdate('c');
}

function rajac_sh_http(string $url, int $timeout = 8): array {
    $start = microtime(true);
    $body = '';
    $code = 0;
    $err = null;
    $headers = [];
    $effective = $url;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'PIO-Rajac-System-Health/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => false,
        ]);
        $body = (string)curl_exec($ch);
        $errNo = curl_errno($ch);
        if ($errNo) $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "User-Agent: PIO-Rajac-System-Health/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $tmp = @file_get_contents($url, false, $ctx);
        if ($tmp === false) {
            $err = 'HTTP fetch није успео';
            $body = '';
        } else {
            $body = (string)$tmp;
        }
        if (isset($http_response_header) && is_array($http_response_header)) {
            $headers = $http_response_header;
            foreach ($headers as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~', $h, $m)) $code = (int)$m[1];
            }
        }
    }

    $elapsedMs = (int)round((microtime(true) - $start) * 1000);
    return [
        'url' => $url,
        'effective_url' => $effective,
        'http_code' => $code,
        'response_ms' => $elapsedMs,
        'body' => $body,
        'error' => $err,
    ];
}

function rajac_sh_find_key($data, array $keys) {
    if (!is_array($data)) return null;
    foreach ($keys as $k) {
        if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') return $data[$k];
    }
    foreach ($data as $v) {
        if (is_array($v)) {
            $found = rajac_sh_find_key($v, $keys);
            if ($found !== null) return $found;
        }
    }
    return null;
}

function rajac_sh_parse_date($value): ?int {
    if ($value === null) return null;
    if (is_int($value) || is_float($value)) {
        $n = (int)$value;
        if ($n > 1000000000000) $n = (int)round($n / 1000);
        return ($n > 1000000000) ? $n : null;
    }
    if (!is_string($value)) return null;
    $s = trim($value);
    if ($s === '') return null;

    if (preg_match('/^\d{4}-\d{2}$/', $s)) {
        $t = strtotime($s . '-01T00:00:00Z');
        return $t === false ? null : $t;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        $t = strtotime($s . 'T00:00:00Z');
        return $t === false ? null : $t;
    }
    $t = strtotime($s);
    return $t === false ? null : $t;
}

function rajac_sh_semantic_page_check(string $body): array {
    $text = mb_strtolower(strip_tags($body), 'UTF-8');
    $fatal = [
        '500 internal server error',
        '504 gateway time-out',
        '504 gateway timeout',
        'fatal error',
        'uncaught exception',
        'database connection error',
    ];
    foreach ($fatal as $needle) {
        if (mb_strpos($text, $needle, 0, 'UTF-8') !== false) {
            return ['ok' => false, 'reason' => 'Страница садржи сигнал серверске грешке: ' . $needle];
        }
    }
    return ['ok' => true, 'reason' => null];
}

function rajac_sh_check_service(array $svc): array {
    $timeout = (int)($svc['timeout_s'] ?? 8);
    $http = rajac_sh_http((string)$svc['url'], $timeout);
    $code = (int)$http['http_code'];
    $reachable = $code >= 200 && $code < 400 && $http['error'] === null;

    $status = 'radi';
    $statusLabel = 'РАДИ';
    $reason = null;
    $fresh = null;
    $dataTime = null;
    $dataAge = null;

    if (!$reachable) {
        $status = 'greska';
        $statusLabel = 'ГРЕШКА';
        $reason = $http['error'] ?: ('HTTP ' . $code);
    } else {
        $kind = (string)($svc['kind'] ?? 'page');
        if ($kind === 'json') {
            $json = json_decode($http['body'], true);
            if (!is_array($json)) {
                $status = 'delimicno_radi';
                $statusLabel = 'ДЕЛИМИЧНО РАДИ';
                $reason = 'URL одговара, али садржај није валидан JSON.';
            } else {
                $freshness = $svc['freshness'] ?? null;
                if (is_array($freshness)) {
                    $keys = $freshness['date_keys'] ?? ['updated_at','generated_at','latest','date','timestamp'];
                    $raw = rajac_sh_find_key($json, $keys);
                    $ts = rajac_sh_parse_date($raw);
                    if ($ts !== null) {
                        $dataTime = gmdate('c', $ts);
                        $dataAge = max(0, time() - $ts);
                        $max = (int)($freshness['max_age_s'] ?? 0);
                        $fresh = $max <= 0 ? null : ($dataAge <= $max);
                        if ($fresh === false) {
                            $status = 'radi_podatak_kasni';
                            $statusLabel = 'РАДИ — ПОДАТАК КАСНИ';
                            $reason = 'Извор је доступан, али је последњи препознат податак старији од дозвољеног прага.';
                        }
                    } else {
                        $fresh = null;
                        if (!empty($freshness['required'])) {
                            $status = 'delimicno_radi';
                            $statusLabel = 'ДЕЛИМИЧНО РАДИ';
                            $reason = 'JSON ради, али није пронађено време последњег податка.';
                        }
                    }
                }
            }
        } else {
            $sem = rajac_sh_semantic_page_check($http['body']);
            if (!$sem['ok']) {
                $status = 'delimicno_radi';
                $statusLabel = 'ДЕЛИМИЧНО РАДИ';
                $reason = $sem['reason'];
            }
        }
    }

    if ($reachable && $http['response_ms'] > 5000 && $status === 'radi') {
        $status = 'delimicno_radi';
        $statusLabel = 'ДЕЛИМИЧНО РАДИ';
        $reason = 'Сервис ради, али је одзив спорији од 5 секунди.';
    }

    return [
        'id' => (string)$svc['id'],
        'name' => (string)$svc['name'],
        'status' => $status,
        'status_label' => $statusLabel,
        'critical' => (bool)($svc['critical'] ?? false),
        'url' => (string)$svc['url'],
        'effective_url' => $http['effective_url'],
        'http_code' => $code,
        'response_ms' => $http['response_ms'],
        'checked_at' => rajac_sh_now(),
        'data_time' => $dataTime,
        'data_age_s' => $dataAge,
        'fresh' => $fresh,
        'reason' => $reason,
    ];
}

function rajac_sh_severity_rank(string $status): int {
    return match ($status) {
        'greska' => 4,
        'delimicno_radi' => 3,
        'radi_podatak_kasni' => 2,
        'radi' => 1,
        default => 0,
    };
}

function rajac_sh_run(): array {
    $services = require __DIR__ . '/config/services.php';
    $results = [];
    foreach ($services as $svc) {
        $results[] = rajac_sh_check_service($svc);
    }

    $counts = [
        'radi' => 0,
        'radi_podatak_kasni' => 0,
        'delimicno_radi' => 0,
        'greska' => 0,
        'nepoznato' => 0,
    ];
    foreach ($results as $r) {
        $k = $r['status'];
        if (!isset($counts[$k])) $counts['nepoznato']++;
        else $counts[$k]++;
    }

    $criticalFailures = array_values(array_filter($results, fn($r) =>
        !empty($r['critical']) && in_array($r['status'], ['greska','delimicno_radi'], true)
    ));

    $overall = 'radi';
    $overallLabel = 'РАДИ';
    if ($criticalFailures) {
        $overall = 'greska';
        $overallLabel = 'ГРЕШКА';
    } elseif ($counts['greska'] > 0 || $counts['delimicno_radi'] > 0) {
        $overall = 'delimicno_radi';
        $overallLabel = 'ДЕЛИМИЧНО РАДИ';
    } elseif ($counts['radi_podatak_kasni'] > 0) {
        $overall = 'radi_podatak_kasni';
        $overallLabel = 'РАДИ — ПОДАТАК КАСНИ';
    }

    $alerts = [];
    foreach ($results as $r) {
        if (rajac_sh_severity_rank($r['status']) >= 2) {
            $alerts[] = [
                'service_id' => $r['id'],
                'service_name' => $r['name'],
                'status' => $r['status'],
                'message' => $r['reason'] ?: $r['status_label'],
                'priority' => $r['critical'] ? 'visok' : 'srednji',
            ];
        }
    }

    $facts = [
        'Проверено сервиса: ' . count($results),
        'Исправно: ' . $counts['radi'],
        'Кашњење податка: ' . $counts['radi_podatak_kasni'],
        'Делимично ради: ' . $counts['delimicno_radi'],
        'Грешка: ' . $counts['greska'],
    ];

    return [
        'schema_version' => '1.0',
        'agent' => 'system-health',
        'name' => 'Агент надзора система',
        'status' => $overall,
        'status_label' => $overallLabel,
        'timestamp' => rajac_sh_now(),
        'data_age_s' => 0,
        'sources' => array_map(fn($r) => [
            'id' => $r['id'],
            'name' => $r['name'],
            'url' => $r['url'],
        ], $results),
        'facts' => $facts,
        'analysis' => $alerts
            ? 'Постоје сервиси који захтевају пажњу. Статус странице, свежина податка и успешност обраде треба тумачити одвојено.'
            : 'Проверени сервиси су доступни у оквиру дефинисаних прагова.',
        'confidence' => 1.0,
        'alerts' => $alerts,
        'recommended_checks' => array_map(fn($a) =>
            'Проверити: ' . $a['service_name'] . ' — ' . $a['message'], $alerts),
        'counts' => $counts,
        'services' => $results,
        'version' => RAJAC_SH_VERSION,
    ];
}

function rajac_sh_save(array $snapshot): array {
    $dir = rajac_sh_private_dir();
    $latest = $dir . '/latest.json';
    $history = $dir . '/history.jsonl';

    $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('JSON encode није успео.');

    $tmp = $latest . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Не могу да упишем latest.json.');
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $latest)) {
        @unlink($tmp);
        throw new RuntimeException('Не могу да заменим latest.json.');
    }
    @chmod($latest, 0640);

    $line = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (is_string($line)) {
        @file_put_contents($history, $line . "\n", FILE_APPEND | LOCK_EX);
        @chmod($history, 0640);
    }

    return ['dir' => $dir, 'latest' => $latest, 'history' => $history];
}

function rajac_sh_load_latest(): ?array {
    $file = rajac_sh_private_dir() . '/latest.json';
    if (!is_file($file)) return null;
    $raw = @file_get_contents($file);
    if (!is_string($raw) || $raw === '') return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}
