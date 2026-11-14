<?php
// ПИО „Рајац“ — сређивање 14. 11. 2026. Привремена скрипта, брише се после употребе.
// Сваки корак: прво провера (без &apply=1), па примена. Резерва сваког измењеног фајла, дневник за враћање.
if (!hash_equals('__KLJUC__', (string)($_GET['k'] ?? ''))) { http_response_code(404); exit; }
@set_time_limit(115); ignore_user_abort(true);
header('Content-Type: text/plain; charset=utf-8');
$H = '/home/piorajac'; $P = "$H/public_html";
$REZ = "$H/_rezerva_s2_20261114"; $DN = "$REZ/dnevnik.jsonl";
$apply = ($_GET['apply'] ?? '') === '1'; $m = $_GET['m'] ?? ''; $t0 = microtime(true);

function dn_add(array $e) { global $DN, $REZ; if (!is_dir($REZ)) mkdir($REZ, 0700, true);
  file_put_contents($DN, json_encode($e + ['t' => date('c')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX); }
function dn_read() { global $DN; $l = []; if (is_file($DN)) foreach (file($DN, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $x) $l[] = json_decode($x, true); return $l; }
function rel($f) { global $H; return substr($f, strlen($H) + 1); }
function rezerva($f) { global $REZ; $d = "$REZ/" . rel($f); if (is_file($d)) return true; if (!is_dir(dirname($d))) mkdir(dirname($d), 0700, true); return copy($f, $d); }
function upisi($f, $s) { $perm = fileperms($f) & 0777; if (!rezerva($f)) return false;
  if (file_put_contents($f, $s) !== strlen($s)) return false; @chmod($f, $perm); dn_add(['op' => 'izmena', 'f' => $f]); return true; }
function strana($p) { global $P; $p = ltrim($p, '/'); if ($p === '' || str_ends_with($p, '/')) $p .= 'index.html'; return "$P/$p"; }
function izvestaj($apply, $f, $ok, $opis = '') { global $P; echo ($apply ? ($ok ? 'OK ' : 'ГРЕШКА ') : 'мењам ') . substr($f, strlen($P) + 1) . ($opis !== '' ? " — $opis" : '') . "\n"; }
function ulaz() { static $u = null; if ($u === null) { $u = json_decode((string)file_get_contents('php://input'), true) ?: []; } return $u; }

// --- преглед ---
if ($m === 'read') { $f = realpath("$P/" . ($_GET['f'] ?? '')); if ($f && str_starts_with($f, "$P/") && !preg_match('~config|kljuc|token|secret|\.env|key~i', basename($f))) { $s = file_get_contents($f); echo substr($s, (int)($_GET['a'] ?? 0), (int)($_GET['n'] ?? 6000)); } }
if ($m === 'grep') { $d = trim($_GET['d'] ?? '', '/'); $re = '~' . str_replace('~', '\~', $_GET['q'] ?? 'x') . '~u';
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$P/$d", FilesystemIterator::SKIP_DOTS)) as $fi) {
    if (microtime(true) - $t0 > 95) { echo "ПРЕКИД\n"; break; }
    if (!$fi->isFile() || $fi->getSize() > 8e6 || !preg_match('~\.(php|js|json|html?|css|txt)$~i', $fi->getFilename()) || str_contains($fi->getPathname(), '/_rezerva')) continue;
    $s = file_get_contents($fi->getPathname()); if (preg_match($re, $s, $mm, PREG_OFFSET_CAPTURE)) { $o = $mm[0][1];
      echo substr($fi->getPathname(), strlen($P) + 1) . " | " . str_replace("\n", ' ', substr($s, max(0, $o - 120), 320)) . "\n"; } } }
if ($m === 'nadji') { $d = trim($_GET['d'] ?? '', '/'); $n = mb_strtolower((string)($_GET['n'] ?? '')); $c = 0;
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$H/$d", FilesystemIterator::SKIP_DOTS)) as $fi) {
    if (microtime(true) - $t0 > 95) { echo "ПРЕКИД\n"; break; } $c++;
    if (str_contains(mb_strtolower($fi->getFilename()), $n)) echo rel($fi->getPathname()) . ' ' . $fi->getSize() . "\n"; }
  echo "прегледано $c\n"; }

// --- 1) враћање туђих адреса које је претходна замена погрешно променила (www.ekologija.gov.rs/assets/arhiva → .../wp-content/uploads) ---
if ($m === 'hostovi') {
  $lista = []; foreach (file("$H/arhiva-wordpress-20261113/dnevnik.jsonl", FILE_IGNORE_NEW_LINES) ?: [] as $x) { $e = json_decode($x, true);
    if (($e['op'] ?? '') === 'izmena' && str_starts_with($e['f'], "$P/")) $lista[] = substr($e['f'], strlen($P) + 1); }
  $lista = array_values(array_unique($lista));
  $o = (int)($_GET['o'] ?? 0); $n = 0; $i = $o;
  for (; $i < count($lista); $i++) { if (microtime(true) - $t0 > 90) break;
    $f = "$P/" . $lista[$i]; if (!is_file($f)) continue; $s = file_get_contents($f);
    $nov = preg_replace_callback('~((?:https?:)?(?:\\\\?/){2})([A-Za-z0-9.-]+)(\\\\?/)assets(\\\\?/)arhiva(\\\\?/)~', function ($mm) {
      $h = strtolower($mm[2]); if ($h === 'piorajac.rs' || $h === 'www.piorajac.rs') return $mm[0];
      return $mm[1] . $mm[2] . $mm[3] . 'wp-content' . $mm[4] . 'uploads' . $mm[5]; }, $s);
    $nov = str_replace('https://piorajac.rshttps://piorajac.rs/', 'https://piorajac.rs/', $nov);
    if ($nov === $s) continue; $n++;
    izvestaj($apply, $f, $apply ? upisi($f, $nov) : true, (substr_count($s, 'assets') - substr_count($nov, 'assets')) . ' адреса');
  }
  echo "ИЗМЕЊЕНО $n, ДАЉЕ " . ($i < count($lista) ? $i : 'КРАЈ') . " / " . count($lista) . "\n";
}


// Један облик линка ка почетној: „⊙ ПИО „РАЈАЦ““ — мењају се само ознака и текст унутар постојећег линка.
function pio_pocetna(string $s, &$staro = null, &$novo = null): ?string {
  if (!preg_match_all('~<a\b([^>]*\bhref\s*=\s*["\'](?:/|https://piorajac\.rs/?)["\'][^>]*)>(.*?)</a>~su', $s, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) return null;
  foreach ($mm as $m) {
    $inner = $m[2][0]; $txt = trim(preg_replace('~\s+~u', ' ', strip_tags($inner)));
    if (!preg_match('~ПИО\s*„(Рајац|РАЈАЦ)“~u', $txt)) continue;
    $n = $inner;
    $n = preg_replace('~<small\b[^>]*>.*?</small>~su', '', $n);
    $n = str_replace('ПИО „Рајац“', 'ПИО „РАЈАЦ“', $n);
    $n = preg_replace('~ПИО „РАЈАЦ“\s*(?:—|–|-)\s*почетна(?: страница| страна)?~u', 'ПИО „РАЈАЦ“', $n);
    $n = preg_replace('~ПИО „РАЈАЦ“\s*↗~u', 'ПИО „РАЈАЦ“', $n);
    $n = preg_replace('~(<span\b[^>]*>)\s*(?:Р|◆|⌂|◉|✦|⊙)\s*(</span>)~u', '$1⊙$2', $n, 1, $c1);
    if (!$c1) { $n = preg_replace('~^(\s*)(?:◉|←|⌂|◆|✦|⊙)\s*~u', '$1', $n); $n = preg_replace('~ПИО „РАЈАЦ“~u', '<span aria-hidden="true">⊙</span> ПИО „РАЈАЦ“', $n, 1, $c2); if (!$c2) $n = preg_replace('~^(\s*)~u', '$1<span aria-hidden="true">⊙</span> ', $n, 1); }
    else $n = preg_replace('~(<span\b(?![^>]*aria-hidden)([^>]*)>)⊙</span>~u', '<span aria-hidden="true"$2>⊙</span>', $n, 1);
    $staro = trim(preg_replace('~\s+~u', ' ', strip_tags($inner))); $novo = trim(preg_replace('~\s+~u', ' ', strip_tags($n)));
    if ($n === $inner) return null;
    return substr($s, 0, $m[2][1]) . $n . substr($s, $m[2][1] + strlen($inner));
  }
  return null;
}

function lista_post() { $u = ulaz(); return is_array($u) ? $u : []; }
function petlja(array $stavke, callable $f) { global $t0, $apply; $o = (int)($_GET['o'] ?? 0); $n = 0; $i = $o; $kljucevi = array_keys($stavke);
  for (; $i < count($kljucevi); $i++) { if (microtime(true) - $t0 > 90) break; $k = $kljucevi[$i]; $r = $f($k, $stavke[$k]); if ($r) $n++; }
  echo "ИЗМЕЊЕНО $n, ДАЉЕ " . ($i < count($kljucevi) ? $i : 'КРАЈ') . " / " . count($kljucevi) . "\n"; }

// --- 2) један облик линка ка почетној (POST: списак страница) ---
if ($m === 'pocetna') { petlja(lista_post(), function ($i, $p) use ($apply) { $f = strana($p); if (!is_file($f)) { echo "НЕМА $p\n"; return false; }
  $s = file_get_contents($f); $n = pio_pocetna($s, $st, $nv); if ($n === null) { echo "без промене $p\n"; return false; }
  izvestaj($apply, $f, $apply ? upisi($f, $n) : true, "$st → $nv"); return true; }); }

// --- 3) невидљив главни наслов h1 (POST: {страница: текст}) ---
if ($m === 'h1') { petlja(lista_post(), function ($p, $t) use ($apply) { $f = strana($p); if (!is_file($f)) { echo "НЕМА $p\n"; return false; }
  $s = file_get_contents($f); if (preg_match('~<h1\b~i', $s)) { echo "већ има h1 $p\n"; return false; }
  $h = '<h1 style="position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0">' . htmlspecialchars($t, ENT_QUOTES) . '</h1>';
  $n = preg_replace('~(<body\b[^>]*>)~i', "$1\n$h", $s, 1, $c); if (!$c) { echo "НЕМА body $p\n"; return false; }
  izvestaj($apply, $f, $apply ? upisi($f, $n) : true, $t); return true; }); }

// --- 4) рад о дневним лептирима: поглавља h1 → h2, подпоглавља h2 → h3 (изглед остаје исти) ---
if ($m === 'leptiri') { $f = strana('rajac-vesti/radovi/dnevni-leptiri-pio-rajac-2026/'); $s = file_get_contents($f);
  $a = strpos($s, '<main class="doc">'); $b = strpos($s, '</main>', (int)$a);
  if ($a === false || $b === false || substr_count($s, '<h1') !== 15) exit("НЕ ПРЕПОЗНАЈЕМ\n");
  $doc = substr($s, $a, $b - $a); $doc = preg_replace(['~<h2\b~', '~</h2>~'], ['<h3', '</h3>'], $doc); $doc = preg_replace(['~<h1\b~', '~</h1>~'], ['<h2', '</h2>'], $doc);
  $n = substr($s, 0, $a) . $doc . substr($s, $b);
  $n = str_replace(['.doc h2{', '.doc h1{', '.doc section:first-child h1{'], ['.doc h3{', '.doc h2{', '.doc section:first-child h2{'], $n);
  echo "h1 пре: 15, после: " . substr_count($n, '<h1') . "\n"; izvestaj($apply, $f, $apply ? upisi($f, $n) : true); }

// --- 5) опис за Google (POST: {страница: опис}) ---
if ($m === 'opis') { petlja(lista_post(), function ($p, $t) use ($apply) { $f = strana($p); if (!is_file($f)) { echo "НЕМА $p\n"; return false; }
  $s = file_get_contents($f); if (preg_match('~<meta\s[^>]*name\s*=\s*["\']description["\']~i', $s)) { echo "већ има $p\n"; return false; }
  $meta = '<meta name="description" content="' . htmlspecialchars($t, ENT_QUOTES) . '">';
  $n = preg_replace('~(</title>)~i', "$1\n$meta", $s, 1, $c); if (!$c) $n = preg_replace('~(<head\b[^>]*>)~i', "$1\n$meta", $s, 1, $c);
  if (!$c) { echo "НЕМА head $p\n"; return false; } izvestaj($apply, $f, $apply ? upisi($f, $n) : true); return true; }); }

// --- 6) туђе слике на наш сервер (POST: {адреса: путања у public_html}) ---
if ($m === 'slike_preuzmi') { global $REZ; $mapa = is_file("$REZ/slike_mapa.json") ? json_decode(file_get_contents("$REZ/slike_mapa.json"), true) : [];
  petlja(lista_post(), function ($u, $rel) use ($apply, &$mapa, $P) { $f = "$P/$rel"; if (is_file($f) && filesize($f) > 500) { $mapa[$u] = $rel; return false; }
    if (!$apply) { echo "преузимам $u → $rel\n"; return true; }
    $ch = curl_init($u); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 6, CURLOPT_TIMEOUT => 40,
      CURLOPT_USERAGENT => 'PIO-Rajac-site/1.0 (https://piorajac.rs/; office@piorajac.rs)']);
    $b = curl_exec($ch); $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE); $tip = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($kod !== 200 || !$b || !preg_match('~^image/~', $tip)) { echo "НЕУСПЕХ $kod $tip $u\n"; return false; }
    if (!is_dir(dirname($f))) mkdir(dirname($f), 0755, true); file_put_contents($f, $b); chmod($f, 0644); $mapa[$u] = $rel; dn_add(['op' => 'nov', 'f' => $f]);
    echo "OK " . round(strlen($b) / 1024) . " KB $rel\n"; usleep(300000); return true; });
  if ($apply) file_put_contents("$REZ/slike_mapa.json", json_encode($mapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); echo "у мапи: " . count($mapa) . "\n"; }
