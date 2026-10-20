<?php
/**
 * PIO Rajac — Biodiversity report engine v14 INAT + PIO boundary
 * Put this file here:
 * /home/piorajac/public_html/monitoring-izvestaji/biodiversity_report_engine.php
 *
 * Purpose:
 * - Biodiversity reports use iNaturalist only by default.
 * - Every iNaturalist observation is checked against the GeoJSON boundary of PIO Rajac.
 * - eBird can be refreshed separately through its own endpoint, not through this report engine.
 * - Generates daily, weekly, monthly and annual biodiversity reports, including 2025 and 2026.
 */
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('memory_limit', '384M');
@set_time_limit(45);
date_default_timezone_set('Europe/Belgrade');

if (!defined('RBIO_VERSION')) define('RBIO_VERSION', '2026-07-06.20-inat-plus-field-bridge');
$GLOBALS['RBIO_STEP'] = 'start';
$GLOBALS['RBIO_FINISHED'] = false;
$GLOBALS['RBIO_DIAG'] = array();

register_shutdown_function(function(){
    if (!empty($GLOBALS['RBIO_FINISHED'])) return;
    $err = error_get_last();
    $payload = array(
        'ok' => false,
        'version' => RBIO_VERSION,
        'error' => 'Скрипта је прекинута пре завршетка. Ово је заштитни JSON уместо празне беле странице.',
        'last_step' => isset($GLOBALS['RBIO_STEP']) ? $GLOBALS['RBIO_STEP'] : 'unknown',
        'php_error' => $err ? array('type'=>$err['type'], 'message'=>$err['message'], 'file'=>$err['file'], 'line'=>$err['line']) : null,
        'diagnostics' => isset($GLOBALS['RBIO_DIAG']) ? $GLOBALS['RBIO_DIAG'] : array()
    );
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
});

