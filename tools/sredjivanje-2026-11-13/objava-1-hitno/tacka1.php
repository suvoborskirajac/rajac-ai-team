<?php
// Привремена скрипта (Claude, 13.11.2026): тачка 1 сређивања piorajac.rs.
// Ради само са тачним кључем; без ?apply=1 само проверава. Брише се одмах после употребе.
header('Content-Type: text/plain; charset=utf-8');
if (!isset($_GET['k']) || !hash_equals('__KLJUC__', $_GET['k'])) { http_response_code(404); exit; }
@set_time_limit(300); @ini_set('memory_limit', '512M');
$ROOT = __DIR__;
$EXPECT = [
__HASHES__
];
$out = [];   // relpath => bytes
$copies = []; // relpath => source relpath

function rd($p) { global $ROOT; $s = @file_get_contents("$ROOT/$p"); if ($s === false) throw new Exception("нема фајла $p"); return $s; }

function extract_b64($h, $names, $htmlPrefix, $diskFolder, &$out) {
    $EXT = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/svg+xml'=>'svg','video/mp4'=>'mp4'];
    $res = ''; $pos = 0; $i = 0; $len = strlen($h); $from = 0;
    while (($d = strpos($h, 'data:', $from)) !== false) {
        $from = $d + 5;
        $semi = strpos($h, ';base64,', $d);
        if ($semi === false) break;
        $mime = substr($h, $d + 5, $semi - $d - 5);
        if (!preg_match('~^[a-z0-9/+.-]+$~', $mime)) continue;
        $s = $semi + 8; $e = $s;
        while ($e < $len && strpos("ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/= \t\r\n\f\v", $h[$e]) !== false) $e++;
        if ($e - $s < 3000 || $e >= $len || strpos("\"')", $h[$e]) === false) continue;
        if (!isset($EXT[$mime])) throw new Exception("непознат тип $mime");
        $name = $names[$i++] . '.' . $EXT[$mime];
        $out[$diskFolder . $name] = base64_decode(preg_replace('~\s~', '', substr($h, $s, $e - $s)), true);
        $res .= substr($h, $pos, $d - $pos) . $htmlPrefix . $name; $pos = $e; $from = $e;
    }
    if ($i !== count($names)) throw new Exception("очекивано " . count($names) . " уграђених, нађено $i");
    return $res . substr($h, $pos);
}

