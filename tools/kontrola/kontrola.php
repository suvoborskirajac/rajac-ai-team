<?php
/**
 * ПИО „Рајац“ — КОНТРОЛНА СКРИПТА
 * ------------------------------------------------------------------
 * Само ЧИТА јавне странице сајта и пописује грешке. Ништа не мења,
 * ништа не брише, ништа не шаље ван сервера.
 *
 * Поставља се у:  public_html/kontrola/kontrola.php
 * Покреће се:     https://piorajac.rs/kontrola/kontrola.php?token=TOKEN
 *
 * Обрађује странице у серијама (да не дође до 504), а страница се
 * сама освежава док не заврши. Резултати остају у kontrola/rezultati/.
 *
 * Провере по страници:
 *   1. речи у којима се мешају ћирилична и латинична слова
 *   2. латиничне речи на ћириличним страницама (ван курзива и кода)
 *   3. фонтови (Google Fonts и font-family у страници и локалним CSS-овима)
 *   4. варијанта главног менија (први <nav> или <header>)
 *   5. линк ка почетној ПИО „Рајац“ у заглављу — текст и постојање
 *   6. target="_blank"
 *   7. мртви унутрашњи линкови
 *   8. слике са туђих адреса и слике без alt описа
 *   9. наслов (title), број h1, viewport, canonical, meta description
 *
 * Верзија 1.0 — 14. 10. 2026.
 */

declare(strict_types=1);

/* ============ ПОДЕШАВАЊА ============ */

const TOKEN       = 'rajac-kontrola-7q4m2x9w';
const BATCH       = 120;            // страница по једном захтеву
const MAX_LINKS   = 3000;           // највише мртвих линкова у извештају
const MAX_SAMPLES = 8;              // примера по страници за сваку проверу

/* Фолдери (по имену, на било ком нивоу) који се не читају. */
const SKIP_DIRS = [
    'ris', 'baza', 'kopindeksi', 'monitoring-izvestaji', 'inventar', 'kontrola',
    'wp-content', 'wp-admin', 'wp-includes', 'cgi-bin', 'node_modules', 'vendor',
    '.git', '.well-known', 'cache', 'tmp', 'temp', 'logs', 'log', 'trash', '.trash',
    'backup', 'backups', '_backup', '_arhiva', '_old', 'old', 'staro', '_seo_backup',
];

/* Латиничне речи које су дозвољене у ћириличном тексту (мала слова). */
const ALLOW_LATIN = [
    'pio', 'rajac', 'piorajac', 'rs', 'www', 'http', 'https', 'html', 'php', 'css', 'js',
    'gps', 'gis', 'kml', 'gpx', 'pdf', 'csv', 'json', 'api', 'url', 'qr', 'ai', 'id',
    'iucn', 'lc', 'nt', 'vu', 'en', 'cr', 'dd', 'ne', 'ndvi', 'ndmi', 'reci', 'vci', 'ndwi',
    'fwi', 'ffmc', 'dmc', 'dc', 'isi', 'bui', 'sqm', 'viirs', 'dnb', 'noaa', 'nasa',
    'firms', 'modis', 'esa', 'alan', 'cct', 'ulor', 'bortle', 'led', 'uv', 'et', 'gdd',
    'sentinel', 'copernicus', 'landsat', 'sdgsat', 'ebird', 'inaturalist', 'xeno', 'canto',
    'merlin', 'birdnet', 'audiomoth', 'gbif', 'powo', 'ioc', 'wikimedia', 'commons',
    'wikipedia', 'cc', 'by', 'sa', 'davis', 'vantage', 'pro', 'weatherlink', 'leaflet',
    'google', 'smart', 'cost', 'insectai', 'ams', 'mos', 'km', 'cm', 'mm', 'm', 'ha',
    'kg', 'g', 'mg', 'h', 'min', 's', 'hz', 'khz', 'db', 'nm', 'mag', 'arcsec', 'lx',
    'cd', 'k', 'w', 'kw', 'mw', 'mb', 'kb', 'gb', 'px', 'l', 'ml',
];

