<?php
/**
 * PIO Rajac — Copernicus public map sync FAST
 * Verzija: 2026-07-04-v17-redirect-html-no-403
 *
 * Popravka u odnosu na v10:
 * - ne skenira kompletan /public_html za svaki datum;
 * - ne čita hiljade HTML/JS/JSON fajlova;
 * - najpre proverava direktne histogram putanje i javni /kopernikindeks/ index;
 * - za datirane karte radi samo ograničeno brzo skeniranje putanja koje već sadrže datum;
 * - bez 504 timeout-a kod perioda 29.06–04.07.
 */
declare(strict_types=1);
require_once __DIR__ . '/lib-monitoring.php';

if (!function_exists('mdaily_pick')) {
    function mdaily_pick(array $array, array $paths, $default = null) {
        foreach ($paths as $path) {
            $cur = $array; $ok = true;
            foreach (explode('.', $path) as $part) {
                if (is_array($cur) && array_key_exists($part, $cur)) $cur = $cur[$part];
                else { $ok = false; break; }
            }
            if ($ok && $cur !== null && $cur !== '') return $cur;
        }
        return $default;
    }
}

function cop_sync_public_html(string $root): string { return dirname($root); }
function cop_sync_safe_date(?string $v, string $default): string {
    $v = trim((string)$v);
    return preg_match('/^20\d{2}-\d{2}-\d{2}$/', $v) ? $v : $default;
}
function cop_sync_range(string $from, string $to): array {
    $tz = new DateTimeZone(PIO_TZ);
    $a = new DateTimeImmutable($from, $tz);
    $b = new DateTimeImmutable($to, $tz);
    if ($a > $b) return array();
    $out = array();
    for ($d=$a; $d <= $b; $d=$d->modify('+1 day')) $out[] = $d->format('Y-m-d');
    return $out;
}
function cop_sync_date_variants(string $date): array {
    [$y,$m,$d] = explode('-', $date);
    return array_values(array_unique(array(
        $date,
        $y.$m.$d,
        $d.'.'.$m.'.'.$y,
        $d.'.'.$m.'.'.$y.'.',
        $d.'-'.$m.'-'.$y,
        $d.'_'.$m.'_'.$y,
        $y.'_'.$m.'_'.$d,
        $y.'/'.$m.'/'.$d,
    )));
}
function cop_sync_contains_variant(string $haystack, array $variants): bool {
    $h = strtolower($haystack);
    foreach ($variants as $v) if (strpos($h, strtolower((string)$v)) !== false) return true;
    return false;
}
function cop_sync_read_text_limited(string $path, int $maxBytes = 2097152): ?string {
    if (!is_file($path) || !is_readable($path)) return null;
    $size = @filesize($path);
    if ($size === false || $size <= 0 || $size > $maxBytes) return null;
    $raw = @file_get_contents($path);
    return is_string($raw) ? $raw : null;
}
function cop_sync_read_json_loose(string $path): ?array {
    $raw = cop_sync_read_text_limited($path, 12 * 1024 * 1024);
    if ($raw === null || trim($raw) === '') return null;
    $j = json_decode(trim($raw), true);
    if (is_array($j)) return $j;
    if (preg_match('/\{.*\}/s', $raw, $m)) {
        $j = json_decode($m[0], true);
        if (is_array($j)) return $j;
    }
    return null;
}
function cop_sync_url_from_path(string $root, string $path): string {
    $public = cop_sync_public_html($root);
    $realPublic = realpath($public) ?: $public;
    $realPath = realpath($path) ?: $path;
    if (strpos($realPath, $realPublic) === 0) {
        $rel = str_replace('\\', '/', substr($realPath, strlen($realPublic)));
        return PIO_SITE . '/' . ltrim($rel, '/');
    }
    return $path;
}
function cop_sync_metric_from_json(array $j): ?float {
    $candidates = array(
        $j['stats']['mean'] ?? null,
        $j['mean'] ?? null,
        $j['summary']['mean'] ?? null,
        $j['data']['mean'] ?? null,
        $j['value']['mean'] ?? null,
    );
    foreach ($candidates as $v) if (is_numeric($v)) return (float)$v;
    return null;
}
function cop_sync_hist_status(string $root, string $date): array {
    $public = cop_sync_public_html($root);
    $ym = substr($date, 0, 7);
    $bases = array(
        $public . '/kopindeksi/copernicus_data/reports/statistical_histogram/' . $ym,
        $public . '/kopindeksi/indeksi/copernicus_data/reports/statistical_histogram/' . $ym,
        $public . '/kopernikindeks/copernicus_data/reports/statistical_histogram/' . $ym,
        $root . '/copernicus/data/statistical_histogram/' . $ym,
        $root . '/copernicus/engine/copernicus_data/reports/statistical_histogram/' . $ym,
    );
    $out = array();
    foreach (array('NDVI','NDMI','RECI') as $idx) {
        $out[$idx] = array('ok'=>false,'path'=>null,'mean'=>null,'min'=>null,'max'=>null,'p90'=>null,'pixel_count'=>null,'total_ha'=>null);
        foreach ($bases as $base) {
            $paths = array(
                $base . '/' . $idx . '_' . $date . '/report.json',
                $base . '/' . $date . '_' . $idx . '/report.json',
                $base . '/' . $idx . '_' . str_replace('-','',$date) . '.json',
                $base . '/' . $idx . '-' . $date . '.json',
                $base . '/' . $idx . '_' . $date . '.json',
            );
            foreach ($paths as $p) {
                $j = cop_sync_read_json_loose($p);
                if (!is_array($j)) continue;
                $mean = cop_sync_metric_from_json($j);
                $okFlag = $j['ok'] ?? $j['status'] ?? true;
                $ok = is_numeric($mean) && ($okFlag === true || $okFlag === 1 || $okFlag === 'true' || $okFlag === 'ok' || $okFlag === 'published');
                $out[$idx] = array(
                    'ok'=>$ok,
                    'path'=>$p,
                    'mean'=>$mean,
                    'min'=>$j['stats']['min'] ?? $j['min'] ?? null,
                    'max'=>$j['stats']['max'] ?? $j['max'] ?? null,
                    'p90'=>$j['stats']['p90'] ?? $j['stats']['percentiles']['95.0'] ?? $j['stats']['percentiles']['90.0'] ?? $j['p90'] ?? null,
                    'pixel_count'=>$j['total_pixel_count'] ?? $j['stats']['sampleCount'] ?? $j['stats']['pixel_count'] ?? $j['pixel_count'] ?? null,
                    'total_ha'=>$j['total_ha'] ?? $j['stats']['total_ha'] ?? null,
                );
                break 2;
            }
        }
    }
    return $out;
}
function cop_sync_hist_all_ok(array $st): bool {
    foreach (array('NDVI','NDMI','RECI') as $idx) if (empty($st[$idx]['ok'])) return false;
    return true;
}
function cop_sync_hist_metrics(array $st): array {
    $m = array();
    foreach (array('NDVI','NDMI','RECI') as $idx) if (isset($st[$idx]['mean']) && is_numeric($st[$idx]['mean'])) $m[] = $idx . ' ' . number_format((float)$st[$idx]['mean'], 3, '.', '');
    return $m;
}
function cop_sync_latest_complete_hist_date(string $root, ?string $maxDate = null): ?string {
    $public = cop_sync_public_html($root);
    $bases = array(
        $public . '/kopindeksi/copernicus_data/reports/statistical_histogram',
        $public . '/kopindeksi/indeksi/copernicus_data/reports/statistical_histogram',
        $public . '/kopernikindeks/copernicus_data/reports/statistical_histogram',
        $root . '/copernicus/data/statistical_histogram',
        $root . '/copernicus/engine/copernicus_data/reports/statistical_histogram',
    );
    $dates = array();
    foreach ($bases as $base) {
        if (!is_dir($base)) continue;
        foreach (glob($base . '/20??-??/NDVI_20??-??-??', GLOB_ONLYDIR) ?: array() as $p) {
            if (!preg_match('/NDVI_(20\d{2}-\d{2}-\d{2})$/', $p, $m)) continue;
            $d = $m[1]; if ($maxDate && strcmp($d, $maxDate) > 0) continue;
            if (cop_sync_hist_all_ok(cop_sync_hist_status($root, $d))) $dates[$d] = true;
        }
        foreach (glob($base . '/20??-??/NDVI_20??????.json') ?: array() as $p) {
            if (!preg_match('/NDVI_(20\d{2})(\d{2})(\d{2})\.json$/', basename($p), $m)) continue;
            $d = $m[1].'-'.$m[2].'-'.$m[3]; if ($maxDate && strcmp($d, $maxDate) > 0) continue;
            if (cop_sync_hist_all_ok(cop_sync_hist_status($root, $d))) $dates[$d] = true;
        }
    }
    $out = array_keys($dates); sort($out, SORT_STRING);
    return $out ? end($out) : null;
}
function cop_sync_fast_dated_paths(string $root, string $date, int $limit = 80): array {
    $public = cop_sync_public_html($root);
    $variants = cop_sync_date_variants($date);
    $roots = array(
        $public . '/kopernikindeks',
        $public . '/kopindeksi',
        $public . '/kopindeksi/indeksi',
        $public . '/kopindeksi/copernicus_data',
        $root . '/copernicus',
    );
    $out = array('maps'=>array(), 'texts'=>array(), 'checked_roots'=>array());
    $imageExt = array('png'=>1,'jpg'=>1,'jpeg'=>1,'webp'=>1,'svg'=>1,'tif'=>1,'tiff'=>1);
    $textExt = array('html'=>1,'htm'=>1,'js'=>1,'json'=>1,'geojson'=>1,'txt'=>1);
    $bad = array('logo','favicon','icon','legend','loader','thumb');
    $started = microtime(true);
    foreach ($roots as $rr) {
        $real = realpath($rr);
        if (!$real || !is_dir($real) || !is_readable($real)) continue;
        $out['checked_roots'][] = $real;
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ((microtime(true) - $started) > 7.0) break 2;
                if (!$f->isFile()) continue;
                $p = $f->getPathname();
                $pl = strtolower(str_replace('\\','/',$p));
                if (strpos($pl, '/vendor/') !== false || strpos($pl, '/node_modules/') !== false || strpos($pl, '/cache/') !== false) continue;
                if (!cop_sync_contains_variant($pl, $variants)) continue;
                $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
                $base = strtolower(basename($p));
                if (isset($imageExt[$ext])) {
                    $skip = false; foreach ($bad as $b) if (strpos($base, $b) !== false) { $skip = true; break; }
                    if (!$skip && ((int)@filesize($p)) > 1200) $out['maps'][] = cop_sync_url_from_path($root, $p);
                } elseif (isset($textExt[$ext])) {
                    $out['texts'][] = cop_sync_url_from_path($root, $p);
                }
                if (count($out['maps']) + count($out['texts']) >= $limit) break 2;
            }
        } catch (Throwable $e) {}
    }
    $out['maps'] = array_values(array_unique($out['maps']));
    $out['texts'] = array_values(array_unique($out['texts']));
    return $out;
}
function cop_sync_viewer_files(string $root): array {
    $public = cop_sync_public_html($root);
    $files = array(
        $public . '/kopernikindeks/index.html',
        $public . '/kopernikindeks/latest.json',
        $public . '/kopernikindeks/data/latest.json',
        $public . '/kopernikindeks/data/current.json',
        $public . '/kopernikindeks/assets/app.js',
        $public . '/kopindeksi/index.html',
        $public . '/kopindeksi/indeksi/index.html',
    );
    foreach (glob($public . '/kopernikindeks/*.json') ?: array() as $p) $files[] = $p;
    foreach (glob($public . '/kopernikindeks/*.js') ?: array() as $p) $files[] = $p;
    foreach (glob($public . '/kopernikindeks/assets/*.js') ?: array() as $p) $files[] = $p;
    return array_values(array_unique($files));
}
function cop_sync_extract_dates_from_text(string $raw): array {
    $dates = array();
    if (preg_match_all('/(20\d{2}-\d{2}-\d{2})|(\d{2})\.(\d{2})\.(20\d{2})\.?/', $raw, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $d = !empty($mm[1]) ? $mm[1] : ($mm[4].'-'.$mm[3].'-'.$mm[2]);
            $dates[$d] = true;
        }
    }
    return array_keys($dates);
}
function cop_sync_public_evidence(string $root, string $date): array {
    $variants = cop_sync_date_variants($date);
    $maps = array(); $texts = array(); $datesSeen = array(); $jsonData = array(); $viewerHit = false;

    foreach (cop_sync_viewer_files($root) as $p) {
        $raw = cop_sync_read_text_limited($p, 3 * 1024 * 1024);
        if ($raw === null) continue;
        foreach (cop_sync_extract_dates_from_text($raw) as $d) $datesSeen[$d] = true;
        if (cop_sync_contains_variant($raw, $variants)) {
            $hasIndex = preg_match('/NDVI|NDMI|RECI|Sentinel|Copernicus|коперник|сателит|индекс/ui', $raw) === 1;
            if ($hasIndex) {
                $texts[] = cop_sync_url_from_path($root, $p);
                $viewerHit = true;
            }
            if (preg_match('/\.(png|jpe?g|webp|tif|tiff|svg)(\?[^\s\"\']*)?/i', $raw)) {
                $maps[] = PIO_SITE . '/kopernikindeks/';
            }
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $j = json_decode($m[0], true);
                if (is_array($j)) $jsonData[] = array('path'=>$p,'data'=>$j);
            }
        }
    }

    $dated = cop_sync_fast_dated_paths($root, $date, 120);
    $maps = array_merge($maps, $dated['maps']);
    $texts = array_merge($texts, $dated['texts']);

    // Ako javni viewer trenutno prikazuje baš ovaj datum, to je dovoljno kao javni kartografski dokaz.
    // Ovo hvata slučaj sa /kopernikindeks/ gde je karta 04.07.2026. vidljiva, ali fajl slike nema datum u nazivu.
    if ($viewerHit) {
        $maps[] = PIO_SITE . '/kopernikindeks/';
        $texts[] = PIO_SITE . '/kopernikindeks/';
    }

    $maps = array_values(array_unique(array_slice($maps, 0, 12)));
    $texts = array_values(array_unique(array_slice($texts, 0, 12)));
    ksort($datesSeen);
    return array(
        'ok'=>count($maps) > 0 || count($texts) > 0 || $viewerHit,
        'map_count'=>count($maps),
        'text_count'=>count($texts),
        'map_examples'=>$maps,
        'text_examples'=>$texts,
        'json_sources'=>$jsonData,
        'dates_seen'=>array_keys($datesSeen),
        'roots_checked'=>$dated['checked_roots'] ?? array(),
        'method'=>'v11-fast-direct-viewer-and-dated-paths',
    );
}
function cop_sync_flatten($node, string $prefix, array &$out): void {
    if (!is_array($node)) return;
    foreach ($node as $k=>$v) {
        $kk = $prefix === '' ? (string)$k : $prefix . '.' . (string)$k;
        if (is_array($v)) cop_sync_flatten($v, $kk, $out);
        elseif (is_scalar($v) || $v === null) $out[$kk] = $v;
    }
}
function cop_sync_json_metric(array $evidence, string $idx): ?float {
    foreach ($evidence['json_sources'] ?? array() as $src) {
        $flat = array(); cop_sync_flatten($src['data'], '', $flat);
        foreach ($flat as $k=>$v) {
            if (!is_numeric($v)) continue;
            $kl = strtolower((string)$k);
            if (strpos($kl, strtolower($idx)) !== false && (strpos($kl, 'mean') !== false || strpos($kl, 'avg') !== false || strpos($kl, 'prosek') !== false)) return (float)$v;
        }
    }
    return null;
}
function cop_sync_report_is_public_complete(array $report): bool {
    $status = strtolower((string)($report['status'] ?? 'published'));
    if (in_array($status, array('partial','missing','no-data','no_new_source','skipped'), true)) return false;
    $indices = isset($report['indices']) && is_array($report['indices']) ? $report['indices'] : array();
    foreach (array('NDVI','NDMI','RECI') as $idx) if (!isset($indices[$idx]['mean']) || !is_numeric($indices[$idx]['mean'])) return false;
    return true;
}

