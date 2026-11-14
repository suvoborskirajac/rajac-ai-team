<?php
// ПИО „Рајац“ — искључивање WordPress-а (13. 11. 2026). Привремена скрипта, брише се после употребе.
// Сваки корак: прво провера (без &apply=1), па примена. Ништа се не брише: премешта се у архиву, измењени фајлови имају резерву.
if (!hash_equals('__KLJUC__', (string)($_GET['k'] ?? ''))) { http_response_code(404); exit; }
@set_time_limit(110); ignore_user_abort(true);
header('Content-Type: text/plain; charset=utf-8');
$H = '/home/piorajac'; $P = "$H/public_html";
$ARH = "$H/arhiva-wordpress-20261113";          // архива ван сајта
$REZ = "$H/_rezerva_wpout_20261113";            // резерве измењених фајлова
$PRIV = "$H/rajac-private";
$DN = "$ARH/dnevnik.jsonl";
$apply = ($_GET['apply'] ?? '') === '1';
$faza = $_GET['faza'] ?? '';
$t0 = microtime(true);

function dn_add(array $e) { global $DN, $ARH; if (!is_dir($ARH)) mkdir($ARH, 0700, true);
  file_put_contents($DN, json_encode($e + ['t' => date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX); }
function dn_read() { global $DN; $l = []; if (is_file($DN)) foreach (file($DN, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $x) $l[] = json_decode($x, true); return $l; }
function rezerva($f) { global $P, $H, $REZ; $rel = str_starts_with($f, "$P/") ? 'public_html/' . substr($f, strlen($P) + 1) : substr($f, strlen($H) + 1);
  $d = "$REZ/$rel"; if (is_file($d)) return true; if (!is_dir(dirname($d))) mkdir(dirname($d), 0700, true);
  return copy($f, $d); }
function upisi($f, $s) { $perm = fileperms($f) & 0777; if (!rezerva($f)) return false;
  if (file_put_contents($f, $s) !== strlen($s)) return false; @chmod($f, $perm); dn_add(['op' => 'izmena', 'f' => $f]); return true; }
function premesti($a, $b, $apply) { if (!file_exists($a)) return "НЕМА $a";
  if (file_exists($b)) return "ВЕЋ ПОСТОЈИ $b";
  if (!$apply) return "премештам $a → $b";
  if (!is_dir(dirname($b))) mkdir(dirname($b), 0700, true);
  if (!rename($a, $b)) return "ГРЕШКА rename $a";
  dn_add(['op' => 'premesti', 'od' => $a, 'u' => $b]); return "OK $a → $b"; }
function kopiraj_dir($a, $b) { $n = 0; if (!is_dir($b)) mkdir($b, 0755, true);
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($a, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $fi) {
    $t = $b . '/' . substr($fi->getPathname(), strlen($a) + 1);
    if ($fi->isDir()) { if (!is_dir($t)) mkdir($t, 0755, true); } elseif (!is_file($t)) { copy($fi->getPathname(), $t); chmod($t, 0644); $n++; } }
  return $n; }

// Замене у тексту — исти редослед свуда.
$ZAMENE = [
  'wp-content/plugins/rajac-light-monitor-static/assets/' => 'assets/svetlosno-monitor/',
  'wp-content\/plugins\/rajac-light-monitor-static\/assets\/' => 'assets\/svetlosno-monitor\/',
  'wp-content/plugins/ptice_plugin_v2/rajac-bird-photo.php' => 'rajac-bird-photo.php',
  'wp-content\/plugins\/ptice_plugin_v2\/rajac-bird-photo.php' => 'rajac-bird-photo.php',
  'wp-content/uploads/' => 'assets/arhiva/',
  'wp-content\/uploads\/' => 'assets\/arhiva\/',
];

if ($faza === 'a') {
  // 1) пошта без WordPress-а
  $mail = "$PRIV/pio-mail.php";
  echo is_file($mail) ? "OK постоји $mail (" . filesize($mail) . " B)\n" : "НЕДОСТАЈЕ $mail — прво га отпремити\n";
  // 2) обрасци: wp-load.php → pio-mail.php
  $obrasci = ['posalji-doprinos.php', 'biljke/posalji-doprinos.php', 'biljke/posalji-istrazivanje.php', 'gljive/posalji-doprinos.php',
              'insekti/posalji-doprinos.php', 'ptice/posalji-doprinos.php', 'sisari/posalji-doprinos.php'];
  foreach ($obrasci as $o) { $f = "$P/$o"; if (!is_file($f)) { echo "НЕМА $o\n"; continue; }
    $s = file_get_contents($f);
    $n = preg_replace('~\$wpLoads\s*=\s*\[[^\]]*wp-load\.php[^\]]*\];~', "\$wpLoads = ['$mail']; // раније wp-load.php — WordPress искључен 13. 11. 2026.", $s, 1, $c);
    if (!$c) { echo (str_contains($s, $mail) ? "већ урађено" : "НЕ ПРЕПОЗНАЈЕМ") . " $o\n"; continue; }
    echo $apply ? ((is_file($mail) && upisi($f, $n)) ? "OK $o\n" : "ГРЕШКА $o\n") : "мењам $o\n"; }
  // 3) eBird кључ из wp-config.php у приватни фајл
  $kf = "$PRIV/ebird-kljuc.php"; $cfg = (string)@file_get_contents("$P/wp-config.php");
  $ima = preg_match("~define\(\s*['\"]RAJAC_EBIRD_API_KEY['\"]\s*,\s*['\"]([^'\"]+)['\"]~", $cfg, $mm);
  echo $ima ? "eBird кључ у wp-config.php: има (" . strlen($mm[1]) . " знакова)\n" : "eBird кључ у wp-config.php: НЕМА\n";
  if ($ima && !is_file($kf)) { if ($apply) { file_put_contents($kf, "<?php\n// eBird API кључ (премештен из wp-config.php 13. 11. 2026). Не објављивати.\nif (!defined('RAJAC_EBIRD_API_KEY')) define('RAJAC_EBIRD_API_KEY', '" . addslashes($mm[1]) . "');\n"); chmod($kf, 0600); dn_add(['op' => 'nov', 'f' => $kf]); echo "OK $kf\n"; } else echo "правим $kf\n"; }
  foreach (['ebird-l7416435-data.php', 'ptice/ebird-l7416435-data.php'] as $o) { $f = "$P/$o"; if (!is_file($f)) { echo "НЕМА $o\n"; continue; }
    $s = file_get_contents($f); if (str_contains($s, $kf)) { echo "већ урађено $o\n"; continue; }
    $n = str_replace("    if (defined('RAJAC_EBIRD_API_KEY')) return;\n", "    if (defined('RAJAC_EBIRD_API_KEY')) return;\n    if (is_file('$kf')) { require_once '$kf'; return; } // без WordPress-а, 13. 11. 2026.\n", $s, $c);
    if ($c !== 1) { echo "НЕ ПРЕПОЗНАЈЕМ $o\n"; continue; }
    echo $apply ? (upisi($f, $n) ? "OK $o\n" : "ГРЕШКА $o\n") : "мењам $o\n"; }
  // 4) Светлосни монитор: датотеке из додатка у /assets/svetlosno-monitor/
  $src = "$P/wp-content/plugins/rajac-light-monitor-static/assets"; $dst = "$P/assets/svetlosno-monitor";
  if (!is_dir($src)) echo "НЕМА $src\n";
  else { $l = []; foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS)) as $fi) $l[] = substr($fi->getPathname(), strlen($src) + 1);
    echo "Светлосни монитор: " . count($l) . " датотека: " . implode(', ', $l) . "\n";
    if ($apply) { $n = kopiraj_dir($src, $dst); dn_add(['op' => 'kopija', 'od' => $src, 'u' => $dst]); echo "OK копирано $n у $dst\n"; } }
  // 5) .htaccess: шта помиње index.php / ErrorDocument / wp-
  foreach (preg_split('~\n~', (string)file_get_contents("$P/.htaccess")) as $i => $l) if (preg_match('~index\.php|ErrorDocument|wp-|RewriteBase~i', $l)) echo ".htaccess " . ($i + 1) . ": " . trim($l) . "\n";
}

