<?php
/** Интерна евиденција налаза ПИО „Рајац“ — само уз cPanel Directory Privacy. */
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
if(empty($_SERVER['REMOTE_USER']) && empty($_SERVER['REDIRECT_REMOTE_USER'])){http_response_code(403);exit('{"error":"not_authenticated"}');}
$dbPath='/home/piorajac/rajac-private/inat/rajac_inat_cache.sqlite';
if(!is_file($dbPath)){http_response_code(503);exit('{"error":"no_database"}');}
try{
    $db=new SQLite3($dbPath,SQLITE3_OPEN_READONLY);$db->busyTimeout(5000);
    $cols=[];$c=$db->query('PRAGMA table_info(obs)');
    while($c && ($x=$c->fetchArray(SQLITE3_ASSOC)))$cols[$x['name']]=true;
    $hasMeta=isset($cols['coordinates_obscured']);
    $r=$db->query('SELECT * FROM obs ORDER BY datum DESC, obs_id DESC');$records=[];
    while($r && ($ob=$r->fetchArray(SQLITE3_ASSOC))){
        $obscured=$hasMeta && (!empty($ob['coordinates_obscured'])
            || in_array((string)($ob['geoprivacy']??''),['obscured','private'],true)
            || in_array((string)($ob['taxon_geoprivacy']??''),['obscured','private'],true));
        // tacna = извор није замаглио; zamagljena = iNaturalist је померио тачку; nepoznata = стари запис без метаподатака
        $status=!$hasMeta?'nepoznata':($obscured?'zamagljena':'tacna');
        $lat=isset($ob['lat'])&&is_numeric($ob['lat'])?(float)$ob['lat']:null;
        $lng=isset($ob['lng'])&&is_numeric($ob['lng'])?(float)$ob['lng']:null;
        $records[]=['obs_id'=>(int)$ob['obs_id'],'naucni'=>(string)$ob['naucni'],'narodni'=>(string)($ob['narodni']??''),
          'rank'=>(string)($ob['rank']??''),'korisnik'=>(string)$ob['korisnik'],'grupa_taxon'=>(string)$ob['grupa_taxon'],
          'je_leptir'=>(int)($ob['je_leptir']??0),'quality_grade'=>(string)$ob['quality_grade'],'datum'=>(string)$ob['datum'],
          'lat'=>$lat,'lng'=>$lng,'location_status'=>$status,
          'positional_accuracy'=>isset($ob['positional_accuracy'])&&is_numeric($ob['positional_accuracy'])?(float)$ob['positional_accuracy']:null,
          'url'=>'https://www.inaturalist.org/observations/'.(int)$ob['obs_id']];
    }
    $db->close();
    echo json_encode(['status'=>'ok','records'=>$records],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){error_log('RAJAC admin records: '.$e->getMessage());http_response_code(503);echo '{"error":"read_failed"}';}
