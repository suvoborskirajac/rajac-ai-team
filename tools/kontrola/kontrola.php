<?php
/**
 * ПИО „Рајац“ — КОНТРОЛНА СКРИПТА
 * ------------------------------------------------------------------
 * Скрипта не мења изворне странице сајта; уписује и по захтеву брише
 * само сопствене радне датотеке и извештаје, који се чувају ВАН
 * public_html (у фолдеру kontrola-podaci поред public_html).
 *
 * Поставља се у:  public_html/kontrola/kontrola.php
 * Фолдер public_html/kontrola/ МОРА бити заштићен лозинком преко
 * cPanel → Directory Privacy. Без те заштите скрипта одбија да ради.
 *
 * Покреће се:     https://piorajac.rs/kontrola/kontrola.php
 *
 * Провере по страници:
 *   1. речи у којима се мешају ћирилична и латинична слова
 *   2. латиничне речи на ћириличним страницама (ван курзива и кода)
 *   3. декларисани фонтови (Google Fonts, font-family, CSS променљиве, @import)
 *   4. варијанта главног менија (називи и адресе линкова)
 *   5. линк ка почетној у заглављу и да ли гласи ПИО „Рајац“
 *   6. target="_blank" као атрибут
 *   7. унутрашњи линкови: недостаје фајл / могуће преусмерење у .htaccess
 *   8. слике са туђих адреса (src, srcset, picture, CSS позадине); alt недостаје / празан
 *   9. title, број h1, viewport, canonical, meta description
 *
 * Верзија 1.1 — 14. 10. 2026.
 */

declare(strict_types=1);

/* ============ ПОДЕШАВАЊА ============ */

const VERSION     = '1.2';
const MAX_SECONDS = 15;             // колико секунди сме да траје једна серија
const MAX_BATCH   = 400;            // највише страница у једној серији
const MAX_LINKS   = 3000;           // највише мртвих линкова у извештају
const MAX_SAMPLES = 8;              // примера по страници за сваку проверу
const HOME_NAME   = 'ПИО „Рајац“';  // прописани текст линка ка почетној
const DATA_DIR    = 'kontrola-podaci';
const IZVOZ_ZIP   = 'izvoz-sajta.zip';
const IZVOZ_MAX   = 2097152;        // највише 2 MB по фајлу у извозу

/* Извоз: само текстуални изворни фајлови правог сајта, без слика и података. */
const IZVOZ_EXT = ['html', 'htm', 'css', 'js'];
/* Путање (почетак) које се никад не извозе: генерисано, лични подаци, копије. */
const IZVOZ_SKIP_PREFIX = [
    'agro-kopernikus/copernicus_data/', 'agro-kopernikus/_cache', 'amsrajac/dnevna-statistika/',
    'vlasnici/', 'vlasnici_backup', 'rajac-vesti/public_html/', 'rajac-vesti/vesti1007-rad/',
    'rajac-sajt-komplet-vesti-thumbcards-2105/', 'kopernikindeks/20', 'za-vlasnike/data/',
];
/* Делови путање који значе копију, радну верзију или кеш. */
const IZVOZ_SKIP_RE = '~(^|/)[_.]|backup|rezerva|patch|probni|-test|_test|test/|kumulativni|izvoz|cache|kopija~i';

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

/* Ознаке које не прекидају реч (текст се спаја без размака, као у прегледачу). */
/* Линк <a> није на списку: суседни линкови у менију су засебне речи, иако између њих нема размака. */
const INLINE_TAGS = 'abbr|b|bdi|bdo|cite|code|data|dfn|em|font|i|kbd|mark|q|s|samp|small|span|strong|sub|sup|time|u|var|wbr';

/* Тело ознаке: наводници се прате, па ни „>“ ни назив атрибута у вредности не варају анализу. */
const TAG_BODY = '(?:[^>"\']|"[^"]*"|\'[^\']*\')*';

if (!defined('KONTROLA_TEST')) {
    main();
}

/* ============ ГЛАВНИ ТОК ============ */