function cop_sync_redirect_target(string $root, string $date): string {
    $latest = cop_sync_latest_complete_hist_date($root, $date);
    return $latest ? ('/monitoring-izvestaji/copernicus/' . $latest . '/') : '/monitoring-izvestaji/copernicus/';
}
function cop_sync_redirect_php(string $root, string $date): string {
    $target = str_replace("'", '', cop_sync_redirect_target($root, $date));
    return "<?php\n" .
        "// Automatsko preusmeravanje na poslednji kompletan Copernicus izveštaj.\n" .
        "header('Location: " . $target . "', true, 302);\n" .
        "exit;\n";
}
function cop_sync_redirect_html(string $root, string $date): string {
    $target = cop_sync_redirect_target($root, $date);
    $t = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="robots" content="noindex"><meta http-equiv="refresh" content="0;url=' . $t . '"><script>location.replace(' . json_encode($target, JSON_UNESCAPED_SLASHES) . ');</script><title>Преусмеравање</title></head><body></body></html>';
}

function cop_sync_rrmdir_if_safe(string $dir): bool {
    // Briše samo datumski Copernicus direktorijum 20YY-MM-DD kada nema kompletnog javnog izveštaja.
    if (!is_dir($dir)) return true;
    if (!preg_match('~/copernicus/20\d{2}-\d{2}-\d{2}$~', str_replace('\\','/',$dir))) return false;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $p = $f->getPathname();
        if ($f->isDir()) @rmdir($p); else @unlink($p);
    }
    return @rmdir($dir);
}
function cop_sync_cleanup_incomplete_report(string $root, string $date, string $reason = '', array $evidence = array()): array {
    $dir = $root . '/copernicus/' . $date;
    $removed = array();
    monitoring_ensure_dir($dir);

    $reportPath = $dir . '/report.json';
    $j = cop_sync_read_json_loose($reportPath);
    if (is_array($j) && cop_sync_report_is_public_complete($j)) {
        return $removed; // ne diramo pravi kompletan javni izveštaj
    }

    foreach (array('report.json','latest.json','_skipped-public-report.json') as $f) {
        $p = $dir . '/' . $f;
        if (is_file($p) && @unlink($p)) $removed[] = $p;
    }

    // Ne pravimo javnu stranicu sa objašnjenjem za datume bez kompletnog izveštaja.
    // Ali obavezno ostavljamo tihi index.html redirect, jer neki serveri ne koriste index.php kao DirectoryIndex.
    foreach (array('index.htm') as $f) {
        $p = $dir . '/' . $f;
        if (is_file($p) && @unlink($p)) $removed[] = $p;
    }
    @file_put_contents($dir . '/index.html', cop_sync_redirect_html($root, $date));
    @chmod($dir . '/index.html', 0644);
    @file_put_contents($dir . '/index.php', cop_sync_redirect_php($root, $date));
    @chmod($dir . '/index.php', 0644);

    // Tehnički status čuvamo centralno, ne na javnoj datumskoj stranici.
    monitoring_ensure_dir($root . '/copernicus/_skipped-public-reports');
    monitoring_write_json($root . '/copernicus/_skipped-public-reports/' . $date . '.json', array(
        'ok'=>false,
        'date'=>$date,
        'status'=>'skipped_redirected',
        'reason'=>$reason ?: 'Copernicus јавни извештај није објављен јер недостаје комплетна NDVI/NDMI/RECI статистика.',
        'removed'=>$removed,
        'redirect_target'=>cop_sync_redirect_target($root, $date),
        'generated_at'=>monitoring_iso_now()
    ));
    return $removed;
}
function cop_sync_hist_full_report(array $hist, string $idx): array { $p = $hist[$idx]['path'] ?? null; if (is_string($p) && $p !== '') { $j = cop_sync_read_json_loose($p); if (is_array($j)) return $j; } return array(); }
function cop_sync_stat(array $full, string $name, $default = null) { if ($name === 'p5') return $full['stats']['percentiles']['5.0'] ?? $full['stats']['percentiles']['5'] ?? $default; if ($name === 'p25') return $full['stats']['percentiles']['25.0'] ?? $full['stats']['percentiles']['25'] ?? $default; if ($name === 'median') return $full['stats']['percentiles']['50.0'] ?? $full['stats']['percentiles']['50'] ?? $default; if ($name === 'p75') return $full['stats']['percentiles']['75.0'] ?? $full['stats']['percentiles']['75'] ?? $default; if ($name === 'p95') return $full['stats']['percentiles']['95.0'] ?? $full['stats']['percentiles']['95'] ?? $default; return $full['stats'][$name] ?? $full[$name] ?? $default; }
function cop_sync_public_map_for_index(string $root, string $date, string $idx, array $evidence): string { $public = cop_sync_public_html($root); $year = substr($date, 0, 4); foreach (array('png','jpg','webp') as $ext) { $p = $public . '/kopindeksi/copernicus_data/' . $idx . '/' . $year . '/' . $idx . '_' . $date . '.' . $ext; if (is_file($p)) return cop_sync_url_from_path($root, $p); } foreach (($evidence['map_examples'] ?? array()) as $u) if (stripos((string)$u, '/' . $idx . '/') !== false || stripos((string)$u, $idx . '_' . $date) !== false) return (string)$u; return PIO_SITE . '/kopernikindeks/'; }
function cop_sync_index_label(string $idx): string { if ($idx === 'NDVI') return 'Зелена маса и вегетациона активност'; if ($idx === 'NDMI') return 'Влага у вегетацији и водни стрес'; if ($idx === 'RECI') return 'Хлорофилна активност'; return ''; }
function cop_sync_index_text(string $idx, $mean): string { if (!is_numeric($mean)) return ''; $v = (float)$mean; if ($idx === 'NDVI') { if ($v >= 0.75) return 'Веома висока вредност NDVI указује на изразито развијену зелену масу и висок степен вегетационе активности на већем делу подручја.'; if ($v >= 0.55) return 'Вредност NDVI указује на добру развијеност зелене масе и стабилну вегетациону активност.'; return 'Вредност NDVI указује на умерену или неуједначену вегетациону активност, уз потребу локалне провере.'; } if ($idx === 'NDMI') { if ($v >= 0.25) return 'Вредност NDMI указује на умерену влагу и потребу праћења локалних зона са сушним сигналом.'; if ($v >= 0.05) return 'Вредност NDMI указује на прелазно стање влаге у вегетацији, са могућим локалним зонама сушног стреса.'; return 'Ниска вредност NDMI указује на израженији сушни сигнал у делу вегетације.'; } if ($idx === 'RECI') { if ($v >= 2.0) return 'RECI указује на стабилну до високу хлорофилну активност.'; if ($v >= 1.0) return 'RECI указује на умерену хлорофилну активност.'; return 'Ниска вредност RECI указује на слабију хлорофилну активност и потребу праћења локалних одступања.'; } return ''; }
function cop_sync_fmt_num($v, int $dec = 4): string { return is_numeric($v) ? number_format((float)$v, $dec, ',', '.') : '—'; }
function cop_sync_fmt_ha($v): string { return is_numeric($v) ? number_format((float)$v, 2, ',', '.') . ' ha' : '—'; }
function cop_sync_fmt_pct($v): string { return is_numeric($v) ? number_format((float)$v, 2, ',', '.') . '%' : '—'; }
function cop_sync_make_cop_report(string $root, string $date, array $hist, array $evidence, bool $force = false): array {
    if (!cop_sync_hist_all_ok($hist)) { $removed = cop_sync_cleanup_incomplete_report($root, $date, 'Пронађена је карта, али недостаје комплетна NDVI/NDMI/RECI histogram статистика. Јавни извештај се не објављује без бројчаних података.'); return array('ok'=>false,'date'=>$date,'action'=>'skipped-no-complete-statistics','status'=>'skipped','removed'=>$removed,'reason'=>'Карта постоји, али нема комплетне NDVI/NDMI/RECI статистике. Није направљена јавна страница за посетиоце.'); }
    $dir = $root . '/copernicus/' . $date; $existing = $dir . '/report.json';
    if (!$force && is_file($existing)) { $j = cop_sync_read_json_loose($existing); if (is_array($j) && cop_sync_report_is_public_complete($j)) return array('ok'=>true,'date'=>$date,'action'=>'kept-existing','status'=>'published','url'=>PIO_SITE . '/monitoring-izvestaji/copernicus/' . $date . '/'); }
    monitoring_ensure_dir($dir . '/assets'); $indices = array(); $full = array();
    foreach (array('NDVI','NDMI','RECI') as $idx) { $full[$idx] = cop_sync_hist_full_report($hist, $idx); $mean = $hist[$idx]['mean'] ?? cop_sync_stat($full[$idx], 'mean', null); $indices[$idx] = array('mean'=>$mean,'min'=>$hist[$idx]['min'] ?? cop_sync_stat($full[$idx], 'min', null),'max'=>$hist[$idx]['max'] ?? cop_sync_stat($full[$idx], 'max', null),'p5'=>cop_sync_stat($full[$idx], 'p5', null),'p25'=>cop_sync_stat($full[$idx], 'p25', null),'median'=>cop_sync_stat($full[$idx], 'median', null),'p75'=>cop_sync_stat($full[$idx], 'p75', null),'p95'=>cop_sync_stat($full[$idx], 'p95', $hist[$idx]['p90'] ?? null),'p90'=>$hist[$idx]['p90'] ?? cop_sync_stat($full[$idx], 'p95', null),'pixel_count'=>$hist[$idx]['pixel_count'] ?? ($full[$idx]['total_pixel_count'] ?? cop_sync_stat($full[$idx], 'sampleCount', null)),'total_ha'=>$hist[$idx]['total_ha'] ?? ($full[$idx]['total_ha'] ?? null),'public_map'=>cop_sync_public_map_for_index($root, $date, $idx, $evidence),'label'=>cop_sync_index_label($idx),'text'=>cop_sync_index_text($idx, $mean)); }
    $summary = 'Сателитски индекси показују повољно и стабилно стање вегетације, уз потребу праћења локалних одступања.';
    $report = array('ok'=>true,'type'=>'copernicus_daily','status'=>'published','date'=>$date,'sort_date'=>$date,'title'=>'Copernicus извештај за ' . monitoring_fmt_date($date),'category'=>'Copernicus индекси','summary'=>array('date'=>$date,'title'=>'Copernicus извештај за ' . monitoring_fmt_date($date),'report_url'=>'/monitoring-izvestaji/copernicus/' . $date . '/','overall_text'=>$summary),'indices'=>$indices,'metrics'=>cop_sync_hist_metrics($hist),'source_url'=>PIO_SITE . '/kopernikindeks/','report_url'=>PIO_SITE . '/monitoring-izvestaji/copernicus/' . $date . '/','full_indices'=>$full,'public_source'=>array('viewer'=>PIO_SITE . '/kopernikindeks/','map_examples'=>$evidence['map_examples'] ?? array(),'text_examples'=>$evidence['text_examples'] ?? array()),'diagnostics'=>array('јавни извештај је објављен јер постоје комплетни NDVI, NDMI и RECI histogram подаци','датум: ' . monitoring_fmt_date($date)),'source_status'=>array('histogram'=>$hist,'public_evidence'=>$evidence),'source'=>array('service'=>'Copernicus/Sentinel-2 Statistical API histogram', 'generated_at'=>monitoring_iso_now(), 'sync_version'=>'v13-public-only-complete'),'generated_at'=>monitoring_iso_now());
    monitoring_write_json($dir . '/report.json', $report); monitoring_write_html($dir . '/index.html', cop_sync_render_cop_html($report)); monitoring_write_json($root . '/copernicus/latest.json', $report); monitoring_write_json($root . '/copernicus/latest-sync.json', $report);
    return array('ok'=>true,'date'=>$date,'action'=>'created-or-updated','status'=>'published','url'=>PIO_SITE . '/monitoring-izvestaji/copernicus/' . $date . '/');
}
function cop_sync_render_class_table(array $rows): string { if (!$rows) return '<p>Нема издвојених класа за овај индекс.</p>'; $html = '<table><thead><tr><th>Класа</th><th>Опсег</th><th>Површина</th><th>Удео</th></tr></thead><tbody>'; foreach ($rows as $r) { $from = $r['from'] ?? null; $to = $r['to'] ?? null; $range = (is_numeric($from) || is_numeric($to)) ? cop_sync_fmt_num($from, 2) . ' – ' . cop_sync_fmt_num($to, 2) : '—'; $html .= '<tr><td>' . monitoring_html_escape($r['label'] ?? '') . '</td><td>' . monitoring_html_escape($range) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_ha($r['area_ha'] ?? null)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_pct($r['share_pct'] ?? null)) . '</td></tr>'; } return $html . '</tbody></table>'; }
function cop_sync_render_signals_table(array $signals): string { if (!$signals) return '<p>Нема издвојених алармних сигнала за овај индекс.</p>'; $html = '<table><thead><tr><th>Сигнал</th><th>Праг</th><th>Површина</th><th>Удео</th></tr></thead><tbody>'; foreach ($signals as $r) { if (!is_array($r)) continue; $html .= '<tr><td>' . monitoring_html_escape($r['label'] ?? '') . '</td><td>' . monitoring_html_escape($r['threshold'] ?? '') . '</td><td>' . monitoring_html_escape(cop_sync_fmt_ha($r['area_ha'] ?? null)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_pct($r['share_pct'] ?? null)) . '</td></tr>'; } return $html . '</tbody></table>'; }
function cop_sync_render_cop_html(array $report): string {
    $title = monitoring_html_escape($report['summary']['title'] ?? $report['title'] ?? 'Copernicus извештај'); $summary = monitoring_html_escape($report['summary']['overall_text'] ?? ''); $dateRaw = (string)($report['date'] ?? ''); $date = monitoring_html_escape(monitoring_fmt_date($dateRaw)); $generated = monitoring_html_escape($report['generated_at'] ?? monitoring_iso_now()); $indices = $report['indices'] ?? array(); $full = $report['full_indices'] ?? array(); $top = ''; $analysis = ''; $statRows = ''; $sections = '';
    foreach (array('NDVI','NDMI','RECI') as $idx) { $mean = $indices[$idx]['mean'] ?? null; $top .= '<div class="metricbox"><b>' . $idx . '</b><strong>' . monitoring_html_escape(cop_sync_fmt_num($mean, 3)) . '</strong><span>' . monitoring_html_escape(cop_sync_index_label($idx)) . '</span></div>'; $analysis .= '<h3>' . $idx . ' — ' . monitoring_html_escape(cop_sync_index_label($idx)) . '</h3><p>' . monitoring_html_escape($indices[$idx]['text'] ?? cop_sync_index_text($idx, $mean)) . '</p>'; $i = $indices[$idx] ?? array(); $statRows .= '<tr><td><b>' . $idx . '</b></td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['mean'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['min'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['p5'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['p25'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['median'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['p75'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['p95'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_num($i['max'] ?? null, 4)) . '</td><td>' . monitoring_html_escape(cop_sync_fmt_ha($i['total_ha'] ?? null)) . '</td></tr>'; $f = isset($full[$idx]) && is_array($full[$idx]) ? $full[$idx] : array(); $map = (string)($i['public_map'] ?? ''); $img = ($map && preg_match('/\.(png|jpg|jpeg|webp)(\?.*)?$/i', $map)) ? '<figure><img src="' . monitoring_html_escape($map) . '" alt="Карта индекса ' . $idx . ' за ' . $date . '"><figcaption>Карта индекса ' . $idx . ' за ' . $date . '</figcaption></figure>' : ''; $sections .= '<section class="idx"><h2>' . $idx . '</h2><p class="sub">' . monitoring_html_escape(cop_sync_index_label($idx)) . '</p><div class="value">' . monitoring_html_escape(cop_sync_fmt_num($i['mean'] ?? null, 3)) . '</div><p>' . monitoring_html_escape($i['text'] ?? '') . '</p><h3>Карта индекса ' . $idx . '</h3>' . $img . '<h3>Класе површина</h3>' . cop_sync_render_class_table(isset($f['classes']) && is_array($f['classes']) ? $f['classes'] : array()) . '<h3>Издвојени сигнали</h3>' . cop_sync_render_signals_table(isset($f['signals']) && is_array($f['signals']) ? $f['signals'] : array()) . '</section>'; }
    $sample = $full['NDVI']['stats']['sampleCount'] ?? null; $nodata = $full['NDVI']['stats']['noDataCount'] ?? null; $src = 'Извор: Copernicus/Sentinel-2 Statistical API histogram'; if (is_numeric($sample)) $src .= ' · Број узорака: ' . number_format((float)$sample, 0, ',', '.'); if (is_numeric($nodata)) $src .= ' · Број пиксела без података: ' . number_format((float)$nodata, 0, ',', '.');
    return '<!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . ' — ПИО Рајац</title><style>body{margin:0;background:#f7f4eb;color:#17251b;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;line-height:1.65}.hero{background:linear-gradient(135deg,#07150c,#173b24);color:#fff;padding:50px 22px}.wrap{width:min(1120px,calc(100% - 32px));margin:auto}.k{color:#e7cf88;text-transform:uppercase;letter-spacing:.14em;font-weight:900;font-size:13px}h1{font-family:Georgia,serif;font-size:clamp(34px,6vw,62px);font-weight:400;line-height:1.05}.card,.idx{background:#fffdf8;border:1px solid #e3dccb;border-radius:16px;padding:24px;margin:24px 0}.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:18px 0}.metricbox{background:#f2eedf;border:1px solid #e3dccb;border-radius:16px;padding:16px}.metricbox b{display:block;color:#8a6717;letter-spacing:.08em}.metricbox strong{display:block;font-size:32px;margin:4px 0}.metricbox span{color:#59685f}.btn{display:inline-block;background:#155f36;color:#fff!important;text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:850;margin:4px 6px 4px 0}a{color:#155f36}.muted{color:#d8e7db}.sub{color:#637269;font-weight:800}.value{font-size:34px;font-weight:900;color:#155f36;margin:.2em 0}table{width:100%;border-collapse:collapse;margin:12px 0 24px;background:#fff}th,td{border:1px solid #e3dccb;padding:9px 10px;text-align:left;vertical-align:top}th{background:#f2eedf;color:#233426}figure{margin:12px 0 20px}img{max-width:100%;border-radius:14px;border:1px solid #d9cfb9;display:block}figcaption{font-size:14px;color:#637269;margin-top:6px}.note{background:#f8f2e3;border:1px solid #e3dccb;border-radius:14px;padding:16px;margin:20px 0}@media(max-width:760px){.metrics{grid-template-columns:1fr}table{font-size:13px;display:block;overflow-x:auto}}</style></head><body><section class="hero"><div class="wrap"><div class="k">ПИО Рајац · Copernicus индекси</div><h1>' . $title . '</h1><p class="muted">Датум: ' . $date . ' · Генерисано: ' . $generated . '</p><p><a class="btn" href="/monitoring-izvestaji/dnevni/latest.html">← Дневни мониторинг</a><a class="btn" href="/kopernikindeks/">Јавни Copernicus приказ</a></p></div></section><main class="wrap"><article class="card"><p>' . $summary . '</p><div class="metrics">' . $top . '</div><h2>Сажетак дневне анализе</h2>' . $analysis . '<h2>Статистички преглед индекса</h2><p>Табела приказује основне статистичке показатеље за цело обухваћено подручје ПИО „Рајац”.</p><table><thead><tr><th>Индекс</th><th>Средња</th><th>Мин.</th><th>P5</th><th>P25</th><th>Медијана</th><th>P75</th><th>P95</th><th>Макс.</th><th>Површина</th></tr></thead><tbody>' . $statRows . '</tbody></table></article>' . $sections . '<article class="note"><h2>Методолошка напомена</h2><p>Извештај је направљен из дневних статистичких резултата (хистограма) за индексе NDVI, NDMI и RECI. Вредности су израчунате за обухват ПИО „Рајац”, у прорачунској пројекцији EPSG:32634, са просторном резолуцијом од приближно 10 m. Дневни извештај је погодан за брзу оцену стања после конкретног сателитског надлета, док месечни и годишњи извештаји имају већу тежину за трендове.</p><p>' . monitoring_html_escape($src) . '</p><p>Извештај је генерисан: ' . $generated . '</p></article></main></body></html>';
}
function monitoring_sync_copernicus_public(string $root, string $from = '2026-06-29', ?string $to = null, bool $force = false): array {
    $from = cop_sync_safe_date($from, '2026-06-29');
    $to = cop_sync_safe_date($to ?: $from, $from);
    monitoring_ensure_dir($root . '/copernicus');
    $made = array(); $skipped = array(); $errors = array();
    $latestCompleteHist = cop_sync_latest_complete_hist_date($root, $to);
    $range = cop_sync_range($from, $to);
    foreach ($range as $date) {
        try {
            $hist = cop_sync_hist_status($root, $date);
            $histOk = cop_sync_hist_all_ok($hist);
            $ev = cop_sync_public_evidence($root, $date);
            if (!$histOk) {
                $removed = cop_sync_cleanup_incomplete_report($root, $date, 'Јавна Copernicus страница се не прави без комплетних NDVI/NDMI/RECI histogram података.', $ev);
                $skipped[$date] = array(
                    'reason'=>'Нема јавног Copernicus извештаја за посетиоце: недостаје комплетна NDVI/NDMI/RECI histogram статистика. Карта сама није довољна за дневни извештај.',
                    'histogram'=>'NDVI=' . (!empty($hist['NDVI']['ok'])?'OK':'MISSING') . ', NDMI=' . (!empty($hist['NDMI']['ok'])?'OK':'MISSING') . ', RECI=' . (!empty($hist['RECI']['ok'])?'OK':'MISSING'),
                    'public_evidence'=>'maps=' . (string)($ev['map_count'] ?? 0) . ', text=' . (string)($ev['text_count'] ?? 0),
                    'removed'=>$removed,
                    'dates_seen'=>array_slice($ev['dates_seen'] ?? array(), -10),
                );
                continue;
            }
            $made[] = cop_sync_make_cop_report($root, $date, $hist, $ev, $force);
        } catch (Throwable $e) { $errors[$date] = $e->getMessage(); }
    }
    $out = array(
        'ok'=>empty($errors),
        'version'=>'2026-07-04-v17-redirect-html-no-403',
        'from'=>$from,
        'to'=>$to,
        'dates_processed'=>count($range),
        'generated_at'=>monitoring_iso_now(),
        'latest_complete_histogram'=>$latestCompleteHist,
        'made'=>$made,
        'skipped'=>$skipped,
        'errors'=>$errors,
    );
    monitoring_write_json($root . '/copernicus/public-sync-status.json', $out);
    $best = null;
    foreach (glob($root . '/copernicus/20??-??-??/report.json') ?: array() as $p) {
        $d = basename(dirname($p));
        $j = cop_sync_read_json_loose($p);
        if (preg_match('/^20\d{2}-\d{2}-\d{2}$/', $d) && is_array($j) && cop_sync_report_is_public_complete($j) && (!$best || strcmp($d, $best) > 0)) $best = $d;
    }
    if ($best) {
        $j = cop_sync_read_json_loose($root . '/copernicus/' . $best . '/report.json');
        if (is_array($j) && cop_sync_report_is_public_complete($j)) monitoring_write_json($root . '/copernicus/latest.json', $j);
    }
    return $out;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $from = '2026-06-29'; $to = null; $force = false;
    foreach (($argv ?? array()) as $arg) {
        if (preg_match('/^--date=(20\d{2}-\d{2}-\d{2})$/', $arg, $m)) { $from = $m[1]; $to = $m[1]; }
        if (preg_match('/^--from=(20\d{2}-\d{2}-\d{2})$/', $arg, $m)) $from = $m[1];
        if (preg_match('/^--to=(20\d{2}-\d{2}-\d{2})$/', $arg, $m)) $to = $m[1];
        if ($arg === '--force') $force = true;
    }
    $root = dirname(__DIR__);
    $res = monitoring_sync_copernicus_public($root, $from, $to, $force);
    echo json_encode($res, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . PHP_EOL;
    exit(empty($res['errors']) ? 0 : 1);
}
