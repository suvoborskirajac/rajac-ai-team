<?php
declare(strict_types=1);

use PioRajac\Store;
use PioRajac\Text;

$config = require __DIR__ . '/src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

try {
    $store = Store::open($config);
    $action = $_GET['action'] ?? 'observations';
    $rows = $store->observations();
    // Група према роду, за налазе које је увоз сврстао у „Остало“ (у Biologer CSV-у нема реда ни породице).
    $genusGroup = [
        'Aglais'=>'Дневни лептири',
        'Aporia'=>'Дневни лептири',
        'Araschnia'=>'Дневни лептири',
        'Arethusana'=>'Дневни лептири',
        'Argynnis'=>'Дневни лептири',
        'Aricia'=>'Дневни лептири',
        'Boloria'=>'Дневни лептири',
        'Brenthis'=>'Дневни лептири',
        'Brintesia'=>'Дневни лептири',
        'Carcharodus'=>'Дневни лептири',
        'Celastrina'=>'Дневни лептири',
        'Coenonympha'=>'Дневни лептири',
        'Colias'=>'Дневни лептири',
        'Cupido'=>'Дневни лептири',
        'Cyaniris'=>'Дневни лептири',
        'Erebia'=>'Дневни лептири',
        'Erynnis'=>'Дневни лептири',
        'Euphydryas'=>'Дневни лептири',
        'Fabriciana'=>'Дневни лептири',
        'Gonepteryx'=>'Дневни лептири',
        'Hamearis'=>'Дневни лептири',
        'Hesperia'=>'Дневни лептири',
        'Hipparchia'=>'Дневни лептири',
        'Iphiclides'=>'Дневни лептири',
        'Issoria'=>'Дневни лептири',
        'Kirinia'=>'Дневни лептири',
        'Lasiommata'=>'Дневни лептири',
        'Leptidea'=>'Дневни лептири',
        'Lycaena'=>'Дневни лептири',
        'Lysandra'=>'Дневни лептири',
        'Maniola'=>'Дневни лептири',
        'Melanargia'=>'Дневни лептири',
        'Melitaea'=>'Дневни лептири',
        'Minois'=>'Дневни лептири',
        'Neptis'=>'Дневни лептири',
        'Ochlodes'=>'Дневни лептири',
        'Papilio'=>'Дневни лептири',
        'Pararge'=>'Дневни лептири',
        'Phengaris'=>'Дневни лептири',
        'Pieris'=>'Дневни лептири',
        'Plebejus'=>'Дневни лептири',
        'Polygonia'=>'Дневни лептири',
        'Polyommatus'=>'Дневни лептири',
        'Pontia'=>'Дневни лептири',
        'Pyrgus'=>'Дневни лептири',
        'Satyrium'=>'Дневни лептири',
        'Speyeria'=>'Дневни лептири',
        'Thymelicus'=>'Дневни лептири',
        'Vanessa'=>'Дневни лептири',
        'Parnassius'=>'Дневни лептири',
        'Zerynthia'=>'Дневни лептири',
        'Apatura'=>'Дневни лептири',
        'Satyrus'=>'Дневни лептири',
        'Acontia'=>'Ноћни лептири',
        'Agapeta'=>'Ноћни лептири',
        'Agrotis'=>'Ноћни лептири',
        'Amphipyra'=>'Ноћни лептири',
        'Antheraea'=>'Ноћни лептири',
        'Bena'=>'Ноћни лептири',
        'Biston'=>'Ноћни лептири',
        'Catoptria'=>'Ноћни лептири',
        'Clepsis'=>'Ноћни лептири',
        'Crambidae'=>'Ноћни лептири',
        'Cryphia'=>'Ноћни лептири',
        'Dolicharthria'=>'Ноћни лептири',
        'Eilema'=>'Ноћни лептири',
        'Ematurga'=>'Ноћни лептири',
        'Endotricha'=>'Ноћни лептири',
        'Euclidia'=>'Ноћни лептири',
        'Euplagia'=>'Ноћни лептири',
        'Geometridae'=>'Ноћни лептири',
        'Hyles'=>'Ноћни лептири',
        'Idaea'=>'Ноћни лептири',
        'Lasiocampa'=>'Ноћни лептири',
        'Lomaspilis'=>'Ноћни лептири',
        'Lygephila'=>'Ноћни лептири',
        'Lymantria'=>'Ноћни лептири',
        'Macroglossum'=>'Ноћни лептири',
        'Metasia'=>'Ноћни лептири',
        'Miltochrista'=>'Ноћни лептири',
        'Mythimna'=>'Ноћни лептири',
        'Noctua'=>'Ноћни лептири',
        'Phyllodesma'=>'Ноћни лептири',
        'Plutella'=>'Ноћни лептири',
        'Pseudopanthera'=>'Ноћни лептири',
        'Scopula'=>'Ноћни лептири',
        'Sesiidae'=>'Ноћни лептири',
        'Synaphe'=>'Ноћни лептири',
        'Timandra'=>'Ноћни лептири',
        'Tyta'=>'Ноћни лептири',
        'Xestia'=>'Ноћни лептири',
        'Ambrosia'=>'Биљке',
        'Anthyllis'=>'Биљке',
        'Arum'=>'Биљке',
        'Asarum'=>'Биљке',
        'Asplenium'=>'Биљке',
        'Atropa'=>'Биљке',
        'Cornus'=>'Биљке',
        'Crataegus'=>'Биљке',
        'Dryopteris'=>'Биљке',
        'Euphorbia'=>'Биљке',
        'Fagus'=>'Биљке',
        'Fraxinus'=>'Биљке',
        'Gentiana'=>'Биљке',
        'Molinia'=>'Биљке',
        'Ostrya'=>'Биљке',
        'Oxalis'=>'Биљке',
        'Polypodium'=>'Биљке',
        'Prunus'=>'Биљке',
        'Pteridium'=>'Биљке',
        'Rubus'=>'Биљке',
        'Sambucus'=>'Биљке',
        'Sanguisorba'=>'Биљке',
        'Urtica'=>'Биљке',
        'Buteo'=>'Птице',
        'Carduelis'=>'Птице',
        'Falco'=>'Птице',
        'Lanius'=>'Птице',
        'Phoenicurus'=>'Птице',
        'Sitta'=>'Птице',
        'Myotis'=>'Сисари',
        'Anguis'=>'Гмизавци',
        'Lacerta'=>'Гмизавци',
        'Podarcis'=>'Гмизавци',
        'Cordulegaster'=>'Вилински коњици',
        'Onychogomphus'=>'Вилински коњици',
        'Gryllus'=>'Правокрилци',
        'Oedipoda'=>'Правокрилци',
        'Allecula'=>'Тврдокрилци',
        'Cerambycidae'=>'Тврдокрилци',
        'Coccinella'=>'Тврдокрилци',
        'Curculio'=>'Тврдокрилци',
        'Harmonia'=>'Тврдокрилци',
        'Hymenalia'=>'Тврдокрилци',
        'Lucanus'=>'Тврдокрилци',
        'Mylabris'=>'Тврдокрилци',
        'Nacerdes'=>'Тврдокрилци',
        'Prionus'=>'Тврдокрилци',
        'Stenagostus'=>'Тврдокрилци',
        'Xanthogaleruca'=>'Тврдокрилци',
        'Rosalia'=>'Тврдокрилци',
        'Cerambyx'=>'Тврдокрилци',
        'Morimus'=>'Тврдокрилци',
        'Gonocerus'=>'Стенице',
        'Graphosoma'=>'Стенице',
        'Halyomorpha'=>'Стенице',
        'Heteroptera'=>'Стенице',
        'Spilostethus'=>'Стенице',
        'Syrphidae'=>'Двокрилци',
        'Macrolepiota'=>'Гљиве'
    ];
    foreach ($rows as &$row) {
        $g = (string)($row['taxon_group'] ?? '');
        if ($g === '' || $g === 'Остало') {
            $genus = strtok(trim((string)($row['scientific_name'] ?? '')), ' /');
            if ($genus !== false && isset($genusGroup[$genus])) $row['taxon_group'] = $genusGroup[$genus];
        }
    }
    unset($row);

    $species = trim((string)($_GET['species'] ?? ''));
    $group = trim((string)($_GET['group'] ?? ''));
    $year = trim((string)($_GET['year'] ?? ''));
    $month = trim((string)($_GET['month'] ?? ''));
    $zone = trim((string)($_GET['zone'] ?? ''));
    $q = Text::lower(trim((string)($_GET['q'] ?? '')));
    $showUncertain = ($_GET['uncertain'] ?? '0') === '1';

    $filtered = array_values(array_filter($rows, static function(array $r) use($species,$group,$year,$month,$zone,$q,$showUncertain): bool {
        if (!$showUncertain && !empty($r['uncertain'])) return false;
        if ($species !== '' && (string)($r['scientific_name'] ?? '') !== $species) return false;
        if ($group !== '' && (string)($r['taxon_group'] ?? '') !== $group) return false;
        if ($year !== '' && (int)($r['year'] ?? 0) !== (int)$year) return false;
        if ($month !== '' && (int)($r['month'] ?? 0) !== (int)$month) return false;
        if ($zone !== '' && (int)($r['zone'] ?? 0) !== (int)$zone) return false;
        if ($q !== '') {
            $hay = Text::lower(implode(' ', [
                (string)($r['scientific_name'] ?? ''),(string)($r['native_name'] ?? ''),
                (string)($r['observer'] ?? ''),(string)($r['identifier'] ?? '')
            ]));
            if (!str_contains($hay, $q)) return false;
        }
        return true;
    }));

    if ($action === 'filters') {
        $groups=[];$speciesMap=[];$years=[];$zones=[];
        foreach ($rows as $r) {
            if (($v=$r['taxon_group'] ?? '')!=='') $groups[$v]=true;
            if (($v=$r['scientific_name'] ?? '')!=='') {
                $key=$v.'|'.($r['native_name'] ?? '').'|'.($r['taxon_group'] ?? '');
                if (!isset($speciesMap[$key])) $speciesMap[$key]=['scientific_name'=>$v,'native_name'=>$r['native_name'] ?? '','taxon_group'=>$r['taxon_group'] ?? '','records'=>0];
                $speciesMap[$key]['records']++;
            }
            if (!empty($r['year'])) $years[(int)$r['year']]=true;
            if (!empty($r['zone'])) {
                $k=(int)$r['zone'];
                if (!isset($zones[$k])) $zones[$k]=['zone'=>$k,'zone_name'=>$r['zone_name'] ?? '', 'records'=>0];
                $zones[$k]['records']++;
            }
        }
        $groups=array_keys($groups);sort($groups,SORT_NATURAL|SORT_FLAG_CASE);
        $speciesList=array_values($speciesMap);usort($speciesList,static fn($a,$b)=>strnatcasecmp($a['scientific_name'],$b['scientific_name']));
        $years=array_keys($years);rsort($years,SORT_NUMERIC);ksort($zones,SORT_NUMERIC);
        echo json_encode(['groups'=>$groups,'species'=>$speciesList,'years'=>$years,'zones'=>array_values($zones),'last_sync'=>$store->lastLog()], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'summary') {
        $speciesSet=[];$latest=null;$monthly=[];$top=[];
        foreach ($filtered as $r) {
            $name=(string)($r['scientific_name'] ?? '');
            if ($name!=='') {$speciesSet[$name]=true;$top[$name]['scientific_name']=$name;$top[$name]['native_name']=$r['native_name'] ?? '';$top[$name]['records']=($top[$name]['records'] ?? 0)+1;}
            $d=$r['event_date'] ?? null;if ($d && ($latest===null || $d>$latest)) $latest=$d;
            $m=(int)($r['month'] ?? 0);if ($m>=1&&$m<=12){$monthly[$m]['month']=$m;$monthly[$m]['records']=($monthly[$m]['records']??0)+1;$monthly[$m]['species_set'][$name]=true;}
        }
        foreach ($monthly as &$m) {$m['species']=count($m['species_set']??[]);unset($m['species_set']);}unset($m);
        ksort($monthly,SORT_NUMERIC);
        $top=array_values($top);usort($top,static fn($a,$b)=>($b['records']<=>$a['records']) ?: strnatcasecmp($a['scientific_name'],$b['scientific_name']));
        $top=array_slice($top,0,15);
        echo json_encode(['totals'=>['records'=>count($filtered),'species'=>count($speciesSet),'latest'=>$latest],'monthly'=>array_values($monthly),'top'=>$top], JSON_UNESCAPED_UNICODE);
        exit;
    }

    usort($filtered, static fn($a,$b)=>strcmp((string)($b['event_date']??''),(string)($a['event_date']??'')));
    $limit = min((int)$config['public_max_features'], max(1, (int)($_GET['limit'] ?? $config['public_max_features'])));
    $filtered=array_slice($filtered,0,$limit);
    // Строго заштићене врсте: јавно се објављује само поље од око 1 km (0,01°), без тачне тачке и надморске висине.
    $sensitive = [
        'Lycaena dispar','Papilio machaon','Pieris brassicae','Plebejus argyrognomon','Pyrgus sidae',
        'Euphydryas aurinia','Parnassius mnemosyne','Zerynthia polyxena','Apatura ilia','Satyrus ferula',
        'Rosalia alpina','Lucanus cervus','Cerambyx cerdo','Morimus funereus','Morimus asper',
        'Phengaris alcon','Gentiana pneumonanthe','Gentiana lutea','Euplagia quadripunctaria',
        'Myotis myotis','Lacerta viridis','Anguis fragilis','Podarcis muralis',
    ];
    $sensitiveSet = array_fill_keys(array_map('strtolower', $sensitive), true);
    $cell = static fn(float $v): float => round(floor($v / 0.01) * 0.01 + 0.005, 4);
    $features=[];
    foreach ($filtered as $r) {
        $props=$r;unset($props['latitude'],$props['longitude'],$props['raw_json']);
        $lng=(float)$r['longitude'];$lat=(float)$r['latitude'];
        if (isset($sensitiveSet[strtolower(trim((string)($r['scientific_name'] ?? '')))])) {
            $lng=$cell($lng);$lat=$cell($lat);
            unset($props['elevation']);
            $props['accuracy']=1000;$props['coarse']=1;
            $props['coarse_note']='Заштићена врста — приказано је поље од око 1 km, а не тачно место налаза.';
        }
        $features[]=['type'=>'Feature','id'=>$r['source_id'],'geometry'=>['type'=>'Point','coordinates'=>[$lng,$lat]],'properties'=>$props];
    }
    echo json_encode(['type'=>'FeatureCollection','features'=>$features,'count'=>count($features)], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error'=>true,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
