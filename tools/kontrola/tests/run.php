<?php
/**
 * Аутоматски тестови контролне скрипте.
 * Покретање: php tools/kontrola/tests/run.php
 * Сваки тест има позитиван (грешка мора бити нађена) и негативан пример
 * (исправан садржај не сме бити пријављен).
 */

declare(strict_types=1);
define('KONTROLA_TEST', true);
require __DIR__ . '/../kontrola.php';

$pass = 0; $fail = 0;
function ok(string $name, bool $cond): void
{
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "НЕУСПЕХ: $name\n"; }
}

/* ---------- 1. мешана слова ---------- */
ok('1+ сoко (латинично o)',            array_keys(mixed_words(visible_text('<p>Степски сoко</p>'))) === ['сoко']);
ok('1+ с<strong>o</strong>ко',          count(mixed_words(visible_text('<p>с<strong>o</strong>ко</p>'))) === 1);
ok('1+ čвор (латинично č)',            count(mixed_words(visible_text('<p>čвор</p>'))) === 1);
ok('1+ латинично C у речи Сокол',      count(mixed_words(visible_text('<p>Cокол</p>'))) === 1);
ok('1- исто у <script>',               mixed_words(visible_text('<script>var a="сoко";</script><p>соко</p>')) === []);
ok('1- исто у коментару',              mixed_words(visible_text('<!-- сoко --><p>соко</p>')) === []);
ok('1- ћирилица и латиница одвојене',  mixed_words(visible_text('<p><span>Птице</span> <span>eBird</span></p>')) === []);
ok('1- суседни линкови у менију нису једна реч', mixed_words(visible_text('<nav><a href="/">eBird</a><a href="/p/">Птице</a></nav>')) === []);
ok('1- блок ознаке не спајају речи',   mixed_words(visible_text('<p>Птице</p><p>eBird</p>')) === []);
ok('1- чиста ћирилица',                mixed_words(visible_text('<p>Обични мишар кружи</p>')) === []);


/* ---------- назив и наводници (1.2) ---------- */
ok('N+ ПИО Рајац без наводника',        array_keys(name_issues('Птице у ПИО Рајац.')) === ['ПИО Рајац']);
ok('N+ ПИО „Рајац” погрешан затв.',     count(name_issues('у ПИО „Рајац” и')) === 1);
ok('N+ PIO Rajac латиницом',            count(name_issues('PIO Rajac')) === 1);
ok('N+ Предео изузетних одлика Рајац',   count(name_issues('Предео изузетних одлика Рајац')) === 1);
ok('N- ПИО „Рајац“',                     name_issues('ПИО „Рајац“ и ПИО „РАЈАЦ“ и Предео изузетних одлика „Рајац“') === []);
ok('N- други Рајац',                     name_issues('планина Рајац, на Рајцу') === []);
ok('Q+ „…” погрешан',                   count(quote_issues('гласно „ко-ко-ко” при узлету')) === 1);
ok('Q+ „…" погрешан',                   count(quote_issues('гласно „ко-ко-ко" при')) === 1);
ok('Q+ "…" око ћирилице',               count(quote_issues('реч "посмртни" значи')) === 1);
ok('Q- „…“ исправно',                    quote_issues('гласно „ко-ко-ко“ и „Рајац“') === []);
ok('Q- латинички наводници',             quote_issues('He said "hello" there') === []);

/* ---------- 2. латиничне речи ---------- */
$lw = function (string $h): array { return array_keys(latin_words($h)); };
ok('2+ самостално C у календару',      $lw('<p>јан C феб Ц/Г</p>') === ['C']);
ok('2+ латиница у тексту линка',       in_array('kamp', $lw('<p>Види <a href="/x/">Rajac kamp</a></p>'), true));
ok('2+ čvor (\p{Latin})',              in_array('čvor', $lw('<p>Један čvor овде</p>'), true));
ok('2- °C и °F',                       $lw('<p>Температура 12 °C и 50 °F</p>') === []);
ok('2- научни назив у курзиву',        $lw('<p>Врста <i>Buteo buteo</i> и <em>Fagus moesiaca</em></p>') === []);
ok('2- дозвољени изрази',              $lw('<p>Снимак Sentinel-2, индекс NDVI, IUCN LC, eBird</p>') === []);
ok('2- адресе',                        $lw('<p>Види www.example.com и 10000birds.com и https://gbif.org/x</p>') === []);
ok('2- код',                           $lw('<p>Фајл <code>parcele_joined.geojson</code></p>') === []);
ok('2  латинична страница се не проверава', script_of(visible_text('<p>Protected landscape with forests</p>')) === 'латиница');