function rbio_params(){
    $p = isset($_GET) && is_array($_GET) ? $_GET : array();
    if (PHP_SAPI === 'cli' && !empty($_SERVER['argv'])) {
        $args = array_slice($_SERVER['argv'], 1);
        foreach ($args as $a) {
            if (strpos($a, '=') !== false) { $x = explode('=', $a, 2); $p[$x[0]] = $x[1]; }
            else { $p[$a] = '1'; }
        }
    }
    return $p;
}
function rbio_bool($v){ return in_array(strtolower((string)$v), array('1','true','yes','da','on'), true); }
function rbio_out($arr, $code=200){
    $GLOBALS['RBIO_FINISHED'] = true;
    if (PHP_SAPI !== 'cli') { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); }
    echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if (PHP_SAPI === 'cli') echo PHP_EOL;
    exit($code >= 400 ? 1 : 0);
}
function rbio_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function rbio_entity_flags(){ return defined('ENT_HTML5') ? ENT_QUOTES|ENT_HTML5 : ENT_QUOTES; }
function rbio_write_file($path, $text){ @mkdir(dirname($path), 0775, true); @file_put_contents($path, $text); }
function rbio_write_json($path, $arr){ rbio_write_file($path, json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)); }
function rbio_read_json($path){ if (!is_file($path)) return null; $j = rbio_decode_jsonish((string)@file_get_contents($path)); return is_array($j) ? $j : null; }
function rbio_decode_jsonish($raw){
    $raw = (string)$raw;
    if ($raw === '') return null;
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    $raw = trim($raw);
    if ($raw === '') return null;
    /* Some local files are saved as: const name = {...}; or window.name = [...]; */
    $raw = preg_replace('/^\s*(?:var|let|const)\s+[A-Za-z0-9_.$]+\s*=\s*/', '', $raw);
    $raw = preg_replace('/^\s*window\.[A-Za-z0-9_.$]+\s*=\s*/', '', $raw);
    $raw = preg_replace('/;\s*$/', '', $raw);
    $j = json_decode($raw, true);
    if (is_array($j)) return $j;
    /* Last fallback: cut the first JSON-looking array/object out of a JS/HTML file. */
    $firstObj = strpos($raw, '{'); $firstArr = strpos($raw, '[');
    $starts = array();
    if ($firstObj !== false) $starts[] = $firstObj;
    if ($firstArr !== false) $starts[] = $firstArr;
    if (!$starts) return null;
    $start = min($starts);
    $endObj = strrpos($raw, '}'); $endArr = strrpos($raw, ']');
    $end = max($endObj === false ? -1 : $endObj, $endArr === false ? -1 : $endArr);
    if ($end <= $start) return null;
    $chunk = substr($raw, $start, $end - $start + 1);
    $j = json_decode($chunk, true);
    return is_array($j) ? $j : null;
}
function rbio_root(){
    if (!empty($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT'])) {
        $r = realpath($_SERVER['DOCUMENT_ROOT']);
        if ($r) return $r;
    }
    $r = realpath(dirname(__DIR__));
    return $r ? $r : dirname(__DIR__);
}
function rbio_safe_rel($path){ $root = rbio_root(); return strpos($path, $root) === 0 ? ltrim(substr($path, strlen($root)), '/\\') : $path; }
function rbio_clean($s){
    if ($s === null || is_array($s) || is_object($s)) return '';
    $s = (string)$s;
    if ($s === '') return '';
    $s = @html_entity_decode($s, rbio_entity_flags(), 'UTF-8');
    if ($s === null || $s === false) $s = '';
    if (function_exists('mb_check_encoding') && !@mb_check_encoding($s, 'UTF-8')) {
        $fix = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
        if ($fix !== false && $fix !== null) $s = $fix;
    }
    $tmp = @preg_replace('/\s+/u', ' ', $s);
    if ($tmp === null) $tmp = @preg_replace('/\s+/', ' ', $s);
    if ($tmp === null) $tmp = $s;
    return trim((string)$tmp, " \t\n\r\0\x0B.,;:|•–—-()[]{}");
}
function rbio_lower($s){ return function_exists('mb_strtolower') ? mb_strtolower((string)$s, 'UTF-8') : strtolower((string)$s); }
function rbio_strlen($s){ return function_exists('mb_strlen') ? mb_strlen((string)$s, 'UTF-8') : strlen((string)$s); }
function rbio_date($s){
    $s = rbio_clean($s);
    if ($s === '') return null;
    if (preg_match('/(20\d{2})[-_\/\.](\d{1,2})[-_\/\.](\d{1,2})/', $s, $m)) return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    if (preg_match('/(\d{1,2})[\.\/](\d{1,2})[\.\/](20\d{2})/', $s, $m)) return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    return null;
}
function rbio_latin_key($latin){ return rbio_lower(rbio_clean($latin)); }
function rbio_is_latin_binomial($s){
    $s = rbio_clean($s);
    if ($s === '') return false;
    return (bool)preg_match('/^[A-Za-z][a-zA-Z\-]+\s+[a-z][a-zA-Z\-]+(?:\s+[a-z][a-zA-Z\-]+)?$/', $s);
}

function rbio_species_name_map(){
    static $map = null;
    if ($map !== null) return $map;
    $map = array(
        'lophophanes cristatus'=>array('sr'=>'ћубаста сеница','en'=>'Crested Tit'),
        'poecile palustris'=>array('sr'=>'сива сеница','en'=>'Marsh Tit'),
        'cecropis rufula'=>array('sr'=>'даурска ласта','en'=>'European Red-rumped Swallow'),
        'saxicola rubetra'=>array('sr'=>'обична траварка','en'=>'Whinchat'),
        'streptopelia turtur'=>array('sr'=>'грлица','en'=>'European Turtle-Dove'),
        'upupa epops'=>array('sr'=>'пупавац','en'=>'Common Hoopoe'),
        'caprimulgus europaeus'=>array('sr'=>'легањ','en'=>'Eurasian Nightjar'),
        'emberiza hortulana'=>array('sr'=>'вртна стрнадица','en'=>'Ortolan Bunting'),
        'emberiza cia'=>array('sr'=>'планинска стрнадица','en'=>'Rock Bunting'),
        'strix aluco'=>array('sr'=>'шумска сова','en'=>'Tawny Owl'),
        'circaetus gallicus'=>array('sr'=>'змијар','en'=>'Short-toed Snake-Eagle'),
        'circus pygargus'=>array('sr'=>'еја ливадарка','en'=>"Montagu's Harrier"),
        'pernis apivorus'=>array('sr'=>'осичар','en'=>'European Honey-buzzard'),
        'vanessa cardui'=>array('sr'=>'чичков шаренац','en'=>'Painted Lady'),
        'arctia villica'=>array('sr'=>'тиграсти мољац','en'=>'Cream-spot Tiger'),
        'spialia orbifer'=>array('sr'=>'црвенокрили дебелоглавац','en'=>'Orbed red-underwing skipper'),
        'salvia verticillata verticillata'=>array('sr'=>'пршљенаста жалфија','en'=>'Whorled Clary'),
        'salvia pratensis'=>array('sr'=>'ливадна жалфија','en'=>'Meadow Sage'),
        'carduus nutans'=>array('sr'=>'поникла чичка / пољски чичак','en'=>'musk thistle'),
        'muscari comosum'=>array('sr'=>'кићени зумбулчић','en'=>'Tassel Hyacinth'),
        'muscari armeniacum'=>array('sr'=>'јерменски зумбулчић','en'=>'Garden Grape-hyacinth'),
        'scabiosa ochroleuca'=>array('sr'=>'жућкаста удовица','en'=>'Cream Scabious'),
        'parietaria officinalis'=>array('sr'=>'усправна стенакиња','en'=>'Erect Pellitory-of-the-wall'),
        'lathyrus sylvestris'=>array('sr'=>'шумска грахорица','en'=>'Narrow-leaved Everlasting-pea'),
        'leucanthemum vulgare'=>array('sr'=>'волујско око','en'=>'oxeye daisy'),
        'campanula patula'=>array('sr'=>'разграната звончица','en'=>'Spreading Bellflower'),
        'campanula persicifolia'=>array('sr'=>'брескволисна звончица','en'=>'Peach-leaved Bellflower'),
        'asplenium scolopendrium'=>array('sr'=>'јеленак папрат','en'=>"Hart's-tongue fern"),
        'asplenium trichomanes'=>array('sr'=>'црна слезница','en'=>'maidenhair spleenwort'),
        'fragaria vesca'=>array('sr'=>'шумска јагода','en'=>'woodland strawberry'),
        'geranium pyrenaicum'=>array('sr'=>'пиринејски здравац','en'=>"Hedgerow Crane's-bill"),
        'viola tricolor'=>array('sr'=>'дивља дан-и-ноћ','en'=>'Wild Pansy'),
        'viola hirta'=>array('sr'=>'длакава љубичица','en'=>'Hairy Violet'),
        'viburnum lantana'=>array('sr'=>'црна удиковина','en'=>'Wayfaring-tree'),
        'anemonoides nemorosa'=>array('sr'=>'бела шумска саса','en'=>'wood anemone'),
        'orchis pallens'=>array('sr'=>'бледи каћун','en'=>'Pale-flowered Orchid'),
        'anacamptis pyramidalis'=>array('sr'=>'пирамидални каћун','en'=>'pyramidal orchid'),
        'anacamptis papilionacea'=>array('sr'=>'лептиролики каћун','en'=>'Pink-butterfly Orchid'),
        'anacamptis morio'=>array('sr'=>'мали каћун','en'=>'green-winged orchid'),
        'anacamptis papilionacea grandiflora'=>array('sr'=>'крупноцветни лептиролики каћун','en'=>'Anacamptis papilionacea grandiflora'),
        'helix pomatia'=>array('sr'=>'виноградарски пуж','en'=>'Roman Snail'),
        'trifolium medium'=>array('sr'=>'средња детелина','en'=>'Zigzag Clover'),
        'pontechium maculatum'=>array('sr'=>'пегави волујак','en'=>'Pontechium maculatum'),
        'sedum hispanicum'=>array('sr'=>'шпанска жедњача','en'=>'Spanish Stonecrop'),
        'viscaria vulgaris'=>array('sr'=>'лепљива вискарија','en'=>'Sticky Catchfly'),
        'siler montanum'=>array('sr'=>'планински силер','en'=>'laserwort')
    );
    return $map;
}
function rbio_sr_name($latin, $name){
    $map = rbio_species_name_map();
    $lk = rbio_latin_key($latin);
    if ($lk !== '' && isset($map[$lk])) return $map[$lk]['sr'];
    $nl = rbio_lower(rbio_clean($name));
    foreach ($map as $lat=>$v) if ($nl !== '' && rbio_lower($v['en']) === $nl) return $v['sr'];
    return '';
}
function rbio_en_name($latin, $name){
    $map = rbio_species_name_map();
    $lk = rbio_latin_key($latin);
    if ($lk !== '' && isset($map[$lk])) return $map[$lk]['en'];
    $n = rbio_clean($name);
    if ($n !== '' && !rbio_is_latin_binomial($n)) return $n;
    return '';
}
function rbio_iconic_group($hay){
    $h = rbio_lower($hay);
    if (preg_match('/aves|птиц|bird|eagle|falcon|hawk|bunting|swallow|tit|owl|dove|nightjar|hoopoe|whinchat|emberiza|lophophanes|poecile|cecropis|saxicola|streptopelia|upupa|caprimulgus|strix|circaetus|circus|pernis/u', $h)) return 'Птице';
    if (preg_match('/plantae|биљ|plant|flower|orchid|sage|thistle|clover|fern|grass|tree|bellflower|daisy|hyacinth|anacamptis|orchis|salvia|muscari|scabiosa|carduus|parietaria|lathyrus|leucanthemum|campanula|asplenium|fragaria|geranium|viola|viburnum|sedum|trifolium/u', $h)) return 'Биљке';
    if (preg_match('/fungi|гљив|mushroom|agaricus|boletus|russula|amanita|morchella/u', $h)) return 'Гљиве';
    if (preg_match('/lepidoptera|лептир|butterfl|moth|skipper|papilio|pieris|vanessa|arctia|spialia|argynnis|polyommatus|lycaena|coenonympha/u', $h)) return 'Лептири';
    if (preg_match('/insecta|инсект|odonata|bee|apis|andrena|bombus|beetle|dragonfl/u', $h)) return 'Инсекти';
    if (preg_match('/mollusca|snail|slug|helix|пуж/u', $h)) return 'Пужеви и остали бескичмењаци';
    return '';
}
function rbio_group($source, $obj, $name, $latin){
    if ($source === 'ebird') return 'Птице';
    if (isset($obj['group']) && rbio_clean($obj['group']) !== '' && rbio_clean($obj['group']) !== 'iNaturalist') return rbio_clean($obj['group']);
    $iconic = isset($obj['iconic']) ? rbio_lower($obj['iconic']) : '';
    if ($iconic === 'aves') return 'Птице';
    if ($iconic === 'plantae') return 'Биљке';
    if ($iconic === 'fungi') return 'Гљиве';
    if ($iconic === 'mollusca') return 'Пужеви и остали бескичмењаци';
    if ($iconic === 'insecta') return 'Инсекти';
    if ($iconic === 'animalia') return 'Животиње';
    $hay = '';
    if (is_array($obj)) $hay .= json_encode($obj, JSON_UNESCAPED_UNICODE);
    $hay .= ' ' . $name . ' ' . $latin;
    $g = rbio_iconic_group($hay);
    if ($g !== '') return $g;
    return 'Остало / непотпуна идентификација';
}
function rbio_importance($latin, $name, $group){
    $h = rbio_lower($latin . ' ' . $name . ' ' . $group);
    $out = array();
    if (preg_match('/streptopelia turtur|emberiza hortulana|caprimulgus europaeus|circaetus gallicus|circus pygargus|pernis apivorus|strix aluco|upupa epops|cecropis rufula|saxicola rubetra/u', $h)) $out[] = 'значајна врста птица за праћење стања станишта и притисака у пределу';
    if (preg_match('/aquila|milvus|falco|circaetus|circus|pernis/u', $h)) $out[] = 'грабљивица — важан индикатор очуваности отворених и шумских станишта';
    if (preg_match('/anacamptis|orchis|dactylorhiza|gymnadenia|limodorum|cephalanthera/u', $h)) $out[] = 'орхидеја — налаз је важан за праћење флоре и очуваности ливадских/шумских станишта';
    if (preg_match('/gentiana|allium|hypericum/u', $h)) $out[] = 'биљна врста од значаја за флористички мониторинг Рајца';
    if (preg_match('/vanessa cardui|spialia orbifer|arctia villica|papilio|polyommatus|lycaena/u', $h)) $out[] = 'лептир/мољац — користан индикатор стања ливадских станишта';
    return array_values(array_unique($out));
}
function rbio_broad_reason($o){
    $name = rbio_lower(isset($o['name']) ? $o['name'] : '');
    $latin = rbio_lower(isset($o['latin']) ? $o['latin'] : '');
    $rank = rbio_lower(isset($o['rank']) ? $o['rank'] : '');
    if (in_array($rank, array('species','subspecies','variety','form'), true)) return '';
    if ($latin === '' && preg_match('/dicots|monocots|bellflowers|stonecrops|onions|grape hyacinths|viper|pellitories|oxeye daisies|spleenworts|marsh orchids|plumeless thistles|sweet peas|vetchlings|ladies and related admirals|evening primroses|sundrops|beeblossoms|smoketrees|viburnums|star-of-bethlehem|plants|birds|insects|apioideae|pseudoturritis/u', $name)) return 'шири или непотпун таксон';
    if ($latin !== '' && !rbio_is_latin_binomial($latin)) return 'таксон није на нивоу врсте';
    return '';
}
function rbio_enrich_item($o){
    $latin = isset($o['latin']) ? $o['latin'] : '';
    $name = isset($o['name']) ? $o['name'] : '';
    $sr = rbio_sr_name($latin, $name);
    $en = rbio_en_name($latin, $name);
    $o['name_sr'] = $sr !== '' ? $sr : '';
    $o['name_en'] = $en !== '' ? $en : '';
    $o['display_name'] = $sr !== '' ? $sr : ($name !== '' ? $name : $latin);
    if (empty($o['group']) || $o['group'] === 'iNaturalist') $o['group'] = rbio_group(isset($o['source']) ? $o['source'] : 'inat', $o, $name, $latin);
    $o['broad_reason'] = rbio_broad_reason($o);
    $imp = rbio_importance($latin, $name, $o['group']);
    $o['importance'] = $imp;
    $o['is_significant'] = count($imp) > 0;
    return $o;
}
function rbio_reduce_broad_taxa($items){
    $out = array();
    foreach ($items as $o) $out[] = rbio_enrich_item($o);
    usort($out, function($a, $b){
        $aa = empty($a['broad_reason']) ? 0 : 1;
        $bb = empty($b['broad_reason']) ? 0 : 1;
        if ($aa !== $bb) return $aa - $bb;
        $ad = isset($a['date']) ? $a['date'] : '';
        $bd = isset($b['date']) ? $b['date'] : '';
        $c = strcmp($bd, $ad);
        if ($c) return $c;
        return strcmp(isset($a['key']) ? $a['key'] : '', isset($b['key']) ? $b['key'] : '');
    });
    return $out;
}
function rbio_key($source, $latin, $name){
    $base = rbio_clean($latin) !== '' ? rbio_clean($latin) : rbio_clean($name);
    if ($base === '') return '';
    $base = rbio_lower($base);
    $base = @preg_replace('/[^\p{L}\p{N}]+/u', '-', $base);
    if ($base === null) $base = preg_replace('/[^A-Za-z0-9]+/', '-', rbio_lower($base));
    return $source . ':' . trim((string)$base, '-');
}
function rbio_add(&$obs, $o){
    $source = rbio_clean(isset($o['source']) ? $o['source'] : 'unknown');
    $name = rbio_clean(isset($o['name']) ? $o['name'] : '');
    $latin = rbio_clean(isset($o['latin']) ? $o['latin'] : '');
    if ($name === '' && $latin === '') return;
    if (rbio_strlen($name) > 140 || rbio_strlen($latin) > 140) return;
    if ($latin !== '' && !preg_match('/^[A-Za-z][a-zA-Z\-]+\s+[a-z][a-zA-Z\-]+(?:\s+[a-z][a-zA-Z\-]+)?$/', $latin)) {
        if (preg_match('/([A-Za-z][a-zA-Z\-]+\s+[a-z][a-zA-Z\-]+(?:\s+[a-z][a-zA-Z\-]+)?)/', $latin, $m)) $latin = $m[1]; else $latin = '';
    }
    $date = rbio_date(isset($o['date']) ? $o['date'] : '') ?: date('Y-m-d');
    $group = isset($o['group']) && rbio_clean($o['group']) !== '' ? rbio_clean($o['group']) : rbio_group($source, $o, $name, $latin);
    $key = rbio_key($source, $latin, $name);
    if ($key === '' || $key === $source . ':') return;

    $lat = null; $lng = null;
    if (isset($o['lat']) && is_numeric($o['lat'])) $lat = (float)$o['lat'];
    if (isset($o['lng']) && is_numeric($o['lng'])) $lng = (float)$o['lng'];
    if ($lat === null && isset($o['latitude']) && is_numeric($o['latitude'])) $lat = (float)$o['latitude'];
    if ($lng === null && isset($o['longitude']) && is_numeric($o['longitude'])) $lng = (float)$o['longitude'];

    $sourceUrl = isset($o['source_url']) ? (string)$o['source_url'] : '';
    $obsId = rbio_clean(isset($o['observation_id']) ? $o['observation_id'] : '');
    if ($obsId === '' && $sourceUrl !== '' && preg_match('#/observations/(\d+)#', $sourceUrl, $m)) $obsId = $m[1];

    /* For iNaturalist, keep the concrete observation URL in the key.
       This prevents an outside observation of the same taxon/date from overwriting
       an inside-boundary observation before the PIO boundary filter is applied. */
    $unique = $key . '|' . $date;
    if ($source === 'inat' && $obsId !== '') $unique .= '|obs:' . $obsId;

    $item = array(
        'key'=>$key,
        'source'=>$source,
        'group'=>$group,
        'name'=>$name !== '' ? $name : $latin,
        'latin'=>$latin,
        'date'=>$date,
        'count'=>isset($o['count']) && is_numeric($o['count']) ? (int)$o['count'] : null,
        'observer'=>rbio_clean(isset($o['observer']) ? $o['observer'] : ''),
        'photo_url'=>isset($o['photo_url']) ? (string)$o['photo_url'] : '',
        'source_url'=>$sourceUrl,
        'rank'=>rbio_clean(isset($o['rank']) ? $o['rank'] : ''),
        'iconic'=>rbio_clean(isset($o['iconic']) ? $o['iconic'] : ''),
        'date_inferred'=>empty($o['date'])
    );
    if ($lat !== null && $lng !== null) { $item['lat'] = $lat; $item['lng'] = $lng; }
    $obs[$unique] = rbio_enrich_item($item);
}
function rbio_http($url, $headers, $timeout){
    $GLOBALS['RBIO_STEP'] = 'http: ' . $url;
    $h = "User-Agent: PIO-Rajac-biodiversity-monitor/1.0\r\nAccept: application/json,text/html,*/*\r\n";
    foreach ($headers as $k=>$v) $h .= $k . ': ' . $v . "\r\n";
    $ctx = stream_context_create(array('http'=>array('timeout'=>$timeout, 'ignore_errors'=>true, 'header'=>$h)));
    $raw = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) $code = (int)$m[1];
    }
    return array('url'=>$url, 'ok'=>$raw!==false && $raw!=='', 'code'=>$code, 'bytes'=>$raw!==false ? strlen($raw) : 0, 'body'=>$raw!==false ? (string)$raw : '');
}