if ($m === 'slike_zameni') { global $REZ; $mapa = json_decode((string)@file_get_contents("$REZ/slike_mapa.json"), true) ?: [];
  $z = []; foreach ($mapa as $u => $rel) { $z[$u] = "/$rel"; $z[htmlspecialchars($u, ENT_QUOTES)] = "/$rel"; $z[str_replace('/', '\/', $u)] = str_replace('/', '\/', "/$rel"); }
  $d = trim($_GET['d'] ?? '', '/'); $lista = [];
  if ($d === '.') { foreach (scandir($P) as $x) if (is_file("$P/$x")) $lista[] = "$P/$x"; }
  else foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$P/$d", FilesystemIterator::SKIP_DOTS)) as $fi) if ($fi->isFile() && !str_contains($fi->getPathname(), '/_rezerva')) $lista[] = $fi->getPathname();
  sort($lista); petlja($lista, function ($i, $f) use ($apply, $z) { if (!preg_match('~\.(html?|php|js|json|css)$~i', $f) || filesize($f) > 20e6) return false;
    $s = file_get_contents($f); if (!preg_match('~wikimedia|rajac\.rs/wp-content|wklcdn|ytimg|davisinstruments|science\.nasa~', $s)) return false;
    $n = strtr($s, $z); if ($n === $s) return false; izvestaj($apply, $f, $apply ? upisi($f, $n) : true); return true; }); }

