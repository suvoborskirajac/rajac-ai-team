<?php
/**
 * ПИО „РАЈАЦ“ — преглед свих активних страница сајта (за сређивање сајта).
 * Отвара се са ?token=… . Сам проналази странице на диску; помоћне скрипте и копије прескаче.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Belgrade');

const RAJAC_TOKEN = 'rajac2026';
if (!hash_equals(RAJAC_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit('Приступ није дозвољен.');
}
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
@set_time_limit(60);

$ROOT = __DIR__;
$BASE = 'https://piorajac.rs/';
const DUBINA = 3;

/* Називи области (горњи ниво фасцикли). Непознате фасцикле добијају свој назив. */
$OBLASTI = [
  ''=>['🏠','Почетна и опште странице'],
  'o-platformi'=>['🧭','Уводне странице'],'za-posetioce'=>['🧭','Уводне странице'],'za-istrazivace'=>['🧭','Уводне странице'],
  'za-vlasnike'=>['🧭','Уводне странице'],'aplikacije'=>['🧭','Уводне странице'],
  'ptice'=>['🐦','Биодиверзитет — птице'],'sisari'=>['🦌','Биодиверзитет — сисари'],'insekti'=>['🦋','Биодиверзитет — инсекти'],
  'gljive'=>['🍄','Биодиверзитет — гљиве'],'biljke'=>['🌿','Биодиверзитет — биљке'],'trofodinamika'=>['🔗','Биодиверзитет — трофодинамика'],
  'sumska-stanista'=>['🌲','Станишта'],'livadska-stanista'=>['🌲','Станишта'],'vodena-stanista'=>['🌲','Станишта'],
  'speleoloska-stanista'=>['🌲','Станишта'],'monitoring-ekosistema'=>['🌲','Станишта'],
  'geologija'=>['⛰️','Природне одлике'],'reljef'=>['⛰️','Природне одлике'],'hidrografija'=>['⛰️','Природне одлике'],
  'klima'=>['⛰️','Природне одлике'],'pedologija'=>['⛰️','Природне одлике'],
  'istorijat'=>['🏛️','О подручју и управљачу'],'ko-upravlja'=>['🏛️','О подручју и управљачу'],'nadleznosti'=>['🏛️','О подручју и управљачу'],
  'saradnja'=>['🏛️','О подручју и управљачу'],'kontakt'=>['🏛️','О подручју и управљачу'],'kodeks'=>['🏛️','О подручју и управљачу'],
  'grupne-posete-i-dozvole'=>['🏛️','О подручју и управљачу'],
  'odricanje-odgovornosti'=>['⚖️','Правне странице'],'uslovi-i-odredbe'=>['⚖️','Правне странице'],'politika-privatnosti'=>['⚖️','Правне странице'],
  'politika-linkova'=>['⚖️','Правне странице'],'podesavanja-privatnosti'=>['⚖️','Правне странице'],
  'zvezdani-park'=>['🌌','Звездани парк и ноћно небо'],'astronomska-posmatranja'=>['🌌','Звездани парк и ноћно небо'],'mesec'=>['🌌','Звездани парк и ноћно небо'],
  'svetlosno-zagadjenje'=>['🌌','Звездани парк и ноћно небо'],'monitoring-svetlosne-zagadjenosti'=>['🌌','Звездани парк и ноћно небо'],
  'pametni-park'=>['📡','Паметни парк и мониторинг'],'savremene-tehnologije'=>['📡','Паметни парк и мониторинг'],
  'podrska-poljoprivredi-2'=>['📡','Паметни парк и мониторинг'],'monitoring-izvestaji'=>['📡','Паметни парк и мониторинг'],
  'amsrajac'=>['🌡️','Метео и непогоде'],'nevreme'=>['🌡️','Метео и непогоде'],
  'kopindeksi'=>['🛰️','Сателитски мониторинг'],'agro-kopernikus'=>['🛰️','Сателитски мониторинг'],
  'ris'=>['🛡️','РИС — оперативни центар'],'ai-nadzor'=>['🛡️','РИС — оперативни центар'],'vlasnici'=>['🛡️','РИС — оперативни центар'],
  'inat'=>['🔎','Налази и грађанска наука'],'ebird'=>['🔎','Налази и грађанска наука'],'biologer-rajac'=>['🔎','Налази и грађанска наука'],
  'rajac-vesti'=>['📰','Дневник — вести'],'staze'=>['🥾','Посете и стазе'],'staponeti'=>['🥾','Посете и стазе'],
  'istrazivacki-park-rajac'=>['🎓','Образовање'],
];
/* Фасцикле које се никад не прегледају. */
$PRESKOCI_DIR = '/^(\.|_|wp-|cgi-bin$|assets$|vendor$|cache$|data$|private$|api$|node_modules$|tmp$|temp$|backup|bak|old$|stari|test|proba|output$|fonts?$|img$|images?$|slike$|css$|js$|lib$|uploads$|tiles$|biljke-map-live$|inat-upravljac$)/i';
/* HTML датотеке које нису праве странице. */
$PRESKOCI_HTML = '/(fragment|\.bak|before|backup|kopija|copy|test|proba|preview|draft|stari|old|_block)/i';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function starost(int $sec): string {
    if ($sec < 3600) return 'пре ' . max(0, intdiv($sec, 60)) . ' мин';
    if ($sec < 86400) return 'пре ' . intdiv($sec, 3600) . ' ч';
    return 'пре ' . intdiv($sec, 86400) . ' д';
}
function naslov(string $f): string {
    $s = (string)@file_get_contents($f, false, null, 0, 8192);
    if (preg_match('~<title[^>]*>(.*?)</title>~is', $s, $m)) {
        $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
        if ($t !== '' && strpos($t, '<?') === false && strpos($t, '$') === false) return preg_replace('/\s+/u', ' ', $t);
    }
    return '';
}
function ima_naziv(string $f): ?bool {
    if (substr($f, -4) === '.php') return null; // PHP се не процењује по изворном коду
    $s = (string)@file_get_contents($f, false, null, 0, 400000);
    return (bool)preg_match('~ПИО(?:\s|&nbsp;|<[^>]{0,80}>)*„РАЈАЦ“~u', $s);
}

