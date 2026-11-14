<?php
// Само читање: попис зависности од WordPress-а. Брише се после употребе.
if (!hash_equals('__KLJUC__', (string)($_GET['k'] ?? ''))) { http_response_code(404); exit; }
@set_time_limit(100); header('Content-Type: application/json; charset=utf-8');
$P = '/home/piorajac/public_html'; $m = $_GET['m'] ?? 'root'; $out = [];
function cfg() { $c = @file_get_contents('/home/piorajac/public_html/wp-config.php'); $r = [];
  foreach (['DB_NAME','DB_USER','DB_PASSWORD','DB_HOST'] as $k) if (preg_match("~define\(\s*['\"]{$k}['\"]\s*,\s*['\"]([^'\"]*)['\"]~", $c, $mm)) $r[$k] = $mm[1];
  $r['prefix'] = preg_match('~\$table_prefix\s*=\s*[\'"]([^\'"]+)~', $c, $mm) ? $mm[1] : 'wp_'; return $r; }
if ($m === 'root') {
  foreach (scandir($P) as $f) if ($f !== '.' && $f !== '..') $out['root'][] = $f . (is_dir("$P/$f") ? '/' : '');
  $h = file_get_contents("$P/.htaccess"); $out['htaccess_len'] = strlen($h);
  $out['htaccess_wp'] = preg_match('~# BEGIN WordPress.*?# END WordPress~s', $h, $mm) ? $mm[0] : null;
  $out['directoryindex'] = preg_match_all('~^\s*DirectoryIndex.*$~mi', $h, $mm) ? $mm[0] : [];
  $out['index_php_head'] = substr((string)@file_get_contents("$P/index.php"), 0, 400);
  $c = cfg(); $out['db_name'] = $c['DB_NAME'] ?? null; $out['prefix'] = $c['prefix'];
  foreach (['plugins','themes','mu-plugins'] as $d) $out[$d] = is_dir("$P/wp-content/$d") ? array_values(array_diff(scandir("$P/wp-content/$d"), ['.','..'])) : [];
  $out['wp_content'] = array_values(array_diff(scandir("$P/wp-content"), ['.','..']));
}
if ($m === 'db') {
  $c = cfg(); $db = @new mysqli($c['DB_HOST'], $c['DB_USER'], $c['DB_PASSWORD'], $c['DB_NAME']);
  if ($db->connect_errno) { echo json_encode(['err' => $db->connect_error]); exit; }
  $db->set_charset('utf8mb4'); $p = $c['prefix'];
  $q = $db->query("SELECT ID,post_type,post_name,post_parent,LEFT(post_title,80) t FROM {$p}posts WHERE post_status='publish' AND post_type NOT IN ('attachment','revision','nav_menu_item','wp_global_styles','customize_changeset','oembed_cache','wp_navigation','wp_template','wp_template_part','custom_css','wp_font_family','wp_font_face')");
  while ($r = $q->fetch_assoc()) $out['posts'][] = $r;
  foreach (['siteurl','home','permalink_structure','active_plugins','template','stylesheet','page_on_front','show_on_front'] as $o) {
    $r = $db->query("SELECT option_value FROM {$p}options WHERE option_name='$o'")->fetch_row(); $out['opt'][$o] = $r[0] ?? null; }
  $r = $db->query("SHOW TABLES"); while ($t = $r->fetch_row()) $out['tables'][] = $t[0];
}
if ($m === 'scan') {
  $d = trim($_GET['d'] ?? '', '/'); $base = $d === '' ? $P : "$P/$d"; $deep = $d !== '';
  $pat = ['uploads' => '~wp-content/uploads/([^\s"\'\)<>?#\\\\]+)~', 'theme_plugin' => '~wp-content/(?:themes|plugins)/[^\s"\'\)<>]+~',
    'includes' => '~wp-includes/[^\s"\'\)<>]+~', 'core_php' => '~wp-load\.php|wp-config\.php|wp-blog-header\.php|\$wpdb|\bABSPATH\b|get_option\(|wp_remote_|add_action\(~',
    'wpapi' => '~wp-json|admin-ajax\.php|xmlrpc\.php|wp-login\.php|wp-admin/~', 'dbname' => '~' . preg_quote(cfg()['DB_NAME'] ?? 'zzzz', '~') . '~'];
  $skip = ['wp-admin','wp-includes','wp-content']; $n = 0;
  $it = $deep ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) : new ArrayIterator(array_map(fn($f) => new SplFileInfo("$P/$f"), array_diff(scandir($P), ['.','..'])));
  foreach ($it as $fi) { if (!$fi->isFile()) continue; $f = $fi->getPathname(); $rel = substr($f, strlen($P) + 1);
    if (!$deep && in_array(explode('/', $rel)[0], $skip)) continue;
    if (!preg_match('~\.(html?|php|js|css|json|xml|txt|svg|sh|htaccess|inc|tpl|md)$|/\.htaccess$~i', $f) || $fi->getSize() > 8e6) continue;
    $n++; $s = file_get_contents($f);
    foreach ($pat as $k => $re) if (preg_match_all($re, $s, $mm)) {
      if ($k === 'uploads') { foreach ($mm[1] as $u) $out['uploads'][urldecode($u)][] = $rel; }
      else $out[$k][$rel] = array_slice(array_values(array_unique($mm[0])), 0, 6);
    } }
  if (isset($out['uploads'])) foreach ($out['uploads'] as $u => $l) $out['uploads'][$u] = [count($l), array_slice(array_values(array_unique($l)), 0, 3), is_file("$P/wp-content/uploads/$u") ? filesize("$P/wp-content/uploads/$u") : -1];
  $out['scanned'] = $n;
}
if ($m === 'dirs') { foreach (scandir($P) as $f) if ($f[0] !== '.' && is_dir("$P/$f")) $out[] = $f; }
if ($m === 'up') { $t = 0; $c = 0; $byd = [];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$P/wp-content/uploads", FilesystemIterator::SKIP_DOTS)) as $fi) { if (!$fi->isFile()) continue; $t += $fi->getSize(); $c++;
    $k = explode('/', substr($fi->getPathname(), strlen("$P/wp-content/uploads/")))[0]; $byd[$k][0] = ($byd[$k][0] ?? 0) + 1; $byd[$k][1] = ($byd[$k][1] ?? 0) + $fi->getSize(); }
  $out = ['count' => $c, 'bytes' => $t, 'by' => $byd]; }