if ($faza === 'b') {
  // .htaccess преусмерења + премештање uploads у /assets/arhiva/
  $ht = "$P/.htaccess"; $s = file_get_contents($ht);
  $blok = "# BEGIN PIO-PREUSMERENJA (13. 11. 2026)\n<IfModule mod_rewrite.c>\nRewriteEngine On\n"
    . "# www → без www (само прегледање страница)\n"
    . "RewriteCond %{HTTP_HOST} ^www\\.piorajac\\.rs$ [NC]\nRewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$\nRewriteCond %{REQUEST_URI} !^/\\.well-known/\nRewriteRule ^(.*)$ https://piorajac.rs/$1 [R=301,L]\n"
    . "# http → https (само прегледање страница; .php адресе уређаја и обрасци се не дирају)\n"
    . "RewriteCond %{HTTP_HOST} ^piorajac\\.rs$ [NC]\nRewriteCond %{HTTPS} !=on\nRewriteCond %{HTTP:X-Forwarded-Proto} !=https\nRewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$\nRewriteCond %{REQUEST_URI} !\\.php$\nRewriteCond %{REQUEST_URI} !^/\\.well-known/\nRewriteRule ^(.*)$ https://piorajac.rs/$1 [R=301,L]\n"
    . "# старе WordPress адресе слика и датотека → нова архива\n"
    . "RewriteRule ^wp-content/uploads/(.*)$ /assets/arhiva/$1 [R=301,L]\n"
    . "RewriteRule ^wp-content/plugins/rajac-light-monitor-static/assets/(.*)$ /assets/svetlosno-monitor/$1 [R=301,L]\n"
    . "RewriteRule ^wp-content/plugins/ptice_plugin_v2/rajac-bird-photo\\.php$ /rajac-bird-photo.php [R=301,L,QSA]\n"
    . "</IfModule>\n# END PIO-PREUSMERENJA\n\n";
  if (str_contains($s, '# BEGIN PIO-PREUSMERENJA')) echo "већ постоји блок у .htaccess\n";
  elseif (!$apply) echo "додајем на врх .htaccess:\n$blok";
  else { echo upisi($ht, $blok . $s) ? "OK .htaccess\n" : "ГРЕШКА .htaccess\n"; }
  $up = "$P/wp-content/uploads"; $ar = "$P/assets/arhiva";
  echo premesti($up, $ar, $apply) . "\n";
  // ствари које не треба да буду јавне
  $base = $apply ? $ar : $up;
  if (is_dir($base)) foreach (scandir($base) as $f) if (preg_match('~^(backup|mwai_.*\.log|webp-converter-for-media-test.*|\.htaccess)$~', $f))
    echo premesti("$base/$f", "$ARH/uploads-nejavno/$f", $apply) . "\n";
  if ($apply && is_dir($ar)) { file_put_contents("$ar/.htaccess", "# Архива слика и датотека — без извршавања PHP-а\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\nOptions -Indexes\n"); dn_add(['op' => 'nov', 'f' => "$ar/.htaccess"]); echo "OK $ar/.htaccess\n"; }
}