/* ============ ПОКРЕТАЊЕ ============ */

@ini_set('display_errors', '0');
date_default_timezone_set('Europe/Belgrade');
@set_time_limit(60);
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!isset($_GET['token']) || !hash_equals(TOKEN, (string)$_GET['token'])) {
    http_response_code(403);
    exit('Приступ није дозвољен.');
}

$ROOT    = realpath(dirname(__DIR__));          // public_html
$OUT     = __DIR__ . '/rezultati';
$STATE   = $OUT . '/stanje.json';
$tok     = rawurlencode(TOKEN);
$self    = strtok((string)$_SERVER['REQUEST_URI'], '?');

if (!is_dir($OUT)) {
    @mkdir($OUT, 0755, true);
}
if (!is_file($OUT . '/.htaccess')) {
    @file_put_contents($OUT . '/.htaccess',
        "Options -Indexes\n<IfModule mod_headers.c>\nHeader set X-Robots-Tag \"noindex, nofollow\"\n</IfModule>\n");
}

if (isset($_GET['obrisi'])) {
    foreach (glob($OUT . '/*') ?: [] as $f) { if (is_file($f)) { @unlink($f); } }
    @unlink($OUT . '/.htaccess');
    @rmdir($OUT);
    page('Обрисано', '<p>Резултати и стање су обрисани. Саму скрипту обриши у File Manager-у: '
        . '<code>public_html/kontrola/kontrola.php</code>.</p>');
    exit;
}

if (isset($_GET['reset']) || !is_file($STATE)) {
    $files = collect($ROOT);
    $state = ['files' => $files, 'pos' => 0, 'start' => date('d. m. Y. H:i'), 'pages' => [], 'css' => []];
    save($STATE, $state);
    refresh($self, $tok, 0, count($files));
    exit;
}

$state = json_decode((string)file_get_contents($STATE), true);
if (!is_array($state)) {
    page('Грешка', '<p>Стање је оштећено. <a href="?token=' . $tok . '&reset=1">Почни испочетка</a>.</p>');
    exit;
}

$total = count($state['files']);
if ($state['pos'] < $total) {
    $end = min($total, $state['pos'] + BATCH);
    for ($i = $state['pos']; $i < $end; $i++) {
        $rel = $state['files'][$i];
        $state['pages'][$rel] = analyze($ROOT, $rel, $state['css']);
    }
    $state['pos'] = $end;
    save($STATE, $state);
    if ($end < $total) {
        refresh($self, $tok, $end, $total);
        exit;
    }
    write_reports($OUT, $state);
    save($STATE, $state);
}

$base = rtrim(dirname($self), '/');
page('Контрола завршена',
    '<p>Прочитано страница: <b>' . $total . '</b>.</p>'
    . '<p><a class="b" href="' . h($base) . '/rezultati/izvestaj.html">Отвори извештај</a></p>'
    . '<p>За слање Claude-у преузми: '
    . '<a href="' . h($base) . '/rezultati/kontrola.json">kontrola.json</a> и '
    . '<a href="' . h($base) . '/rezultati/stranice.csv">stranice.csv</a>.</p>'
    . '<p>Ново читање: <a href="?token=' . $tok . '&reset=1">почни испочетка</a> · '
    . 'Брисање резултата: <a href="?token=' . $tok . '&obrisi=1">обриши резултате</a></p>');
exit;

/* ============ ПОПИС ФАЈЛОВА ============ */

function collect(string $root): array
{
    $out = [];
    $skip = array_flip(SKIP_DIRS);
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($cur) use ($skip) {
                $name = $cur->getFilename();
                if ($cur->isDir()) {
                    return !isset($skip[strtolower($name)]) && stripos($name, '.bak') === false;
                }
                if (preg_match('#\.bak|\.old$|~$#i', $name)) { return false; }
                return (bool)preg_match('~\.html?$~i', $name);
            }
        )
    );
    foreach ($it as $f) {
        $out[] = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($root))), '/');
    }
    sort($out, SORT_STRING);
    return $out;
}