if ($m === 'big') { foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($P, FilesystemIterator::SKIP_DOTS)) as $fi) if ($fi->isFile() && $fi->getSize() > 50e6) $out[substr($fi->getPathname(), strlen($P)+1)] = round($fi->getSize()/1e6); arsort($out); }
if ($m === 'grep') { $d = trim($_GET['d'] ?? '', '/'); $re = '~' . str_replace('~', '\~', $_GET['q'] ?? 'x') . '~';
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$P/$d", FilesystemIterator::SKIP_DOTS)) as $fi) {
    if (!$fi->isFile() || $fi->getSize() > 8e6 || !preg_match('~\.(php|js|json|html?|txt|sh|css)$~i', $fi->getFilename())) continue;
    $s = file_get_contents($fi->getPathname()); if (preg_match_all($re, $s, $mm, PREG_OFFSET_CAPTURE)) { $o = $mm[0][0][1];
      $out[substr($fi->getPathname(), strlen($P)+1)] = [count($mm[0]), date('Y-m-d', $fi->getMTime()), substr($s, max(0, $o-150), 400)]; } } }
if ($m === 'mt') { $d = trim($_GET['d'] ?? '', '/'); $l = [];
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$P/$d", FilesystemIterator::SKIP_DOTS)) as $fi) if ($fi->isFile()) $l[substr($fi->getPathname(), strlen($P)+1)] = [date('Y-m-d H:i', $fi->getMTime()), $fi->getSize()];
  uasort($l, fn($a, $b) => strcmp($b[0], $a[0])); $out = array_slice($l, 0, (int)($_GET['n'] ?? 15), true); }
if ($m === 'read') { $f = realpath("$P/" . ($_GET['f'] ?? '')); if ($f && str_starts_with($f, $P . '/') && !preg_match('~wp-config|config\.local|token|secret|\.env~i', $f)) { $s = file_get_contents($f); $a = (int)($_GET['a'] ?? 0); $out = ['len' => strlen($s), 'txt' => substr($s, $a, (int)($_GET['n'] ?? 6000))]; } }
if ($m === 'log') { $f = '/home/piorajac/logs/' . basename($_GET['f'] ?? 'piorajac.rs-Nov-2026.gz'); $h = gzopen($f, 'r'); $agg = []; $n = 0;
  while ($h && ($l = gzgets($h, 8192)) !== false) { $n++; if (!preg_match('~^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) (\S+)[^"]*" (\d+) \S+ "[^"]*" "([^"]*)"~', $l, $mm)) continue;
    $p = preg_replace('~\?.*$~', '?…', $mm[4]); $ua = substr(preg_replace('~[\d.]+~', '', $mm[6]), 0, 60);
    $bot = preg_match('~bot|crawl|spider|Mozilla~i', $mm[6]); if ($bot && $mm[3] === 'GET') continue;
    $k = $mm[3] . ' ' . $p . ' | ' . $ua; $agg[$k] = ($agg[$k] ?? 0) + 1; }
  arsort($agg); $out = ['lines' => $n, 'top' => array_slice($agg, 0, 60, true)]; }
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
