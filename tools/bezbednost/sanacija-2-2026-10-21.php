<?php
// ПИО „РАЈАЦ“ — безбедносна санација, други део, 2026-10-21 (на основу прегледа изворног кода).
// 1) Merlin: отпремање прима само звук и слике (до сада је примало и PHP фајлове).
// 2) Фасцикле са отпремљеним фајловима: забрана покретања скрипти + провера да ли већ има подметнутих скрипти.
// 3) Теренски извештаји: брисање пријава само уз тајни кључ (до сада је свако могао да брише).
// 4) Скрипте које покрећу обраде на серверу: из прегледача само уз кључ; cron и даље ради.
// 5) Једнократне скрипте које раде чим се адреса отвори (final2026, s2-audit) — ван сајта.
// 6) Провера и враћање резервне копије коју је final2026.php направио 21. 10. 2026.
// 7) (по избору) Затварање WordPress пријаве; формулари и даље шаљу пошту.
// GET = само преглед. Свака радња тек на дугме. Пре сваке измене копија иде у карантин. На крају скрипта брише саму себе.
declare(strict_types=1);
@set_time_limit(40);
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
const KLJUC = 'POSTAVI_KLJUC';
if (!hash_equals(KLJUC, (string)($_GET['k'] ?? ''))) { http_response_code(404); exit('Not found'); }
$root = __DIR__; $van = dirname(__DIR__);
$kar = $van . '/_karantin-bezbednost-2026-10-21';
$kop = $kar . '/_kopije-pre-izmene-2';
$stf = $kar . '/.stanje-2.json';
$self = basename(__FILE__) . '?k=' . KLJUC;
const OZNAKA = 'РАЈАЦ-ЗАШТИТА 2026-10-21';

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function strana(string $n, string $t, string $r=''): void { header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$r.'<title>'.h($n).'</title><style>body{font-family:system-ui,sans-serif;background:#f6f3ed;color:#1d2939;margin:0;padding:24px}main{max-width:900px;margin:auto;background:#fff;border:1px solid #ddd5c8;border-radius:12px;padding:22px}button{background:#245b3a;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-weight:700;font-size:16px;cursor:pointer;margin:4px 0}button.d{background:#8a1c1c}button.s{background:#5b6470}.ok{background:#eef8f0;border:1px solid #b8d8bf;padding:10px 12px;border-radius:8px;margin:8px 0}.err{background:#fff0ef;border:1px solid #e2b2ad;padding:10px 12px;border-radius:8px;margin:8px 0}.inf{background:#f3f1ea;border:1px solid #ddd5c8;padding:10px 12px;border-radius:8px;margin:8px 0}details{margin:8px 0}ul{max-height:340px;overflow:auto;font-size:13px}code{background:#f2efe8;padding:1px 4px;border-radius:3px;word-break:break-all}.kljuc{font-size:18px;font-weight:700;background:#fff7d6;border:1px solid #e3cf7a;padding:8px 10px;border-radius:8px;display:inline-block}</style></head><body><main><h1>'.h($n).'</h1>'.$t.'</main></body></html>'; exit; }
function forma(string $self, string $a, string $label, string $cls=''): string { return '<form method="post" action="'.h($self.'&a='.$a).'"><button class="'.$cls.'" type="submit">'.h($label).'</button></form>'; }
function stanje(string $stf): array { return is_file($stf) ? (array)json_decode((string)file_get_contents($stf), true) : []; }
function sacuvaj(string $stf, array $s): void { @mkdir(dirname($stf), 0700, true); file_put_contents($stf, json_encode($s, JSON_UNESCAPED_UNICODE)); }
function kar_htaccess(string $kar): void { @mkdir($kar, 0700, true); if (!is_file($kar.'/.htaccess')) @file_put_contents($kar.'/.htaccess', "Require all denied\n"); }
function kopiraj_pre(string $root, string $kop, string $rel): bool { $d = $kop.'/'.$rel; @mkdir(dirname($d), 0700, true); if (is_file($d)) return true; return @copy($root.'/'.$rel, $d); }
function upisi(string $f, string $s): bool { $t = $f.'.pio-tmp'; if (@file_put_contents($t, $s) === false) return false; @chmod($t, fileperms($f) & 0777); return @rename($t, $f); }
function http_status(string $url): int {
  if (!function_exists('curl_init')) return -1;
  $c = curl_init($url); curl_setopt_array($c, [CURLOPT_NOBODY=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>12, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>false]);
  curl_exec($c); $k = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE); curl_close($c); return $k;
}
function php_lint(string $f): ?bool { // null = не може да се провери
  if (!function_exists('exec')) return null;
  foreach (['/opt/alt/php84/usr/bin/php','/opt/alt/php83/usr/bin/php','/opt/alt/php82/usr/bin/php','/usr/local/bin/php','/usr/bin/php'] as $p) {
    if (@is_executable($p)) { $o=[]; $c=1; @exec(escapeshellarg($p).' -l '.escapeshellarg($f).' 2>&1', $o, $c); return $c === 0; }
  }
  return null;
}
function cli_test(string $kar): ?bool { // да ли PHP покренут из веб процеса (као build_worker) види REQUEST_METHOD?
  if (!function_exists('exec')) return null;
  $bin = null; foreach (['/usr/local/bin/lsphp', '/usr/bin/php', PHP_BINARY ?: ''] as $c) if ($c && @is_executable($c)) { $bin = $c; break; }
  if ($bin === null) return null;
  $f = $kar.'/.test-straza.php'; @file_put_contents($f, "<?php echo isset(\$_SERVER['REQUEST_METHOD']) ? 'WEB' : 'CLI-OK';");
  $o = []; $c = 1; @exec(escapeshellarg($bin).' '.escapeshellarg($f).' 2>&1', $o, $c); @unlink($f);
  return strpos(implode("\n", $o), 'CLI-OK') !== false;
}
function relglob(string $root, array $pat): array { $r = []; foreach ($pat as $p) foreach (glob($root.'/'.$p, GLOB_NOSORT) ?: [] as $x) $r[substr($x, strlen($root)+1)] = 1; ksort($r); return array_keys($r); }

