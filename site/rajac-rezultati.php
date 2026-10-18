<?php
/**
 * ПИО „Рајац“ — преглед резултата агената (само корисне странице).
 * Отвара се са ?token=… ; листа страница је изабрана ручно, старост се чита са диска.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Belgrade');

const RAJAC_TOKEN = 'rajac2026';
$token = (string)($_GET['token'] ?? '');
if (!hash_equals(RAJAC_TOKEN, $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Приступ није дозвољен.');
}
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

$ROOT = __DIR__;
$BASE = 'https://piorajac.rs/';

/* Групе: странице (путања, наслов, кратак опис) и подаци агента (само за показатељ свежине). */
$GRUPE = [
  ['ikona'=>'🌡️','naziv'=>'Метео станица АМС Рајац','opis'=>'Временски услови уживо, локални модел и дневна статистика.',
   'strane'=>[
     ['amsrajac/index.html','АМС Рајац уживо','Временски услови и метеоролошки подаци'],
     ['amsrajac/fmi_shadow_php_status.php','Анализа локалног модела','Поређење прогнозе и мерења'],
     ['amsrajac/dnevna-statistika/index.php','Дневна статистика','Последњи дан, сажето'],
     ['amsrajac/dnevna-statistika/arhiva.html','Архива дневних статистика','Сви претходни дани'],
     ['ris/meteo-stanica/index.html','РИС M11 — Метео станица','Технички надзор станице'],
     ['ris/agrometeo/index.html','РИС M5 — Агрометео','Здравље биљака и агрометеорологија'],
     ['pametni-park/index.html','Дигитални мониторинг','Јавна страница паметног парка'],
   ],
   'podaci'=>['amsrajac/data/current_live.json','amsrajac/data/latest.json']],
  ['ikona'=>'⛈️','naziv'=>'Непогоде и грмљавина','opis'=>'Рана најава грмљавине и невремена.',
   'strane'=>[['nevreme/index.php','Невреме Рајац','Рана најава грмљавине']],
   'podaci'=>['nevreme/data/public.json','nevreme/data/forecast.json']],
  ['ikona'=>'🔥','naziv'=>'Пожари — Fire Monitor','opis'=>'Пожарна опасност и зоне угрожености.',
   'strane'=>[
     ['ris/pozari/index.php','Пожари — оперативни преглед','Тренутна пожарна опасност'],
     ['ris/pozari/index.html','Зоне повећане угрожености','Карта зона'],
   ],
   'podaci'=>['ris/pozari/data/latest_status.json']],
  ['ikona'=>'🪓','naziv'=>'Сеча шума и промене простора','opis'=>'Сателитско праћење сече и промена у простору.',
   'strane'=>[
     ['kopindeksi/forest-monitor/index.php','Forest Monitor','Детекција сече шума'],
     ['ris/promene-prostora/index.php','Монитор промена простора','Промене на терену'],
   ],
   'podaci'=>[]],
  ['ikona'=>'🛰️','naziv'=>'Copernicus индекси и парцеле','opis'=>'Сателитски индекси, парцеле и поређења снимака.',
   'strane'=>[
     ['kopindeksi/index.html','Сателитски и агрометео мониторинг','Почетна страница индекса'],
     ['kopindeksi/ams-copernicus-agent/output/latest.html','АМС × Copernicus — последњи извештај','Аутоматски дневни извештај агента'],
     ['kopindeksi/parcel_longterm_analytics.html','Дугорочна анализа парцела','Трендови по парцелама'],
     ['kopindeksi/field-changes/field_changes_map.php','RGB поређење снимака','Пре и после на карти'],
   ],
   'podaci'=>['kopindeksi/ams-copernicus-agent/output/latest.json']],
  ['ikona'=>'🌾','naziv'=>'Пољопривредни Copernicus','opis'=>'Пољопривредне културе, карте и трендови.',
   'strane'=>[
     ['agro-kopernikus/index.html','Агро Коперникус','Почетна страница'],
     ['agro-kopernikus/copernicus_agri_dashboard.php','Културе — карте и статистика','Табла по културама'],
     ['agro-kopernikus/copernicus_agri_monthly_trends.php','Месечни трендови','Кретање индекса по месецима'],
     ['agro-kopernikus/copernicus_agri_season_trends.php','Сезонски трендови','Поређење сезона'],
   ],
   'podaci'=>[]],
  ['ikona'=>'🐦','naziv'=>'Птице — eBird','opis'=>'Посматрања птица на Рајцу и у околини.',
   'strane'=>[
     ['ebird/index.php','eBird мониторинг птица','Рајац и непосредна околина'],
     ['ptice/index.html','Каталог птица','Странице врста'],
   ],
   'podaci'=>[]],
  ['ikona'=>'🦋','naziv'=>'Налази врста — Biologer','opis'=>'Налази из базе Biologer.',
   'strane'=>[['biologer-rajac/index.php','Biologer — налази на Рајцу','Преглед налаза']],
   'podaci'=>['biologer-rajac/data/observations.json']],
  ['ikona'=>'📊','naziv'=>'Дневни извештаји мониторинга','opis'=>'Аутоматски извештаји Copernicus-а и биодиверзитета.',
   'strane'=>[
     ['monitoring-izvestaji/index.html','Мониторинг извештаји','Јавна страница'],
     ['monitoring-izvestaji/copernicus/index.html','Copernicus извештаји','Дневни сателитски извештаји'],
     ['monitoring-izvestaji/biodiverzitet/index.html','Извештаји биодиверзитета','Последњи налази'],
     ['monitoring-izvestaji/biodiverzitet/cron-status.php','Статус освежавања (Cron)','Да ли агент ради'],
   ],
   'podaci'=>['monitoring-izvestaji/copernicus/index.html','monitoring-izvestaji/biodiverzitet/latest-fragment.html']],
  ['ikona'=>'🌌','naziv'=>'Светлосно загађење','opis'=>'Приручник и радар нове литературе.',
   'strane'=>[
     ['svetlosno-zagadjenje/index.html','Светлосно загађење заштићених предела','Приручник'],
     ['svetlosno-zagadjenje/metodologija-merenja-svetlosnog-zagadjenja.html','Методологија истраживања','Поглавље'],
     ['svetlosno-zagadjenje/svetlosno-zagadjenje-u-srbiji.html','Светлосно загађење у Србији','Поглавље'],
     ['svetlosno-zagadjenje/obrada-podataka.html','Обрада и анализа података','Поглавље'],
     ['svetlosno-zagadjenje/dodaci.html','Додаци (прилози)','Поглавље'],
     ['svetlosno-zagadjenje/literatura/index.html','Најновији радови за проверу','Радар литературе'],
   ],
   'podaci'=>['svetlosno-zagadjenje/literatura/data/latest.json']],
  ['ikona'=>'🏠','naziv'=>'Власници објеката и парцела','opis'=>'Пријава власника и евиденција.',
   'strane'=>[['vlasnici/index.php','Пријава власника','Улаз у евиденцију']],
   'podaci'=>[]],
  ['ikona'=>'📷','naziv'=>'Надзор камера и AI','opis'=>'Камере на терену и аутоматска анализа.',
   'strane'=>[
     ['ai-nadzor/index.php','RAJAC AI Monitor','Преглед камера'],
     ['ai-nadzor/rajac3-status.php','RAJAC-03 статус','Стање камере 3'],
   ],
   'podaci'=>[]],
  ['ikona'=>'🥾','naziv'=>'Стазе','opis'=>'Пешачке стазе са временским условима.',
   'strane'=>[['staze/index.html','Пешачке стазе на Рајцу','Карта и метео за стазе']],
   'podaci'=>[]],
  ['ikona'=>'🛡️','naziv'=>'РИС — оперативни центар','opis'=>'Осматрачница и модули за управљача и чуваре.',
   'strane'=>[
     ['ris/index.html','Осматрачница (РИС)','Оперативни центар уживо'],
     ['ris/cuvar/index.html','Чуварски центар','За чуваре'],
     ['ris/cuvar/dnevnik.html','Дневник чувара','Записи са терена'],
     ['ris/cuvar/prijava.html','Пријава по улогама','Улаз у РИС'],
     ['ris/gradjanska-nauka/index.html','Грађанска наука','Модул РИС-а'],
     ['ris/tamno-nebo/index.html','M6 — Тамно небо','Месечна VIIRS радијанса'],
     ['ris/naplata/index.html','M10 — Наплата објеката','Модул РИС-а'],
   ],
   'podaci'=>[]],
];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function starost(int $sec): string {
    if ($sec < 3600)  return 'пре ' . max(0, intdiv($sec, 60)) . ' мин';
    if ($sec < 86400) return 'пре ' . intdiv($sec, 3600) . ' ч';
    return 'пре ' . intdiv($sec, 86400) . ' д';
}
$sada = time();
$ukupno = 0; $svezih = 0; $zastarelih = 0; $agentiOk = 0; $agentiUkupno = 0;
$prikaz = [];
foreach ($GRUPE as $g) {
    $strane = [];
    foreach ($g['strane'] as [$put, $naslov, $opis]) {
        $f = $ROOT . '/' . $put;
        if (!is_file($f)) continue;
        $sec = $sada - (int)filemtime($f);
        $staro = $sec > 60 * 86400;
        $strane[] = ['url'=>$BASE.$put, 'naslov'=>$naslov, 'opis'=>$opis, 'kad'=>starost($sec), 'staro'=>$staro];
        $ukupno++; $staro ? $zastarelih++ : $svezih++;
    }
    $agent = null;
    if ($g['podaci']) {
        $min = null;
        foreach ($g['podaci'] as $p) {
            $f = $ROOT . '/' . $p;
            if (is_file($f)) { $s = $sada - (int)filemtime($f); $min = $min === null ? $s : min($min, $s); }
        }
        if ($min !== null) {
            $agentiUkupno++;
            $stanje = $min <= 3 * 86400 ? 'ok' : ($min <= 14 * 86400 ? 'paznja' : 'stoji');
            if ($stanje === 'ok') $agentiOk++;
            $agent = ['stanje'=>$stanje, 'kad'=>starost($min)];
        }
    }
    if ($strane) $prikaz[] = $g + ['lista'=>$strane, 'agent'=>$agent];
}
$datum = date('d.m.Y. H:i');
?><!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ПИО „РАЈАЦ“ · резултати агената</title>
<style>
:root{--ink:#1f2a22;--muted:#5d6a60;--line:#e4e1d6;--paper:#fbfaf6;--card:#fff;--green:#213325;--green2:#2f5b3d;--sand:#bdad87;--gold:#c9a24c;--ok:#2e7d4f;--warn:#b7791f;--bad:#b23b3b}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
a{color:inherit;text-decoration:none}
.top{background:var(--green);color:#fdf6e6}
.top-in{max-width:1180px;margin:auto;padding:14px 20px;display:flex;align-items:center;gap:12px}
.logo{display:flex;align-items:center;gap:9px;font-weight:700;letter-spacing:.02em;white-space:nowrap}
.logo::before{content:"";width:9px;height:9px;border-radius:50%;background:var(--sand);box-shadow:0 0 0 4px rgba(189,173,135,.25)}
.hero{background:linear-gradient(135deg,#213325,#2f5b3d);color:#fdf6e6;padding:34px 0 30px}
.wrap{max-width:1180px;margin:auto;padding:0 20px}
h1{font:600 clamp(1.7rem,3.4vw,2.5rem)/1.15 Georgia,"Times New Roman",serif;margin:0 0 6px}
.sub{color:rgba(253,246,230,.8);margin:0}
.stats{display:flex;flex-wrap:wrap;gap:10px;margin-top:20px}
.stat{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:10px 14px;min-width:130px}
.stat b{display:block;font-size:1.45rem;line-height:1.1}
.stat span{font-size:.8rem;color:rgba(253,246,230,.75)}
.legend{display:flex;flex-wrap:wrap;gap:14px;font-size:.82rem;color:var(--muted);margin:22px 0 6px}
.dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:6px;vertical-align:1px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px;margin:14px 0 40px}
.grupa{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px 18px 10px;box-shadow:0 6px 18px rgba(31,42,34,.05)}
.gh{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
.ik{font-size:1.6rem;line-height:1;flex:0 0 auto}
.gh h2{font-size:1.06rem;margin:0;line-height:1.3}
.gh p{margin:2px 0 0;font-size:.84rem;color:var(--muted)}
.agent{margin-left:auto;flex:0 0 auto;font-size:.72rem;font-weight:600;border-radius:999px;padding:4px 9px;white-space:nowrap}
.agent.ok{background:#e6f3ea;color:var(--ok)}.agent.paznja{background:#fbf0dc;color:var(--warn)}.agent.stoji{background:#f7e3e3;color:var(--bad)}
ul{list-style:none;margin:0;padding:0}
li a{display:flex;align-items:center;gap:10px;padding:10px 8px;border-top:1px solid #f0eee6;border-radius:8px}
li:first-child a{border-top:0}
li a:hover{background:#f5f3ec}
.t{flex:1;min-width:0}
.t b{display:block;font-weight:600;font-size:.95rem}
.t small{display:block;color:var(--muted);font-size:.8rem}
.kad{font-size:.75rem;color:var(--muted);white-space:nowrap}
.kad.staro{color:var(--warn);font-weight:600}
.arr{color:var(--sand);font-weight:700}
footer{color:var(--muted);font-size:.8rem;padding:0 0 30px}
@media(max-width:520px){.grid{grid-template-columns:1fr}.stat{min-width:calc(50% - 5px)}.agent{font-size:.68rem}}
</style>
</head>
<body>
<div class="top"><div class="top-in"><a class="logo" href="https://piorajac.rs/">ПИО „РАЈАЦ“</a></div></div>
<section class="hero"><div class="wrap">
<h1>Резултати агената</h1>
<p class="sub">Странице са корисним садржајем које аутоматски агенти одржавају на сајту · стање <?= h($datum) ?></p>
<div class="stats">
  <div class="stat"><b><?= $ukupno ?></b><span>страница</span></div>
  <div class="stat"><b><?= count($prikaz) ?></b><span>области</span></div>
  <div class="stat"><b><?= $agentiOk ?>/<?= $agentiUkupno ?></b><span>агената са свежим подацима</span></div>
  <div class="stat"><b><?= $zastarelih ?></b><span>страница старијих од 2 месеца</span></div>
</div>
</div></section>
<main class="wrap">
<div class="legend">
  <span><i class="dot" style="background:var(--ok)"></i>Агент ради — подаци млађи од 3 дана</span>
  <span><i class="dot" style="background:var(--warn)"></i>Проверити — 3 до 14 дана</span>
  <span><i class="dot" style="background:var(--bad)"></i>Агент стоји — преко 14 дана</span>
  <span><b style="color:var(--warn)">жути датум</b> — страница није мењана преко 2 месеца</span>
</div>
<div class="grid">
<?php foreach ($prikaz as $g): ?>
  <section class="grupa">
    <div class="gh">
      <div class="ik" aria-hidden="true"><?= $g['ikona'] ?></div>
      <div><h2><?= h($g['naziv']) ?></h2><p><?= h($g['opis']) ?></p></div>
      <?php if ($g['agent']): $a = $g['agent']; ?>
        <span class="agent <?= h($a['stanje']) ?>" title="Последње освежавање података агента"><?= $a['stanje']==='ok' ? 'ради' : ($a['stanje']==='paznja' ? 'проверити' : 'стоји') ?> · <?= h($a['kad']) ?></span>
      <?php endif; ?>
    </div>
    <ul>
    <?php foreach ($g['lista'] as $s): ?>
      <li><a href="<?= h($s['url']) ?>">
        <span class="t"><b><?= h($s['naslov']) ?></b><small><?= h($s['opis']) ?></small></span>
        <span class="kad<?= $s['staro'] ? ' staro' : '' ?>"><?= h($s['kad']) ?></span><span class="arr">›</span>
      </a></li>
    <?php endforeach; ?>
    </ul>
  </section>
<?php endforeach; ?>
</div>
<footer>Приказане су само странице намењене читању. Помоћне скрипте, подешавања, инсталатери и сирови подаци нису на списку; свежина података агента приказана је као ознака уз сваку област.</footer>
</main>
</body>
</html>