function rbio_collect_field_reports(&$diag, $enabled = true) {
    $obs = array();
    if (!$enabled) {
        $diag['field_reports'] = array('enabled'=>false, 'reason'=>'field reports disabled by parameter');
        return $obs;
    }
    $url = 'https://piorajac.rs/ris/teren/api/reports.php?action=biodiversity_export';
    $r = rbio_http($url, array(), 7);
    $entry = array('enabled'=>true, 'url'=>$url, 'http_code'=>$r['code'], 'bytes'=>$r['bytes'], 'ok'=>false, 'items'=>0);
    if (!$r['ok']) {
        $entry['error'] = 'field service not reachable or empty response';
        $diag['field_reports'] = $entry;
        return $obs;
    }
    $j = json_decode($r['body'], true);
    if (!is_array($j)) {
        $entry['error'] = 'invalid JSON from field service';
        $diag['field_reports'] = $entry;
        return $obs;
    }
    $items = array();
    if (isset($j['items']) && is_array($j['items'])) $items = $j['items'];
    elseif (array_keys($j) === range(0, count($j)-1)) $items = $j;
    foreach ($items as $x) {
        if (!is_array($x)) continue;
        $name = isset($x['display_name']) ? $x['display_name'] : (isset($x['name']) ? $x['name'] : '');
        $latin = isset($x['latin']) ? $x['latin'] : '';
        $date = isset($x['date']) ? $x['date'] : (isset($x['timestamp']) ? $x['timestamp'] : '');
        $group = isset($x['group']) ? $x['group'] : 'Теренски налази';
        $sourceUrl = isset($x['source_url']) ? $x['source_url'] : '';
        $photoUrl = isset($x['photo_url']) ? $x['photo_url'] : '';
        $item = array(
            'source'=>'teren',
            'name'=>$name,
            'latin'=>$latin,
            'date'=>$date,
            'group'=>$group,
            'count'=>isset($x['count']) ? $x['count'] : null,
            'observer'=>isset($x['observer']) ? $x['observer'] : '',
            'photo_url'=>$photoUrl,
            'source_url'=>$sourceUrl,
            'rank'=>isset($x['rank']) ? $x['rank'] : '',
            'iconic'=>isset($x['iconic']) ? $x['iconic'] : ''
        );
        if (isset($x['lat']) && is_numeric($x['lat'])) $item['lat'] = (float)$x['lat'];
        if (isset($x['lng']) && is_numeric($x['lng'])) $item['lng'] = (float)$x['lng'];
        if (isset($x['field_report_id'])) $item['observation_id'] = 'field-' . (string)$x['field_report_id'];
        rbio_add($obs, $item);
    }
    $entry['ok'] = true;
    $entry['items'] = count($obs);
    if (isset($j['generated_at'])) $entry['source_generated_at'] = $j['generated_at'];
    $diag['field_reports'] = $entry;
    return $obs;
}

function rbio_inat_photo_url_from_array($x){
    if (!is_array($x)) return '';
    $photo = '';
    $directKeys = array('square_url','small_url','thumb_url','medium_url','large_url','original_url','url');

    foreach ($directKeys as $k) {
        if (!empty($x[$k]) && is_string($x[$k]) && preg_match('#^https?://#i', $x[$k])) return $x[$k];
    }

    if (!empty($x['photos']) && is_array($x['photos'])) {
        foreach ($x['photos'] as $ph) {
            if (is_array($ph)) {
                foreach ($directKeys as $k) {
                    if (!empty($ph[$k]) && is_string($ph[$k]) && preg_match('#^https?://#i', $ph[$k])) return $ph[$k];
                }
            }
        }
    }

    if (!empty($x['observation_photos']) && is_array($x['observation_photos'])) {
        foreach ($x['observation_photos'] as $op) {
            if (!is_array($op)) continue;
            if (!empty($op['photo']) && is_array($op['photo'])) {
                foreach ($directKeys as $k) {
                    if (!empty($op['photo'][$k]) && is_string($op['photo'][$k]) && preg_match('#^https?://#i', $op['photo'][$k])) return $op['photo'][$k];
                }
            }
            foreach ($directKeys as $k) {
                if (!empty($op[$k]) && is_string($op[$k]) && preg_match('#^https?://#i', $op[$k])) return $op[$k];
            }
        }
    }

    return $photo;
}
function rbio_inat_observation_url($x, $fallback){
    if (is_array($x)) {
        if (!empty($x['uri']) && is_string($x['uri']) && preg_match('#^https?://#i', $x['uri'])) return $x['uri'];
        if (!empty($x['url']) && is_string($x['url']) && preg_match('#^https?://(www\.)?inaturalist\.org/observations/#i', $x['url'])) return $x['url'];
        if (!empty($x['id'])) return 'https://www.inaturalist.org/observations/' . rawurlencode((string)$x['id']);
    }
    return $fallback;
}
function rbio_observer_from_array($x){
    if (!is_array($x)) return '';
    if (!empty($x['user']) && is_array($x['user'])) {
        if (!empty($x['user']['name'])) return rbio_clean($x['user']['name']);
        if (!empty($x['user']['login'])) return rbio_clean($x['user']['login']);
    }
    if (!empty($x['observer'])) return rbio_clean($x['observer']);
    return '';
}