// --- 7) тачне замене у појединачним фајловима (POST: {фајл: [[старо, ново], ...]}) ---
if ($m === 'zameni') { foreach (lista_post() as $p => $parovi) { $f = "$P/" . ltrim($p, '/'); if (!is_file($f)) { echo "НЕМА $p\n"; continue; }
  $s = file_get_contents($f); $n = $s; $ok = true;
  foreach ($parovi as [$a, $b]) { $c = substr_count($n, $a); if ($c < 1) { echo "НЕ НАЛАЗИМ у $p: " . substr($a, 0, 80) . "\n"; $ok = false; continue; } $n = str_replace($a, $b, $n); echo "  $c× у $p\n"; }
  if ($ok && $n !== $s) izvestaj($apply, $f, $apply ? upisi($f, $n) : true); } }

if ($m === 'vrati') { foreach (array_reverse(dn_read()) as $e) if ($e['op'] === 'izmena') echo $apply ? (copy("$REZ/" . rel($e['f']), $e['f']) ? "вратио {$e['f']}\n" : "ГРЕШКА {$e['f']}\n") : "враћам {$e['f']}\n";
  if ($apply) rename($DN, $DN . '.vraceno-' . date('His')); }

echo "\n(" . round(microtime(true) - $t0, 1) . " s)\n";
