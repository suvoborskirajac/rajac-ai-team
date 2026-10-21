<?php
// ПИО „РАЈАЦ“ — старе копије страница у корену сајта (2026-10-21).
// Премешта их ван сајта (у /home/piorajac/_stare-kopije-2026-10-21/) и у главни .htaccess додаје 301 преусмерења
// са старе адресе на праву страницу (нпр. /amanita-muscaria/ → /gljive/amanita-muscaria/).
// Премешта се само фасцикла која у корену има ЈЕДИНО index.html и чија права страница постоји. Све може да се врати.
declare(strict_types=1);
@set_time_limit(40);
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
const KLJUC = 'POSTAVI_KLJUC';
if (!hash_equals(KLJUC, (string)($_GET['k'] ?? ''))) { http_response_code(404); exit('Not found'); }
const MAPA = '{"accipiter-nisus":"ptice","admiral":"insekti","aegithalos-caudatus":"ptice","aeshna-cyanea":"insekti","alati-za-posetioce-gljive-rajca":"gljive","alati-za-posetioce-insekti-rajca":"insekti","alauda-arvensis":"ptice","aleochara-funebris":"insekti","aleochara-xenochara-funebris":"insekti","amanita-caesarea":"gljive","amanita-muscaria":"gljive","amanita-phalloides":"gljive","amanita-rubescens":"gljive","amanita-vaginata":"gljive","anthus-trivialis":"ptice","aphrodes-tricinctus":"insekti","apis-mellifera":"insekti","aprilski-monitoring-ptica-pio-rajac":"rajac-vesti","archive":"ptice","armillaria-mellea":"gljive","atheta-contristata":"insekti","bisnius-fimetarius":"insekti","boletus-edulis":"gljive","bombus-spp":"insekti","brgljez":"ptice","buteo-buteo":"ptice","calopteryx-splendens":"insekti","calopteryx-virgo":"insekti","cantharellus-cibarius":"gljive","carduelis-carduelis":"ptice","cavka":"ptice","certhia-brachydactyla":"ptice","cheilosia-schnabli":"insekti","chloris-chloris":"ptice","choleva-angustata":"insekti","choleva-s-str-angustata":"insekti","ciconia-nigra":"ptice","coccothraustes-coccothraustes":"ptice","coenagrion-puella":"insekti","coenagrion-pulchellum":"insekti","coprinus-comatus":"gljive","coracias-garrulus":"ptice","coturnix-coturnix":"ptice","craterellus-cornucopioides":"gljive","delichon-urbicum":"ptice","diplocolenus-abdominalis":"insekti","dnevni-leptiri":"insekti","dnevni-satelitski-monitoring-poljoprivrednih-parcela":"rajac-vesti","dryocopus-martius":"ptice","dryodurgades-dlabolai":"insekti","duvalius-duvalius-suvoborensis":"insekti","emberiza-calandra":"ptice","emberiza-citrinella":"ptice","emberiza-hortulana":"ptice","episyrphus-balteatus":"insekti","erithacus-rubecula":"ptice","erythria-aureola":"insekti","eupteroidia-stellulata":"insekti","eupteryx-notata":"insekti","evacanthus-acuminatus":"insekti","falco-subbuteo":"ptice","falco-vespertinus":"ptice","ficedula-hypoleuca":"ptice","ficedula-parva":"ptice","fistulina-hepatica":"gljive","fotografisanje-gljiva-rajca":"gljive","fotografisanje-insekata-rajca":"insekti","fotografisanje-ptica":"ptice","fringilla-coelebs":"ptice","ganoderma-lucidum":"gljive","garrulus-glandarius":"ptice","gavran":"ptice","gonepteryx-rhamni":"insekti","hajduckatrava-rajac":"rajac-vesti","hepialidae":"insekti","hirundo-rustica":"ptice","hypholoma-fasciculare":"gljive","idiocerus-confusus":"insekti","idiocerus-distinguendus":"insekti","idiocerus-populi":"insekti","idiocerus-stigmaticalis":"insekti","izvestaj-monitoring-biljaka-22-maj-2026":"rajac-vesti","izvestaj-monitoring-ptica-22-maj-2026":"rajac-vesti","izvori-fotografija":"insekti","jassargus-obtusivalis":"insekti","jynx-torquilla":"ptice","kucice-za-ptice":"ptice","kukavica":"ptice","kviz-prepoznavanje-ptica-rajac":"ptice","lactarius-deliciosus":"gljive","lanius-collurio":"ptice","libellula-depressa":"insekti","limonovac":"insekti","liogluta-londiscula":"insekti","lucanus-cervus":"insekti","lullula-arborea":"ptice","lycoperdon-perlatum":"gljive","macrolepiota-procera":"gljive","macropsis-infuscata":"insekti","mali-detlic":"ptice","marasmius-oreades":"gljive","medonosna-pcela":"insekti","megadelphax-sordidula":"insekti","merlin-ptice-rajac":"ptice","merops-apiaster":"ptice","monitoring-insekata-rajca":"insekti","monitoring-ptica-pio-rajac":"ptice","monitoring2025":"rajac-vesti","montaza-meteo-stanice":"rajac-vesti","morchella-esculenta":"gljive","motacilla-alba":"ptice","motacilla-cinerea":"ptice","muscicapa-striata":"ptice","noctuidae":"insekti","obicna-travarka":"ptice","oenanthe-oenanthe":"ptice","omalium-rivulare":"insekti","orao-krstas":"ptice","oriolus-oriolus":"ptice","otkrice-u-pio-rajac-potvrdjeno-prisustvo-cheilosia-schnabli":"rajac-vesti","phasianus-colchicus":"ptice","phoenicurus-ochruros":"ptice","phoenicurus-phoenicurus":"ptice","pholiota-squarrosa":"gljive","phylloscopus-collybita":"ptice","phylloscopus-trochilus":"ptice","pica-pica":"ptice","picus-canus":"ptice","picus-viridis":"ptice","pieris-rod":"insekti","pieris":"insekti","platycnemia-pennipes":"insekti","platycnemis-pennipes":"insekti","plava-senica":"ptice","pleurotus-ostreatus":"gljive","polyommatus-rod":"insekti","polyommatus":"insekti","pomoc-gljivama-rajca":"gljive","pomoc-insektima-rajca":"insekti","ponasanje-prema-gljivama-i-stanistima-rajca":"gljive","ponasanje-prema-gljivama-rajca":"gljive","ponasanje-prema-insektima-rajca":"insekti","ponasanje-prema-pticama":"ptice","posmatranje-dnevnih-leptira-na-rajcu":"rajac-vesti","posmatranje-ptica-na-rajcu":"rajac-vesti","potvrdjeno-prisustvo-rosalia-alpina":"rajac-vesti","proteinus-ovalis":"insekti","prunella-modularis":"ptice","psammotettix-cephalotes":"insekti","quedius-distichalius-cinctus":"insekti","quedius-mesomelinus-skoraszewskyi":"insekti","regulus-regulus":"ptice","rosalia-alpina":"insekti","rubroboletus-satanas":"gljive","russula-cyanoxantha":"gljive","russula-virescens":"gljive","satelitski-monitoring-svetlosnog-zagadjenja":"rajac-vesti","satelitski-monitoring-vegetacije-pio-rajac-2017-2025":"rajac-vesti","saxicola-rubicola":"ptice","serinus-serinus":"ptice","siva-senica":"ptice","siva-vrana":"ptice","streptopelia-decaocto":"ptice","streptopelia-turtur":"ptice","studenti-poljoprivrednog-fakulteta-prakticna-nastava-rajac":"rajac-vesti","sturnus-vulgaris":"ptice","suillus-luteus":"gljive","sumska-sova":"ptice","suri-orao":"ptice","suvoborski-duvalijus-duvalius-suvoborensis-nova-vrsta":"rajac-vesti","svetlosno-zagadjenje-pio-rajac-2014-2025":"rajac-vesti","sylvia-atricapilla":"ptice","sylvia-communis":"ptice","sympecma-fusca":"insekti","syrphus-ribesii":"insekti","trametes-versicolor":"gljive","trechus-cardioderus-balcanicus":"insekti","troglodytes-troglodytes":"ptice","turdus-merula":"ptice","turdus-philomelos":"ptice","turdus-viscivorus":"ptice","upupa-epops":"ptice","vanessa-atalanta":"insekti","velika-senica":"ptice","veliki-detlic":"ptice","veliki-vranac":"ptice","vestacka-inteligencija-izvestaj-2025":"rajac-vesti","vrabac":"ptice","zimski-monitoring-ptice-2026":"rajac-vesti"}';
$root = __DIR__; $kar = dirname($root).'/_stare-kopije-2026-10-21'; $stf = $kar.'/.stanje.json';
$self = basename(__FILE__).'?k='.KLJUC; $M = json_decode(MAPA, true);
const POC = '# BEGIN РАЈАЦ-СТАРЕ-КОПИЈЕ 2026-10-21'; const KRAJ = '# END РАЈАЦ-СТАРЕ-КОПИЈЕ 2026-10-21';
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function st(string $u, bool $loc=false) { $c=curl_init($u); curl_setopt_array($c,[CURLOPT_NOBODY=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>12,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>false]); $r=(string)curl_exec($c); $k=(int)curl_getinfo($c,CURLINFO_RESPONSE_CODE); curl_close($c); if(!$loc) return $k; preg_match('/^location:\s*(\S+)/mi',$r,$m); return [$k,$m[1]??'']; }
function strana(string $t, string $r=''): void { header('Content-Type: text/html; charset=utf-8'); echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$r.'<title>Старе копије страница</title><style>body{font-family:system-ui,sans-serif;background:#f6f3ed;color:#1d2939;padding:24px;margin:0}main{max-width:900px;margin:auto;background:#fff;border:1px solid #ddd5c8;border-radius:12px;padding:22px}button{background:#245b3a;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-weight:700;font-size:16px;cursor:pointer;margin:4px 0}button.d{background:#8a1c1c}.ok{background:#eef8f0;border:1px solid #b8d8bf;padding:10px;border-radius:8px;margin:8px 0}.err{background:#fff0ef;border:1px solid #e2b2ad;padding:10px;border-radius:8px;margin:8px 0}ul{max-height:320px;overflow:auto;font-size:13px}code{background:#f2efe8;padding:1px 4px;border-radius:3px}</style><main><h1>Старе копије страница у корену сајта</h1>'.$t.'</main>'; exit; }
function forma(string $self, string $a, string $l, string $c=''): string { return '<form method="post" action="'.h($self.'&a='.$a).'"><button class="'.$c.'">'.h($l).'</button></form>'; }
function stanje(string $f): array { return is_file($f) ? (array)json_decode((string)file_get_contents($f), true) : []; }
function sacuvaj(string $f, array $s): void { @mkdir(dirname($f),0700,true); file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE)); }
// која фасцикла сме да се премести: у корену постоји, има само index.html, права страница постоји
function provera(string $root, string $d, string $sek): string {
  $p = $root.'/'.$d; if (!is_dir($p) || is_link($p)) return 'нема';
  if (!is_file($root.'/'.$sek.'/'.$d.'/index.html')) return 'нема праве странице';
  $f = array_values(array_diff(scandir($p) ?: [], ['.','..']));
  if ($f !== ['index.html']) return 'има и друге фајлове ('.implode(', ', array_slice($f,0,4)).')';
  return 'ok';
}
$a = (string)($_GET['a'] ?? ''); $post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; $st = stanje($stf);