/* Проналажење страница. */
$strane = [];
$dodaj = function (string $abs, string $rel) use (&$strane, $ROOT) {
    $top = strpos($rel, '/') === false ? '' : explode('/', $rel)[0];
    $url = preg_replace('~(^|/)index\.(html|php)$~', '$1', $rel);
    $strane[$rel] = ['rel'=>$rel, 'url'=>$url, 'top'=>$top, 'mt'=>(int)filemtime($abs), 'f'=>$abs];
};
$skeniraj = function (string $dir, string $rel, int $d) use (&$skeniraj, $dodaj, $PRESKOCI_DIR, $PRESKOCI_HTML) {
    $ind = null;
    if (is_file("$dir/index.html")) $ind = 'index.html'; elseif (is_file("$dir/index.php")) $ind = 'index.php';
    if ($ind && $rel !== '') $dodaj("$dir/$ind", "$rel/$ind");
    foreach (@scandir($dir) ?: [] as $x) {
        if ($x === '.' || $x === '..') continue;
        $p = "$dir/$x";
        if (is_dir($p)) {
            if ($d < DUBINA && !preg_match($PRESKOCI_DIR, $x)) $skeniraj($p, $rel === '' ? $x : "$rel/$x", $d + 1);
        } elseif ($rel !== '' && preg_match('/\.html$/i', $x) && $x !== 'index.html' && !preg_match($PRESKOCI_HTML, $x)) {
            $dodaj($p, "$rel/$x");
        }
    }
};
$skeniraj($ROOT, '', 0);
foreach (['index.html', 'index.php'] as $x) if (is_file("$ROOT/$x")) { $dodaj("$ROOT/$x", $x); break; }

