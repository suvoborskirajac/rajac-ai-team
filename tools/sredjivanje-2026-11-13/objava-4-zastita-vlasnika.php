<?php
// Привремена скрипта (Claude, 13.11.2026): подаци о власницима само за пријављене (власници: само управљач).
// Без ?apply=1 само проверава. Брише се после употребе.
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('__KLJUC__', $_GET['k'])) { http_response_code(404); exit; }
$V = __DIR__ . '/vlasnici'; $apply = !empty($_GET['apply']);
$bk = dirname(__DIR__) . '/_rezerva_vlasnici_20261113';

$AUTH = "<?php\n// ПИО „Рајац“ 2026-11-13: страница само за управљача (пријављеног администратора).\nrequire_once __DIR__ . '/init.php';\nif (!is_admin()) { header('Location: index.php'); exit; }\nheader('Cache-Control: no-store, private');\nheader('X-Robots-Tag: noindex, nofollow');\n?>";

// [фајл, [[старо, ново, очекивано]...]]
$EDIT = [
 ['admin.php', [["fetch('parcele_joined.geojson'", "fetch('geo.php?f=parcele'", 1], ["fetch('parcelevlasnik2_imaoci.geojson'", "fetch('geo.php?f=vlasnici'", 1], ['data-src="index_parcele.html"', 'data-src="index_parcele.php"', 1], ["|| 'index_parcele.html')", "|| 'index_parcele.php')", 1]]],
 ['admin_view.php', [["fetch('parcele_joined.geojson'", "fetch('geo.php?f=parcele'", 1]]],
 ['rgz_full_admin_view.php', [["fetch('parcele_joined.geojson'", "fetch('geo.php?f=parcele'", 1]]],
 ['rgz_full_admin_view_v2.php', [["fetch('parcele_joined.geojson'", "fetch('geo.php?f=parcele'", 1]]],
 ['owner.php', [["fetch('parcele_joined.geojson'", "fetch('geo.php?f=parcele'", 1]]],
 ['rgz_38_import.php', [['/vlasnici/index_parcele.html', '/vlasnici/index_parcele.php', 1], ['/vlasnici/rgz_38_pregled.html', '/vlasnici/rgz_38_pregled.php', 1]]],
 ['assets/rajac_nova_karta.js', [["'assets/nova-karta/naplata_pio_nova_karta.geojson?v='", "'geo.php?f=naplata&v='", 1]]],
];
// нове странице: [нови, извор, замене]
$NEW = [
 ['index_parcele.php', 'index_parcele.html', [["'parcele_joined.geojson'", "'geo.php?f=parcele'", 1], ["'parcelevlasnik2_imaoci.geojson'", "'geo.php?f=vlasnici'", 1]]],
 ['rgz_38_pregled.php', 'rgz_38_pregled.html', [['/vlasnici/index_parcele.html', '/vlasnici/index_parcele.php', 1]]],
];
$GEO = <<<'PHP'
<?php
// ПИО „Рајац“ 2026-11-13: подаци за карте система власника — само за пријављене.
// Подаци о власницима (имаоци права) и карта наплате: само управљач. Катастарске парцеле: управљач и пријављени власник.
require_once __DIR__ . '/init.php';
$FILES = [
    'parcele'  => ['parcele_joined.geojson', 'prijavljen'],
    'vlasnici' => ['parcelevlasnik2_imaoci.geojson', 'admin'],
    'rgz38'    => ['rgz_38_imaoci.geojson', 'admin'],
    'naplata'  => ['assets/nova-karta/naplata_pio_nova_karta.geojson', 'admin'],
];
$k = (string)($_GET['f'] ?? '');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
if (!isset($FILES[$k])) { http_response_code(404); exit; }
[$file, $nivo] = $FILES[$k];
if (empty($_SESSION['user_type']) || ($nivo === 'admin' && !is_admin())) { http_response_code(403); exit; }
$path = __DIR__ . '/' . $file;
if (!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: application/geo+json; charset=utf-8');
header('Content-Length: ' . filesize($path));
readfile($path);
PHP;
$HT = "\n# BEGIN PIO-ZASTITA-VLASNIKA 2026-11-13\n# Подаци о парцелама и власницима не смеју се преузети директно — само кроз geo.php после пријаве.\n<FilesMatch \"\\.geojson$\">\n  Require all denied\n</FilesMatch>\n<FilesMatch \"^(index_parcele|rgz_38_pregled)\\.html$\">\n  Require all denied\n</FilesMatch>\n# END PIO-ZASTITA-VLASNIKA\n";

$out = []; $err = [];
foreach ($EDIT as [$f, $reps]) {
    $s = @file_get_contents("$V/$f"); if ($s === false) { $err[] = "НЕМА $f"; continue; }
    if (strpos($s, 'geo.php?f=') !== false) { $err[] = "ВЕЋ ИЗМЕЊЕНО $f"; continue; }
    foreach ($reps as [$a, $b, $n]) { $c = substr_count($s, $a); if ($c !== $n) { $err[] = "$f: «{$a}» нађено $c, очекивано $n"; continue 2; } $s = str_replace($a, $b, $s); }
    $out[$f] = $s;
}
foreach ($NEW as [$f, $src, $reps]) {
    if (is_file("$V/$f")) { $err[] = "ВЕЋ ПОСТОЈИ $f"; continue; }
    $s = @file_get_contents("$V/$src"); if ($s === false) { $err[] = "НЕМА $src"; continue; }
    if (strpos($s, '<?') !== false) { $err[] = "$src садржи <? — прескочено"; continue; }
    foreach ($reps as [$a, $b, $n]) { $c = substr_count($s, $a); if ($c !== $n) { $err[] = "$src: «{$a}» нађено $c, очекивано $n"; continue 2; } $s = str_replace($a, $b, $s); }
    $out[$f] = $AUTH . $s;
}
if (is_file("$V/geo.php")) $err[] = 'ВЕЋ ПОСТОЈИ geo.php'; else $out['geo.php'] = $GEO;
$ht = @file_get_contents("$V/.htaccess");
if ($ht === false || strpos($ht, 'PIO-ZASTITA-VLASNIKA') !== false) $err[] = '.htaccess недостаје или је већ измењен'; else $out['.htaccess'] = rtrim($ht, "\n") . "\n" . $HT;

echo ($apply ? 'УПИС' : 'ПРОВЕРА') . ': спремно ' . count($out) . " фајлова\n" . implode("\n", array_map(fn($f) => '  ' . $f, array_keys($out))) . "\n";
if ($err) { echo "СТОП — ништа није уписано:\n" . implode("\n", $err) . "\n"; exit; }
if (!$apply) exit;
foreach ($out as $f => $s) if (is_file("$V/$f")) { @mkdir(dirname("$bk/$f"), 0755, true); if (!copy("$V/$f", "$bk/$f")) { echo "РЕЗЕРВА НИЈЕ УСПЕЛА: $f\n"; exit; } }
$ok = 0; foreach ($out as $f => $s) { if (file_put_contents("$V/$f", $s, LOCK_EX) === strlen($s)) { @chmod("$V/$f", 0644); $ok++; } else echo "УПИС НИЈЕ УСПЕО: $f\n"; }
echo "Уписано $ok од " . count($out) . ". Резерва: $bk\n";