function main(): void
{
    @ini_set('display_errors', '0');
    date_default_timezone_set('Europe/Belgrade');
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');

    foreach (['json_encode', 'preg_match', 'session_start', 'random_bytes'] as $fn) {
        if (!function_exists($fn)) {
            page('Недостаје PHP проширење', '<p>На серверу није доступна функција <code>' . h($fn) . '</code>. Јави Claude-у.</p>');
            return;
        }
    }
    if (!auth_ok($_SERVER)) {
        http_response_code(403);
        page('Фолдер није заштићен',
            '<p>Скрипта ради само ако је фолдер <code>public_html/kontrola/</code> заштићен лозинком.</p>'
            . '<p>У cPanel-у отвори <b>Directory Privacy</b>, изабери <code>public_html/kontrola</code>, '
            . 'укључи <b>Password protect this directory</b>, сачувај и додај корисника са лозинком. '
            . 'Затим поново отвори ову страницу.</p>'
            . '<p>Ако је лозинка већ укључена, а ова порука се и даље појављује, сервер скрипти не прослеђује '
            . 'потврђеног корисника. Тада јави Claude-у — скрипта намерно не ради без те потврде.</p>');
        return;
    }

    $root = realpath(dirname(__DIR__));
    if ($root === false) {
        page('Грешка', '<p>Не могу да пронађем фолдер public_html.</p>');
        return;
    }
    $data = dirname($root) . '/' . DATA_DIR;
    if (!is_dir($data) && !@mkdir($data, 0700, true)) {
        page('Грешка', '<p>Не могу да направим фолдер за резултате ван public_html: <code>' . h($data) . '</code>.</p>');
        return;
    }

    session_name('rajac_kontrola');
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    $csrf  = (string)$_SESSION['csrf'];
    $self  = (string)strtok((string)$_SERVER['REQUEST_URI'], '?');
    $state = $data . '/stanje.json';

    /* --- само читање: извештај и преузимање --- */
    if (isset($_GET['izvestaj'])) {
        $f = $data . '/izvestaj.html';
        if (is_file($f)) { readfile($f); } else { page('Нема извештаја', '<p>Контрола још није завршена.</p>'); }
        return;
    }
    if (isset($_GET['preuzmi'])) {
        $allowed = ['kontrola.json' => 'application/json', 'stranice.csv' => 'text/csv', IZVOZ_ZIP => 'application/zip'];
        $name = (string)$_GET['preuzmi'];
        if (!isset($allowed[$name]) || !is_file($data . '/' . $name)) {
            http_response_code(404);
            page('Нема фајла', '<p>Тражени фајл не постоји.</p>');
            return;
        }
        header('Content-Type: ' . $allowed[$name] . ($allowed[$name] === 'application/zip' ? '' : '; charset=utf-8'));
        header('Content-Length: ' . (string)filesize($data . '/' . $name));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($data . '/' . $name);
        return;
    }

    /* --- измене стања: само POST са CSRF кодом --- */
    $action = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
            http_response_code(400);
            page('Грешка', '<p>Захтев није важећи. Освежи страницу и покушај поново.</p>');
            return;
        }
        $action = (string)($_POST['akcija'] ?? '');
    }

    $lock = @fopen($data . '/.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        echo refresh_html($self . '?nastavi=1', 3, 'Контрола већ ради', 'Друга серија је у току. Страница ће се освежити.');
        return;
    }

    try {
        if ($action === 'obrisi') {
            foreach (['stanje.json', 'kontrola.json', 'stranice.csv', 'izvestaj.html', IZVOZ_ZIP] as $f) {
                if (is_file($data . '/' . $f)) { @unlink($data . '/' . $f); }
            }
            page('Обрисано', '<p>Резултати су обрисани. Сајт није мењан.</p><p><a href="' . h($self) . '">Назад</a></p>');
            return;
        }
        if ($action === 'izvoz') {
            $r = make_export($root, $data . '/' . IZVOZ_ZIP);
            if ($r['ok']) {
                page('Извоз је готов',
                    '<p>Спаковано фајлова: <b>' . $r['n'] . '</b> (' . h(number_format($r['bytes'] / 1048576, 1, ',', '.')) . ' MB пре паковања).</p>'
                    . '<p><a class="b" href="' . h($self) . '?preuzmi=' . IZVOZ_ZIP . '">Преузми ' . IZVOZ_ZIP . '</a></p>'
                    . '<p>Само HTML, CSS и JS правог сајта. Нема слика, PHP фајлова, података власника ни резервних копија. Сајт није мењан.</p>'
                    . '<p><a href="' . h($self) . '">Назад</a></p>');
            } else {
                page('Извоз није успео', '<p>' . h($r['error']) . '</p><p><a href="' . h($self) . '">Назад</a></p>');
            }
            return;
        }
        if ($action === 'pokreni') {
            $c = collect($root);
            $st = ['version' => VERSION, 'files' => $c['files'], 'skipped' => $c['skipped'], 'php' => $c['php'],
                   'pos' => 0, 'start' => date('d. m. Y. H:i'), 'pages' => [], 'css' => [], 'ht' => [], 'done' => false];
            if (!save_json($state, $st)) {
                page('Грешка', '<p>Не могу да упишем стање у <code>' . h($data) . '</code>.</p>');
                return;
            }
            echo refresh_html($self . '?nastavi=1', 0, 'Почињем…', '0 / ' . count($c['files']));
            return;
        }

        $st = is_file($state) ? json_decode((string)file_get_contents($state), true) : null;

        if (isset($_GET['nastavi']) && is_array($st) && !$st['done']) {
            $total = count($st['files']);
            $t0 = microtime(true);
            $n = 0;
            while ($st['pos'] < $total && $n < MAX_BATCH && (microtime(true) - $t0) < MAX_SECONDS) {
                $rel = $st['files'][$st['pos']];
                $st['pages'][$rel] = analyze($root, $rel, $st['css'], $st['ht']);
                $st['pos']++;
                $n++;
            }
            if ($st['pos'] >= $total) {
                if (!write_reports($data, $st)) {
                    page('Грешка', '<p>Извештај није могао да се упише. Контрола НИЈЕ завршена.</p>');
                    return;
                }
                $st['done'] = true;
                $st['end'] = date('d. m. Y. H:i');
            }
            if (!save_json($state, $st)) {
                page('Грешка', '<p>Стање није могло да се упише. Покрени контролу поново.</p>');
                return;
            }
            if (!$st['done']) {
                echo refresh_html($self . '?nastavi=1', 1, 'Читам странице…', $st['pos'] . ' / ' . $total);
                return;
            }
        }

        echo home_html($self, $csrf, is_array($st) ? $st : null, is_file($data . '/' . IZVOZ_ZIP) ? (int)filemtime($data . '/' . IZVOZ_ZIP) : 0);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Скрипта ради само ако је веб-сервер СТВАРНО проверио лозинку.
 * Верује се искључиво REMOTE_USER (и REDIRECT_REMOTE_USER после унутрашњег
 * преусмерења): то поставља сервер тек после успешне Basic провере, а
 * посетилац га не може послати заглављем. PHP_AUTH_USER се НЕ узима у обзир —
 * PHP га попуњава из заглавља Authorization које посетилац може да измисли.
 * Постојање .htaccess фајла није доказ ни за шта. У сумњи — одбиј.
 */
function auth_ok(array $server): bool
{
    foreach (['REMOTE_USER', 'REDIRECT_REMOTE_USER'] as $k) {
        if (isset($server[$k]) && is_string($server[$k]) && tr($server[$k]) !== '') { return true; }
    }
    return false;
}

/* ============ ПОПИС ФАЈЛОВА ============ */

function collect(string $root): array
{
    $files = []; $skipped = []; $php = 0;
    $skip = array_flip(SKIP_DIRS);
    $it = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            function ($cur) use ($skip, $root, &$skipped) {
                $name = $cur->getFilename();
                if ($cur->isDir()) {
                    if (isset($skip[strtolower($name)]) || stripos($name, '.bak') !== false) {
                        $skipped[] = rel_of($root, $cur->getPathname()) . '/';
                        return false;
                    }
                    return true;
                }
                return true;
            }
        )
    );
    foreach ($it as $f) {
        $name = $f->getFilename();
        if (preg_match('#\.bak|\.old$|~$#i', $name)) { continue; }
        if (preg_match('~\.html?$~i', $name)) { $files[] = rel_of($root, $f->getPathname()); }
        elseif (preg_match('~\.php$~i', $name)) { $php++; }
    }
    sort($files, SORT_STRING);
    sort($skipped, SORT_STRING);
    return ['files' => $files, 'skipped' => $skipped, 'php' => $php];
}