if ($faza === 'lista') { foreach (scandir($P) as $f) if ($f[0] !== '.' && is_dir("$P/$f") && !in_array($f, ['wp-admin', 'wp-includes', 'wp-content'])) echo "$f\n"; }

if ($faza === 'r') {
  // замене у текстуалним фајловима једне фасцикле (d=. — само фајлови у корену). Наставља се од o=.
  $d = trim($_GET['d'] ?? '', '/'); $o = (int)($_GET['o'] ?? 0);
  if ($d === '' || str_contains($d, '..')) exit("d?\n");
  $lista = [];
  if ($d === '.') { foreach (scandir($P) as $f) if (is_file("$P/$f")) $lista[] = "$P/$f"; }
  else { $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator("$P/$d", FilesystemIterator::SKIP_DOTS),
      function ($c) { $n = $c->getFilename(); return !($c->isDir() && (str_starts_with($n, '_rezerva') || str_starts_with($n, '_karantin') || $n === 'node_modules' || $n === '.git')); }));
    foreach ($it as $fi) if ($fi->isFile()) $lista[] = $fi->getPathname(); }
  sort($lista); $izm = 0; $prov = 0; $i = $o;
  for (; $i < count($lista); $i++) {
    if (microtime(true) - $t0 > 75) break;
    $f = $lista[$i];
    if (!preg_match('~\.(html?|php[0-9]?|js|mjs|css|json|geojson|xml|txt|md|svg|csv|sh|inc|tpl|htaccess)$|/\.htaccess$~i', $f) || filesize($f) > 30e6) continue;
    if (isset($_GET['x'])) { $ext = str_replace(',', '|', preg_replace('~[^a-z0-9,]~', '', (string)$_GET['x'])); if ($ext === '' || !preg_match('~\.(' . $ext . ')$~i', $f)) continue; }
    if (str_contains($f, '/_claude_')) continue;
    $prov++; $s = file_get_contents($f); if (!str_contains($s, 'wp-content') || str_contains($s, '# BEGIN PIO-PREUSMERENJA')) continue;
    $n = strtr($s, $ZAMENE); if ($n === $s) continue;
    $izm++; $rel = substr($f, strlen($P) + 1);
    if ($apply) { if (!upisi($f, $n)) echo "ГРЕШКА $rel\n"; }
    if ($izm <= 400) echo ($apply ? 'OK ' : 'мењам ') . $rel . "\n";
  }
  echo "ПРЕГЛЕДАНО $prov, ИЗМЕЊЕНО $izm, ДАЉЕ " . ($i < count($lista) ? $i : 'КРАЈ') . " / " . count($lista) . "\n";
}

