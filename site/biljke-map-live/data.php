<?php
/** Read-only JSON for approved flora page slug; never serves the raw internal state or coordinates for protected species. */
declare(strict_types=1);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
function reply(array $json,int $status=200):void{
    http_response_code($status);
    echo json_encode($json,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;
}
$slug=(string)($_GET['slug']??'');
if(!preg_match('/\A[a-z0-9-]{1,100}\z/D',$slug))reply(['status'=>'error','reason'=>'invalid_slug'],400);
try {
    $species=json_decode((string)file_get_contents(__DIR__.'/data/species.json'),true,512,JSON_THROW_ON_ERROR);
    if(!isset($species[$slug]))reply(['status'=>'error','reason'=>'unknown_species'],404);
    $path=__DIR__.'/private/state.json';
    if(!is_file($path))reply(['status'=>'not_ready','reason'=>'awaiting_first_successful_cron']);
    $state=json_decode((string)file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    $entry=$state['species'][$slug]??null;
    if(!is_array($entry))reply(['status'=>'error','reason'=>'species_missing_from_cache'],503);
    $public=($species[$slug]['show_precise_points']??false)===true;
    $result=['status'=>$entry['status']??'not_ready','scientific_name'=>$species[$slug]['scientific_name'],
       'taxon_id'=>$entry['taxon_id']??null,'research_count'=>(int)($entry['research_count']??0),
       'unverified_count'=>(int)($entry['unverified_count']??0),'privacy'=>$public?'public':'restricted',
       'updated_at'=>(string)($state['updated_at']??''),'stale'=>empty($state['updated_at'])||time()-strtotime((string)$state['updated_at'])>86400,
       'points'=>$public?($entry['points']??[]):[]];
    // Defense-in-depth: never return points unless both manifest allowlist AND cache agree.
    if(($entry['privacy']??'')!=='public')$result['points']=[];
    reply($result);
} catch(Throwable $e){
    error_log('Flora maps data v13: '.$e->getMessage());
    reply(['status'=>'error','reason'=>'read_failed'],503);
}