function rel_of(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

/* ============ ТЕКСТ СТРАНИЦЕ ============ */

/** HTML без коментара, скрипти, стилова и сличних блокова. */
function strip_code(string $html): string
{
    $s = preg_replace('~<!--.*?-->~s', ' ', $html);
    return (string)preg_replace('~<(script|style|noscript|svg|template)\b.*?</\1\s*>~is', ' ', (string)$s);
}

/** Видљив текст: inline ознаке не прекидају реч, остале ознаке дају размак. */
function visible_text(string $html, string $dropTags = ''): string
{
    $s = body_of(strip_code($html));
    if ($dropTags !== '') {
        $s = (string)preg_replace('~<(' . $dropTags . ')\b' . TAG_BODY . '>.*?</\1\s*>~is', ' ', $s);
    }
    $s = (string)preg_replace('~</?(?:' . INLINE_TAGS . ')\b' . TAG_BODY . '>~i', '', $s);
    $s = (string)preg_replace('~</?[a-z][a-z0-9:-]*\b' . TAG_BODY . '>|<!' . TAG_BODY . '>~i', ' ', $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return tr((string)preg_replace('~\s+~u', ' ', $s));
}

/** Садржај <body> ако постоји; иначе HTML без <head> и <title>. Метаподаци се проверавају одвојено. */
function body_of(string $html): string
{
    if (preg_match('~<body\b' . TAG_BODY . '>~i', $html, $m, PREG_OFFSET_CAPTURE)) {
        $s = substr($html, (int)$m[0][1] + strlen($m[0][0]));
        $end = stripos($s, '</body');
        return $end === false ? $s : substr($s, 0, $end);
    }
    $s = (string)preg_replace('~<head\b.*?</head\s*>~is', ' ', $html);
    return (string)preg_replace('~<title\b.*?</title\s*>~is', ' ', $s);
}

/** Кратак текст из HTML фрагмента (наслов, текст линка). */
function plain(string $s): string
{
    $s = (string)preg_replace('~</?(?:' . INLINE_TAGS . ')\b' . TAG_BODY . '>~i', '', strip_code($s));
    $s = (string)preg_replace('~</?[a-z][a-z0-9:-]*\b' . TAG_BODY . '>~i', ' ', $s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return tr((string)preg_replace('~\s+~u', ' ', $s));
}

/** Речи са и ћириличним и латиничним словима: [реч => број]. */
function mixed_words(string $text): array
{
    $out = [];
    if (preg_match_all('~\p{L}[\p{L}\p{M}]*~u', $text, $m)) {
        foreach ($m[0] as $w) {
            if (preg_match('~\p{Cyrillic}~u', $w) && preg_match('~\p{Latin}~u', $w)) {
                $out[$w] = ($out[$w] ?? 0) + 1;
            }
        }
    }
    return $out;
}

function script_of(string $text): string
{
    $c = (int)preg_match_all('~\p{Cyrillic}~u', $text);
    $l = (int)preg_match_all('~\p{Latin}~u', $text);
    if ($c + $l === 0) { return 'празно'; }
    return $c >= $l ? 'ћирилица' : 'латиница';
}

/** Латиничне речи ван курзива и кода, без адреса, °C/°F и дозвољених израза. */
function latin_words(string $html): array
{
    $t = visible_text($html, 'i|em|code|pre|kbd|var|cite|samp');
    $t = (string)preg_replace('~(?:https?://|www\.)\S+|\S+@\S+\.\S+|\b[\w.-]+\.(?:rs|com|org|net|eu|gov|edu|io|info)\b\S*~u', ' ', $t);
    $t = (string)preg_replace('~°\s?[CF](?!\p{L})~u', ' ', $t);
    $allow = array_flip(ALLOW_LATIN);
    $out = [];
    if (preg_match_all('~(?<!\p{L})\p{Latin}+(?!\p{L})~u', $t, $m)) {
        foreach ($m[0] as $w) {
            if (!isset($allow[strtolower($w)])) { $out[$w] = ($out[$w] ?? 0) + 1; }
        }
    }
    arsort($out);
    return $out;
}

/* ============ АТРИБУТИ И ОЗНАКЕ ============ */

/**
 * Атрибути једне ознаке, читани редом: назив, па вредност у наводницима или без њих.
 * Вредност се прогута цела, па назив унутар туђе вредности (title='href="/x"') није атрибут.
 * Као у прегледачу, важи прво појављивање истог атрибута.
 */
function attrs(string $tag): array
{
    $body = (string)preg_replace('~^<[a-z][a-z0-9:-]*~i', '', $tag);
    $body = (string)preg_replace('~/?>$~', '', $body);
    $out = [];
    if (preg_match_all('~([^\s"\'>/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?~', $body, $m, PREG_SET_ORDER)) {
        foreach ($m as $a) {
            $name = strtolower($a[1]);
            if (isset($out[$name])) { continue; }
            $val = isset($a[2]) && $a[2] !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? ''));
            $out[$name] = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    return $out;
}

/** Вредност атрибута из једне ознаке; null ако атрибута нема. */
function attr(string $tag, string $name): ?string
{
    $a = attrs($tag);
    $k = strtolower($name);
    return array_key_exists($k, $a) ? $a[$k] : null;
}

/** Све почетне ознаке датог имена (наводници се поштују). */
function tags(string $html, string $names): array
{
    preg_match_all('~<(?:' . $names . ')\b' . TAG_BODY . '>~i', $html, $m);
    return $m[0];
}

function count_blank(string $html): int
{
    $n = 0;
    foreach (tags(strip_code($html), 'a|area|form|base') as $t) {
        $v = attr($t, 'target');
        if ($v !== null && strtolower(tr($v)) === '_blank') { $n++; }
    }
    return $n;
}

function head_checks(string $html): array
{
    $s = strip_code($html);
    $title = '';
    if (preg_match('~<title\b[^>]*>(.*?)</title\s*>~is', $s, $m)) { $title = plain($m[1]); }
    $viewport = false; $desc = false; $canon = false;
    foreach (tags($s, 'meta') as $t) {
        $n = strtolower((string)attr($t, 'name'));
        if ($n === 'viewport') { $viewport = true; }
        if ($n === 'description' && tr((string)attr($t, 'content')) !== '') { $desc = true; }
    }
    foreach (tags($s, 'link') as $t) {
        if (preg_match('~(^|\s)canonical(\s|$)~i', (string)attr($t, 'rel')) && tr((string)attr($t, 'href')) !== '') { $canon = true; }
    }
    return ['title' => $title, 'h1' => (int)preg_match_all('~<h1\b~i', $s),
            'viewport' => $viewport, 'canonical' => $canon, 'description' => $desc];
}

/* ============ ПУТАЊЕ И ЛИНКОВИ ============ */

/** href → путања у односу на public_html; null ако није унутрашњи линк. */
function local_path(string $fromRel, string $href): ?string
{
    $href = tr(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || $href[0] === '#' || preg_match('~^(mailto|tel|javascript|data|sms|blob):~i', $href)) { return null; }
    if ($href[0] === '?') { return $fromRel; }                         // иста страница са параметрима
    if (preg_match('~^(?:https?:)?//(?:www\.)?piorajac\.rs(?::\d+)?(/[^?#]*)?(?:[?#].*)?$~i', $href, $m)) {
        $href = ($m[1] ?? '') === '' ? '/' : $m[1];
    } elseif (preg_match('~^(?:[a-z][a-z0-9+.-]*:)?//~i', $href) || preg_match('~^[a-z][a-z0-9+.-]*:~i', $href)) {
        return null;                                                    // спољни линк
    }
    $href = (string)preg_replace('~[?#].*$~', '', $href);
    $href = rawurldecode($href);
    $dir  = dirname($fromRel);
    $base = ($href !== '' && $href[0] === '/') ? '' : ($dir === '.' ? '' : $dir);
    $parts = [];
    foreach (explode('/', $base . '/' . $href) as $seg) {
        if ($seg === '' || $seg === '.') { continue; }
        if ($seg === '..') { array_pop($parts); continue; }
        $parts[] = $seg;
    }
    $p = implode('/', $parts);
    if ($p !== '' && substr($href, -1) === '/') { $p .= '/'; }
    return $p === '' ? '/' : $p;
}

function exists_page(string $root, string $p): bool
{
    $abs = rtrim($root . '/' . ($p === '/' ? '' : $p), '/');
    if (is_file($abs)) { return true; }
    if (is_dir($abs)) {
        foreach (['index.html', 'index.htm', 'index.php'] as $ix) {
            if (is_file($abs . '/' . $ix)) { return true; }
        }
    }
    return false;
}

/** 'ok' | 'rewrite_possible' (неки .htaccess изнад има преусмерења) | 'missing_file'. */
function link_status(string $root, string $p, array &$htCache): string
{
    if (exists_page($root, $p)) { return 'ok'; }
    $dir = $p === '/' ? '' : trim(substr($p, -1) === '/' ? $p : dirname($p), '/');
    if ($dir === '.') { $dir = ''; }
    while (true) {
        if (!array_key_exists($dir, $htCache)) {
            $f = $root . ($dir === '' ? '' : '/' . $dir) . '/.htaccess';
            $htCache[$dir] = is_file($f) && preg_match('~^\s*(RewriteRule|Redirect|RedirectMatch|FallbackResource)\b~mi', (string)@file_get_contents($f));
        }
        if ($htCache[$dir]) { return 'rewrite_possible'; }
        if ($dir === '') { break; }
        $dir = dirname($dir) === '.' ? '' : dirname($dir);
    }
    return 'missing_file';
}

function is_home(?string $p): bool
{
    return in_array($p, ['/', 'index.html', 'index.htm', 'index.php'], true);
}

/* ============ МЕНИ ============ */

/** Главно заглавље: прво <header> или <nav> пре <main>/<h1>; иначе прво уопште. */
function main_header(string $html): string
{
    $s = body_of(strip_code($html));
    $limit = strlen($s);
    if (preg_match('~<(?:main|h1)\b~i', $s, $m, PREG_OFFSET_CAPTURE)) { $limit = (int)$m[0][1]; }
    if (!preg_match_all('~<(header|nav)\b.*?</\1\s*>~is', $s, $all, PREG_OFFSET_CAPTURE)) { return ''; }
    foreach ($all[0] as $b) {
        if ((int)$b[1] < $limit) { return $b[0]; }
    }
    return $all[0][0][0];
}

function menu_info(string $fromRel, string $block): array
{
    $items = []; $home = null;
    if (preg_match_all('~<a\b(' . TAG_BODY . ')>(.*?)</a\s*>~is', $block, $as, PREG_SET_ORDER)) {
        foreach ($as as $a) {
            $label = plain($a[2]);
            $href  = (string)attr('<a ' . $a[1] . '>', 'href');
            $p     = local_path($fromRel, $href);
            $items[] = ($label !== '' ? $label : '(без текста)') . ' → ' . ($p ?? $href);
            if ($home === null && is_home($p)) { $home = $label !== '' ? $label : '(слика без текста)'; }
        }
    }
    return ['items' => $items, 'home' => $home];
}

/* ============ ФОНТОВИ ============ */

function fonts_in(string $s): array
{
    $f = [];
    if (preg_match_all('~fonts\.googleapis\.com/css2?\?([^"\'\)\s>]+)~i', $s, $g)) {
        foreach ($g[1] as $q) {
            if (preg_match_all('~family=([^:&;]+)~', html_entity_decode($q, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $fam)) {
                foreach ($fam[1] as $x) { $f[] = tr(str_replace('+', ' ', urldecode($x))); }
            }
        }
    }
    if (preg_match_all('~(?:font-family|--[\w-]*font[\w-]*)\s*:\s*([^;}"]+)~i', $s, $ff)) {
        foreach ($ff[1] as $x) {
            $first = trim(explode(',', $x)[0], " \t\n\r'\"");
            if ($first !== '' && stripos($first, 'var(') !== 0 && strtolower($first) !== 'inherit') { $f[] = $first; }
        }
    }
    return array_values(array_unique($f));
}

/** Фонтови из локалног CSS-а, уз праћење @import до дубине 3. */
function css_fonts(string $root, string $cssRel, array &$cache, int $depth = 0): array
{
    if (isset($cache[$cssRel])) { return $cache[$cssRel]; }
    $cache[$cssRel] = [];
    $file = $root . '/' . $cssRel;
    if ($depth > 3 || !is_file($file)) { return []; }
    $css = (string)@file_get_contents($file);
    $f = fonts_in($css);
    if (preg_match_all('~@import\s+(?:url\()?\s*["\']?([^"\')\s;]+)~i', $css, $im)) {
        foreach ($im[1] as $href) {
            if (stripos($href, 'fonts.googleapis.com') !== false) { $f = array_merge($f, fonts_in($href)); continue; }
            $p = local_path($cssRel, $href);
            if ($p !== null) { $f = array_merge($f, css_fonts($root, $p, $cache, $depth + 1)); }
        }
    }
    $cache[$cssRel] = array_values(array_unique($f));
    return $cache[$cssRel];
}

/* ============ СЛИКЕ ============ */

function image_info(string $html): array
{
    $s = strip_code($html);
    $urls = []; $missing = 0; $empty = 0;
    foreach (tags($s, 'img') as $t) {
        $alt = attr($t, 'alt');
        if ($alt === null) { $missing++; } elseif (tr($alt) === '') { $empty++; }
        foreach (['src', 'data-src'] as $a) { if (($v = attr($t, $a)) !== null) { $urls[] = $v; } }
        if (($v = attr($t, 'srcset')) !== null) { $urls = array_merge($urls, srcset_urls($v)); }
    }
    foreach (tags($s, 'source') as $t) {
        if (($v = attr($t, 'srcset')) !== null) { $urls = array_merge($urls, srcset_urls($v)); }
    }
    $urls = array_merge($urls, css_image_urls(strip_comments_only($html)));
    $hosts = [];
    foreach ($urls as $url) {
        if (preg_match('~^(?:https?:)?//([^/\s"\']+)~i', tr($url), $hm)) {
            $host = strtolower($hm[1]);
            if (!preg_match('~(^|\.)piorajac\.rs$~', $host) && !preg_match('~(^|\.)(googleapis|gstatic)\.com$~', $host)) {
                $hosts[$host] = true;
            }
        }
    }
    return ['alt_missing' => $missing, 'alt_empty' => $empty, 'ext' => array_keys($hosts)];
}

/**
 * Адресе слика из CSS-а: само вредности својстава која приказују слику
 * (background, border-image, list-style, mask, content, cursor).
 * @import и @font-face (фонтови, стилови) се прескачу.
 */
function css_image_urls(string $css): array
{
    $css = (string)preg_replace('~/\*.*?\*/~s', ' ', $css);
    $css = (string)preg_replace('~@import\b[^;]*;?~i', ' ', $css);
    $css = (string)preg_replace('~@font-face\s*\{[^}]*\}~i', ' ', $css);
    $out = [];
    if (preg_match_all('~(?:^|[;{\s])(?:-webkit-)?(?:background(?:-image)?|border-image(?:-source)?|list-style(?:-image)?|mask(?:-image)?|content|cursor)\s*:\s*([^;}]*)~i', $css, $d)) {
        foreach ($d[1] as $val) {
            if (preg_match_all('~url\(\s*["\']?([^"\')]+)~i', $val, $u)) { $out = array_merge($out, $u[1]); }
        }
    }
    return $out;
}

function srcset_urls(string $v): array
{
    $out = [];
    foreach (explode(',', $v) as $part) {
        $u = tr(preg_split('~\s+~', tr($part))[0] ?? '');
        if ($u !== '') { $out[] = $u; }
    }
    return $out;
}

/** Стилови (style атрибути и <style> блокови) без HTML коментара — за CSS позадине. */
function strip_comments_only(string $html): string
{
    $s = (string)preg_replace('~<!--.*?-->~s', ' ', $html);
    $out = '';
    if (preg_match_all('~<style\b[^>]*>(.*?)</style\s*>~is', $s, $m)) { $out .= implode("\n", $m[1]); }
    if (preg_match_all('~\sstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i', $s, $m2)) {
        $out .= "\n" . implode("\n", $m2[1]) . "\n" . implode("\n", $m2[2]);
    }
    return $out;
}

/* ============ ПРОВЕРА ЈЕДНЕ СТРАНИЦЕ ============ */

function analyze(string $root, string $rel, array &$cssCache, array &$htCache): array
{
    $html = @file_get_contents($root . '/' . $rel);
    $r = ['folder' => folder_of($rel), 'read_error' => $html === false, 'bytes' => is_string($html) ? strlen($html) : 0,
          'title' => '', 'h1' => 0, 'viewport' => false, 'canonical' => false, 'description' => false,
          'script' => 'празно', 'mixed' => [], 'mixed_n' => 0, 'latin' => [], 'latin_n' => 0, 'latin_checked' => false,
          'fonts' => [], 'menu' => '', 'menu_sig' => '', 'home_link' => '', 'home_ok' => false, 'blank' => 0,
          'missing' => [], 'rewrite' => [], 'ext_img' => [], 'alt_missing' => 0, 'alt_empty' => 0];
    if (!is_string($html) || $html === '') { return $r; }

    $r = array_merge($r, head_checks($html));
    $r['blank'] = count_blank($html);

    $text = visible_text($html);
    $r['script'] = script_of($text);
    $mixed = mixed_words($text);
    $r['mixed_n'] = array_sum($mixed);
    $r['mixed'] = array_slice(array_keys($mixed), 0, MAX_SAMPLES);

    if ($r['script'] === 'ћирилица') {
        $lat = latin_words($html);
        $r['latin_checked'] = true;
        $r['latin_n'] = array_sum($lat);
        $r['latin'] = array_slice(array_keys($lat), 0, MAX_SAMPLES);
    }

    $fonts = fonts_in(strip_comments_only($html) . "\n" . implode("\n", tags(strip_code($html), 'link')));
    foreach (tags(strip_code($html), 'link') as $t) {
        if (!preg_match('~(^|\s)stylesheet(\s|$)~i', (string)attr($t, 'rel'))) { continue; }
        $p = local_path($rel, (string)attr($t, 'href'));
        if ($p !== null && $p !== '/') { $fonts = array_merge($fonts, css_fonts($root, $p, $cssCache)); }
    }
    $r['fonts'] = array_values(array_unique($fonts));

    $block = main_header($html);
    if ($block !== '') {
        $mi = menu_info($rel, $block);
        $r['menu'] = implode(' · ', $mi['items']);
        $r['menu_sig'] = substr(md5(lower($r['menu'])), 0, 8);
        if ($mi['home'] !== null) {
            $r['home_link'] = $mi['home'];
            $r['home_ok'] = $mi['home'] === HOME_NAME;
        }
    }

    $seen = [];
    foreach (tags(strip_code($html), 'a|area') as $t) {
        $href = attr($t, 'href');
        if ($href === null || isset($seen[$href])) { continue; }
        $seen[$href] = true;
        $p = local_path($rel, $href);
        if ($p === null) { continue; }
        $stt = link_status($root, $p, $htCache);
        if ($stt === 'missing_file') { $r['missing'][] = $href; }
        elseif ($stt === 'rewrite_possible') { $r['rewrite'][] = $href; }
    }

    $im = image_info($html);
    $r['alt_missing'] = $im['alt_missing'];
    $r['alt_empty'] = $im['alt_empty'];
    $r['ext_img'] = $im['ext'];
    return $r;
}

/* ============ ПОМОЋНЕ ============ */

/** trim() са истим скупом знакова на свим верзијама PHP-а. */
function tr(string $s): string
{
    return trim($s, " \t\n\r\0\x0B\f");
}

function lower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}

function folder_of(string $rel): string
{
    $parts = explode('/', $rel);
    return count($parts) > 1 ? $parts[0] . '/' : '(корен)';
}

/** Атомско уписивање: привремени фајл, па rename. */
function save_json(string $file, array $data): bool
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json !== false && write_atomic($file, $json);
}

function write_atomic(string $file, string $content): bool
{
    $tmp = $file . '.tmp' . getmypid();
    if (@file_put_contents($tmp, $content) !== strlen($content)) { @unlink($tmp); return false; }
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
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
        . 'a.b,button{display:inline-block;background:#0f2744;color:#fff;padding:12px 20px;border:0;border-radius:6px;'
        . 'text-decoration:none;font:inherit;cursor:pointer;margin:4px 0}button.d{background:#8a2b2b}'
        . 'code{background:#f2efe8;padding:2px 5px;border-radius:3px}</style></head><body>'
        . '<h1>' . h($title) . '</h1>' . $body . '</body></html>';
}

function refresh_html(string $url, int $sec, string $title, string $line): string
{
    return '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
        . '<meta http-equiv="refresh" content="' . $sec . ';url=' . h($url) . '"><title>' . h($title) . '</title></head>'
        . '<body style="font:16px system-ui;max-width:600px;margin:60px auto;padding:0 16px">'
        . '<h1>' . h($title) . ' ' . h($line) . '</h1><p>Страница се сама освежава. Не затварај прозор.</p></body></html>';
}

function home_html(string $self, string $csrf, ?array $st, int $izvozTime = 0): string
{
    $form = function (string $akcija, string $label, string $cls = '', string $confirm = '') use ($self, $csrf): string {
        return '<form method="post" action="' . h($self) . '" style="display:inline"'
            . ($confirm !== '' ? ' onsubmit="return confirm(\'' . h($confirm) . '\')"' : '') . '>'
            . '<input type="hidden" name="csrf" value="' . h($csrf) . '"><input type="hidden" name="akcija" value="' . h($akcija) . '">'
            . '<button class="' . $cls . '">' . h($label) . '</button></form> ';
    };
    ob_start();
    if ($st && !empty($st['done'])) {
        page('Контрола сајта ПИО „Рајац“',
            '<p>Последња контрола: <b>' . h((string)$st['start']) . '</b>, прочитано страница: <b>' . count($st['files']) . '</b>.</p>'
            . '<p><a class="b" href="' . h($self) . '?izvestaj=1">Отвори извештај</a></p>'
            . '<p>За слање Claude-у: <a href="' . h($self) . '?preuzmi=kontrola.json">kontrola.json</a> · '
            . '<a href="' . h($self) . '?preuzmi=stranice.csv">stranice.csv</a></p>'
            . '<p>' . $form('pokreni', 'Покрени нову контролу') . $form('obrisi', 'Обриши резултате', 'd', 'Обрисати резултате?') . '</p>');
    } elseif ($st) {
        page('Контрола није завршена',
            '<p>Прочитано ' . (int)$st['pos'] . ' од ' . count($st['files']) . ' страница.</p>'
            . '<p><a class="b" href="' . h($self) . '?nastavi=1">Настави</a></p><p>' . $form('pokreni', 'Почни испочетка') . '</p>');
    } else {
        page('Контрола сајта ПИО „Рајац“',
            '<p>Скрипта чита јавне странице и пописује грешке. Сајт не мења.</p><p>' . $form('pokreni', 'Покрени контролу') . '</p>');
    }
    echo '<div style="max-width:760px;margin:-20px auto 40px;padding:0 16px;font:16px/1.6 system-ui,sans-serif">'
        . '<h2>Извоз изворног кода</h2><p>ZIP са HTML, CSS и JS фајловима правог сајта за GitHub — без слика, PHP-а, '
        . 'података власника и резервних копија.</p><p>' . $form('izvoz', 'Направи извоз')
        . ($izvozTime > 0 ? ' <a href="' . h($self) . '?preuzmi=' . IZVOZ_ZIP . '">Преузми последњи извоз (' . h(date('d. m. Y. H:i', $izvozTime)) . ')</a>' : '')
        . '</p></div>';
    return (string)ob_get_clean();
}

/* ============ ИЗВОЗ ============ */

/** Да ли фолдер (путања у односу на public_html, без косе црте на крају) улази у извоз. */
function izvoz_dir_ok(string $dir): bool
{
    $dir = trim(str_replace('\\', '/', $dir), '/');
    if ($dir === '' || $dir === '.') { return true; }
    foreach (IZVOZ_SKIP_PREFIX as $p) {
        if (strncasecmp($dir . '/', $p, strlen($p)) === 0) { return false; }
        if (substr($p, -1) !== '/' && strncasecmp($dir, $p, strlen($p)) === 0) { return false; }
    }
    if (preg_match(IZVOZ_SKIP_RE, $dir . '/')) { return false; }
    foreach (explode('/', $dir) as $seg) {
        if (in_array(strtolower($seg), SKIP_DIRS, true) || stripos($seg, '.bak') !== false) { return false; }
    }
    return true;
}

/** Да ли фајл (путања у односу на public_html) улази у извоз. */
function izvoz_ok(string $rel): bool
{
    $rel = str_replace('\\', '/', $rel);
    $ext = strtolower((string)pathinfo($rel, PATHINFO_EXTENSION));
    if (!in_array($ext, IZVOZ_EXT, true)) { return false; }
    if (preg_match('#\.bak|\.old$|~$|\.min\.(js|css)$#i', basename($rel))) { return false; }
    foreach (IZVOZ_SKIP_PREFIX as $p) {
        if (strncasecmp($rel, $p, strlen($p)) === 0) { return false; }
    }
    return izvoz_dir_ok(dirname($rel));
}

/** Прави ZIP извоза атомски (привремени фајл, па rename). */
function make_export(string $root, string $zipPath): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'На серверу није укључено PHP проширење zip (ZipArchive). Јави Claude-у.'];
    }
    $tmp = $zipPath . '.tmp' . getmypid();
    @unlink($tmp);
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'error' => 'Не могу да направим ZIP фајл.'];
    }
    $n = 0; $bytes = 0;
    $it = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($cur) use ($root) {
            return !$cur->isDir() || izvoz_dir_ok(rel_of($root, $cur->getPathname()));
        }
    ));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->isLink()) { continue; }
        $rel = rel_of($root, $f->getPathname());
        if (!izvoz_ok($rel) || $f->getSize() > IZVOZ_MAX) { continue; }
        if (!$zip->addFile($f->getPathname(), 'site/' . $rel)) { $zip->close(); @unlink($tmp); return ['ok' => false, 'error' => 'Грешка при паковању: ' . $rel]; }
        $n++; $bytes += $f->getSize();
    }
    $zip->addFromString('site/_IZVOZ.txt', 'Извоз сајта ПИО „Рајац“, ' . date('d. m. Y. H:i') . ', верзија ' . VERSION . ', фајлова: ' . $n . "\n");
    if (!$zip->close() || !@rename($tmp, $zipPath)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'ZIP није могао да се сачува.'];
    }
    return ['ok' => true, 'n' => $n, 'bytes' => $bytes];
}