$a = (string)($_GET['a'] ?? ''); $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; $st = stanje($stf);

// ===== 1. Merlin =====
const MERLIN = 'merlin/api/upload_media.php';
const MERLIN_STARO = '$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));';
const MERLIN_NOVO = '$ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
// '.OZNAKA.': само звук и слике (раније је могао да се отпреми и PHP фајл)
$__dozvoljeno = $type === \'image\' ? [\'jpg\',\'jpeg\',\'png\',\'webp\',\'gif\'] : [\'mp3\',\'wav\',\'ogg\',\'oga\',\'opus\',\'m4a\',\'aac\',\'flac\',\'webm\',\'mp4\'];
if (!in_array($ext, $__dozvoljeno, true)) { send_json([\'ok\' => false, \'error\' => \'Тип фајла није дозвољен.\'], 415); }';
if ($a === 'merlin' && $post) {
  kar_htaccess($kar); $f = $root.'/'.MERLIN; $s = is_file($f) ? (string)file_get_contents($f) : '';
  if ($s === '') $st['merlin'] = 'фајл не постоји';
  elseif (strpos($s, OZNAKA) !== false) $st['merlin'] = 'већ закрпљено';
  elseif (substr_count($s, MERLIN_STARO) !== 1) $st['merlin'] = 'грешка: фајл није онакав какав сам прегледао — ништа није мењано';
  elseif (!kopiraj_pre($root, $kop, MERLIN)) $st['merlin'] = 'грешка: не могу да направим копију — ништа није мењано';
  else {
    upisi($f, str_replace(MERLIN_STARO, MERLIN_NOVO, $s));
    $l = php_lint($f);
    if ($l === false) { upisi($f, $s); $st['merlin'] = 'ВРАЋЕНО: PHP провера није прошла, стари фајл је враћен'; }
    else $st['merlin'] = 'закрпљено'.($l === null ? '' : ' (PHP провера: у реду)');
  }
  sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== 2. Фасцикле са отпремљеним фајловима =====
const UPL_HT = "# BEGIN ".OZNAKA."\n# У ову фасциклу посетиоци отпремају фајлове: скрипте се овде никад не покрећу\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|shtml|htaccess)$\">\n  Require all denied\n</FilesMatch>\n# END ".OZNAKA."\n";
function upl_dirs(string $root): array {
  $c = ['wp-content/uploads','bird-live/uploads','merlin/media','merlin/data/media','merlin/api/media'];
  $c = array_merge($c, relglob($root, ['*/uploads','*/*/uploads','*/*/*/uploads','merlin/*/media','merlin/media/*','merlin/*/media/*','merlin/*/*/media']));
  foreach (relglob($root, ['merlin/*','merlin/*/*','merlin/*/*/*']) as $d) if (in_array(basename($d), ['media','uploads','upload','audio','images','files','snimci','slike'], true)) $c[] = $d;
  $r = []; foreach (array_unique($c) as $d) if (is_dir($root.'/'.$d) && !is_link($root.'/'.$d)) $r[] = $d;
  // не дира поддиректоријуме ако је родитељ већ на списку
  sort($r); $out = []; foreach ($r as $d) { $ima = false; foreach ($out as $o) if (strpos($d.'/', $o.'/') === 0) { $ima = true; break; } if (!$ima) $out[] = $d; }
  return $out;
}
function upl_skripte(string $root, array $dirs, float $rok): array {
  $nadj = []; $pun = false;
  foreach ($dirs as $d) {
    try { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$d, FilesystemIterator::SKIP_DOTS)); } catch (Throwable $e) { continue; }
    foreach ($it as $fi) {
      if (microtime(true) > $rok) { $pun = true; break 2; }
      if (!$fi->isFile()) continue; $n = strtolower($fi->getFilename());
      if (!preg_match('/\.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|sh|shtml)$/', $n) && !preg_match('/\.php[0-9]?\./', $n)) continue;
      $rel = substr($fi->getPathname(), strlen($root)+1);
      $sad = (string)@file_get_contents($fi->getPathname(), false, null, 0, 400);
      $tiho = $fi->getSize() < 64 && preg_match('/^<\?php\s*(\/\/\s*Silence is golden\.?)?\s*(\?>)?\s*$/i', trim($sad));
      if ($tiho) continue; // WordPress index.php „Silence is golden“ — безопасан
      $nadj[] = ['rel'=>$rel, 'vel'=>$fi->getSize(), 'vreme'=>date('Y-m-d H:i', $fi->getMTime()), 'pocetak'=>mb_substr(preg_replace('/\s+/', ' ', $sad), 0, 160)];
    }
  }
  return [$nadj, $pun];
}
if ($a === 'upload' && $post) {
  kar_htaccess($kar); $r = [];
  foreach (upl_dirs($root) as $d) {
    $f = $root.'/'.$d.'/.htaccess'; $s = is_file($f) ? (string)file_get_contents($f) : '';
    if (strpos($s, OZNAKA) !== false) { $r[] = $d.': већ постоји'; continue; }
    if ($s !== '') kopiraj_pre($root, $kop, $d.'/.htaccess');
    $r[] = $d.(@file_put_contents($f, UPL_HT.($s !== '' ? "\n".$s : '')) !== false ? ': заштићено' : ': није успело');
  }
  $st['upload'] = $r;
  $st['upload_provera'] = [http_status('https://piorajac.rs/'), http_status('https://piorajac.rs/ptice/')];
  sacuvaj($stf, $st); header('Location: '.$self); exit;
}
if ($a === 'upload-skripte' && $post) {
  kar_htaccess($kar); [$nadj] = upl_skripte($root, upl_dirs($root), microtime(true) + 25); $r = [];
  foreach ($nadj as $x) { $dst = $kar.'/podmetnute-skripte/'.$x['rel']; @mkdir(dirname($dst), 0700, true); $r[] = $x['rel'].(@rename($root.'/'.$x['rel'], $dst) ? ' → премештено' : ' → није успело'); }
  $st['upload_skripte'] = $r ?: ['ништа није нађено']; sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== 3. Теренски извештаји: брисање само уз кључ =====
const REP_USLOV_STARO = 'if ($DELETE_TOKEN !== "" && $token !== $DELETE_TOKEN)';
const REP_USLOV_NOVO = 'if ($DELETE_TOKEN === "" || !hash_equals($DELETE_TOKEN, $token)) /* '.OZNAKA.' */';
function rep_fajlovi(string $root): array {
  $r = []; foreach (relglob($root, ['reports.php','*/reports.php','*/*/reports.php','*/*/*/reports.php']) as $rel) {
    $s = (string)@file_get_contents($root.'/'.$rel); if (strpos($s, REP_USLOV_STARO) !== false || strpos($s, OZNAKA) !== false) $r[] = $rel; }
  return $r;
}
if ($a === 'izvestaji' && $post) {
  kar_htaccess($kar);
  $tok = (string)($st['rep_kljuc'] ?? bin2hex(random_bytes(10))); $r = [];
  foreach (rep_fajlovi($root) as $rel) {
    $f = $root.'/'.$rel; $s = (string)file_get_contents($f);
    if (strpos($s, OZNAKA) !== false) { $r[] = $rel.': већ закрпљено'; continue; }
    if (substr_count($s, REP_USLOV_STARO) !== 1 || !preg_match('/\$DELETE_TOKEN\s*=\s*"[^"]*"\s*;/', $s)) { $r[] = $rel.': није онакав какав сам прегледао — прескочено'; continue; }
    if (!kopiraj_pre($root, $kop, $rel)) { $r[] = $rel.': нема копије — прескочено'; continue; }
    $n = str_replace(REP_USLOV_STARO, REP_USLOV_NOVO, $s);
    $n = preg_replace('/\$DELETE_TOKEN\s*=\s*"[^"]*"\s*;/', '$DELETE_TOKEN = "'.$tok.'";', $n, 1);
    upisi($f, $n); $l = php_lint($f);
    if ($l === false) { upisi($f, $s); $r[] = $rel.': ВРАЋЕНО (PHP провера није прошла)'; } else $r[] = $rel.': закрпљено';
  }
  @file_put_contents($kar.'/KLJUC-brisanje-izvestaja.txt', "Кључ за брисање теренских пријава: $tok\n");
  $st['rep_kljuc'] = $tok; $st['izvestaji'] = $r; sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== 4. Скрипте које покрећу обраде =====
const POKRETACI = ['amsrajac/run_agrometeo_async.php','amsrajac/run_agrometeo_steps.php','kopindeksi/run_agrometeo_async.php','kopindeksi/run_agrometeo_steps.php','monitoring-izvestaji/biodiverzitet/run-biodiverzitet-now.php','monitoring-izvestaji/biodiverzitet/godisnji/run-godisnji-inat-now.php'];
const RADNICI = ['amsrajac/build_worker.php','kopindeksi/build_worker.php'];
function ubaci_posle_otvaranja(string $s, string $kod): ?string {
  if (!preg_match('/^(?:\xEF\xBB\xBF)?<\?php[ \t]*\r?\n?(\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;)?/', $s, $m)) return null;
  return substr($s, 0, strlen($m[0])) . "\n" . $kod . "\n" . substr($s, strlen($m[0]));
}
if ($a === 'pokretaci' && $post) {
  kar_htaccess($kar);
  $pk = (string)($st['pok_kljuc'] ?? bin2hex(random_bytes(10))); $r = [];
  $straza = '/* '.OZNAKA.': из прегледача само уз кључ (?k=…); cron из командне линије ради као и пре */ '
    . 'if (isset($_SERVER[\'REQUEST_METHOD\'])) { $__k = \''.$pk.'\'; $__g = (string)($_GET[\'k\'] ?? $_POST[\'k\'] ?? $_COOKIE[\'rajac_k\'] ?? \'\'); '
    . 'if (!hash_equals($__k, $__g)) { http_response_code(403); header(\'Content-Type: text/plain; charset=utf-8\'); exit(\'Приступ није дозвољен.\'); } '
    . 'if (isset($_GET[\'k\']) && !headers_sent()) { setcookie(\'rajac_k\', $__k, [\'expires\' => time() + 43200, \'path\' => \'/\', \'secure\' => true, \'httponly\' => true, \'samesite\' => \'Strict\']); } }';
  $radnik = '/* '.OZNAKA.': покреће се само из командне линије */ if (isset($_SERVER[\'REQUEST_METHOD\'])) { http_response_code(403); exit; }';
  $test = cli_test($kar); $lista = array_map(fn($x)=>[$x,$straza], POKRETACI);
  if ($test === true) $lista = array_merge($lista, array_map(fn($x)=>[$x,$radnik], RADNICI));
  else foreach (RADNICI as $x) $r[] = $x.': није мењано ('.($test === null ? 'exec није доступан за проверу' : 'самопровера показала да би забрана сметала обради').')';
  foreach ($lista as [$rel, $kod]) {
    $f = $root.'/'.$rel; if (!is_file($f)) { $r[] = $rel.': не постоји'; continue; }
    $s = (string)file_get_contents($f);
    if (strpos($s, OZNAKA) !== false) { $r[] = $rel.': већ заштићено'; continue; }
    $n = ubaci_posle_otvaranja($s, $kod); if ($n === null) { $r[] = $rel.': не почиње са <?php — прескочено'; continue; }
    if (!kopiraj_pre($root, $kop, $rel)) { $r[] = $rel.': нема копије — прескочено'; continue; }
    upisi($f, $n); $l = php_lint($f);
    if ($l === false) { upisi($f, $s); $r[] = $rel.': ВРАЋЕНО (PHP провера није прошла)'; } else $r[] = $rel.': заштићено';
  }
  @file_put_contents($kar.'/KLJUC-pokretanje-obrada.txt', "Кључ за покретање обрада из прегледача: $pk\nДодај на крај адресе: ?k=$pk\n");
  $st['pok_kljuc'] = $pk; $st['pokretaci'] = $r; sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== 5. Једнократне скрипте =====
const JEDNOKRATNE = ['kopindeksi/final2026.php','kopindeksi/s2-audit-fixed.php'];
if ($a === 'jednokratne' && $post) {
  kar_htaccess($kar); $r = [];
  foreach (JEDNOKRATNE as $rel) { $s = $root.'/'.$rel; if (!is_file($s)) { $r[] = $rel.': не постоји'; continue; } $d = $kar.'/'.$rel; @mkdir(dirname($d), 0700, true); if (is_file($d)) $d .= '-'.date('His'); $r[] = $rel.(@rename($s, $d) ? ': премештено ван сајта' : ': није успело'); }
  $st['jednokratne'] = $r; sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== 6. Резервна копија коју је final2026.php направио данас =====
$pd = $root.'/kopindeksi/copernicus_data/parcels';
function final_kopije(string $pd): array { $r = glob($pd.'/_repair_backups/20261021_*_FINAL_HTTP400_2026', GLOB_ONLYDIR) ?: []; sort($r); return $r; }
function final_mapa(string $pd, string $ime): ?string {
  if ($ime === 'state.json') return $pd.'/_autofill_fast/state.json';
  if ($ime === 'parcels.geojson') return $pd.'/parcels.geojson';
  if (preg_match('/^(NDVI|NDMI|RECI)_(monthly\.json|monthly\.csv|latest\.json)$/', $ime)) return $pd.'/stats/2026/'.$ime;
  if (strpos($ime, 'longterm__') === 0) return $pd.'/longterm/'.substr($ime, 10);
  return null;
}
if ($a === 'final-vrati' && $post) {
  $k = final_kopije($pd); $r = [];
  if (!$k) $r[] = 'нема данашње копије — ништа није враћено';
  else {
    $izv = end($k); // најновија данашња = стање тачно пре мог отварања адресе
    $sad = $kar.'/final2026-stanje-pre-vracanja-'.date('His'); @mkdir($sad, 0700, true);
    foreach (glob($izv.'/*') ?: [] as $f) {
      $ime = basename($f); $cilj = final_mapa($pd, $ime); if ($cilj === null) { $r[] = $ime.': непознат — прескочено'; continue; }
      if (is_file($cilj)) @copy($cilj, $sad.'/'.$ime);
      $r[] = $ime.(@copy($f, $cilj) ? ': враћено' : ': није успело');
    }
    $r[] = 'Извор: '.basename($izv).'; садашње стање пре враћања сачувано у '.basename($sad);
  }
  $st['final_vraceno'] = $r; sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== 7. WordPress пријава (по избору) =====
const WP_BLOK = "# BEGIN ".OZNAKA." WP\n# WordPress пријава и администрација затворене (сајт је статички; формулари шаљу пошту преко wp_mail без пријаве)\n<FilesMatch \"^(wp-login\\.php|xmlrpc\\.php|wp-signup\\.php|wp-trackback\\.php)$\">\n  Require all denied\n</FilesMatch>\n# END ".OZNAKA." WP\n";
const WP_ADMIN_HT = "# ".OZNAKA."\nRequire all denied\n";
if ($a === 'wp' && $post) {
  kar_htaccess($kar); $f = $root.'/.htaccess'; $s = (string)@file_get_contents($f);
  if (strpos($s, OZNAKA.' WP') !== false) { $st['wp'] = 'већ затворено'; }
  else {
    kopiraj_pre($root, $kop, '.htaccess');
    $wa = $root.'/wp-admin/.htaccess'; $waStaro = is_file($wa) ? (string)file_get_contents($wa) : null;
    if ($waStaro !== null) kopiraj_pre($root, $kop, 'wp-admin/.htaccess');
    file_put_contents($f, WP_BLOK."\n".$s);
    if (is_dir($root.'/wp-admin')) file_put_contents($wa, WP_ADMIN_HT.($waStaro ? "\n".$waStaro : ''));
    usleep(400000);
    $p = http_status('https://piorajac.rs/'); $pt = http_status('https://piorajac.rs/ptice/'); $wl = http_status('https://piorajac.rs/wp-login.php');
    if ($p !== 200 || $pt !== 200) { file_put_contents($f, $s); if ($waStaro === null) @unlink($wa); else file_put_contents($wa, $waStaro); $st['wp'] = 'ВРАЋЕНО: почетна је давала '.$p.' — ништа није промењено'; }
    else { $st['wp'] = 'затворено (wp-login.php сада даје '.$wl.'; почетна: '.$p.')'; $st['wp_staro'] = ['ht'=>$s, 'wa'=>$waStaro]; }
  }
  sacuvaj($stf, $st); header('Location: '.$self); exit;
}
if ($a === 'wp-vrati' && $post) {
  if (isset($st['wp_staro'])) { file_put_contents($root.'/.htaccess', (string)$st['wp_staro']['ht']); $wa = $root.'/wp-admin/.htaccess'; if ($st['wp_staro']['wa'] === null) @unlink($wa); else file_put_contents($wa, (string)$st['wp_staro']['wa']); unset($st['wp_staro']); $st['wp'] = 'WordPress пријава поново отворена'; }
  sacuvaj($stf, $st); header('Location: '.$self); exit;
}

// ===== крај =====
if ($a === 'kraj' && $post) { @unlink($stf); @unlink(__FILE__); strana('Готово', '<p>Скрипта је обрисала саму себе. Копије измењених фајлова су у <code>'.h($kop).'</code>, а кључеви у <code>'.h($kar).'</code> (фајлови KLJUC-*.txt), ван сајта.</p>'); }

// ===== преглед =====
function stavke(array $r): string { return '<ul>'.implode('', array_map(fn($x)=>'<li>'.h($x).'</li>', $r)).'</ul>'; }
function kutija($v): string { $s = is_array($v) ? implode(' ', $v) : (string)$v; $los = preg_match('/ВРАЋЕНО|грешка|није успело|прескочено/u', $s); return '<div class="'.($los?'err':'ok').'">'.(is_array($v) ? stavke($v) : h($v)).'</div>'; }
$t = '<p>Ништа се не брише. Пре сваке измене стари фајл се копира ван сајта, у <code>'.h($kop).'</code>. Свака радња има своје дугме.</p>';

$t .= '<h2>1. Merlin: отпремање фајлова</h2>';
if (isset($st['merlin'])) $t .= kutija($st['merlin']);
else { $ms = (string)@file_get_contents($root.'/'.MERLIN);
  $t .= '<p><code>'.MERLIN.'</code> прима фајл било ког типа, без пријаве, па и PHP скрипту коју онда може да покрене. После закрпе прима само слике (jpg, png, webp, gif) и звук (mp3, wav, ogg, m4a, flac, webm…).</p>';
  $t .= $ms === '' ? '<div class="inf">Фајл не постоји.</div>' : (strpos($ms, OZNAKA) !== false ? '<div class="ok">Већ закрпљено.</div>' : forma($self, 'merlin', 'Закрпи Merlin отпремање')); }

$t .= '<h2>2. Фасцикле са отпремљеним фајловима</h2>';
$ud = upl_dirs($root); [$nadj, $pun] = upl_skripte($root, $ud, microtime(true) + 10);
if (isset($st['upload'])) $t .= kutija($st['upload']).'<p>Провера после измене: почетна '.h((string)($st['upload_provera'][0] ?? '?')).', птице '.h((string)($st['upload_provera'][1] ?? '?')).'.</p>';
else $t .= '<p>У ове фасцикле посетиоци и апликације отпремају слике и снимке. Додаје се правило да се ту никада не покреће скрипта, па чак и ако неко подметне PHP фајл, он неће радити:</p>'.stavke(array_map(fn($d)=>$d.'/', $ud)).forma($self, 'upload', 'Забрани скрипте у тим фасциклама');
$t .= '<h3>Провера: има ли већ подметнутих скрипти?</h3>';
if (isset($st['upload_skripte'])) $t .= kutija($st['upload_skripte']);
elseif (!$nadj) $t .= '<div class="ok">Нема ниједне скрипте у тим фасциклама'.($pun ? ' (провера није стигла до краја — освежи страницу)' : '').'. Добар знак.</div>';
else $t .= '<div class="err"><b>Нађено фајлова који не би смели да буду ту: '.count($nadj).'</b>. Пошаљи Claude-у снимак овог списка:<ul>'.implode('', array_map(fn($x)=>'<li><code>'.h($x['rel']).'</code> — '.h((string)$x['vel']).' B, '.h($x['vreme']).'<br><small>'.h($x['pocetak']).'</small></li>', $nadj)).'</ul></div>'.forma($self, 'upload-skripte', 'Премести их ван сајта', 'd');

$t .= '<h2>3. Теренски извештаји: брисање пријава</h2>';
$rf = rep_fajlovi($root);
if (isset($st['izvestaji'])) $t .= kutija($st['izvestaji']).'<p>Кључ за брисање пријава (сачуван и у <code>KLJUC-brisanje-izvestaja.txt</code> ван сајта):<br><span class="kljuc">'.h($st['rep_kljuc']).'</span></p>';
else $t .= '<p>У овим фајловима је кључ за брисање празан, па <b>свако</b> ко зна адресу може да обрише било коју теренску пријаву:</p>'.stavke($rf).'<p>После закрпе брисање ради само уз кључ, а слање нових пријава ради као и до сада.</p>'.($rf ? forma($self, 'izvestaji', 'Закључај брисање пријава') : '');

$t .= '<h2>4. Скрипте које покрећу обраде на серверу</h2>';
if (isset($st['pokretaci'])) { $pk = (string)$st['pok_kljuc'];
  $t .= kutija($st['pokretaci']).'<p>Кључ (сачуван и у <code>KLJUC-pokretanje-obrada.txt</code> ван сајта): <span class="kljuc">'.h($pk).'</span></p><p>Отварај их овако (после првог отварања прегледач памти кључ 12 сати):</p><ul>'.implode('', array_map(fn($x)=>'<li><code>https://piorajac.rs/'.h($x).'?k='.h($pk).'</code></li>', POKRETACI)).'</ul>'; }
else $t .= '<p>Ове странице свакоме дозвољавају да покрене тешке обраде на серверу (метео, корелације, iNaturalist). После закрпе из прегледача раде само уз кључ, а cron из командне линије ради као и до сада:</p>'.stavke(POKRETACI).'<p>Ове две раде само из командне линије (позивају их странице изнад):</p>'.stavke(RADNICI).forma($self, 'pokretaci', 'Заштити покретање обрада');

$t .= '<h2>5. Једнократне скрипте које раде чим се адреса отвори</h2>';
if (isset($st['jednokratne'])) $t .= kutija($st['jednokratne']);
else $t .= '<p><code>kopindeksi/final2026.php</code> (завршна поправка статистике парцела, већ урађена раније) и <code>kopindeksi/s2-audit-fixed.php</code> (провера Sentinel-2 снимака) крећу чим неко отвори адресу. Њихов посао је завршен, па их премештам ван сајта.</p>'.forma($self, 'jednokratne', 'Премести их ван сајта');

$t .= '<h2>6. Статистика парцела: провера после мог отварања final2026.php</h2>';
$fk = final_kopije($pd); $lg = $pd.'/_autofill_fast/final-http400-repair.log';
$rep = is_file($lg) ? array_slice(file($lg, FILE_IGNORE_NEW_LINES) ?: [], -6) : [];
$t .= '<p>Данас сам при провери отворио <code>kopindeksi/final2026.php</code>, па је скрипта поново урадила завршну поправку. Пре рада је сама направила копију података.</p>';
$t .= '<div class="inf">Данашње копије: '.($fk ? h(implode(', ', array_map('basename', $fk))) : '<b>нема</b>, па скрипта вероватно није ни почела да мења податке').'<br>Последњи редови дневника:'.($rep ? '<pre style="font-size:12px;white-space:pre-wrap">'.h(implode("\n", $rep)).'</pre>' : ' нема').'</div>';
if (isset($st['final_vraceno'])) $t .= kutija($st['final_vraceno']);
elseif ($fk) $t .= '<p>Ако у дневнику пише <code>FINAL COMPLETE</code>, поново је урађено исто што и први пут и бројке би требало да су исте. Ако желиш да подаци буду тачно онакви какви су били јутрос, врати копију. Садашње стање се пре враћања такође чува.</p>'.forma($self, 'final-vrati', 'Врати податке парцела на стање од јутрос', 's');

$t .= '<h2>7. WordPress пријава (по избору)</h2>';
if (isset($st['wp'])) $t .= kutija($st['wp']).(isset($st['wp_staro']) ? forma($self, 'wp-vrati', 'Поново отвори WordPress пријаву', 'd') : '');
else $t .= '<p>Сајт је статички, а WordPress се користи само за слање поште из формулара „Пошаљи допринос“ (<code>wp_mail</code>), за шта пријава није потребна. Ако WordPress администрацију више не отвараш, затвори <code>wp-login.php</code>, <code>xmlrpc.php</code> и <code>/wp-admin/</code>. Формулари и слике у <code>wp-content/uploads</code> раде и даље. Може да се врати једним кликом.</p>'.forma($self, 'wp', 'Затвори WordPress пријаву');

$t .= '<h2>8. Крај</h2><p>Кад завршиш и провериш сајт:</p>'.forma($self, 'kraj', 'Обриши ову скрипту', 'd');
strana('Безбедносна санација — други део', $t);