if ($post && $a === 'premesti') {
  @mkdir($kar, 0700, true); if (!is_file($kar.'/.htaccess')) @file_put_contents($kar.'/.htaccess', "Require all denied\n");
  $ht = $root.'/.htaccess'; $staro = (string)@file_get_contents($ht);
  if (strpos($staro, POC) !== false) strana('<div class="ok">Већ је урађено.</div>');
  file_put_contents($kar.'/htaccess-pre-'.date('His').'.txt', $staro);
  $pre = st('https://piorajac.rs/');
  $prem = []; $presk = []; $pravila = [];
  foreach ($M as $d => $sek) {
    $r = provera($root, $d, $sek);
    if ($r !== 'ok') { if ($r !== 'нема') $presk[] = $d.': '.$r; continue; }
    if (@rename($root.'/'.$d, $kar.'/'.$d)) { $prem[] = $d; $pravila[$sek][] = preg_quote($d, '#'); }
    else $presk[] = $d.': премештање није успело';
  }
  if ($pravila) {
    $red = []; foreach ($pravila as $sek => $imena) foreach (array_chunk($imena, 40) as $grupa) $red[] = 'RewriteRule ^('.implode('|', $grupa).')(/.*)?$ https://piorajac.rs/'.$sek.'/$1/ [R=301,L]';
    $blok = POC."\n# Старе копије у корену → права страница (фасцикле су у ".$kar.")\n<IfModule mod_rewrite.c>\nRewriteEngine On\n".implode("\n", $red)."\n</IfModule>\n".KRAJ."\n\n";
    file_put_contents($ht, $blok.$staro); usleep(400000);
    $p = st('https://piorajac.rs/'); $pt = st('https://piorajac.rs/ptice/');
    $prvi = $prem[0]; [$k, $loc] = st('https://piorajac.rs/'.$prvi.'/', true); $cilj = st('https://piorajac.rs/'.$M[$prvi].'/'.$prvi.'/');
    $preusm = ($k === 301 && strpos($loc, '/'.$M[$prvi].'/'.$prvi.'/') !== false);
    if (($pre === 200 && ($p !== 200 || $pt !== 200)) || !$preusm) {
      file_put_contents($ht, $staro); foreach ($prem as $d) @rename($kar.'/'.$d, $root.'/'.$d);
      strana('<div class="err">Провера није прошла (почетна '.$p.', птице '.$pt.', /'.h($prvi).'/ → '.$k.' '.h($loc).'). Све је враћено како је било, ништа није промењено. Пошаљи Claude-у снимак ове странице.</div>');
    }
    $st = ['prem'=>$prem, 'presk'=>$presk, 'mapa'=>array_intersect_key($M, array_flip($prem)), 'provera'=>"почетна $p, птице $pt; /$prvi/ → $k $loc; права страница $cilj"];
  } else $st = ['prem'=>[], 'presk'=>$presk, 'provera'=>'ништа није премештено'];
  file_put_contents($kar.'/SPISAK.txt', implode("\n", array_map(fn($d)=>"/$d/ → /".$M[$d]."/$d/", $prem))."\n");
  sacuvaj($stf, $st); header('Location: '.$self); exit;
}
if ($post && $a === 'vrati') {
  $ht = $root.'/.htaccess'; $s = (string)@file_get_contents($ht);
  $s = preg_replace('/'.preg_quote(POC,'/').'.*?'.preg_quote(KRAJ,'/').'\R*/su', '', $s); file_put_contents($ht, $s);
  $n = 0; foreach ((array)($st['prem'] ?? []) as $d) if (!file_exists($root.'/'.$d) && @rename($kar.'/'.$d, $root.'/'.$d)) $n++;
  sacuvaj($stf, ['vraceno'=>$n]); strana('<div class="ok">Враћено фасцикли: '.$n.'. Преусмерења су уклоњена из .htaccess.</div>'.forma($self,'kraj','Обриши ову скрипту','d'));
}
if ($post && $a === 'kraj') { @unlink(__FILE__); strana('<div class="ok">Скрипта је обрисала саму себе. Старе копије су у <code>'.h($kar).'</code>, списак у SPISAK.txt.</div>'); }