function rbio_num($v){
    if (is_int($v) || is_float($v)) return (float)$v;
    if (is_string($v)) {
        $v = str_replace(',', '.', trim($v));
        if (is_numeric($v)) return (float)$v;
    }
    return null;
}
function rbio_inat_coords_from_array($x){
    if (!is_array($x)) return null;
    if (!empty($x['geojson']) && is_array($x['geojson']) && !empty($x['geojson']['coordinates']) && is_array($x['geojson']['coordinates'])) {
        $c = $x['geojson']['coordinates'];
        if (count($c) >= 2) {
            $lng = rbio_num($c[0]); $lat = rbio_num($c[1]);
            if ($lat !== null && $lng !== null) return array('lat'=>$lat, 'lng'=>$lng, 'source'=>'geojson');
        }
    }
    if (!empty($x['location']) && is_string($x['location']) && preg_match('/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/', $x['location'], $m)) {
        $lat = rbio_num($m[1]); $lng = rbio_num($m[2]);
        if ($lat !== null && $lng !== null) return array('lat'=>$lat, 'lng'=>$lng, 'source'=>'location');
    }
    $lat = null; $lng = null;
    foreach (array('lat','latitude') as $k) if (isset($x[$k])) { $lat = rbio_num($x[$k]); if ($lat !== null) break; }
    foreach (array('lng','lon','longitude') as $k) if (isset($x[$k])) { $lng = rbio_num($x[$k]); if ($lng !== null) break; }
    if ($lat !== null && $lng !== null) return array('lat'=>$lat, 'lng'=>$lng, 'source'=>'lat_lng');
    return null;
}
function rbio_boundary_candidate_files(){
    $root = rbio_root();
    $here = __DIR__;
    $dirs = array(
        $root . '/inat',
        $root,
        $root . '/assets',
        $root . '/data',
        $root . '/monitoring-izvestaji/biodiverzitet/data',
        $here . '/../inat',
        $here . '/biodiverzitet/data',
        '/home/piorajac/public_html/inat',
        '/home/piorajac/public_html'
    );
    $names = array('rajac_granica.json','granica_mapa.json','rajac_granica.geojson','granica_mapa.geojson');
    $files = array();
    foreach ($dirs as $d) {
        foreach ($names as $n) $files[] = rtrim($d, '/\\') . '/' . $n;
    }
    return array_values(array_unique($files));
}
function rbio_boundary_candidate_urls(){
    return array(
        'https://piorajac.rs/inat/rajac_granica.json',
        'https://piorajac.rs/inat/granica_mapa.json',
        'https://piorajac.rs/rajac_granica.json',
        'https://piorajac.rs/granica_mapa.json'
    );
}
function rbio_boundary_pair_to_lnglat($pair){
    if (!is_array($pair)) return null;
    /* Accept both [lng,lat], [lat,lng] and objects {lat,lng}/{latitude,longitude}. */
    $lat = null; $lng = null;
    foreach (array('lat','latitude','y') as $k) if (isset($pair[$k])) { $lat = rbio_num($pair[$k]); if ($lat !== null) break; }
    foreach (array('lng','lon','longitude','x') as $k) if (isset($pair[$k])) { $lng = rbio_num($pair[$k]); if ($lng !== null) break; }
    if ($lat !== null && $lng !== null) return array('lng'=>$lng, 'lat'=>$lat);

    if (count($pair) < 2) return null;
    $a = rbio_num(isset($pair[0]) ? $pair[0] : null); $b = rbio_num(isset($pair[1]) ? $pair[1] : null);
    if ($a === null || $b === null) return null;
    /* GeoJSON uses [lng, lat]. Leaflet-style local files often use [lat, lng].
       Around Rajac, latitude is about 44 and longitude about 20, so this is safe. */
    if ($a > 42 && $a < 46 && $b > 18 && $b < 23) return array('lng'=>$b, 'lat'=>$a);
    if ($a > 18 && $a < 23 && $b > 42 && $b < 46) return array('lng'=>$a, 'lat'=>$b);
    return array('lng'=>$a, 'lat'=>$b);
}
function rbio_boundary_coords_to_rings($coords, &$rings){
    if (!is_array($coords) || empty($coords)) return;
    $looksLikeRing = true; $ring = array();
    foreach ($coords as $p) {
        $q = rbio_boundary_pair_to_lnglat($p);
        if ($q === null) { $looksLikeRing = false; break; }
        $ring[] = $q;
    }
    if ($looksLikeRing && count($ring) >= 3) { $rings[] = $ring; return; }
    foreach ($coords as $v) if (is_array($v)) rbio_boundary_coords_to_rings($v, $rings);
}
function rbio_boundary_extract_rings($j, &$rings){
    if (!is_array($j)) return;

    /* Direct list of points: [{lat,lng}, ...] or [[lat,lng], ...] */
    $before = count($rings);
    rbio_boundary_coords_to_rings($j, $rings);
    if (count($rings) > $before) return;

    if (!empty($j['type']) && !empty($j['coordinates']) && is_array($j['coordinates'])) {
        rbio_boundary_coords_to_rings($j['coordinates'], $rings);
    }
    if (!empty($j['geometry']) && is_array($j['geometry'])) rbio_boundary_extract_rings($j['geometry'], $rings);
    if (!empty($j['features']) && is_array($j['features'])) foreach ($j['features'] as $f) rbio_boundary_extract_rings($f, $rings);

    foreach (array('granica','boundary','coordinates','coords','latlngs','points','polygon','polygons','features') as $k) {
        if (!empty($j[$k]) && is_array($j[$k])) rbio_boundary_extract_rings($j[$k], $rings);
    }
}
function rbio_load_boundary_from_json($j){
    if (!is_array($j)) return array();
    $rings = array();
    rbio_boundary_extract_rings($j, $rings);
    return $rings;
}
function rbio_load_pio_boundary(&$diag){
    $checked = array();
    $failed = array();
    foreach (rbio_boundary_candidate_files() as $file) {
        $rel = rbio_safe_rel($file);
        if (strlen($rel) > 180) $rel = basename(dirname($file)) . '/' . basename($file);
        $checked[] = $rel;
        if (!is_file($file) || filesize($file) <= 0) continue;
        $raw = @file_get_contents($file);
        if ($raw === false || trim($raw) === '') { $failed[] = array('file'=>$rel,'reason'=>'empty_or_unreadable'); continue; }
        $j = rbio_decode_jsonish($raw);
        if (!is_array($j)) { $failed[] = array('file'=>$rel,'reason'=>'json_decode_failed'); continue; }
        $rings = rbio_load_boundary_from_json($j);
        if (count($rings) > 0) {
            $diag['pio_boundary'] = array(
                'enabled'=>true,
                'file'=>$rel,
                'rings'=>count($rings),
                'points_in_first_ring'=>count($rings[0])
            );
            return $rings;
        }
        $failed[] = array('file'=>$rel,'reason'=>'no_polygon_rings_found');
    }

    /* Fallback through public URLs. This helps if the script is moved or DOCUMENT_ROOT is different. */
    foreach (rbio_boundary_candidate_urls() as $url) {
        $checked[] = $url;
        $r = rbio_http($url, array(), 8);
        if (empty($r['ok'])) { $failed[] = array('file'=>$url,'reason'=>'http_'.$r['code']); continue; }
        $j = rbio_decode_jsonish($r['body']);
        if (!is_array($j)) { $failed[] = array('file'=>$url,'reason'=>'json_decode_failed'); continue; }
        $rings = rbio_load_boundary_from_json($j);
        if (count($rings) > 0) {
            $diag['pio_boundary'] = array(
                'enabled'=>true,
                'file'=>$url,
                'rings'=>count($rings),
                'points_in_first_ring'=>count($rings[0])
            );
            return $rings;
        }
        $failed[] = array('file'=>$url,'reason'=>'no_polygon_rings_found');
    }
    $diag['pio_boundary'] = array('enabled'=>false, 'reason'=>'Граница ПИО Рајац није учитана; engine неће објавити извештај без просторног филтера.', 'checked'=>$checked, 'failed'=>$failed);
    return array();
}
function rbio_point_in_ring($lng, $lat, $ring){
    $inside = false; $n = count($ring);
    if ($n < 3) return false;
    for ($i=0, $j=$n-1; $i<$n; $j=$i++) {
        $xi = $ring[$i]['lng']; $yi = $ring[$i]['lat'];
        $xj = $ring[$j]['lng']; $yj = $ring[$j]['lat'];
        $intersect = (($yi > $lat) != ($yj > $lat)) && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) == 0 ? 1.0e-12 : ($yj - $yi)) + $xi);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}