/* ---------- 3. фонтови ---------- */
$gf = '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&amp;family=DM+Sans&display=swap" rel="stylesheet">';
$f = fonts_in($gf);
ok('3+ Google Fonts, две породице',    in_array('Cormorant Garamond', $f, true) && in_array('DM Sans', $f, true));
ok('3+ CSS променљива',                fonts_in(":root{--font-head:'DM Mono',monospace}") === ['DM Mono']);
ok('3+ системски фонт',                fonts_in('body{font-family:-apple-system,Segoe UI}') === ['-apple-system']);
ok('3- var() и inherit нису фонт',     fonts_in('body{font-family:var(--x)}p{font-family:inherit}') === []);

/* ---------- 4. мени ---------- */
$a = menu_info('index.html', '<nav><a href="/ptice/">Птице</a><a href="/biljke/">Биљке</a></nav>');
$b = menu_info('index.html', '<nav><a href="/ptice/">Птице</a><a href="/flora/">Биљке</a></nav>');
ok('4+ исти називи, различите адресе', implode('·', $a['items']) !== implode('·', $b['items']));
$c = menu_info('ptice/index.html', '<nav><a href="../ptice/">Птице</a><a href="/biljke/">Биљке</a></nav>');
ok('4- исте адресе у различитом облику', implode('·', $a['items']) === implode('·', $c['items']));
$hdr = main_header('<body><nav><a href="/">ПИО „Рајац“</a></nav><main><article><header><a href="/x">Вест</a></header></article></main></body>');
ok('4+ главно заглавље пре <main>',    strpos($hdr, 'ПИО') !== false && strpos($hdr, 'Вест') === false);

/* ---------- 5. линк ка почетној ---------- */
$m = menu_info('ptice/buteo-buteo/index.html', '<header><a href="../../">ПИО „Рајац“</a></header>');
ok('5+ релативно ../../ је почетна',   $m['home'] === 'ПИО „Рајац“');
ok('5+ /index.html је почетна',        menu_info('a.html', '<header><a href="/index.html">ПИО Рајац</a></header>')['home'] === 'ПИО Рајац');
ok('5+ https://piorajac.rs је почетна', menu_info('a.html', '<header><a href="https://piorajac.rs">x</a></header>')['home'] === 'x');
ok('5- /ptice/ није почетна',          menu_info('a.html', '<header><a href="/ptice/">Птице</a></header>')['home'] === null);
ok('5  назив се пореди тачно',         HOME_NAME === 'ПИО „Рајац“' && 'ПИО Рајац' !== HOME_NAME);

/* ---------- 6. target="_blank" ---------- */
ok('6+ target="_blank"',               count_blank('<a href="x" target="_blank">x</a>') === 1);
ok("6+ target='_blank'",               count_blank("<a href='x' target='_blank'>x</a>") === 1);
ok('6+ TARGET = _blank без наводника', count_blank('<a href=x TARGET = _blank>x</a>') === 1);
ok('6- data-target="_blank"',          count_blank('<button data-target="_blank">x</button><a data-target="_blank" href="x">y</a>') === 0);
ok('6- у JavaScript-у',                count_blank('<script>a.target="_blank";</script>') === 0);
ok('6- у коментару',                   count_blank('<!-- <a target="_blank"> -->') === 0);
ok('6- текст на страници',             count_blank('<p>атрибут target="_blank" не користимо</p>') === 0);

/* ---------- 7. путање и линкови ---------- */
ok('7  ?page=2 је иста страница',      local_path('vesti/index.html', '?page=2') === 'vesti/index.html');
ok('7  ../ релативно',                 local_path('ptice/buteo-buteo/index.html', '../../index.html') === 'index.html');
ok('7  апсолутна адреса сајта',        local_path('a.html', 'https://piorajac.rs/ptice/?x=1#y') === 'ptice/');
ok('7  кодирана ћирилица',             local_path('a.html', '/%D0%B1%D0%B8%D1%99%D0%BA%D0%B5/') === 'биљке/');
ok('7  спољни линк',                   local_path('a.html', 'https://ebird.org/x') === null);
ok('7  mailto, tel, #',                local_path('a.html', 'mailto:a@b.rs') === null && local_path('a.html', 'tel:+381') === null && local_path('a.html', '#top') === null);