/* ============ ПРОВЕРА ЈЕДНЕ СТРАНИЦЕ ============ */

function analyze(string $root, string $rel, array &$cssCache): array
{
    $abs  = $root . '/' . $rel;
    $html = (string)@file_get_contents($abs);
    $r = [
        'folder' => folder_of($rel), 'bytes' => strlen($html),
        'title' => '', 'h1' => 0, 'viewport' => false, 'canonical' => false, 'description' => false,
        'script' => 'празно', 'mixed' => [], 'mixed_n' => 0, 'latin' => [], 'latin_n' => 0,
        'fonts' => [], 'menu' => '', 'menu_sig' => '', 'home_link' => '', 'blank' => 0,
        'broken' => [], 'ext_img' => [], 'img_noalt' => 0,
    ];
    if ($html === '') { return $r; }

    if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
        $r['title'] = trim(clean_text($m[1]));
    }
    $r['h1']          = preg_match_all('~<h1[\s>]~i', $html);
    $r['viewport']    = (bool)preg_match('~<meta[^>]+name=["\']viewport["\']~i', $html);
    $r['canonical']   = (bool)preg_match('~<link[^>]+rel=["\']canonical["\']~i', $html);
    $r['description'] = (bool)preg_match('~<meta[^>]+name=["\']description["\']~i', $html);
    $r['blank']       = preg_match_all('~target\s*=\s*["\']?_blank~i', $html);

    /* --- текст који посетилац види --- */
    $visible = preg_replace('~<(script|style|noscript|svg|template)\b.*?</\1>~is', ' ', $html);
    $visible = preg_replace('~<!--.*?-->~s', ' ', (string)$visible);
    $text    = clean_text((string)$visible);
    $nCyr = preg_match_all('~\p{Cyrillic}~u', $text);
    $nLat = preg_match_all('~[A-Za-z]~', $text);
    $r['script'] = ($nCyr + $nLat === 0) ? 'празно' : ($nCyr >= $nLat ? 'ћирилица' : 'латиница');

    /* 1. мешана слова у истој речи */
    preg_match_all('~[\p{L}\p{M}]+~u', $text, $words);
    $mixed = [];
    foreach ($words[0] as $w) {
        if (preg_match('~\p{Cyrillic}~u', $w) && preg_match('~[A-Za-z]~', $w)) {
            $mixed[$w] = ($mixed[$w] ?? 0) + 1;
        }
    }
    $r['mixed_n'] = array_sum($mixed);
    $r['mixed']   = array_slice(array_keys($mixed), 0, MAX_SAMPLES);

    /* 2. латиничне речи на ћириличној страници, ван курзива, кода и линкова */
    if ($r['script'] === 'ћирилица') {
        $t2 = preg_replace('~<(i|em|code|pre|kbd|var|cite|a)\b[^>]*>.*?</\1>~is', ' ', (string)$visible);
        $t2 = clean_text((string)$t2);
        $t2 = preg_replace('~\b(?:https?://|www\.)\S+|\S+@\S+\.\S+~u', ' ', $t2);
        $t2 = preg_replace('~°\s?[CF]\b~u', ' ', (string)$t2);          // °C и °F су дозвољени
        preg_match_all('~(?<![\p{L}])[A-Za-z]+(?![\p{L}])~u', (string)$t2, $lw);
        $allow = array_flip(ALLOW_LATIN);
        $lat = [];
        foreach ($lw[0] as $w) {
            if (!isset($allow[strtolower($w)])) { $lat[$w] = ($lat[$w] ?? 0) + 1; }
        }
        arsort($lat);
        $r['latin_n'] = array_sum($lat);
        $r['latin']   = array_slice(array_keys($lat), 0, MAX_SAMPLES);
    }

    /* 3. фонтови */
    $fonts = fonts_in($html);
    if (preg_match_all('~<link[^>]+href=["\']([^"\']+\.css[^"\']*)["\']~i', $html, $css)) {
        foreach ($css[1] as $href) {
            $p = local_path($root, $rel, $href);
            if ($p === null) { continue; }
            if (!isset($cssCache[$p])) {
                $cssCache[$p] = is_file($root . '/' . $p) ? fonts_in((string)@file_get_contents($root . '/' . $p)) : [];
            }
            $fonts = array_merge($fonts, $cssCache[$p]);
        }
    }
    $r['fonts'] = array_values(array_unique($fonts));

    /* 4. и 5. мени и линк ка почетној */
    $block = '';
    if (preg_match('~<header\b.*?</header>~is', $html, $m)) { $block = $m[0]; }
    elseif (preg_match('~<nav\b.*?</nav>~is', $html, $m)) { $block = $m[0]; }
    if ($block !== '') {
        preg_match_all('~<a\b([^>]*)>(.*?)</a>~is', $block, $as, PREG_SET_ORDER);
        $labels = [];
        foreach ($as as $a) {
            $label = trim(clean_text($a[2]));
            if ($label !== '') { $labels[] = $label; }
            if ($r['home_link'] === '' && preg_match('~href=["\'](?:https?://(?:www\.)?piorajac\.rs)?/(?:index\.html?)?["\']|href=["\']https?://(?:www\.)?piorajac\.rs["\']~i', $a[1])) {
                $r['home_link'] = $label !== '' ? $label : '(слика без текста)';
            }
        }
        $r['menu']     = implode(' · ', $labels);
        $r['menu_sig'] = substr(md5(mb_strtolower($r['menu'])), 0, 8);
    }

    /* 7. мртви унутрашњи линкови */
    if (preg_match_all('~<a\b[^>]*href=["\']([^"\']+)["\']~i', $html, $hs)) {
        foreach (array_unique($hs[1]) as $href) {
            $p = local_path($root, $rel, $href);
            if ($p === null) { continue; }
            if (!exists_page($root, $p)) { $r['broken'][] = $href; }
        }
    }

    /* 8. слике */
    if (preg_match_all('~<img\b[^>]*>~i', $html, $imgs)) {
        foreach ($imgs[0] as $tag) {
            if (!preg_match('~\balt\s*=\s*["\'][^"\']+["\']~i', $tag)) { $r['img_noalt']++; }
            if (preg_match('~\bsrc\s*=\s*["\']https?://([^/"\']+)~i', $tag, $hm)) {
                $host = strtolower($hm[1]);
                if (!preg_match('~(^|\.)piorajac\.rs$~', $host)) { $r['ext_img'][$host] = true; }
            }
        }
        $r['ext_img'] = array_keys($r['ext_img']);
    }
    return $r;
}