function rbio_point_in_boundary($lng, $lat, $rings){
    if (!is_array($rings) || empty($rings)) return true;
    foreach ($rings as $ring) if (rbio_point_in_ring($lng, $lat, $ring)) return true;
    return false;
}
function rbio_boundary_gate_inat_observation($x){
    $coords = rbio_inat_coords_from_array($x);
    if (empty($GLOBALS['RBIO_BOUNDARY_FILTER_ACTIVE'])) return array(true, $coords);
    if (!isset($GLOBALS['RBIO_BOUNDARY_STATS']) || !is_array($GLOBALS['RBIO_BOUNDARY_STATS'])) $GLOBALS['RBIO_BOUNDARY_STATS'] = array('checked'=>0,'kept'=>0,'outside'=>0,'without_coordinates'=>0);
    $GLOBALS['RBIO_BOUNDARY_STATS']['checked']++;
    if ($coords === null) { $GLOBALS['RBIO_BOUNDARY_STATS']['without_coordinates']++; return array(false, null); }
    $inside = rbio_point_in_boundary($coords['lng'], $coords['lat'], $GLOBALS['RBIO_BOUNDARY_RINGS']);
    if ($inside) $GLOBALS['RBIO_BOUNDARY_STATS']['kept']++; else $GLOBALS['RBIO_BOUNDARY_STATS']['outside']++;
    return array($inside, $coords);
}
function rbio_filter_items_to_boundary($items, &$diag){
    if (empty($GLOBALS['RBIO_BOUNDARY_FILTER_ACTIVE'])) return $items;
    $out = array(); $removedOutside = 0; $removedNoCoords = 0; $before = count($items);
    foreach ($items as $o) {
        if (isset($o['lat']) && isset($o['lng']) && is_numeric($o['lat']) && is_numeric($o['lng'])) {
            if (rbio_point_in_boundary((float)$o['lng'], (float)$o['lat'], $GLOBALS['RBIO_BOUNDARY_RINGS'])) $out[] = $o;
            else $removedOutside++;
        } elseif (isset($o['source']) && $o['source'] === 'inat') {
            $removedNoCoords++;
        } else {
            $out[] = $o;
        }
    }
    $diag['pio_boundary_filter'] = array(
        'enabled'=>true,
        'before'=>$before,
        'after'=>count($out),
        'removed_outside'=>$removedOutside,
        'removed_without_coordinates'=>$removedNoCoords,
        'api_checked'=>isset($GLOBALS['RBIO_BOUNDARY_STATS']['checked']) ? $GLOBALS['RBIO_BOUNDARY_STATS']['checked'] : 0,
        'api_kept_inside'=>isset($GLOBALS['RBIO_BOUNDARY_STATS']['kept']) ? $GLOBALS['RBIO_BOUNDARY_STATS']['kept'] : 0,
        'api_outside'=>isset($GLOBALS['RBIO_BOUNDARY_STATS']['outside']) ? $GLOBALS['RBIO_BOUNDARY_STATS']['outside'] : 0,
        'api_without_coordinates'=>isset($GLOBALS['RBIO_BOUNDARY_STATS']['without_coordinates']) ? $GLOBALS['RBIO_BOUNDARY_STATS']['without_coordinates'] : 0
    );
    return $out;
}
function rbio_walk_json($x, $source, $sourceUrl, &$obs, $depth=0){
    if ($depth > 10) return;
    if (!is_array($x)) return;
    $keys = array_keys($x);
    $isAssoc = $keys !== range(0, count($x)-1);

    if ($isAssoc && $source === 'inat' && isset($x['taxon']) && is_array($x['taxon'])) {
        /* iNaturalist observation: add exactly one card from the observation itself.
           This keeps the direct observation link and the observation photo.
           Before adding, the observation is checked against the GeoJSON boundary of PIO Rajac. */
        list($insidePioBoundary, $coords) = rbio_boundary_gate_inat_observation($x);
        if (!$insidePioBoundary) return;
        $tx = $x['taxon'];
        $latin = isset($tx['name']) ? $tx['name'] : (isset($tx['scientific_name']) ? $tx['scientific_name'] : '');
        $common = isset($tx['preferred_common_name']) ? $tx['preferred_common_name'] : (isset($tx['common_name']) ? $tx['common_name'] : (isset($tx['english_common_name']) ? $tx['english_common_name'] : $latin));
        $date = isset($x['observed_on']) ? $x['observed_on'] : (isset($x['time_observed_at']) ? $x['time_observed_at'] : (isset($x['created_at']) ? $x['created_at'] : ''));
        $photo = rbio_inat_photo_url_from_array($x);
        $link = rbio_inat_observation_url($x, $sourceUrl);
        $row = array(
            'source'=>'inat',
            'name'=>$common,
            'latin'=>$latin,
            'date'=>$date,
            'photo_url'=>$photo,
            'source_url'=>$link,
            'observer'=>rbio_observer_from_array($x),
            'rank'=>isset($tx['rank']) ? $tx['rank'] : '',
            'iconic'=>isset($tx['iconic_taxon_name']) ? $tx['iconic_taxon_name'] : '',
            'observation_id'=>isset($x['id']) ? $x['id'] : ''
        );
        if (is_array($coords)) { $row['lat'] = $coords['lat']; $row['lng'] = $coords['lng']; }
        rbio_add($obs, $row);
        return;
    }

    if ($isAssoc) {
        $com = isset($x['comName']) ? $x['comName'] : (isset($x['commonName']) ? $x['commonName'] : (isset($x['speciesName']) ? $x['speciesName'] : (isset($x['srName']) ? $x['srName'] : (isset($x['name_sr']) ? $x['name_sr'] : null))));
        $sci = isset($x['sciName']) ? $x['sciName'] : (isset($x['scientificName']) ? $x['scientificName'] : (isset($x['latin']) ? $x['latin'] : (isset($x['taxonName']) ? $x['taxonName'] : null)));
        $date = isset($x['obsDt']) ? $x['obsDt'] : (isset($x['obsDate']) ? $x['obsDate'] : (isset($x['date']) ? $x['date'] : (isset($x['observed_on']) ? $x['observed_on'] : (isset($x['time_observed_at']) ? $x['time_observed_at'] : (isset($x['created_at']) ? $x['created_at'] : null)))));
        $cnt = isset($x['howMany']) ? $x['howMany'] : (isset($x['count']) ? $x['count'] : (isset($x['individual_count']) ? $x['individual_count'] : null));
        if (($com || $sci) && ($date || isset($x['locName']))) {
            rbio_add($obs, array('source'=>$source,'name'=>$com ? $com : $sci,'latin'=>$sci ? $sci : '','date'=>$date ? $date : '','count'=>$cnt,'source_url'=>$sourceUrl));
        }
    }
    foreach ($x as $v) if (is_array($v)) rbio_walk_json($v, $source, $sourceUrl, $obs, $depth+1);
}
function rbio_extract_regex($body, $source, $sourceUrl, &$obs){
    $body = (string)$body;
    if (strlen($body) > 1000000) $body = substr($body, 0, 1000000);
    $txt = @preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $body);
    $txt = @preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $txt);
    $txt = html_entity_decode(strip_tags((string)$txt), rbio_entity_flags(), 'UTF-8');
    $txt = rbio_clean($txt);
    $patterns = array(
        '/([\p{Lu}\p{L}][\p{L}\s\-]{2,80})\s*\(([A-Z][a-zA-Z\-]+\s+[a-z][a-zA-Z\-]+(?:\s+[a-z][a-zA-Z\-]+)?)\)\s*(?:,|·|—|-)?\s*(\d{1,2}\.\d{1,2}\.20\d{2}|20\d{2}-\d{2}-\d{2})/u',
        '/([A-Z][a-zA-Z\-]+\s+[a-z][a-zA-Z\-]+(?:\s+[a-z][a-zA-Z\-]+)?)\s*(?:,|·|—|-)?\s*(\d{1,2}\.\d{1,2}\.20\d{2}|20\d{2}-\d{2}-\d{2})/u'
    );
    foreach ($patterns as $i=>$pat) {
        if (@preg_match_all($pat, $txt, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                if ($i === 0) rbio_add($obs, array('source'=>$source,'name'=>$x[1],'latin'=>$x[2],'date'=>$x[3],'source_url'=>$sourceUrl));
                else rbio_add($obs, array('source'=>$source,'name'=>$x[1],'latin'=>$x[1],'date'=>$x[2],'source_url'=>$sourceUrl));
            }
        }
    }
}

