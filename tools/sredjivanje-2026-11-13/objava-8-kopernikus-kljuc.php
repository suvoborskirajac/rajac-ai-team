<?php
// Привремена скрипта (Claude, 13.11.2026): нов кључ Copernicus система уместо rajac2026; старе инсталационе скрипте у карантин.
// Без ?apply=1 само проверава. Брише се после употребе.
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('__KLJUC__', $_GET['k'])) { http_response_code(404); exit; }
@set_time_limit(100);
$H = dirname(__DIR__); $NK = '__NOVI_KLJUC__'; $bk = "$H/_rezerva_kopernikus_kljuc_20261113"; $kar = "$H/_karantin_20261113/kopernikus";
$RE = '~(?<![A-Za-z0-9.])rajac2026(?![A-Za-z0-9])~';
$Z = array_filter(explode("\n", <<<'TXT'
public_html/agroportal/copernicus_backfill_2025.php|1
public_html/agroportal/copernicus_builder.php|1
public_html/agroportal/cron_copernicus_all.sh|1
public_html/amsrajac/UPUTSTVO_COPERNICUS.txt|5
public_html/amsrajac/copernicus_auto_fetch.php|1
public_html/amsrajac/copernicus_backfill_2025.php|1
public_html/amsrajac/copernicus_config.php|1
public_html/amsrajac/copernicus_frames_audit.php|1
public_html/amsrajac/cron_copernicus_all.sh|1
public_html/amsrajac/cron_copernicus_auto.sh|1
public_html/amsrajac/cron_parcel_stats_monthly.sh|1
public_html/amsrajac/parcel_import.php|1
public_html/amsrajac/parcel_longterm_console.php|1
public_html/amsrajac/parcel_stats_console.php|1
public_html/amsrajac/parcel_vci_builder.php|1
public_html/amsrajac/parcel_vci_console.php|1
public_html/kopernikindeks/engine/daily-build.php|1
public_html/kopindeksi/UPUTSTVO_COPERNICUS.txt|5
public_html/kopindeksi/copernicus_auto_fetch.php|1
public_html/kopindeksi/copernicus_backfill_2025.php|1
public_html/kopindeksi/copernicus_config.php|1
public_html/kopindeksi/copernicus_frames_audit.php|1
public_html/kopindeksi/cron_copernicus_all.sh|1
public_html/kopindeksi/cron_copernicus_auto.sh|1
public_html/kopindeksi/cron_parcel_stats_monthly.sh|1
public_html/kopindeksi/parcel_longterm_console.php|1
public_html/kopindeksi/parcel_stats_console.php|1
public_html/kopindeksi/parcel_vci_console.php|1
public_html/monitoring-izvestaji/_engine/kopernikus-auto-sync.php|1
public_html/monitoring-izvestaji/copernicus/copernicus_guard_check.php|1
public_html/monitoring-izvestaji/copernicus/engine/auto-publish.php|2
public_html/monitoring-izvestaji/copernicus/engine/daily-build.php|1
public_html/monitoring-izvestaji/copernicus/engine/daily-probe.php|1
public_html/monitoring-izvestaji/copernicus/engine/force-rebuild-maps.php|1
public_html/monitoring-izvestaji/copernicus/engine/map-probe.php|1
public_html/monitoring-izvestaji/copernicus/engine/rebuild-existing.php|1
public_html/monitoring-izvestaji/copernicus/engine/refresh-index.php|1
public_html/monitoring-izvestaji/monitoring-izvestaji/napravi-kopernikus-29-06-i-04-07.php|1
public_html/monitoring-izvestaji/napravi-kopernikus-29-06-i-04-07.php|1
rajac-ai/data/fleet-health/eo-forest-diagnostics-v1.5.4.json|1
TXT));
$KF = array_filter(explode("\n", <<<'TXT'
public_html/kopindeksi/remove_histogram_from_index_v14.php
public_html/amsrajac/install_histogram_sync_clean.php
public_html/amsrajac/remove_histogram_from_index_v14.php
public_html/kopindeksi/_arhiva-instalacionih-skripti
TXT));
$e = []; $plan = 0; $ukupno = 0;
foreach ($Z as $row) { [$p, $n] = explode('|', $row); $c = @file_get_contents("$H/$p"); if ($c === false) { $e[] = "нема $p"; continue; }
  $k = preg_match_all($RE, $c); if ($k < 1) $e[] = "$p: кључ није нађен"; else { $plan++; $ukupno += $k; } }
foreach ($KF as $p) if (!file_exists("$H/$p")) $e[] = "нема за карантин: $p";
echo "ПРОВЕРА: фајлова за замену $plan (места: $ukupno), за карантин " . count($KF) . "\n";
if ($e) { echo "СТОП:\n  " . implode("\n  ", $e) . "\n"; exit; }
if (empty($_GET['apply'])) exit;
$ok = 0;
foreach ($Z as $row) { [$p] = explode('|', $row); $src = "$H/$p"; @mkdir(dirname("$bk/$p"), 0700, true);
  if (!copy($src, "$bk/$p")) { echo "РЕЗЕРВА НИЈЕ УСПЕЛА: $p\n"; continue; }
  $c = file_get_contents($src); $n = preg_replace($RE, $NK, $c); $perm = fileperms($src) & 0777;
  if (file_put_contents($src, $n) === strlen($n)) { @chmod($src, $perm); $ok++; } else echo "УПИС НИЈЕ УСПЕО: $p\n"; }
foreach ($KF as $p) { @mkdir(dirname("$kar/$p"), 0700, true); echo (rename("$H/$p", "$kar/$p") ? 'OK' : 'ГРЕШКА') . " карантин $p\n"; }
@file_put_contents("$H/_karantin_20261113/.htaccess", "Require all denied\n");
echo "Замењено у $ok фајлова. Резерва: $bk. Карантин: $kar\n";