/* ============ ПОМОЋНЕ ФУНКЦИЈЕ ============ */

function clean_text(string $s): string
{
    $s = preg_replace('~<[^>]+>~', ' ', $s);
    $s = html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string)preg_replace('~\s+~u', ' ', $s));
}

function fonts_in(string $s): array
{
    $f = [];
    if (preg_match_all('~fonts\.googleapis\.com/css2?\?([^"\'\)\s>]+)~i', $s, $g)) {
        foreach ($g[1] as $q) {
            if (preg_match_all('~family=([^:&;]+)~', html_entity_decode($q), $fam)) {
                foreach ($fam[1] as $x) { $f[] = trim(str_replace('+', ' ', urldecode($x))); }
            }
        }
    }
    if (preg_match_all('~(?:font-family|--[\w-]*font[\w-]*)\s*:\s*([^;}"]+)~i', $s, $ff)) {
        foreach ($ff[1] as $x) {
            $first = trim(explode(',', $x)[0], " \t\n\r'\"");
            if ($first !== '' && stripos($first, 'var(') !== 0 && $first !== 'inherit') { $f[] = $first; }
        }
    }
    return array_values(array_unique($f));
}

/** Претвара href у путању у односу на public_html; null ако није унутрашњи. */
function local_path(string $root, string $fromRel, string $href): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES, 'UTF-8'));
    if ($href === '' || $href[0] === '#' || preg_match('~^(mailto|tel|javascript|data|sms):~i', $href)) { return null; }
    if (preg_match('~^(?:https?:)?//(?:www\.)?piorajac\.rs(/.*)?$~i', $href, $m)) {
        $href = $m[1] ?? '/';
    } elseif (preg_match('~^(?:[a-z][a-z0-9+.-]*:)?//~i', $href)) {
        return null;                                   // спољни линк
    }
    $href = preg_replace('~[?#].*$~', '', $href);
    $href = rawurldecode((string)$href);
    $base = $href !== '' && $href[0] === '/' ? '' : dirname($fromRel);
    $parts = [];
    foreach (explode('/', ($base === '.' ? '' : $base) . '/' . $href) as $seg) {
        if ($seg === '' || $seg === '.') { continue; }
        if ($seg === '..') { array_pop($parts); continue; }
        $parts[] = $seg;
    }
    $p = implode('/', $parts);
    if (substr($href, -1) === '/' && $p !== '') { $p .= '/'; }
    return $p === '' ? '/' : $p;
}

