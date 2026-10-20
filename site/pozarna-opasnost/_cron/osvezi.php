<?php
/**
 * ПИО „Рајац“ — јавна копија података о пожарној угрожености. 2026-10-20
 * Покреће се САМО из cron-а (CLI). Из РИС-а (који остаје под лозинком) копира:
 *   - latest.json (само поља потребна јавној карти),
 *   - два растера на која latest.json показује (дневни и просторни),
 *   - границу ПИО (GeoJSON).
 * Ништа друго из РИС-а не прелази у јавни фолдер.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$pub  = dirname(__DIR__);                    // .../public_html/pozarna-opasnost
$root = dirname($pub);                       // .../public_html
$risRoot = $root . '/ris/promene-prostora/';
$risData = $risRoot . 'data/local-fire-risk/';
$boundarySrc = $root . '/ris/pozari/p4e-meteo-fusion/data/pio_boundary.geojson';
$out = $pub . '/data/';
$log = __DIR__ . '/osvezi.log';

function lg(string $m): void { global $log; @file_put_contents($log, '['.date('c').'] '.$m."\n", FILE_APPEND); if (PHP_SAPI==='cli') echo $m, "\n"; }
function resolve_src(string $u, string $root, string $risRoot, string $risData): ?string {
    $u = trim($u);
    if ($u === '' || preg_match('#^[a-z]+://#i', $u)) return null;
    $u = preg_replace('#[?\#].*$#', '', $u);
    if ($u[0] === '/') $p = $root . $u;
    else { $u = preg_replace('#^\./#', '', $u);
        if (strpos($u, 'data/local-fire-risk/') === 0) $p = $risRoot . $u;
        elseif (strpos($u, 'local-fire-risk/') === 0) $p = $risRoot . 'data/' . $u;
        else $p = $risData . $u; }
    $real = realpath($p);
    if ($real === false || !is_file($real)) return null;
    $risReal = realpath($root . '/ris');
    if ($risReal === false || strpos($real, $risReal . DIRECTORY_SEPARATOR) !== 0) return null; // само из РИС-а
    if (!preg_match('/\.(png|webp|jpe?g)$/i', $real)) return null;                            // само слике
    if (filesize($real) > 20 * 1024 * 1024) return null;
    return $real;
}
function copy_atomic(string $from, string $to): bool { $tmp = $to . '.tmp'; if (!@copy($from, $tmp)) return false; return @rename($tmp, $to); }

if (!is_dir($out) && !@mkdir($out, 0755, true)) { lg('ГРЕШКА: не могу да направим ' . $out); exit(1); }
$raw = @file_get_contents($risData . 'latest.json');
$j = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($j) || empty($j['grid']['bbox'])) { lg('ГРЕШКА: latest.json у РИС-у не постоји или није исправан'); exit(1); }

$pubJson = [
  'ok' => true,
  'kind' => 'lokalni-orijentacioni-indeks',
  'generated_at' => $j['generated_at'] ?? null,
  'grid' => ['bbox' => array_intersect_key((array)$j['grid']['bbox'], array_flip(['south','west','north','east']))],
  'inputs' => [
    'fwi' => array_intersect_key((array)($j['inputs']['fwi'] ?? []), array_flip(['value','risk_label','date'])),
    'sentinel2' => array_intersect_key((array)($j['inputs']['sentinel2'] ?? []), array_flip(['date'])),
  ],
  'published_at' => date('c'),
];
foreach (['raster_url', 'spatial_susceptibility_url'] as $k) {
    $src = isset($j[$k]) ? resolve_src((string)$j[$k], $root, $risRoot, $risData) : null;
    if ($src === null) { lg('ГРЕШКА: не налазим фајл за ' . $k . ' (' . ($j[$k] ?? '—') . ')'); exit(1); }
    $name = ($k === 'raster_url' ? 'dnevna-karta' : 'prostorna-osnova') . '.' . strtolower(pathinfo($src, PATHINFO_EXTENSION));
    if (!copy_atomic($src, $out . $name)) { lg('ГРЕШКА: копирање ' . $src); exit(1); }
    $pubJson[$k] = $name;
}
if (is_file($boundarySrc)) copy_atomic($boundarySrc, $out . 'pio_boundary.geojson');
$tmp = $out . 'latest.json.tmp';
file_put_contents($tmp, json_encode($pubJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
rename($tmp, $out . 'latest.json');
lg('OK: објављено ' . ($pubJson['generated_at'] ?? '?') . ' · Sentinel-2 ' . ($pubJson['inputs']['sentinel2']['date'] ?? '?'));