/* Архиве дневних извештаја — скупљају се у по једну ставку. */
$ARHIVE = [
  '~^monitoring-izvestaji/copernicus/.+~'      => ['monitoring-izvestaji/copernicus/', 'Copernicus дневни извештаји'],
  '~^monitoring-izvestaji/meteo-stanica/.+~'   => ['monitoring-izvestaji/meteo-stanica/', 'Метео станица — дневни извештаји'],
  '~^monitoring-izvestaji/dnevni/.+~'          => ['monitoring-izvestaji/dnevni/', 'Дневни оперативни брифови'],
  '~^amsrajac/dnevna-statistika/.+~'           => ['amsrajac/dnevna-statistika/arhiva.html', 'АМС — дневна статистика'],
  '~^kopernikindeks/\d{4}-\d\d-\d\d/~'         => ['kopernikindeks/', 'Copernicus индекси по датумима'],
];
/* Радне копије, тестови, закрпе, покварена кодировка. */
$CISTI = '~(test|backup|prototype|provera_|restore|probni|patch|thumbcards-2105/|vesti1007-rad/|/public_html/|radni-fajlovi/|rajac-home|#U|╨|тХ|-род/|^ptice/ptice/|-fix\.html|fixed|_stable\.html|kumulativni)~iu';
function preusmerenje(string $f): bool {
    if (@filesize($f) > 6000) return false;
    $s = (string)@file_get_contents($f);
    return (bool)preg_match('~http-equiv=["\']?refresh|location\.(replace|href)~i', $s);
}
$sada = time(); $grupe = []; $arh = []; $cist = []; $ukupno = 0; $bezNaziva = 0; $staro = 0; $nArh = 0;
foreach ($strane as $s) {
    $s['sec'] = $sada - $s['mt'];
    foreach ($ARHIVE as $re => [$koren, $ime]) {
        if (preg_match($re, $s['rel'])) {
            $a = &$arh[$ime]; $a['koren'] = $koren; $a['n'] = ($a['n'] ?? 0) + 1;
            $a['sec'] = min($a['sec'] ?? PHP_INT_MAX, $s['sec']); unset($a); $nArh++;
            continue 2;
        }
    }
    $s['naslov'] = naslov($s['f']) ?: $s['rel'];
    if (preg_match($CISTI, $s['rel']) || preusmerenje($s['f'])) {
        $s['naziv'] = null; $s['vrsta'] = preusmerenje($s['f']) ? 'преусмерење' : 'копија';
        $cist[] = $s; continue;
    }
    [$ik, $ime] = $OBLASTI[$s['top']] ?? ['📁', 'Остале фасцикле'];
    $s['naziv'] = ima_naziv($s['f']);
    $grupe[$ime]['ik'] = $ik;
    $grupe[$ime]['lista'][] = $s;
    $ukupno++;
    if ($s['naziv'] === false) $bezNaziva++;
    if ($s['sec'] > 60 * 86400) $staro++;
}
$redosled = array_values(array_unique(array_map(fn($v) => $v[1], $OBLASTI)));
uksort($grupe, function ($a, $b) use ($redosled) {
    $ia = array_search($a, $redosled, true); $ib = array_search($b, $redosled, true);
    return ($ia === false ? 999 : $ia) <=> ($ib === false ? 999 : $ib) ?: strcmp($a, $b);
});
?><!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ПИО „РАЈАЦ“ · преглед сајта</title>
<style>
:root{--ink:#1f2a22;--muted:#5d6a60;--line:#e4e1d6;--paper:#fbfaf6;--card:#fff;--green:#213325;--sand:#bdad87;--ok:#2e7d4f;--warn:#b7791f;--bad:#b23b3b}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:inherit;text-decoration:none}
.top{background:var(--green);color:#fdf6e6}
.top-in,.wrap{max-width:1180px;margin:auto;padding-left:20px;padding-right:20px}
.top-in{padding-top:14px;padding-bottom:14px;display:flex;gap:16px;align-items:center;flex-wrap:wrap}
.logo{display:flex;align-items:center;gap:9px;font-weight:700;letter-spacing:.02em;white-space:nowrap}
.logo::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--sand);box-shadow:0 0 0 4px rgba(189,173,135,.25)}
.top-in .vez{margin-left:auto;font-size:.85rem;color:rgba(253,246,230,.8)}
.hero{background:linear-gradient(135deg,#213325,#2f5b3d);color:#fdf6e6;padding:32px 0 26px}
h1{font:600 clamp(1.7rem,3.4vw,2.5rem)/1.15 Georgia,"Times New Roman",serif;margin:0 0 6px}
.sub{color:rgba(253,246,230,.8);margin:0}
.stats{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}
.stat{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:10px 14px;min-width:130px}
.stat b{display:block;font-size:1.45rem;line-height:1.1}.stat span{font-size:.8rem;color:rgba(253,246,230,.75)}
.alat{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:20px 0 6px}
.alat input[type=search]{flex:1;min-width:220px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff}
.alat label{font-size:.88rem;color:var(--muted);display:flex;gap:6px;align-items:center}
.legend{display:flex;flex-wrap:wrap;gap:14px;font-size:.8rem;color:var(--muted);margin:8px 0}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(360px,1fr));gap:18px;margin:12px 0 40px}
.grupa.cist{background:#f4f3ef;border-style:dashed;box-shadow:none}
.grupa{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px 16px 8px;box-shadow:0 6px 18px rgba(31,42,34,.05)}
.gh{display:flex;gap:10px;align-items:center;margin-bottom:6px}
.gh .ik{font-size:1.5rem}.gh h2{font-size:1.04rem;margin:0;flex:1}
.br{font-size:.75rem;color:var(--muted);background:#f3f1ea;border-radius:999px;padding:2px 9px}
details summary{cursor:pointer;font-size:.86rem;color:var(--green);padding:6px 8px;list-style:none}
details summary::before{content:"▸ ";color:var(--sand)}details[open] summary::before{content:"▾ "}
ul{list-style:none;margin:0;padding:0}
li a{display:flex;gap:10px;align-items:center;padding:8px;border-top:1px solid #f0eee6;border-radius:8px}
li:first-child a{border-top:0}
li a:hover{background:#f5f3ec}
.t{flex:1;min-width:0}.t b{display:block;font-weight:600;font-size:.92rem;overflow-wrap:anywhere}
.t small{display:block;color:var(--muted);font-size:.74rem;overflow-wrap:anywhere}
.kad{font-size:.74rem;color:var(--muted);white-space:nowrap}.kad.staro{color:var(--warn);font-weight:600}
.zn{font-size:.72rem;font-weight:700;border-radius:999px;padding:2px 7px;white-space:nowrap}
.zn.da{background:#e6f3ea;color:var(--ok)}.zn.ne{background:#f7e3e3;color:var(--bad)}.zn.php{background:#eef0f3;color:#5a6472}
.skriven{display:none!important}
footer{color:var(--muted);font-size:.8rem;padding:0 0 30px}
@media(max-width:520px){.grid{grid-template-columns:1fr}.stat{min-width:calc(50% - 5px)}}
</style>
</head>
<body>
<div class="top"><div class="top-in"><a class="logo" href="https://piorajac.rs/">ПИО „РАЈАЦ“</a>
<a class="vez" href="rajac-rezultati.php?token=<?= h(RAJAC_TOKEN) ?>">Резултати агената ›</a></div></div>
<section class="hero"><div class="wrap">
<h1>Преглед сајта</h1>
<p class="sub">Све активне странице на piorajac.rs, пронађене на диску · стање <?= h(date('d.m.Y. H:i')) ?></p>
<div class="stats">
  <div class="stat"><b><?= $ukupno ?></b><span>јавних страница</span></div>
  <div class="stat"><b><?= $nArh ?></b><span>дневних извештаја у архивама</span></div>
  <div class="stat"><b><?= count($cist) ?></b><span>копија и преусмерења</span></div>
  <div class="stat"><b><?= $bezNaziva ?></b><span>без ПИО „РАЈАЦ“</span></div>
  <div class="stat"><b><?= $staro ?></b><span>немењано преко 2 месеца</span></div>
</div>
</div></section>
<main class="wrap">
<div class="alat">
  <input id="q" type="search" placeholder="Тражи по наслову или адреси…" aria-label="Претрага страница">
  <label><input id="samoNe" type="checkbox"> само без ПИО „РАЈАЦ“</label>
</div>
<div class="legend">
  <span><span class="zn da">РАЈАЦ</span> натпис ПИО „РАЈАЦ“ постоји</span>
  <span><span class="zn ne">нема</span> натпис још није уједначен</span>
  <span><span class="zn php">PHP</span> динамичка страница, натпис се не проверава</span>
  <span><b style="color:var(--warn)">жути датум</b> — немењано преко 2 месеца</span>
</div>
<div class="grid">
<?php if ($arh): ?>
  <section class="grupa" data-g>
    <div class="gh"><span class="ik" aria-hidden="true">🗂️</span><h2>Архиве дневних извештаја</h2><span class="br"><?= $nArh ?></span></div>
    <ul><?php foreach ($arh as $ime => $a): ?>
      <li data-t="<?= h(mb_strtolower($ime)) ?>" data-ne="0"><a href="<?= h($BASE . $a['koren']) ?>">
        <span class="t"><b><?= h($ime) ?></b><small><?= (int)$a['n'] ?> извештаја · последњи <?= h(starost($a['sec'])) ?></small></span>
        <span class="kad<?= $a['sec'] > 3 * 86400 ? ' staro' : '' ?>"><?= h(starost($a['sec'])) ?></span>
      </a></li>
    <?php endforeach; ?></ul>
  </section>
<?php endif; ?>
<?php foreach ($grupe as $ime => $g):
  usort($g['lista'], fn($a, $b) => strcmp($a['rel'], $b['rel']));
  $n = count($g['lista']); $ne = count(array_filter($g['lista'], fn($s) => $s['naziv'] === false));
  $prvih = $n > 12 ? 8 : $n;
?>
  <section class="grupa" data-g>
    <div class="gh"><span class="ik" aria-hidden="true"><?= $g['ik'] ?></span><h2><?= h($ime) ?></h2>
      <span class="br"><?= $n ?><?= $ne ? ' · ' . $ne . ' без натписа' : '' ?></span></div>
    <?php $red = function ($s) use ($BASE) { ?>
      <li data-t="<?= h(mb_strtolower($s['naslov'] . ' ' . $s['rel'])) ?>" data-ne="<?= $s['naziv'] === false ? 1 : 0 ?>"><a href="<?= h($BASE . $s['url']) ?>">
        <span class="t"><b><?= h($s['naslov']) ?></b><small>/<?= h($s['url']) ?></small></span>
        <?php if ($s['naziv'] === null): ?><span class="zn php">PHP</span><?php elseif ($s['naziv']): ?><span class="zn da">РАЈАЦ</span><?php else: ?><span class="zn ne">нема</span><?php endif; ?>
        <span class="kad<?= $s['sec'] > 60 * 86400 ? ' staro' : '' ?>"><?= h(starost($s['sec'])) ?></span>
      </a></li>
    <?php }; ?>
    <ul><?php foreach (array_slice($g['lista'], 0, $prvih) as $s) $red($s); ?></ul>
    <?php if ($n > $prvih): ?>
      <details><summary>Још <?= $n - $prvih ?> страница</summary><ul><?php foreach (array_slice($g['lista'], $prvih) as $s) $red($s); ?></ul></details>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<?php if ($cist): usort($cist, fn($a, $b) => strcmp($a['rel'], $b['rel'])); ?>
  <section class="grupa cist" data-g>
    <div class="gh"><span class="ik" aria-hidden="true">🧹</span><h2>За чишћење — копије и преусмерења</h2><span class="br"><?= count($cist) ?></span></div>
    <p style="margin:0 0 6px;font-size:.82rem;color:var(--muted)">Није део јавног сајта: радне копије, тестови, закрпе и старе адресе које само преусмеравају.</p>
    <details><summary>Прикажи списак</summary><ul><?php foreach ($cist as $s): ?>
      <li data-t="<?= h(mb_strtolower($s['naslov'] . ' ' . $s['rel'])) ?>" data-ne="0"><a href="<?= h($BASE . $s['url']) ?>">
        <span class="t"><b><?= h($s['naslov']) ?></b><small>/<?= h($s['url']) ?></small></span>
        <span class="zn php"><?= h($s['vrsta']) ?></span><span class="kad"><?= h(starost($s['sec'])) ?></span>
      </a></li>
    <?php endforeach; ?></ul></details>
  </section>
<?php endif; ?>
</div>
<footer>Страница сама проналази странице на диску (до три нивоа фасцикли). Прескаче фасцикле са подацима, сликама, копијама и помоћне PHP скрипте; приказује само <code>index</code> странице и самосталне <code>.html</code> странице.</footer>
</main>
<script>
(function(){var q=document.getElementById('q'),ne=document.getElementById('samoNe');
function f(){var t=q.value.trim().toLowerCase(),s=ne.checked;
 document.querySelectorAll('[data-g]').forEach(function(g){var vid=0;
  g.querySelectorAll('li').forEach(function(li){var ok=(!t||li.dataset.t.indexOf(t)>-1)&&(!s||li.dataset.ne==='1');li.classList.toggle('skriven',!ok);if(ok)vid++;});
  var d=g.querySelector('details');if(d&&(t||s))d.open=true;
  g.classList.toggle('skriven',vid===0);});}
q.addEventListener('input',f);ne.addEventListener('change',f);})();
</script>
</body>
</html>