if (!empty($st['prem']) || isset($st['presk'])) {
  $t = '<div class="ok">Премештено: <b>'.count($st['prem']).'</b>. '.h($st['provera']).'</div>';
  if ($st['presk']) $t .= '<div class="err">Прескочено ('.count($st['presk']).'):'.'<ul>'.implode('', array_map(fn($x)=>'<li>'.h($x).'</li>', $st['presk'])).'</ul></div>';
  $pr = array_slice($st['prem'], 0, 3);
  $t .= '<p>Провери неколико старих адреса — треба да те пребаце на праву страницу:</p><ul>'.implode('', array_map(fn($d)=>'<li><a href="https://piorajac.rs/'.h($d).'/">https://piorajac.rs/'.h($d).'/</a></li>', $pr)).'</ul>';
  $t .= forma($self,'kraj','Све ради — обриши ову скрипту').forma($self,'vrati','Врати све како је било','d');
  strana($t);
}
$ok = []; $nije = [];
foreach ($M as $d => $sek) { $r = provera($root, $d, $sek); if ($r === 'ok') $ok[$d] = $sek; elseif ($r !== 'нема') $nije[] = $d.': '.$r; }
$t = '<p>У корену сајта има <b>'.count($ok).'</b> старих копија страница које постоје и на правом месту (птице, инсекти, гљиве, вести). До њих не води ниједан линк са сајта.</p>';
$t .= '<p>Дугме их премешта у <code>'.h($kar).'</code> (ништа се не брише) и у главни <code>.htaccess</code> додаје трајно преусмерење (301) са сваке старе адресе на праву страницу. Пре измене се прави копија .htaccess; ако почетна страна после измене не ради, све се само враћа.</p>';
$t .= '<details><summary>Списак (стара адреса → права страница)</summary><ul>'.implode('', array_map(fn($d,$s)=>'<li><code>/'.h($d).'/</code> → <code>/'.h($s).'/'.h($d).'/</code></li>', array_keys($ok), $ok)).'</ul></details>';
if ($nije) $t .= '<div class="err">Ове се НЕ дирају:<ul>'.implode('', array_map(fn($x)=>'<li>'.h($x).'</li>', $nije)).'</ul></div>';
$t .= $ok ? forma($self,'premesti','Премести старе копије и укључи преусмерења') : '<div class="ok">Нема старих копија.</div>';
strana($t);