try {
    // 1. Дигитални центар
    $p = 'digitalni-centar/index.html';
    $out[$p] = extract_b64(rd($p), ['staze-dobra-voda-20261031', 'staze-dobra-voda-20261031'], '', 'digitalni-centar/', $out);
    // 2. Вести и Лептири
    $p = 'rajac-vesti/ams-rajac-avgust-2026-klimatoloski-izvestaj/'; $f = $p . 'grafikoni/';
    $out[$p.'index.html'] = extract_b64(rd($p.'index.html'), ['01-pokrivenost-merenja','02-dnevna-temperatura','03-dnevni-hod-temperature','04-srednja-avgustovska-temperatura-1950-2020','05-minimalne-temperature-p10-p90','06-avgustovske-padavine-1950-2020','07-dnevni-vpd','08-dnevni-hod-vlaznosti-vpd','09-padavine','10-ruza-vetrova','11-pritisak'], "/$f", $f, $out);
    $p = 'rajac-vesti/prvi-istrazivacki-rad-svetlosna-zagadjenost-pio-rajac/'; $f = $p . 'grafikoni/';
    $out[$p.'index.html'] = extract_b64(rd($p.'index.html'), ['grafikon-1','grafikon-2','grafikon-3','grafikon-4','grafikon-5'], "/$f", $f, $out);
    $p = 'rajac-vesti/svetlosno-zagadjenje-pio-rajac-januar-jun-2026/'; $f = $p . 'slike/';
    $out[$p.'index.html'] = extract_b64(rd($p.'index.html'), ['radijansa-jun-2026'], "/$f", $f, $out);
    $p = 'rajac-leptiri/'; $f = $p . 'slike/';
    $out[$p.'index.html'] = extract_b64(rd($p.'index.html'), ['rajac-leptiri-naslovna'], "/$f", $f, $out);
    // 3. Почетна
    $h = extract_b64(rd('index.html'), ['pocetna-logo'], '/assets/img/pocetna/', 'assets/img/pocetna/', $out);
    preg_match_all('~(?:https://piorajac\.rs)?/wp-content/uploads/([A-Za-z0-9_./-]+)~', $h, $m);
    foreach (array_unique($m[1]) as $img) $copies['assets/img/arhiva/' . $img] = 'wp-content/uploads/' . $img;
    $out['index.html'] = preg_replace('~(?:https://piorajac\.rs)?/wp-content/uploads/~', '/assets/img/arhiva/', $h);
    // 4. Звездани парк
    foreach (['zvezdani-park/07-opservatorija-i-oprema.html', 'zvezdani-park/pravila-ponasanja.html'] as $p) {
        $h = rd($p); $bad = 'href="[mailto:zvezdanipark@piorajac.rs](mailto:zvezdanipark@piorajac.rs)"';
        if (substr_count($h, $bad) !== 1) throw new Exception("mailto није нађен у $p");
        $out[$p] = str_replace($bad, 'href="mailto:zvezdanipark@piorajac.rs"', $h);
    }
    // 5. Стазе
    $h = rd('staze/index.html'); $old = '<h2 id="mainTitle">ПЕШАЧКЕ СТАЗЕ НА РАЈЦУ</h2>';
    if (substr_count($h, $old) !== 1) throw new Exception('наслов на стазама није нађен');
    $h = str_replace($old, '<h1 id="mainTitle">ПЕШАЧКЕ СТАЗЕ НА РАЈЦУ</h1>', $h);
    $out['staze/index.html'] = str_replace(['.rta-header .rta-minihero h2{', '.rta-header h2{'], ['.rta-header .rta-minihero :is(h1,h2){', '.rta-header :is(h1,h2){'], $h);
    // 6. Приручник птица
    $out['ptice/prirucnik/assets/style.css'] = rd('ptice/prirucnik/assets/style.css') .
        "\n\n/* 2026-11-13: заглавље на телефону (до 430 px) без бочног скрола */\n" .
        "@media (max-width:430px){.topbar .wrap{gap:8px}.topbar .wrap .brand{min-width:0;flex:1 1 auto}" .
        ".topbar .wrap .brand strong{white-space:normal;line-height:1.1}" .
        ".field-mode-toggle64{padding:6px 8px;font-size:10.5px;flex:0 0 auto}.topbar .navbtn{flex:0 0 auto}}\n";
    foreach ($copies as $dst => $src) $out[$dst] = rd($src);

    // Провера према пакету
    $bad = [];
    foreach ($EXPECT as $p => $sha) { if (!isset($out[$p])) $bad[] = "НЕДОСТАЈЕ $p"; elseif (hash('sha256', $out[$p]) !== $sha) $bad[] = "РАЗЛИКА $p"; }
    foreach ($out as $p => $b) if (!isset($EXPECT[$p])) $bad[] = "ВИШАК $p";
    if ($bad) { echo "СТОП — ништа није уписано:\n" . implode("\n", $bad) . "\n"; exit; }
    echo "Провера: свих " . count($EXPECT) . " фајлова се поклапа са пакетом.\n";
    if (empty($_GET['apply'])) { echo "Режим провере — ништа није уписано.\n"; exit; }

    // Резервна копија ван public_html
    $bk = dirname($ROOT) . '/_rezerva_tacka1_20261113';
    foreach ($out as $p => $b) if (is_file("$ROOT/$p")) {
        @mkdir(dirname("$bk/$p"), 0755, true);
        if (!is_file("$bk/$p") && !copy("$ROOT/$p", "$bk/$p")) throw new Exception("резерва није успела за $p");
    }
    echo "Резерва: $bk\n";
    $n = 0; $skip = 0;
    foreach ($out as $p => $b) {
        if (is_file("$ROOT/$p") && hash_file('sha256', "$ROOT/$p") === hash('sha256', $b)) { @chmod("$ROOT/$p", 0644); $skip++; continue; }
        if (!is_dir(dirname("$ROOT/$p")) && !@mkdir(dirname("$ROOT/$p"), 0755, true)) throw new Exception("фасцикла није направљена за $p: " . json_encode(error_get_last(), JSON_UNESCAPED_UNICODE));
        $w = @file_put_contents("$ROOT/$p", $b, LOCK_EX);
        if ($w !== strlen($b)) throw new Exception("упис није успео: $p (" . var_export($w, true) . ") " . json_encode(error_get_last(), JSON_UNESCAPED_UNICODE));
        @chmod("$ROOT/$p", 0644); $n++;
    }
    echo "Уписано нових/измењених: $n, већ исправних (прескочено): $skip\n";
    $ok = 0; foreach ($EXPECT as $p => $sha) if (hash_file('sha256', "$ROOT/$p") === $sha) $ok++;
    echo "Уписано и проверено на диску: $ok од " . count($EXPECT) . "\n";
} catch (Exception $e) { echo "ГРЕШКА: " . $e->getMessage() . "\n"; }