/* ---------- 8. слике ---------- */
$im = image_info('<img src="/a.jpg"><img src="/b.jpg" alt=""><img src="/c.jpg" alt="Опис">');
ok('8+ alt недостаје',                 $im['alt_missing'] === 1);
ok('8+ alt празан одвојено',           $im['alt_empty'] === 1);
ok('8+ srcset са туђе адресе',         image_info('<img alt="x" src="/a.jpg" srcset="/a.jpg 1x, https://rajac.rs/b.jpg 2x">')['ext'] === ['rajac.rs']);
ok('8+ <picture><source>',             image_info('<picture><source srcset="https://upload.wikimedia.org/x.webp"><img alt="x" src="/a.jpg"></picture>')['ext'] === ['upload.wikimedia.org']);
ok('8+ CSS позадина',                  image_info('<div style="background:url(https://commons.wikimedia.org/x.jpg)"></div>')['ext'] === ['commons.wikimedia.org']);
ok('8+ позадина у <style>',            image_info('<style>.h{background-image:url("https://rajac.rs/h.jpg")}</style>')['ext'] === ['rajac.rs']);
ok('8- сопствене слике и Google Fonts', image_info('<img alt="x" src="https://piorajac.rs/a.jpg"><style>@font-face{src:url(https://fonts.gstatic.com/x.woff2)}</style>')['ext'] === []);
ok('8- слика у коментару',             image_info('<!-- <img src="https://rajac.rs/x.jpg"> -->')['ext'] === []);

/* ---------- 9. наслов и мета ---------- */
$hc = head_checks('<head><title> Мишар </title><meta content="width=device-width" name = "viewport"><link href="https://piorajac.rs/" rel="canonical"><meta name="description" content="Опис"></head><h1>a</h1>');
ok('9+ title',                         $hc['title'] === 'Мишар');
ok('9+ viewport са размацима и обрнутим редом', $hc['viewport'] === true);
ok('9+ canonical',                     $hc['canonical'] === true);
ok('9+ description',                   $hc['description'] === true);
ok('9+ један h1',                      $hc['h1'] === 1);
$hc2 = head_checks('<!-- <meta name="viewport" content="x"><title>Стари</title><h1>x</h1> --><meta name="description" content=""><h1>a</h1><h1>b</h1>');
ok('9- viewport само у коментару',     $hc2['viewport'] === false);
ok('9- title само у коментару',        $hc2['title'] === '');
ok('9- празан description',            $hc2['description'] === false);
ok('9+ два h1',                        $hc2['h1'] === 2);

/* ---------- регресија: друга ChatGPT рецензија ---------- */
ok('Р1- data-note=\'target="_blank"\' није _blank', count_blank("<a data-note='target=\"_blank\"' href=\"/x\">x</a>") === 0);
ok('Р1+ прави _blank после лажног',     count_blank("<a data-note='target=\"_blank\"' target=\"_blank\" href=\"/x\">x</a>") === 1);
ok('Р1- title=\'href="/fake"\' није href', attr("<a title='href=\"/fake\"' href=\"/real/\">x</a>", 'href') === '/real/');
ok('Р1- лажни href из title у менију',  menu_info('a.html', "<nav><a title='href=\"/\"' href=\"/ptice/\">Птице</a></nav>")['home'] === null);
ok('Р1  „>“ у вредности атрибута',      image_info('<img alt="a > b" src="https://rajac.rs/x.jpg">') === ['alt_missing' => 0, 'alt_empty' => 0, 'ext' => ['rajac.rs']]);
ok('Р1  важи прво појављивање',         attr('<a href="/prvi/" href="/drugi/">x</a>', 'href') === '/prvi/');
ok('Р1  вредност без наводника',        attr('<a href=/ptice/ class=x>x</a>', 'href') === '/ptice/');
ok('Р1  атрибут без вредности',         attr('<input disabled type="text">', 'disabled') === '' && attr('<input disabled>', 'type') === null);
ok('Р1  велика слова у називу',         attr('<A HREF="/x/">x</A>', 'href') === '/x/');
ok('Р2- @import споља није слика',      image_info('<style>@import url("https://cdn.example.com/lib.css");</style>')['ext'] === []);
ok('Р2- @font-face споља није слика',   image_info('<style>@font-face{font-family:X;src:url(https://fonts.example.com/f.woff2)}</style>')['ext'] === []);
ok('Р2- CSS коментар није слика',       image_info('<style>/* background:url(https://rajac.rs/x.jpg) */</style>')['ext'] === []);
ok('Р2+ позадина поред @import',        image_info('<style>@import url(https://cdn.example.com/a.css);.h{background:#000 url(https://rajac.rs/h.jpg) no-repeat}</style>')['ext'] === ['rajac.rs']);
ok('Р2+ border-image и list-style',     image_info('<style>ul{list-style-image:url(https://a.org/d.png)}.b{border-image:url(https://b.org/e.png) 30}</style>')['ext'] === ['a.org', 'b.org']);
$pg = '<html><head><title>Rajac test page</title><meta name="description" content="Opis"></head><body><h1>Мишар</h1><p>Текст о мишару.</p></body></html>';
ok('Р3- <title> није видљив текст',     visible_text($pg) === 'Мишар Текст о мишару.');
ok('Р3- латиница из <title> се не броји', latin_words($pg) === []);
ok('Р3- мешано у <title> није у тексту', mixed_words(visible_text('<html><head><title>Сoко</title></head><body><p>соко</p></body></html>')) === []);
ok('Р3  страница без <body>, само <head>', visible_text('<head><title>Rajac</title></head><p>Текст</p>') === 'Текст');
ok('Р3  наслов се и даље чита за title проверу', head_checks($pg)['title'] === 'Rajac test page');
ok('Р3  текст линка у менију и даље постоји', plain('<a href="/">ПИО <b>„Рајац“</b></a>') === 'ПИО „Рајац“');