function rbio_collect_public_pages(&$diag, $skipEbird){
    $obs = array();
    $pages = array('ebird'=>'https://piorajac.rs/ebird/', 'inat'=>'https://piorajac.rs/inat/');
    if ($skipEbird) {
        unset($pages['ebird']);
        $diag['ebird_public_page'] = array('skipped'=>true, 'reason'=>'eBird је привремено искључен из извештаја биодиверзитета; освежава се посебно.');
    }
    foreach ($pages as $source=>$url) {
        $r = rbio_http($url, array(), 6);
        $diag[$source.'_public_page'] = array('url'=>$url, 'http_code'=>$r['code'], 'bytes'=>$r['bytes']);
        if ($r['ok']) rbio_extract_regex($r['body'], $source, $url, $obs);
        $diag[$source.'_public_page']['observations_total'] = count($obs);
    }
    return $obs;
}
function rbio_candidate_local_files($source, $deep){
    $root = rbio_root();
    $dirs = $source === 'ebird'
        ? array($root.'/ebird', $root.'/ptice', $root.'/birds', $root.'/biodiverzitet/ebird')
        : array($root.'/inat', $root.'/inaturalist', $root.'/biodiverzitet/inat', $root.'/biodiverzitet/inaturalist');
    $files = array();
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $patterns = array($dir.'/*.json', $dir.'/*.geojson', $dir.'/*.csv', $dir.'/*.txt', $dir.'/*.html', $dir.'/*.js', $dir.'/data/*.json', $dir.'/assets/*.json', $dir.'/js/*.json');
        foreach ($patterns as $pat) {
            foreach (glob($pat) ?: array() as $f) if (is_file($f) && filesize($f) <= 2500000) $files[$f] = @filemtime($f);
        }
        if ($deep) {
            $started = time(); $n = 0;
            try {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if (time() - $started > 8) break;
                    if (!$f->isFile()) continue;
                    $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
                    if (!in_array($ext, array('html','htm','js','json','geojson','csv','txt'), true)) continue;
                    if ($f->getSize() > 2500000) continue;
                    $files[$f->getPathname()] = $f->getMTime();
                    $n++; if ($n >= 120) break;
                }
            } catch (Exception $e) {}
        }
    }
    arsort($files);
    return array_slice(array_keys($files), 0, $deep ? 140 : 40);
}
function rbio_collect_local($source, $deep, &$diag){
    $obs = array();
    $files = rbio_candidate_local_files($source, $deep);
    $diag[$source.'_local'] = array('mode'=>$deep ? 'deep_limited' : 'fast_top_level', 'files_found'=>count($files), 'files_checked'=>0, 'examples'=>array());
    foreach ($files as $f) {
        $diag[$source.'_local']['files_checked']++;
        if (count($diag[$source.'_local']['examples']) < 8) $diag[$source.'_local']['examples'][] = rbio_safe_rel($f);
        $raw = @file_get_contents($f);
        if ($raw === false || $raw === '') continue;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext, array('json','geojson','js'), true)) {
            $clean = trim($raw);
            $clean = preg_replace('/^\s*(?:var|let|const)\s+[A-Za-z0-9_]+\s*=\s*/', '', $clean);
            $clean = preg_replace('/;\s*$/', '', $clean);
            $j = json_decode($clean, true);
            if (is_array($j)) { rbio_walk_json($j, $source, 'https://piorajac.rs/'.rbio_safe_rel($f), $obs); continue; }
        }
        rbio_extract_regex($raw, $source, 'https://piorajac.rs/'.rbio_safe_rel($f), $obs);
    }
    $diag[$source.'_local']['observations_total'] = count($obs);
    return $obs;
}
function rbio_inat_api(&$diag, $enabled){
    $obs = array();
    if (!$enabled) { $diag['inat_api'] = array('enabled'=>false); return $obs; }
    $base = 'https://api.inaturalist.org/v1/observations?lat=44.136333&lng=20.224918&radius=12&per_page=200&order=desc&order_by=observed_on&quality_grade=research,needs_id&geo=true';
    $publicBase = 'https://www.inaturalist.org/observations?place_id=any&lat=44.136333&lng=20.224918&radius=12';
    $queries = array(array('label'=>'latest','url'=>$base,'public'=>$publicBase));
    $years = array(date('Y')=>true, '2026'=>true, '2025'=>true);
    $ys = array_keys($years); rsort($ys, SORT_STRING);
    foreach ($ys as $y) {
        $queries[] = array(
            'label'=>'year_'.$y,
            'url'=>$base.'&d1='.$y.'-01-01&d2='.$y.'-12-31',
            'public'=>$publicBase.'&d1='.$y.'-01-01&d2='.$y.'-12-31'
        );
    }
    $diag['inat_api'] = array('enabled'=>true, 'mode'=>'latest_plus_yearly_2025_2026', 'requests'=>array());
    foreach ($queries as $q) {
        for ($page=1; $page<=3; $page++) {
            $url = $q['url'].'&page='.$page;
            $r = rbio_http($url, array(), 10);
            $req = array('label'=>$q['label'], 'page'=>$page, 'url'=>$url, 'http_code'=>$r['code'], 'bytes'=>$r['bytes']);
            if (!$r['ok']) { $diag['inat_api']['requests'][] = $req; break; }
            $j = json_decode($r['body'], true);
            $countBefore = count($obs);
            if (is_array($j)) rbio_walk_json($j, 'inat', $q['public'], $obs);
            $added = count($obs) - $countBefore;
            $total = is_array($j) && isset($j['total_results']) ? (int)$j['total_results'] : 0;
            $pageResults = is_array($j) && isset($j['results']) && is_array($j['results']) ? count($j['results']) : 0;
            $req['total_results'] = $total;
            $req['page_results'] = $pageResults;
            $req['added_before_dedup'] = $added;
            $diag['inat_api']['requests'][] = $req;
            if ($pageResults < 200 || ($page * 200) >= $total) break;
        }
    }
    $diag['inat_api']['observations_total'] = count($obs);
    return $obs;
}
function rbio_ebird_api(&$diag, $enabled){
    $diag['ebird_api'] = array('skipped'=>true, 'reason'=>'eBird није део овог извештаја; користи се само посебно освежавање eBird базе.');
    return array();
}
function rbio_iso_week($date){ $t = strtotime($date); return date('o', $t) . '-W' . date('W', $t); }
function rbio_period_bounds($kind, $id){
    if ($kind === 'daily') return array($id, $id);
    if ($kind === 'yearly') return array($id.'-01-01', $id.'-12-31');
    if ($kind === 'monthly') return array($id.'-01', date('Y-m-t', strtotime($id.'-01')));
    if (preg_match('/^(20\d{2})-W(\d{2})$/', $id, $m)) {
        $d = new DateTime();
        $d->setISODate((int)$m[1], (int)$m[2], 1);
        $s = $d->format('Y-m-d');
        $d->modify('+6 days');
        return array($s, $d->format('Y-m-d'));
    }
    return array(date('Y-m-d'), date('Y-m-d'));
}
function rbio_in_range($o, $s, $e){ $d = isset($o['date']) ? $o['date'] : ''; return $d >= $s && $d <= $e; }
function rbio_period_analysis($items){
    $groups = array(); $species = array(); $new = array(); $repeated = array(); $significant = array();
    foreach ($items as $o) {
        $g = !empty($o['group']) ? $o['group'] : 'Остало';
        if (!isset($groups[$g])) $groups[$g] = 0; $groups[$g]++;
        $sp = !empty($o['key']) ? $o['key'] : ((isset($o['latin']) ? $o['latin'] : '') . '|' . (isset($o['name']) ? $o['name'] : ''));
        if (!isset($species[$sp])) $species[$sp] = $o;
        if (!empty($o['is_new_for_local_register'])) $new[$sp] = $o; else $repeated[$sp] = $o;
        if (!empty($o['is_significant'])) $significant[$sp] = $o;
    }
    arsort($groups);
    return array('total_observations'=>count($items), 'unique_taxa'=>count($species), 'new_taxa'=>count($new), 'repeated_taxa'=>count($repeated), 'significant_taxa'=>count($significant), 'groups'=>$groups, 'new_items'=>array_values($new), 'repeated_items'=>array_values($repeated), 'significant_items'=>array_values($significant));
}
function rbio_card_html($o){
    $title = !empty($o['display_name']) ? $o['display_name'] : (!empty($o['name_sr']) ? $o['name_sr'] : (!empty($o['name']) ? $o['name'] : $o['latin']));
    $cls = !empty($o['is_new_for_local_register']) ? 'card is-new' : 'card';
    if (!empty($o['is_significant'])) $cls .= ' is-important';
    $url = !empty($o['source_url']) ? $o['source_url'] : '#';
    $html = '<a class="'.$cls.'" href="'.rbio_h($url).'">';
    if (!empty($o['photo_url'])) $html .= '<img class="thumb" src="'.rbio_h($o['photo_url']).'" alt="">';
    else $html .= '<div class="thumb empty">'.($o['group']==='Птице'?'🐦':($o['group']==='Биљке'?'🌿':'◆')).'</div>';
    $html .= '<div><b>'.rbio_h($title).'</b>';
    if (!empty($o['latin']) && $o['latin'] !== $title) $html .= '<em>'.rbio_h($o['latin']).'</em>';
    $html .= '<span>'.rbio_h($o['group']).' · '.rbio_h($o['date']).'</span>';
    if (!empty($o['broad_reason'])) $html .= '<small>Непотпуна идентификација: '.rbio_h($o['broad_reason']).'</small>';
    if (!empty($o['importance'])) $html .= '<small>'.rbio_h(implode(' · ', $o['importance'])).'</small>';
    $html .= '</div></a>';
    return $html;
}
function rbio_report_html($title, $lead, $payload){
    $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
    $groups = isset($payload['groups']) && is_array($payload['groups']) ? $payload['groups'] : array();
    $cards = '';
    foreach ($items as $o) $cards .= rbio_card_html($o);
    if ($cards === '') $cards = '<p class="muted">За овај период тренутно нема прочитаних налаза.</p>';
    $groupHtml = '';
    foreach ($groups as $g=>$n) $groupHtml .= '<li><span>'.rbio_h($g).'</span><b>'.rbio_h($n).'</b></li>';
    if ($groupHtml === '') $groupHtml = '<li><span>Нема података</span><b>0</b></li>';
    return '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.rbio_h($title).'</title><style>
body{margin:0;background:#fbfaf6;color:#1f2721;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif}.top{background:#fff;border-bottom:1px solid #e5dbc9;color:#1f2721;padding:46px 20px}.wrap{max-width:1180px;margin:auto}.k{color:#8a6f36;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:13px}.top h1{font-family:Georgia,serif;font-size:clamp(36px,6vw,76px);line-height:.95;margin:18px 0}.top p{max-width:820px;font-size:18px;line-height:1.65}.panel{background:#fff;border:1px solid #e3d8c4;border-radius:18px;margin:28px auto;padding:26px;box-shadow:0 18px 40px rgba(40,30,10,.08)}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}.stat{background:#fffdf8;border:1px solid #e3d8c4;border-radius:14px;padding:18px}.stat b{display:block;font-size:34px;color:#8a6f36}.stat span{font-size:13px;color:#5e6d63;font-weight:700}.groups{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.groups li{background:#fbf8ef;border:1px solid #eadfc9;border-radius:12px;padding:12px;display:flex;justify-content:space-between;gap:10px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}.card{display:grid;grid-template-columns:92px 1fr;gap:12px;text-decoration:none;color:inherit;background:#fffdf8;border:1px solid #eadfc9;border-radius:14px;overflow:hidden;min-height:104px}.card:hover{border-color:#c9b375}.thumb{width:92px;height:104px;object-fit:cover;background:#edf1e8}.thumb.empty{display:grid;place-items:center;font-size:34px}.card b{display:block;font-size:18px;margin:12px 12px 4px 0}.card em{display:block;color:#5d6d63;font-size:14px;margin-right:12px}.card span,.card small{display:block;color:#657369;font-size:12px;margin:6px 12px 0 0}.is-new{box-shadow:inset 0 0 0 2px rgba(183,138,36,.15)}.is-important{background:#fffaf0}.muted{color:#637164}.links a{color:#765f2e;text-decoration:none;font-weight:700}@media(max-width:720px){.stats{grid-template-columns:1fr 1fr}.top{padding-top:34px}.panel{border-radius:0;margin:0;border-left:0;border-right:0}.card{grid-template-columns:78px 1fr}.thumb{width:78px;height:100%}}
</style></head><body><header class="top"><div class="wrap"><div class="k">Биодиверзитет</div><h1>'.rbio_h($title).'</h1><p>'.rbio_h($lead).'</p></div></header><main class="wrap"><section class="panel"><div class="stats"><div class="stat"><b>'.rbio_h($payload['total_items']).'</b><span>прочитаних налаза</span></div><div class="stat"><b>'.rbio_h($payload['unique_taxa']).'</b><span>јединствених таксона</span></div><div class="stat"><b>'.rbio_h($payload['new_items']).'</b><span>нових у локалном регистру</span></div><div class="stat"><b>'.rbio_h($payload['significant_taxa']).'</b><span>значајних налаза</span></div></div></section><section class="panel"><h2>Групе налаза</h2><ul class="groups">'.$groupHtml.'</ul></section><section class="panel"><h2>Налази</h2><div class="grid">'.$cards.'</div></section><section class="panel links"><p><a href="/monitoring-izvestaji/biodiverzitet/">Назад на преглед извештаја</a></p><p class="muted">Последње генерисање: '.rbio_h($payload['generated_at']).'</p></section></main></body></html>';
}
function rbio_kind_slug($kind){
    if ($kind === 'daily') return 'dnevni';
    if ($kind === 'weekly') return 'sedmicni';
    if ($kind === 'monthly') return 'mesecni';
    if ($kind === 'yearly') return 'godisnji';
    return $kind;
}
function rbio_make_report($kind, $id, $items, $outRoot){
    $labels = array('daily'=>'Дневни извештај','weekly'=>'Седмични извештај','monthly'=>'Месечни извештај','yearly'=>'Годишњи извештај');
    list($s, $e) = rbio_period_bounds($kind, $id);
    $periodItems = array();
    foreach ($items as $o) if (rbio_in_range($o, $s, $e)) $periodItems[] = $o;
    $a = rbio_period_analysis($periodItems);
    $slug = rbio_kind_slug($kind);
    $dir = $outRoot . '/' . $slug . '/' . $id;
    $url = 'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/' . $slug . '/' . rawurlencode($id) . '/';
    $payload = array(
        'ok'=>true,
        'version'=>RBIO_VERSION,
        'kind'=>$kind,
        'period'=>$id,
        'start'=>$s,
        'end'=>$e,
        'generated_at'=>date('c'),
        'summary'=>'Аутоматски извештај о најновијим доступним налазима биодиверзитета у границама ПИО „Рајац”.',
        'report_url'=>$url,
        'total_items'=>$a['total_observations'],
        'total_observations'=>$a['total_observations'],
        'unique_taxa'=>$a['unique_taxa'],
        'new_items'=>$a['new_taxa'],
        'new_taxa'=>$a['new_taxa'],
        'repeated_taxa'=>$a['repeated_taxa'],
        'significant_taxa'=>$a['significant_taxa'],
        'groups'=>$a['groups'],
        'items'=>array_values($periodItems),
        'new'=>$a['new_items'],
        'significant'=>$a['significant_items']
    );
    rbio_write_json($dir . '/report.json', $payload);
    rbio_write_file($dir . '/index.html', rbio_report_html('Биодиверзитет — ' . $labels[$kind] . ' — ' . $id, 'Аутоматски јавни преглед најновијих доступних налаза биодиверзитета у границама ПИО „Рајац”.', $payload));
    rbio_write_json($outRoot . '/latest-' . $kind . '.json', $payload);
    return $payload;
}

function rbio_years_to_generate($items){
    // Јавна годишња архива биодиверзитета је намерно ограничена само на 2026. и 2025.
    // Старији iNaturalist налази могу постојати у дијагностици, али се НЕ приказују као јавне године.
    return array('2026', '2025');
}
function rbio_yearly_index_html($yearlyReports){
    // Додатна јавна заштита: на страници /godisnji/ приказују се само 2026. и 2025.
    $allowedYears = array('2026'=>true, '2025'=>true);
    $cards = '';
    foreach ($yearlyReports as $year=>$r) {
        if (!isset($allowedYears[(string)$year])) continue;
        $n = isset($r['total_items']) ? $r['total_items'] : 0;
        $u = isset($r['report_url']) ? $r['report_url'] : '#';
        $taxa = isset($r['unique_taxa']) ? $r['unique_taxa'] : 0;
        $cards .= '<a class="report-card" href="'.rbio_h($u).'"><h3>'.rbio_h($year).'.</h3><p>Годишњи извештај биодиверзитета</p><b>'.rbio_h($n).'</b><span>прочитаних налаза · таксона: '.rbio_h($taxa).'</span></a>';
    }
    if ($cards === '') $cards = '<p class="muted">Годишњи извештаји још нису генерисани.</p>';
    return '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Годишњи извештаји биодиверзитета</title><style>
body{margin:0;background:#fbfaf6;color:#1f2721;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif}.hero{background:#fff;border-bottom:1px solid #e5dbc9;color:#1f2721;padding:54px 20px}.wrap{max-width:1180px;margin:auto}.k{color:#8a6f36;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:13px}.hero h1{font-family:Georgia,serif;font-size:clamp(42px,7vw,82px);line-height:.95;margin:18px 0}.hero p{max-width:830px;font-size:18px;line-height:1.65}.panel{background:#fff;border:1px solid #e3d8c4;border-radius:18px;margin:28px auto;padding:28px;box-shadow:0 18px 40px rgba(40,30,10,.08)}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.report-card{text-decoration:none;color:inherit;background:#fffdf8;border:1px solid #e3d8c4;border-radius:16px;padding:22px}.report-card:hover{border-color:#c9b375}.report-card h3{font-family:Georgia,serif;font-size:36px;margin:0 0 8px}.report-card p{margin:0;color:#5f6d63;font-weight:700}.report-card b{display:block;color:#8a6f36;font-size:40px;margin-top:22px}.report-card span{font-size:13px;color:#5f6d63;font-weight:700}.links a{color:#765f2e;text-decoration:none;font-weight:700}.muted{color:#637164}@media(max-width:820px){.grid{grid-template-columns:1fr}.panel{border-radius:0;border-left:0;border-right:0}.hero{padding-top:40px}}
</style></head><body><header class="hero"><div class="wrap"><div class="k">Биодиверзитет</div><h1>Годишњи извештаји биодиверзитета</h1><p>Годишњи прегледи налаза биодиверзитета у границама ПИО „Рајац”, у истом јавном формату као дневни, седмични и месечни извештаји.</p></div></header><main class="wrap"><section class="panel"><h2>Годишња архива</h2><div class="grid">'.$cards.'</div></section><section class="panel links"><p><a href="/monitoring-izvestaji/biodiverzitet/">Назад на извештаје биодиверзитета</a></p><p class="muted">Последње генерисање: '.rbio_h(date('c')).'</p></section></main></body></html>';
}

function rbio_index_html($latest){
    $d = isset($latest['daily']) ? $latest['daily'] : array();
    $w = isset($latest['weekly']) ? $latest['weekly'] : array();
    $m = isset($latest['monthly']) ? $latest['monthly'] : array();

    $dailyUrl = isset($d['report_url']) ? $d['report_url'] : '#';
    $weeklyUrl = isset($w['report_url']) ? $w['report_url'] : '#';
    $monthlyUrl = isset($m['report_url']) ? $m['report_url'] : '#';

    $dailyPeriod = isset($d['period']) ? $d['period'] : '';
    $weeklyPeriod = isset($w['period']) ? $w['period'] : '';
    $monthlyPeriod = isset($m['period']) ? $m['period'] : '';

    $dailyN = isset($d['total_items']) ? (int)$d['total_items'] : 0;
    $weeklyN = isset($w['total_items']) ? (int)$w['total_items'] : 0;
    $monthlyN = isset($m['total_items']) ? (int)$m['total_items'] : 0;

    $dailyTaxa = isset($d['unique_taxa']) ? (int)$d['unique_taxa'] : 0;
    $weeklyTaxa = isset($w['unique_taxa']) ? (int)$w['unique_taxa'] : 0;
    $monthlyTaxa = isset($m['unique_taxa']) ? (int)$m['unique_taxa'] : 0;

    $yr = isset($latest['yearly']) && is_array($latest['yearly']) ? $latest['yearly'] : array();

    $y2026 = isset($yr['2026']) ? $yr['2026'] : array();
    $y2025 = isset($yr['2025']) ? $yr['2025'] : array();

    $y2026N = isset($y2026['total_items']) ? (int)$y2026['total_items'] : 0;
    $y2026Taxa = isset($y2026['unique_taxa']) ? (int)$y2026['unique_taxa'] : 0;
    $y2026Url = isset($y2026['report_url']) ? $y2026['report_url'] : 'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/godisnji/2026/';

    $y2025N = isset($y2025['total_items']) ? (int)$y2025['total_items'] : 0;
    $y2025Taxa = isset($y2025['unique_taxa']) ? (int)$y2025['unique_taxa'] : 0;
    $y2025Url = isset($y2025['report_url']) ? $y2025['report_url'] : 'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/godisnji/2025/';

    return '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Извештаји биодиверзитета — ПИО Рајац</title><style>
*,*::before,*::after{box-sizing:border-box}body{margin:0;background:#fbfaf6;color:#1f2721;font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}a{text-decoration:none;color:inherit}.hero{background:#fff;border-bottom:1px solid #e5dbc9;color:#1f2721;padding:54px 20px}.wrap{max-width:1180px;margin:auto}.k{color:#8a6f36;font-weight:800;letter-spacing:.12em;text-transform:uppercase;font-size:13px}.hero h1{font-family:Georgia,"Times New Roman",serif;font-size:clamp(42px,7vw,82px);line-height:.95;margin:18px 0 18px;font-weight:700}.hero p{max-width:830px;font-size:18px;line-height:1.65;margin:0;color:#26352b}.pill{display:inline-flex;margin:18px 8px 0 0;background:#fff;color:#43554b;border:1px solid #e3d8c4;border-radius:999px;padding:10px 14px;font-size:13px}.panel{background:#fff;border:1px solid #e3d8c4;border-radius:18px;margin:28px auto;padding:28px;box-shadow:0 18px 40px rgba(40,30,10,.08)}.panel h2{margin:0 0 20px;font-size:25px}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.grid.yearly{grid-template-columns:repeat(2,minmax(0,1fr))}.report-card{text-decoration:none;color:inherit;background:#fffdf8;border:1px solid #e3d8c4;border-radius:16px;padding:22px;transition:.18s ease}.report-card:hover{border-color:#c9b375;transform:translateY(-2px)}.report-card h3{font-family:Georgia,"Times New Roman",serif;font-size:28px;margin:0 0 8px;line-height:1.12}.report-card p{margin:0;color:#5f6d63;font-weight:700}.report-card b{display:block;color:#8a6f36;font-size:40px;margin-top:22px;line-height:1}.report-card span{display:block;margin-top:8px;font-size:13px;color:#5f6d63;font-weight:700}.links{display:flex;flex-wrap:wrap;gap:12px}.links a{display:inline-flex;border:1px solid #e3d8c4;border-radius:999px;padding:10px 14px;color:#765f2e;font-weight:800;background:#fffdf8}.muted{color:#637164}@media(max-width:820px){.grid,.grid.yearly{grid-template-columns:1fr}.panel{border-radius:0;border-left:0;border-right:0}.hero{padding-top:40px}}
</style></head><body><header class="hero"><div class="wrap"><div class="k">Биодиверзитет</div><h1>Извештаји биодиверзитета</h1><p>Најновији доступни налази биодиверзитета у границама ПИО „Рајац”, приказани кроз дневни, седмични, месечни и годишњи преглед. Укључени су iNaturalist налази и теренски уноси управљача/грађанске науке.</p><span class="pill">налази природе</span><span class="pill">теренски уноси</span><span class="pill">дневно</span><span class="pill">седмично</span><span class="pill">месечно</span><span class="pill">годишње</span></div></header><main class="wrap"><section class="panel"><h2>Најновији извештаји</h2><div class="grid"><a class="report-card" href="'.rbio_h($dailyUrl).'"><h3>Дневни извештај</h3><p>'.rbio_h($dailyPeriod).'</p><b>'.rbio_h($dailyN).'</b><span>налаза · таксона / врста: '.rbio_h($dailyTaxa).'</span></a><a class="report-card" href="'.rbio_h($weeklyUrl).'"><h3>Седмични извештај</h3><p>'.rbio_h($weeklyPeriod).'</p><b>'.rbio_h($weeklyN).'</b><span>налаза · таксона / врста: '.rbio_h($weeklyTaxa).'</span></a><a class="report-card" href="'.rbio_h($monthlyUrl).'"><h3>Месечни извештај</h3><p>'.rbio_h($monthlyPeriod).'</p><b>'.rbio_h($monthlyN).'</b><span>налаза · таксона / врста: '.rbio_h($monthlyTaxa).'</span></a></div></section><section class="panel"><h2>Годишњи извештаји</h2><div class="grid yearly"><a class="report-card" href="'.rbio_h($y2026Url).'"><h3>Годишњи извештај</h3><p>2026.</p><b>'.rbio_h($y2026N).'</b><span>прочитаних налаза · таксона / врста: '.rbio_h($y2026Taxa).'</span></a><a class="report-card" href="'.rbio_h($y2025Url).'"><h3>Годишњи извештај</h3><p>2025.</p><b>'.rbio_h($y2025N).'</b><span>прочитаних налаза · таксона / врста: '.rbio_h($y2025Taxa).'</span></a></div></section><section class="panel"><h2>Архива</h2><div class="links"><a href="https://piorajac.rs/monitoring-izvestaji/biodiverzitet/godisnji/">Годишња архива 2025/2026</a><a href="https://piorajac.rs/monitoring-izvestaji/">Назад на мониторинг извештаје</a></div></section><section class="panel"><h2>Напомена</h2><p class="muted">Јавни приказ годишњих извештаја биодиверзитета ограничен је на 2026. и 2025. годину. Старије године се не приказују у јавној архиви.</p></section></main></body></html>';
}

/* ------------------------- MAIN ------------------------- */
$p = rbio_params();
$public = dirname(__DIR__);
$config = $public . '/kopindeksi/copernicus_config.php';
if (is_file($config)) @require_once $config;
if (PHP_SAPI !== 'cli' && defined('RUN_KEY') && RUN_KEY !== '' && (!isset($p['key']) || (string)$p['key'] !== (string)RUN_KEY)) rbio_out(array('ok'=>false, 'error'=>'Недозвољен приступ. Отвори са key=RUN_KEY из copernicus_config.php.', 'version'=>RBIO_VERSION), 403);

$outRoot = __DIR__ . '/biodiverzitet';
$dataDir = $outRoot . '/data';
@mkdir($dataDir, 0775, true);
$dry = rbio_bool(isset($p['dry']) ? $p['dry'] : '0');
$deep = rbio_bool(isset($p['deep']) ? $p['deep'] : '0');
$apiEnabled = !isset($p['api']) || rbio_bool($p['api']);
$fieldReportsEnabled = !isset($p['field_reports']) || rbio_bool($p['field_reports']);
$skipEbirdInBiodiversityReports = true;
if (isset($p['include_ebird']) && rbio_bool($p['include_ebird'])) $skipEbirdInBiodiversityReports = false;

if (isset($p['health'])) rbio_out(array('ok'=>true, 'version'=>RBIO_VERSION, 'message'=>'health ok', 'source_mode'=>$skipEbirdInBiodiversityReports?'inat_only':'inat_plus_ebird', 'time'=>date('c')));

$diag = array('started_at'=>date('c'), 'mode'=>$deep?'deep_limited':'fast', 'api_enabled'=>$apiEnabled, 'field_reports_enabled'=>$fieldReportsEnabled, 'source_mode'=>$skipEbirdInBiodiversityReports?'inat_plus_field':'inat_plus_ebird_plus_field');
$GLOBALS['RBIO_DIAG'] =& $diag;
$GLOBALS['RBIO_BOUNDARY_RINGS'] = rbio_load_pio_boundary($diag);
$GLOBALS['RBIO_BOUNDARY_FILTER_ACTIVE'] = !empty($GLOBALS['RBIO_BOUNDARY_RINGS']);
$GLOBALS['RBIO_BOUNDARY_STATS'] = array('checked'=>0,'kept'=>0,'outside'=>0,'without_coordinates'=>0);
if (!$GLOBALS['RBIO_BOUNDARY_FILTER_ACTIVE'] && !(isset($p['allow_no_boundary']) && rbio_bool($p['allow_no_boundary']))) {
    rbio_out(array('ok'=>false, 'dry'=>$dry, 'version'=>RBIO_VERSION, 'source_mode'=>$skipEbirdInBiodiversityReports?'inat_only':'inat_plus_ebird', 'error'=>'Граница ПИО Рајац није учитана, па извештај није објављен. Постави/провери inat/rajac_granica.json или inat/granica_mapa.json.', 'diagnostics'=>$diag), 500);
}
$all = array();

if (!$skipEbirdInBiodiversityReports) {
    $GLOBALS['RBIO_STEP'] = 'collect local ebird';
    foreach (rbio_collect_local('ebird', $deep, $diag) as $o) rbio_add($all, $o);
} else {
    $diag['ebird_local'] = array('skipped'=>true, 'reason'=>'eBird је привремено искључен из извештаја биодиверзитета.');
}

$GLOBALS['RBIO_STEP'] = 'collect local inat';
foreach (rbio_collect_local('inat', $deep, $diag) as $o) rbio_add($all, $o);

$GLOBALS['RBIO_STEP'] = 'collect public pages';
foreach (rbio_collect_public_pages($diag, $skipEbirdInBiodiversityReports) as $o) rbio_add($all, $o);

$GLOBALS['RBIO_STEP'] = 'collect inaturalist api';
foreach (rbio_inat_api($diag, $apiEnabled) as $o) rbio_add($all, $o);

$GLOBALS['RBIO_STEP'] = 'collect ebird api';
if (!$skipEbirdInBiodiversityReports) {
    foreach (rbio_ebird_api($diag, $apiEnabled) as $o) rbio_add($all, $o);
} else {
    $diag['ebird_api'] = array('skipped'=>true, 'reason'=>'eBird је изузет из извештаја; освежава се посебно.');
}

$GLOBALS['RBIO_STEP'] = 'collect field reports';
foreach (rbio_collect_field_reports($diag, $fieldReportsEnabled) as $o) {
    if (is_array($o)) $all[isset($o['key']) ? ($o['key'] . '|' . ($o['date'] ?? '')) : md5(json_encode($o))] = $o;
}

$items = array_values($all);
$items = rbio_filter_items_to_boundary($items, $diag);
$beforeClean = count($items);
$items = rbio_reduce_broad_taxa($items);
usort($items, function($a, $b){ $c = strcmp($b['date'], $a['date']); return $c ? $c : strcmp($a['group'], $b['group']); });
$diag['taxon_cleaning'] = array('before'=>$beforeClean, 'after'=>count($items), 'removed'=>$beforeClean-count($items), 'mode'=>'balanced_keep_broad_marked');
$diag['finished_collect_at'] = date('c');
$diag['items_found'] = count($items);

if ($dry) {
    rbio_write_json($dataDir . '/debug-last-scan.json', array('generated_at'=>date('c'), 'diagnostics'=>$diag, 'items_found'=>count($items), 'sample'=>array_slice($items, 0, 30)));
    rbio_out(array('ok'=>true, 'dry'=>true, 'version'=>RBIO_VERSION, 'source_mode'=>$skipEbirdInBiodiversityReports?'inat_only':'inat_plus_ebird', 'items_found'=>count($items), 'diagnostics'=>$diag, 'sample'=>array_slice($items, 0, 12), 'debug_url'=>'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/data/debug-last-scan.json'));
}

$statePath = $dataDir . '/observations-master.json';
$state = rbio_read_json($statePath);
if (!is_array($state)) $state = array('version'=>RBIO_VERSION, 'created_at'=>date('c'), 'species'=>array(), 'observations'=>array());
$speciesState = isset($state['species']) && is_array($state['species']) ? $state['species'] : array();
$today = date('Y-m-d');
foreach ($items as $i=>$o) {
    $spKey = $o['key'];
    $isNew = !isset($speciesState[$spKey]);
    $items[$i]['is_new_for_local_register'] = $isNew;
    if ($isNew) {
        $speciesState[$spKey] = array(
            'first_seen'=>$today,
            'source'=>isset($o['source']) ? $o['source'] : '',
            'group'=>isset($o['group']) ? $o['group'] : '',
            'name'=>isset($o['name']) ? $o['name'] : '',
            'latin'=>isset($o['latin']) ? $o['latin'] : '',
            'display_name'=>isset($o['display_name']) ? $o['display_name'] : ''
        );
    }
}
$state['version'] = RBIO_VERSION;
$state['updated_at'] = date('c');
$state['species'] = $speciesState;
$state['last_diagnostics'] = $diag;
$state['last_items_count'] = count($items);
$state['observations'][date('c')] = $items;
if (count($state['observations']) > 60) $state['observations'] = array_slice($state['observations'], -60, null, true);
rbio_write_json($statePath, $state);
rbio_write_json($dataDir . '/last-raw-extract.json', array('generated_at'=>date('c'), 'diagnostics'=>$diag, 'items'=>$items));

$dailyId = date('Y-m-d');
$weeklyId = rbio_iso_week($dailyId);
$monthlyId = date('Y-m');
$daily = rbio_make_report('daily', $dailyId, $items, $outRoot);
$weekly = rbio_make_report('weekly', $weeklyId, $items, $outRoot);
$monthly = rbio_make_report('monthly', $monthlyId, $items, $outRoot);
$yearlyReports = array();
foreach (rbio_years_to_generate($items) as $yearId) {
    $yr = rbio_make_report('yearly', $yearId, $items, $outRoot);
    $yearlyReports[$yearId] = $yr;
    rbio_write_json($outRoot . '/latest-yearly-' . $yearId . '.json', $yr);
}
rbio_write_file($outRoot . '/godisnji/index.html', rbio_yearly_index_html($yearlyReports));
rbio_write_json($outRoot . '/latest-yearly.json', array('ok'=>true, 'version'=>RBIO_VERSION, 'generated_at'=>date('c'), 'reports'=>$yearlyReports));
$latest = array(
    'ok'=>true,
    'version'=>RBIO_VERSION,
    'generated_at'=>date('c'),
    'generated_date'=>$dailyId,
    'title'=>'Најновији извештаји биодиверзитета',
    'summary'=>'Дневни, седмични и месечни преглед најновијих доступних налаза биодиверзитета у границама ПИО „Рајац”, укључујући iNaturalist и теренске налазе управљача/грађанске науке.',
    'report_url'=>'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/',
    'daily'=>$daily,
    'weekly'=>$weekly,
    'monthly'=>$monthly,
    'yearly'=>$yearlyReports,
    'items'=>array_slice($items, 0, 30)
);
rbio_write_json($outRoot . '/latest.json', $latest);

$frag = '<section class="bio-latest"><h2>Биодиверзитет</h2><p>Дневни, седмични и месечни преглед најновијих доступних налаза биодиверзитета.</p><ul>';
foreach (array(array('Дневни извештај', $daily), array('Седмични извештај', $weekly), array('Месечни извештај', $monthly)) as $r) {
    $frag .= '<li><a href="'.rbio_h($r[1]['report_url']).'"><strong>'.rbio_h($r[0]).'</strong> — '.rbio_h($r[1]['period']).' · налази: '.rbio_h($r[1]['total_items']).' · ново: '.rbio_h($r[1]['new_items']).'</a></li>';
}
foreach ($yearlyReports as $year=>$r) {
    $frag .= '<li><a href="'.rbio_h($r['report_url']).'"><strong>Годишњи извештај</strong> — '.rbio_h($year).' · налази: '.rbio_h($r['total_items']).' · таксона: '.rbio_h($r['unique_taxa']).'</a></li>';
}
$frag .= '</ul></section>';
rbio_write_file($outRoot . '/latest-fragment.html', $frag);
rbio_write_file($outRoot . '/index.html', rbio_index_html($latest));

$yearlyCounts = array();
foreach ($yearlyReports as $year=>$r) $yearlyCounts[$year] = $r['total_items'];
rbio_out(array('ok'=>true, 'version'=>RBIO_VERSION, 'generated_date'=>$dailyId, 'source_mode'=>'inat_plus_field', 'daily'=>$daily['total_items'], 'weekly'=>$weekly['total_items'], 'monthly'=>$monthly['total_items'], 'yearly'=>$yearlyCounts, 'items_found'=>count($items), 'diagnostics'=>$diag, 'report_url'=>'https://piorajac.rs/monitoring-izvestaji/biodiverzitet/'));
