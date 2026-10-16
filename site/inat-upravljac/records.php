<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private, max-age=0');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
if(empty($_SERVER['REMOTE_USER']) && empty($_SERVER['REDIRECT_REMOTE_USER'])){http_response_code(403);exit('{"error":"not_authenticated"}');}
$dbPath='/home/piorajac/rajac-private/inat/rajac_inat_cache.sqlite';if(!is_file($dbPath)){http_response_code(503);exit('{"error":"no_database"}');}
try{
$db=new SQLite3($dbPath,SQLITE3_OPEN_READONLY);$columns=[];
$c=$db->query('PRAGMA table_info(obs)');while($r=$c->fetchArray(SQLITE3_ASSOC))$columns[$r['name']]=true;
$r=$db->query('SELECT * FROM obs ORDER BY datum DESC, obs_id DESC');$records=[];
while($ob=$r->fetchArray(SQLITE3_ASSOC)){
    $hasMeta=isset($columns['coordinates_obscured']);
    $masked= !$hasMeta || !empty($ob['coordinates_obscured']) || in_array((string)($ob['geoprivacy']??''),['obscured','private'],true) || in_array((string)($ob['taxon_geoprivacy']??''),['obscured','private'],true);
    $records[]=['obs_id'=>(int)$ob['obs_id'],'naucni'=>$ob['naucni'],'korisnik'=>$ob['korisnik'],'grupa_taxon'=>$ob['grupa_taxon'],'quality_grade'=>$ob['quality_grade'],'datum'=>$ob['datum'],
      'lat'=>!$masked?(float)$ob['lat']:null,'lng'=>!$masked?(float)$ob['lng']:null,
      'location_status'=>$masked?'unverified_or_obscured':'public_exact',
      'positional_accuracy'=>isset($ob['positional_accuracy'])?$ob['positional_accuracy']:null];
}
echo json_encode(['status'=>'ok','records'=>$records],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){error_log('RAJAC admin records: '.$e->getMessage());http_response_code(503);echo '{"error":"read_failed"}';}