if ($faza === 'c') {
  // WordPress у архиву ван сајта
  $l = ['wp-admin', 'wp-includes', 'wp-content', 'index.php', 'wp-activate.php', 'wp-blog-header.php', 'wp-comments-post.php', 'wp-config-sample.php',
        'wp-config.php', 'wp-cron.php', 'wp-links-opml.php', 'wp-load.php', 'wp-login.php', 'wp-mail.php', 'wp-settings.php', 'wp-signup.php',
        'wp-trackback.php', 'xmlrpc.php', 'readme.html', 'license.txt', 'licenca.txt'];
  foreach ($l as $f) if (file_exists("$P/$f")) echo premesti("$P/$f", "$ARH/public_html/$f", $apply) . "\n";
  if (is_dir("$P/monitoring/admin-dashboard")) echo premesti("$P/monitoring/admin-dashboard", "$ARH/public_html/monitoring/admin-dashboard", $apply) . "\n";
}

if ($faza === 'vrati') {
  // враћање уназад по дневнику
  $l = array_reverse(dn_read());
  foreach ($l as $e) {
    if ($e['op'] === 'premesti') echo $apply ? (rename($e['u'], $e['od']) ? "вратио $e[od]\n" : "ГРЕШКА $e[od]\n") : "враћам $e[od]\n";
    if ($e['op'] === 'izmena') { $rel = str_starts_with($e['f'], "$P/") ? 'public_html/' . substr($e['f'], strlen($P) + 1) : substr($e['f'], strlen($H) + 1);
      echo $apply ? (copy("$REZ/$rel", $e['f']) ? "вратио садржај $e[f]\n" : "ГРЕШКА $e[f]\n") : "враћам садржај $e[f]\n"; }
  }
  if ($apply) rename($DN, $DN . '.vraceno-' . date('His'));
}

if ($faza === 'dnevnik') { $l = dn_read(); $c = [];
  foreach ($l as $e) $c[$e['op']] = ($c[$e['op']] ?? 0) + 1; echo json_encode($c, JSON_UNESCAPED_UNICODE) . "\n"; }

if ($faza === 'nadji') {
  // тражи датотеке по имену (n=имена одвојена са |) у фасцикли d (у односу на /home/piorajac)
  $d = trim($_GET['d'] ?? '', '/'); $imena = array_filter(explode('|', mb_strtolower((string)($_GET['n'] ?? ''))));
  if ($d === '' || str_contains($d, '..') || !$imena) exit("?\n");
  $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator("$H/$d", FilesystemIterator::SKIP_DOTS),
    function ($c) { return !($c->isDir() && in_array($c->getFilename(), ['kopindeksi', 'monitoring-izvestaji', 'mail', '.cpanel', '.trash', 'tmp', 'node_modules'])); }));
  $n = 0; foreach ($it as $fi) { if (microtime(true) - $t0 > 95) { echo "ПРЕКИД (време)\n"; break; } $n++;
    if (in_array(mb_strtolower($fi->getFilename()), $imena, true)) echo substr($fi->getPathname(), strlen($H) + 1) . " " . $fi->getSize() . "\n"; }
  echo "прегледано $n\n"; }
echo "\n(" . round(microtime(true) - $t0, 1) . " s)\n";