/* ============ ИЗВЕШТАЈИ ============ */

function write_reports(string $out, array $st): bool
{
    $pages = $st['pages'];
    $sum = ['stranica' => count($pages), 'necitljivo' => 0, 'php_neobuhvaceno' => (int)$st['php'],
            'mesano' => 0, 'mesano_str' => 0, 'latinica_str' => 0, 'latinica_nije_provereno' => 0,
            'blank' => 0, 'blank_str' => 0, 'nedostaje' => 0, 'nedostaje_str' => 0, 'preusmerenje' => 0,
            'bez_naslova' => 0, 'h1_greska' => 0, 'bez_viewport' => 0, 'bez_canonical' => 0, 'bez_opisa' => 0,
            'alt_nedostaje' => 0, 'alt_prazan' => 0, 'tudje_slike_str' => 0, 'bez_pocetne' => 0, 'pocetna_naziv' => 0];
    $menus = []; $homes = []; $fonts = []; $folders = []; $mixedAll = []; $latinAll = []; $hosts = []; $broken = [];

    foreach ($pages as $rel => $p) {
        $f = $p['folder'];
        $folders[$f] = $folders[$f] ?? ['stranica' => 0, 'mesano' => 0, 'latinica' => 0, 'blank' => 0, 'nedostaje' => 0];
        $folders[$f]['stranica']++;
        if ($p['read_error']) { $sum['necitljivo']++; }
        if ($p['mixed_n'] > 0) { $sum['mesano'] += $p['mixed_n']; $sum['mesano_str']++; $folders[$f]['mesano'] += $p['mixed_n']; }
        foreach ($p['mixed'] as $w) { $mixedAll[$w][] = $rel; }
        if (!$p['latin_checked'] && $p['script'] === 'латиница') { $sum['latinica_nije_provereno']++; }
        if ($p['latin_n'] > 0) { $sum['latinica_str']++; $folders[$f]['latinica'] += $p['latin_n']; }
        foreach ($p['latin'] as $w) { $latinAll[$w] = ($latinAll[$w] ?? 0) + 1; }
        if ($p['blank'] > 0) { $sum['blank'] += $p['blank']; $sum['blank_str']++; $folders[$f]['blank'] += $p['blank']; }
        if ($p['missing']) { $sum['nedostaje'] += count($p['missing']); $sum['nedostaje_str']++; $folders[$f]['nedostaje'] += count($p['missing']); }
        $sum['preusmerenje'] += count($p['rewrite']);
        foreach ($p['missing'] as $b) { if (count($broken) < MAX_LINKS) { $broken[] = [$rel, $b, 'недостаје фајл']; } }
        foreach ($p['rewrite'] as $b) { if (count($broken) < MAX_LINKS) { $broken[] = [$rel, $b, 'могуће преусмерење (.htaccess)']; } }
        if ($p['title'] === '') { $sum['bez_naslova']++; }
        if ($p['h1'] !== 1) { $sum['h1_greska']++; }
        if (!$p['viewport']) { $sum['bez_viewport']++; }
        if (!$p['canonical']) { $sum['bez_canonical']++; }
        if (!$p['description']) { $sum['bez_opisa']++; }
        $sum['alt_nedostaje'] += $p['alt_missing'];
        $sum['alt_prazan'] += $p['alt_empty'];
        if ($p['ext_img']) { $sum['tudje_slike_str']++; }
        foreach ($p['ext_img'] as $hst) { $hosts[$hst] = ($hosts[$hst] ?? 0) + 1; }
        if ($p['home_link'] === '') { $sum['bez_pocetne']++; } elseif (!$p['home_ok']) { $sum['pocetna_naziv']++; }
        $homes[$p['home_link'] === '' ? '(нема линка ка почетној у заглављу)' : $p['home_link']][] = $rel;
        $sig = $p['menu_sig'] !== '' ? $p['menu_sig'] : '—';
        $menus[$sig]['menu'] = $p['menu'] !== '' ? $p['menu'] : '(нема <header> ни <nav>)';
        $menus[$sig]['pages'][] = $rel;
        $fonts[$p['fonts'] ? implode(' + ', $p['fonts']) : '(нису декларисани — подразумевани фонт прегледача)'][] = $rel;
    }
    uasort($menus, function ($a, $b) { return count($b['pages']) <=> count($a['pages']); });
    uasort($homes, function ($a, $b) { return count($b) <=> count($a); });
    uasort($fonts, function ($a, $b) { return count($b) <=> count($a); });
    arsort($latinAll); arsort($hosts); ksort($folders);

    $json = ['verzija' => VERSION, 'pocetak' => $st['start'], 'kraj' => date('d. m. Y. H:i'), 'zbir' => $sum,
             'preskoceni_folderi' => $st['skipped'], 'folderi' => $folders, 'meniji' => $menus, 'pocetna' => $homes,
             'fontovi' => $fonts, 'mesana_slova' => $mixedAll, 'latinica_reci' => $latinAll, 'tudje_slike' => $hosts,
             'linkovi' => $broken, 'stranice' => $pages];
    $j = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($j === false || !write_atomic($out . '/kontrola.json', $j)) { return false; }

    $csv = fopen('php://temp', 'w+');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['страница', 'фолдер', 'писмо', 'мешана слова', 'примери мешања', 'латиничне речи', 'примери латинице',
        'target_blank', 'недостаје фајл', 'могуће преусмерење', 'наслов', 'h1', 'viewport', 'canonical', 'опис',
        'декларисани фонтови', 'мени', 'линк ка почетној', 'почетна гласи ' . HOME_NAME, 'alt недостаје', 'alt празан', 'туђе слике'], ';', '"', '');
    foreach ($pages as $rel => $p) {
        fputcsv($csv, [$rel, $p['folder'], $p['script'], $p['mixed_n'], implode(' ', $p['mixed']),
            $p['latin_checked'] ? $p['latin_n'] : 'није проверено', implode(' ', $p['latin']), $p['blank'],
            implode(' ', $p['missing']), implode(' ', $p['rewrite']), $p['title'], $p['h1'],
            $p['viewport'] ? 'да' : 'не', $p['canonical'] ? 'да' : 'не', $p['description'] ? 'да' : 'не',
            implode(' + ', $p['fonts']), $p['menu'], $p['home_link'], $p['home_ok'] ? 'да' : 'не',
            $p['alt_missing'], $p['alt_empty'], implode(' ', $p['ext_img'])], ';', '"', '');
    }
    rewind($csv);
    $csvText = (string)stream_get_contents($csv);
    fclose($csv);
    if (!write_atomic($out . '/stranice.csv', $csvText)) { return false; }

    return write_atomic($out . '/izvestaj.html', report_html($json));
}