/* ---------- безбедност: заштита фолдера ---------- */
ok('А+ REMOTE_USER од сервера',         auth_ok(['REMOTE_USER' => 'zoran']) === true);
ok('А+ REDIRECT_REMOTE_USER',           auth_ok(['REDIRECT_REMOTE_USER' => 'zoran']) === true);
ok('А- ништа',                          auth_ok([]) === false);
ok('А- празан REMOTE_USER',             auth_ok(['REMOTE_USER' => '  ']) === false);
ok('А- само PHP_AUTH_USER (измишљено заглавље)', auth_ok(['PHP_AUTH_USER' => 'napadac', 'PHP_AUTH_PW' => 'x']) === false);
ok('А- само HTTP_AUTHORIZATION',        auth_ok(['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('a:b')]) === false);
ok('А- AUTH_USER није довољан',         auth_ok(['AUTH_USER' => 'x']) === false);
ok('А- HTTP_REMOTE_USER (заглавље Remote-User)', auth_ok(['HTTP_REMOTE_USER' => 'x']) === false);

/* ---------- провере на пробном сајту (фајлови) ---------- */
$site = sys_get_temp_dir() . '/kontrola-site-' . getmypid();
$pub = $site . '/public_html';
foreach (['', '/ptice/buteo-buteo', '/биљке', '/vesti', '/ris', '/stara.bak', '/assets', '/preusmereno'] as $d) { @mkdir($pub . $d, 0777, true); }
file_put_contents($pub . '/index.html', '<h1>x</h1>');
file_put_contents($pub . '/биљке/index.html', '<h1>x</h1>');
file_put_contents($pub . '/vesti/index.php', '<?php echo 1;');
file_put_contents($pub . '/ris/a.html', 'x');
file_put_contents($pub . '/stara.bak/a.html', 'x');
file_put_contents($pub . '/kopija.html.bak', 'x');
file_put_contents($pub . '/preusmereno/.htaccess', "RewriteEngine On\nRewriteRule ^(.*)$ index.php [L]\n");
file_put_contents($pub . '/assets/a.css', "@import url('b.css');\n");
file_put_contents($pub . '/assets/b.css', "body{font-family:'DM Sans',sans-serif}\n");
file_put_contents($pub . '/ptice/buteo-buteo/index.html', '<h1>x</h1>');
$ht = [];
ok('Ф  постоји ћирилични фолдер',      link_status($pub, 'биљке/', $ht) === 'ok');
ok('Ф  vesti/ са index.php постоји',   link_status($pub, 'vesti/', $ht) === 'ok');
ok('Ф+ недостаје фајл',                link_status($pub, 'ptice/nepostoji/', $ht) === 'missing_file');
ok('Ф  могуће преусмерење',            link_status($pub, 'preusmereno/clanak-5/', $ht) === 'rewrite_possible');
$cc = [];
ok('Ф+ @import CSS ланац',             css_fonts($pub, 'assets/a.css', $cc) === ['DM Sans']);
$col = collect($pub);
ok('Ф  обухват: ris/ и .bak прескочени', !in_array('ris/a.html', $col['files'], true) && !in_array('stara.bak/a.html', $col['files'], true) && !in_array('kopija.html.bak', $col['files'], true));
ok('Ф  обухват: прескочени фолдери у извештају', in_array('ris/', $col['skipped'], true) && in_array('stara.bak/', $col['skipped'], true));
ok('Ф  обухват: PHP пребројан',        $col['php'] === 1);
ok('Ф  обухват: HTML пронађен',        in_array('биљке/index.html', $col['files'], true) && in_array('ptice/buteo-buteo/index.html', $col['files'], true));

/* ---------- помоћне ---------- */
ok('П  lower() ради и без mbstring',   lower('ABC') === 'abc');
ok('П  атомско уписивање',             save_json($site . '/s.json', ['a' => 'ћ']) && json_decode((string)file_get_contents($site . '/s.json'), true) === ['a' => 'ћ'] && !glob($site . '/s.json.tmp*'));

exec('rm -rf ' . escapeshellarg($site));
echo "Тестова: " . ($pass + $fail) . ", прошло: $pass, пало: $fail\n";
exit($fail ? 1 : 0);