function exists_page(string $root, string $p): bool
{
    if ($p === '/') { $p = ''; }
    $abs = rtrim($root . '/' . $p, '/');
    if (is_file($abs)) { return true; }
    if (is_dir($abs)) {
        foreach (['index.html', 'index.htm', 'index.php'] as $ix) {
            if (is_file($abs . '/' . $ix)) { return true; }
        }
    }
    return false;
}

function folder_of(string $rel): string
{
    $parts = explode('/', $rel);
    return count($parts) > 1 ? $parts[0] . '/' : '(корен)';
}

function save(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function page(string $title, string $body): void
{
    echo '<!doctype html><html lang="sr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($title) . ' — контрола ПИО „Рајац“</title>'
        . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:760px;margin:40px auto;padding:0 16px;color:#1a2238}'
        . 'a.b{display:inline-block;background:#0f2744;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none}'
        . 'code{background:#f2efe8;padding:2px 5px;border-radius:3px}.bar{height:14px;background:#eee;border-radius:7px}'
        . '.bar i{display:block;height:100%;background:#c8a84b;border-radius:7px}</style></head><body>'
        . '<h1>' . h($title) . '</h1>' . $body . '</body></html>';
}

function refresh(string $self, string $tok, int $done, int $total): void
{
    $pct = $total ? (int)round(100 * $done / $total) : 100;
    echo '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
        . '<meta http-equiv="refresh" content="1;url=' . h($self) . '?token=' . $tok . '">'
        . '<title>Читам странице…</title></head><body style="font:16px system-ui;max-width:600px;margin:60px auto;padding:0 16px">'
        . '<h1>Читам странице… ' . $done . ' / ' . $total . '</h1>'
        . '<div style="height:14px;background:#eee;border-radius:7px"><i style="display:block;height:100%;width:' . $pct
        . '%;background:#c8a84b;border-radius:7px"></i></div>'
        . '<p>Страница се сама освежава. Не затварај прозор.</p></body></html>';
}

/* ============ ИЗВЕШТАЈИ ============ */

function write_reports(string $out, array $state): void
{
    $pages = $state['pages'];
    $sum = ['stranica' => count($pages), 'mesano' => 0, 'mesano_str' => 0, 'latinica_str' => 0,
            'blank' => 0, 'blank_str' => 0, 'mrtvi' => 0, 'mrtvi_str' => 0, 'bez_naslova' => 0,
            'h1_greska' => 0, 'bez_viewport' => 0, 'bez_canonical' => 0, 'bez_opisa' => 0,
            'slike_bez_alt' => 0, 'tudje_slike_str' => 0, 'bez_pocetne' => 0];
    $menus = []; $homes = []; $fonts = []; $folders = []; $mixedAll = []; $latinAll = []; $hosts = [];
    $broken = [];

    foreach ($pages as $rel => $p) {
        $f = $p['folder'];
        $folders[$f] = $folders[$f] ?? ['stranica' => 0, 'mesano' => 0, 'blank' => 0, 'mrtvi' => 0, 'latinica' => 0];
        $folders[$f]['stranica']++;
        if ($p['mixed_n'] > 0) { $sum['mesano'] += $p['mixed_n']; $sum['mesano_str']++; $folders[$f]['mesano'] += $p['mixed_n']; }
        foreach ($p['mixed'] as $w) { $mixedAll[$w][] = $rel; }
        if ($p['latin_n'] > 0) { $sum['latinica_str']++; $folders[$f]['latinica'] += $p['latin_n']; }
        foreach ($p['latin'] as $w) { $latinAll[$w] = ($latinAll[$w] ?? 0) + 1; }
        if ($p['blank'] > 0) { $sum['blank'] += $p['blank']; $sum['blank_str']++; $folders[$f]['blank'] += $p['blank']; }
        if ($p['broken']) {
            $sum['mrtvi'] += count($p['broken']); $sum['mrtvi_str']++; $folders[$f]['mrtvi'] += count($p['broken']);
            foreach ($p['broken'] as $b) { if (count($broken) < MAX_LINKS) { $broken[] = [$rel, $b]; } }
        }
        if ($p['title'] === '') { $sum['bez_naslova']++; }
        if ($p['h1'] !== 1) { $sum['h1_greska']++; }
        if (!$p['viewport']) { $sum['bez_viewport']++; }
        if (!$p['canonical']) { $sum['bez_canonical']++; }
        if (!$p['description']) { $sum['bez_opisa']++; }
        $sum['slike_bez_alt'] += $p['img_noalt'];
        if ($p['ext_img']) { $sum['tudje_slike_str']++; }
        foreach ($p['ext_img'] as $hst) { $hosts[$hst] = ($hosts[$hst] ?? 0) + 1; }
        if ($p['home_link'] === '') { $sum['bez_pocetne']++; }
        $homes[$p['home_link'] === '' ? '(нема линка ка почетној у заглављу)' : $p['home_link']][] = $rel;
        if ($p['menu_sig'] !== '') { $menus[$p['menu_sig']]['menu'] = $p['menu']; $menus[$p['menu_sig']]['pages'][] = $rel; }
        else { $menus['—']['menu'] = '(нема <header> ни <nav>)'; $menus['—']['pages'][] = $rel; }
        $fk = $p['fonts'] ? implode(' + ', $p['fonts']) : '(нису задати — подразумевани фонт прегледача)';
        $fonts[$fk][] = $rel;
    }
    uasort($menus, function ($a, $b) { return count($b['pages']) <=> count($a['pages']); });
    uasort($homes, function ($a, $b) { return count($b) <=> count($a); });
    uasort($fonts, function ($a, $b) { return count($b) <=> count($a); });
    arsort($latinAll); arsort($hosts); ksort($folders);

    $json = ['verzija' => '1.0', 'pocetak' => $state['start'], 'kraj' => date('d. m. Y. H:i'), 'zbir' => $sum,
             'folderi' => $folders, 'meniji' => $menus, 'pocetna' => $homes, 'fontovi' => $fonts,
             'mesana_slova' => $mixedAll, 'latinica_reci' => $latinAll, 'tudje_slike' => $hosts,
             'mrtvi_linkovi' => $broken, 'stranice' => $pages];
    file_put_contents($out . '/kontrola.json', json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    $csv = fopen($out . '/stranice.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['страница', 'фолдер', 'писмо', 'мешана слова', 'примери мешања', 'латиничне речи', 'примери латинице',
        'target_blank', 'мртви линкови', 'наслов', 'h1', 'viewport', 'canonical', 'опис', 'фонтови', 'мени', 'линк ка почетној',
        'слике без alt', 'туђе слике'], ';');
    foreach ($pages as $rel => $p) {
        fputcsv($csv, [$rel, $p['folder'], $p['script'], $p['mixed_n'], implode(' ', $p['mixed']), $p['latin_n'],
            implode(' ', $p['latin']), $p['blank'], count($p['broken']), $p['title'], $p['h1'], $p['viewport'] ? 'да' : 'не',
            $p['canonical'] ? 'да' : 'не', $p['description'] ? 'да' : 'не', implode(' + ', $p['fonts']), $p['menu'],
            $p['home_link'], $p['img_noalt'], implode(' ', $p['ext_img'])], ';');
    }
    fclose($csv);

    file_put_contents($out . '/izvestaj.html', report_html($json));
}

function report_html(array $j): string
{
    $s = $j['zbir'];
    $u = function (string $rel): string { return '<a href="/' . h($rel) . '">' . h($rel) . '</a>'; };
    $rows = function (array $list, int $max = 10) use ($u): string {
        $out = []; foreach (array_slice($list, 0, $max) as $rel) { $out[] = $u($rel); }
        $more = count($list) > $max ? ' … и још ' . (count($list) - $max) : '';
        return implode(', ', $out) . $more;
    };
    $o = '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Извештај контроле — ПИО „Рајац“</title><style>'
       . 'body{font:15px/1.55 system-ui,sans-serif;max-width:1100px;margin:30px auto;padding:0 16px;color:#1a2238;background:#fbf9f4}'
       . 'h1{color:#0f2744}h2{color:#0f2744;border-bottom:2px solid #c8a84b;padding-bottom:4px;margin-top:36px}'
       . 'table{border-collapse:collapse;width:100%;background:#fff;font-size:14px}td,th{border:1px solid #e3ddcf;padding:6px 8px;text-align:left;vertical-align:top}'
       . 'th{background:#0f2744;color:#fff}td.n{text-align:right;font-variant-numeric:tabular-nums}.z{color:#9a2b2b;font-weight:600}'
       . '.ok{color:#2f6b3a;font-weight:600}.w{overflow-x:auto}</style></head><body>'
       . '<h1>Извештај контроле сајта ПИО „Рајац“</h1><p>Прочитано: ' . h($j['pocetak']) . ' → ' . h($j['kraj'])
       . '. Скрипта ништа није мењала.</p>';

    $line = function (string $label, int $n, string $note = '', bool $neutral = false) {
        return '<tr><td>' . h($label) . '</td><td class="n ' . ($neutral ? '' : ($n ? 'z' : 'ok')) . '">' . $n . '</td><td>' . $note . '</td></tr>';
    };
    $o .= '<h2>Збир</h2><div class="w"><table><tr><th>Провера</th><th>Број</th><th>Напомена</th></tr>'
        . $line('Прочитаних страница', $s['stranica'], '', true)
        . $line('Речи са мешаним ћириличним и латиничним словима', $s['mesano'], 'број страница: ' . $s['mesano_str'])
        . $line('Ћириличне странице са латиничним речима (ван дозвољених)', $s['latinica_str'], 'списак речи доле — део је дозвољен')
        . $line('target="_blank"', $s['blank'], 'број страница: ' . $s['blank_str'])
        . $line('Мртви унутрашњи линкови', $s['mrtvi'], 'број страница: ' . $s['mrtvi_str'])
        . $line('Странице без линка ка почетној у заглављу', $s['bez_pocetne'])
        . $line('Странице без наслова (title)', $s['bez_naslova'])
        . $line('Странице без тачно једног h1', $s['h1_greska'])
        . $line('Странице без viewport ознаке (мобилни приказ)', $s['bez_viewport'])
        . $line('Странице без canonical', $s['bez_canonical'])
        . $line('Странице без meta описа', $s['bez_opisa'])
        . $line('Слике без alt описа или са празним описом', $s['slike_bez_alt'])
        . $line('Странице са сликама са туђих адреса', $s['tudje_slike_str'])
        . '</table></div>';

    $o .= '<h2>По фолдерима</h2><div class="w"><table><tr><th>Фолдер</th><th>Страница</th><th>Мешана слова</th><th>Латиница</th><th>_blank</th><th>Мртви линкови</th></tr>';
    foreach ($j['folderi'] as $f => $v) {
        $o .= '<tr><td>' . h($f) . '</td><td class="n">' . $v['stranica'] . '</td><td class="n">' . $v['mesano'] . '</td><td class="n">'
            . $v['latinica'] . '</td><td class="n">' . $v['blank'] . '</td><td class="n">' . $v['mrtvi'] . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Варијанте главног менија</h2><p>Циљ: једна варијанта на свим јавним страницама.</p><div class="w"><table><tr><th>Ставке менија</th><th>Страница</th><th>Примери</th></tr>';
    foreach ($j['meniji'] as $m) {
        $o .= '<tr><td>' . h($m['menu']) . '</td><td class="n">' . count($m['pages']) . '</td><td>' . $rows($m['pages'], 5) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Линк ка почетној у заглављу (ПИО „Рајац“)</h2><p>Циљ: један исти текст на свим страницама.</p><div class="w"><table><tr><th>Текст линка</th><th>Страница</th><th>Примери</th></tr>';
    foreach ($j['pocetna'] as $t => $list) {
        $o .= '<tr><td>' . h((string)$t) . '</td><td class="n">' . count($list) . '</td><td>' . $rows($list, 5) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Фонтови</h2><p>Циљ: Cormorant Garamond (наслови), DM Sans (текст), DM Mono (ознаке).</p><div class="w"><table><tr><th>Фонтови</th><th>Страница</th><th>Примери</th></tr>';
    foreach ($j['fontovi'] as $fk => $list) {
        $o .= '<tr><td>' . h((string)$fk) . '</td><td class="n">' . count($list) . '</td><td>' . $rows($list, 5) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Речи са мешаним писмом</h2><div class="w"><table><tr><th>Реч</th><th>Где</th></tr>';
    foreach (array_slice($j['mesana_slova'], 0, 400, true) as $w => $list) {
        $o .= '<tr><td>' . h((string)$w) . '</td><td>' . $rows(array_values(array_unique($list)), 6) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Латиничне речи на ћириличним страницама</h2><p>Број страница на којима се реч јавља. Део ће бити дозвољен (имена, извори) — о томе одлучује управљач.</p>'
        . '<div class="w"><table><tr><th>Реч</th><th>Страница</th></tr>';
    foreach (array_slice($j['latinica_reci'], 0, 300, true) as $w => $n) {
        $o .= '<tr><td>' . h((string)$w) . '</td><td class="n">' . $n . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Слике са туђих адреса</h2><div class="w"><table><tr><th>Адреса</th><th>Страница</th></tr>';
    foreach ($j['tudje_slike'] as $hst => $n) {
        $o .= '<tr><td>' . h((string)$hst) . '</td><td class="n">' . $n . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Мртви унутрашњи линкови</h2><p>Адресе на које страница линкује, а не постоје на диску. Ако сервер има посебна преусмерења у .htaccess, неки од њих можда ипак раде — то проверава Claude.</p>'
        . '<div class="w"><table><tr><th>Страница</th><th>Линк</th></tr>';
    foreach ($j['mrtvi_linkovi'] as $b) {
        $o .= '<tr><td>' . $u($b[0]) . '</td><td>' . h($b[1]) . '</td></tr>';
    }
    $o .= '</table></div></body></html>';
    return $o;
}