function report_html(array $j): string
{
    $s = $j['zbir'];
    $u = function (string $rel): string { return '<a href="/' . h($rel) . '">' . h($rel) . '</a>'; };
    $rows = function (array $list, int $max = 10) use ($u): string {
        $out = []; foreach (array_slice($list, 0, $max) as $rel) { $out[] = $u($rel); }
        return implode(', ', $out) . (count($list) > $max ? ' … и још ' . (count($list) - $max) : '');
    };
    $line = function (string $label, int $n, string $note = '', bool $neutral = false): string {
        return '<tr><td>' . h($label) . '</td><td class="n ' . ($neutral ? '' : ($n ? 'z' : 'ok')) . '">' . $n . '</td><td>' . $note . '</td></tr>';
    };
    $o = '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<meta name="robots" content="noindex"><title>Извештај контроле — ПИО „Рајац“</title><style>'
       . 'body{font:15px/1.55 system-ui,sans-serif;max-width:1100px;margin:30px auto;padding:0 16px;color:#1a2238;background:#fbf9f4}'
       . 'h1,h2{color:#0f2744}h2{border-bottom:2px solid #c8a84b;padding-bottom:4px;margin-top:36px}'
       . 'table{border-collapse:collapse;width:100%;background:#fff;font-size:14px}td,th{border:1px solid #e3ddcf;padding:6px 8px;text-align:left;vertical-align:top}'
       . 'th{background:#0f2744;color:#fff}td.n{text-align:right;font-variant-numeric:tabular-nums}.z{color:#9a2b2b;font-weight:600}'
       . '.ok{color:#2f6b3a;font-weight:600}.w{overflow-x:auto}</style></head><body>'
       . '<h1>Извештај контроле сајта ПИО „Рајац“</h1><p>Контрола ' . h((string)$j['pocetak']) . ' → ' . h((string)$j['kraj'])
       . ', верзија ' . h((string)$j['verzija']) . '. Странице сајта нису мењане. Налази су кандидати за проверу, не потврђене грешке.</p>';

    $o .= '<h2>Збир</h2><div class="w"><table><tr><th>Провера</th><th>Број</th><th>Напомена</th></tr>'
        . $line('Прочитаних HTML страница', $s['stranica'], '', true)
        . $line('Нечитљивих страница', $s['necitljivo'])
        . $line('PHP фајлова изван провере', $s['php_neobuhvaceno'], 'скрипта чита само .html и .htm', true)
        . $line('Речи са мешаним ћириличним и латиничним словима', $s['mesano'], 'број страница: ' . $s['mesano_str'])
        . $line('Ћириличне странице са латиничним речима (ван дозвољених)', $s['latinica_str'], 'део ће бити дозвољен — одлучује управљач')
        . $line('Латиничне странице (латиница није проверавана)', $s['latinica_nije_provereno'], '', true)
        . $line('target="_blank"', $s['blank'], 'број страница: ' . $s['blank_str'])
        . $line('Линкови ка фајлу који не постоји', $s['nedostaje'], 'број страница: ' . $s['nedostaje_str'])
        . $line('Линкови који можда раде преко .htaccess', $s['preusmerenje'], 'проверити ручно', true)
        . $line('Странице без линка ка почетној у заглављу', $s['bez_pocetne'])
        . $line('Линк ка почетној не гласи ' . HOME_NAME, $s['pocetna_naziv'])
        . $line('Странице без наслова (title)', $s['bez_naslova'])
        . $line('Странице без тачно једног h1', $s['h1_greska'])
        . $line('Странице без viewport ознаке (мобилни приказ)', $s['bez_viewport'])
        . $line('Странице без canonical', $s['bez_canonical'])
        . $line('Странице без meta описа', $s['bez_opisa'])
        . $line('Слике без alt атрибута', $s['alt_nedostaje'])
        . $line('Слике са празним alt (проверити да ли су украсне)', $s['alt_prazan'], '', true)
        . $line('Странице са сликама са туђих адреса', $s['tudje_slike_str'])
        . '</table></div>';

    $o .= '<h2>Фолдери који нису читани</h2><p>' . ($j['preskoceni_folderi'] ? h(implode(', ', $j['preskoceni_folderi'])) : 'нема') . '</p>';

    $o .= '<h2>По фолдерима</h2><div class="w"><table><tr><th>Фолдер</th><th>Страница</th><th>Мешана слова</th><th>Латиница</th><th>_blank</th><th>Недостаје фајл</th></tr>';
    foreach ($j['folderi'] as $f => $v) {
        $o .= '<tr><td>' . h((string)$f) . '</td><td class="n">' . $v['stranica'] . '</td><td class="n">' . $v['mesano'] . '</td><td class="n">'
            . $v['latinica'] . '</td><td class="n">' . $v['blank'] . '</td><td class="n">' . $v['nedostaje'] . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Варијанте главног менија</h2><p>Пореде се називи и адресе линкова. Циљ: једна варијанта на свим јавним страницама.</p>'
        . '<div class="w"><table><tr><th>Ставке менија (назив → адреса)</th><th>Страница</th><th>Примери</th></tr>';
    foreach ($j['meniji'] as $m) {
        $o .= '<tr><td>' . h((string)$m['menu']) . '</td><td class="n">' . count($m['pages']) . '</td><td>' . $rows($m['pages'], 5) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Линк ка почетној у заглављу</h2><p>Прописани текст: ' . h(HOME_NAME) . '.</p><div class="w"><table><tr><th>Текст линка</th><th>Страница</th><th>Примери</th></tr>';
    foreach ($j['pocetna'] as $t => $list) {
        $o .= '<tr><td>' . h((string)$t) . '</td><td class="n">' . count($list) . '</td><td>' . $rows($list, 5) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Декларисани фонтови</h2><p>Ово су фонтови наведени у страници и њеним CSS фајловима, не доказ који је фонт стварно приказан — то се проверава снимцима екрана. Циљ: Cormorant Garamond, DM Sans, DM Mono.</p>'
        . '<div class="w"><table><tr><th>Фонтови</th><th>Страница</th><th>Примери</th></tr>';
    foreach ($j['fontovi'] as $fk => $list) {
        $o .= '<tr><td>' . h((string)$fk) . '</td><td class="n">' . count($list) . '</td><td>' . $rows($list, 5) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Речи са мешаним писмом</h2><div class="w"><table><tr><th>Реч</th><th>Где</th></tr>';
    foreach (array_slice($j['mesana_slova'], 0, 400, true) as $w => $list) {
        $o .= '<tr><td>' . h((string)$w) . '</td><td>' . $rows(array_values(array_unique($list)), 6) . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Латиничне речи на ћириличним страницама</h2><p>Број страница на којима се реч јавља (ван курзива и кода).</p>'
        . '<div class="w"><table><tr><th>Реч</th><th>Страница</th></tr>';
    foreach (array_slice($j['latinica_reci'], 0, 300, true) as $w => $n) {
        $o .= '<tr><td>' . h((string)$w) . '</td><td class="n">' . (int)$n . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Слике са туђих адреса</h2><div class="w"><table><tr><th>Адреса</th><th>Страница</th></tr>';
    foreach ($j['tudje_slike'] as $hst => $n) {
        $o .= '<tr><td>' . h((string)$hst) . '</td><td class="n">' . (int)$n . '</td></tr>';
    }
    $o .= '</table></div>';

    $o .= '<h2>Унутрашњи линкови за проверу</h2><p>„Недостаје фајл“ — на диску нема одредишта. „Могуће преусмерење“ — одредиште не постоји као фајл, али изнад њега постоји .htaccess са преусмерењима, па линк можда ради.</p>'
        . '<div class="w"><table><tr><th>Страница</th><th>Линк</th><th>Стање</th></tr>';
    foreach ($j['linkovi'] as $b) {
        $o .= '<tr><td>' . $u($b[0]) . '</td><td>' . h($b[1]) . '</td><td>' . h($b[2]) . '</td></tr>';
    }
    $o .= '</table></div></body></html>';
    return $o;
}
