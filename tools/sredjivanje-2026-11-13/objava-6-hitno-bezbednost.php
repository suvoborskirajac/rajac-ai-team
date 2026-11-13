<?php
// Привремена скрипта (Claude, 13.11.2026): хитне безбедносне исправке. Без ?apply=1 само проверава. Брише се после употребе.
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('__KLJUC__', $_GET['k'])) { http_response_code(404); exit; }
$R = __DIR__; $H = dirname(__DIR__); $kar = "$H/_karantin_20261113"; $bk = "$H/_rezerva_hitno_20261113"; $NT = '__NOVI_TOKEN__';
$e = []; $plan = [];
// 1. карантин
foreach (['ROLLBACK-PRE-STATIC-GITHUB.php', 'PROCITAJ.txt'] as $f) { if (!is_file("$R/$f")) $e[] = "нема $f"; else $plan[] = "премести $f → $kar/"; }
// 2. eBird кључ
$eng = "$R/monitoring-izvestaji/biodiverzitet/_engine"; if (!is_file("$eng/ebird_token.txt")) $e[] = 'нема ebird_token.txt'; if (is_file("$eng/.htaccess")) $e[] = '_engine/.htaccess већ постоји'; else $plan[] = 'нов _engine/.htaccess (забрана ebird_token.txt)';
// 3. токен у 5 алата
$T = ['rajac-agenti.php' => "\$TOKEN = 'rajac2026';", 'rajac-logrotate.php' => "= 'rajac2026';", 'rajac-pregled-sajta.php' => "const RAJAC_TOKEN = 'rajac2026';", 'rajac-rezultati.php' => "const RAJAC_TOKEN = 'rajac2026';", 'copernicus_builder.php' => "define('RUN_KEY', 'rajac2026');"];
foreach ($T as $f => $s) { $c = @file_get_contents("$R/$f"); if ($c === false || substr_count($c, $s) !== 1) $e[] = "$f: место није јединствено"; else $plan[] = "нов токен у $f (" . substr_count($c, 'rajac2026') . " појављивања)"; }
// 4. подсетници само из cron-а
$bf = "$R/vlasnici/billing_reminders.php"; $bc = @file_get_contents($bf); $guard = "<?php\nif (PHP_SAPI !== 'cli') { http_response_code(403); exit; } // ПИО „Рајац“ 2026-11-13: покреће га само cron, не прегледач\n";
if ($bc === false || strncmp($bc, "<?php\n", 6) !== 0 || strpos($bc, "PHP_SAPI !== 'cli'") !== false) $e[] = 'billing_reminders.php: почетак неочекиван или већ заштићен'; else $plan[] = 'billing_reminders.php само за cron';
echo "ПЛАН:\n  " . implode("\n  ", $plan) . "\n"; if ($e) { echo "СТОП:\n  " . implode("\n  ", $e) . "\n"; exit; }
if (empty($_GET['apply'])) { echo "Режим провере — ништа није мењано.\n"; exit; }
@mkdir($kar, 0700, true); @mkdir("$bk/vlasnici", 0700, true); file_put_contents("$kar/.htaccess", "Require all denied\n");
foreach (['ROLLBACK-PRE-STATIC-GITHUB.php', 'PROCITAJ.txt'] as $f) echo (rename("$R/$f", "$kar/$f") ? 'OK' : 'ГРЕШКА') . " премештено $f\n";
echo (file_put_contents("$eng/.htaccess", "# ПИО „Рајац“ 2026-11-13: кључ за eBird не сме бити јавно доступан\n<FilesMatch \"(^ebird_token\\.txt$|token)\">\n  Require all denied\n</FilesMatch>\n") ? 'OK' : 'ГРЕШКА') . " _engine/.htaccess\n";
foreach ($T as $f => $s) { copy("$R/$f", "$bk/$f"); $c = file_get_contents("$R/$f"); $n = str_replace('rajac2026', $NT, $c); echo (file_put_contents("$R/$f", $n) === strlen($n) ? 'OK' : 'ГРЕШКА') . " токен $f\n"; }
copy($bf, "$bk/vlasnici/billing_reminders.php"); $n = $guard . substr($bc, 6); echo (file_put_contents($bf, $n) === strlen($n) ? 'OK' : 'ГРЕШКА') . " billing_reminders.php\n";
echo "Карантин: $kar\nРезерва: $bk\n";
