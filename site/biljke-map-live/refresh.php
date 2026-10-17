<?php
/** RAJAC FLORA maps v13 - CLI/Cron only. PHP >=7.4. No HTML modification. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
ini_set('display_errors', '0');
@set_time_limit(0);
$root=__DIR__;
$private=$root.'/private';
if (!is_dir($private)) { fwrite(STDERR, "Private folder unavailable\n"); exit(1); }
$lock=@fopen($private.'/refresh.lock','c');
if (!$lock || !@flock($lock,LOCK_EX|LOCK_NB)) { fwrite(STDERR,"Already running\n");exit(1); }
function getApi(string $path, array $query): array {
    $url='https://api.inaturalist.org/v1/'.ltrim($path,'/').'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>45,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'Rajac-flora-monitor/1.3 (https://piorajac.rs/)']);
        $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        if($raw===false||$code!==200)throw new RuntimeException('iNaturalist API HTTP '.$code.' '.$err);
    } else {
        $context=stream_context_create(['http'=>['timeout'=>45,'header'=>"Accept: application/json\r\nUser-Agent: Rajac-flora-monitor/1.3\r\n"]]);
        $raw=@file_get_contents($url,false,$context);
        if($raw===false)throw new RuntimeException('iNaturalist API fetch failed');
    }
    $data=json_decode((string)$raw,true);
    if(!is_array($data)||!isset($data['results'])||!is_array($data['results']))throw new RuntimeException('Invalid API response');
    return $data;
}
function atomicJSON(string $file,array $data):void {
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    if($json===false)throw new RuntimeException('JSON encode error');
    $temp=$file.'.new.'.getmypid();
    if(file_put_contents($temp,$json,LOCK_EX)===false)throw new RuntimeException('Write error');
    if(!@rename($temp,$file))throw new RuntimeException('Atomic write error');
}
function ringHas(float $x,float $y,array $ring):bool {
    $inside=false;$n=count($ring);
    for($i=0,$j=$n-1;$i<$n;$j=$i++) {
        if(!isset($ring[$i][0],$ring[$i][1],$ring[$j][0],$ring[$j][1]))continue;
        $a=(float)$ring[$i][0];$b=(float)$ring[$i][1];$c=(float)$ring[$j][0];$d=(float)$ring[$j][1];
        if((($b>$y)!==($d>$y)) && $x<(($c-$a)*($y-$b)/($d-$b)+$a))$inside=!$inside;
    }
    return $inside;
}
function polyHas(float $x,float $y,array $poly):bool {
    if(!$poly||!ringHas($x,$y,$poly[0]))return false;
    for($i=1;$i<count($poly);$i++)if(ringHas($x,$y,$poly[$i]))return false;
    return true;
}
function isWithin(float $x,float $y,array $g):bool {
    $type=$g['type']??'';
    if($type==='Polygon')return polyHas($x,$y,$g['coordinates']??[]);
    if($type==='MultiPolygon')foreach($g['coordinates']??[] as $p)if(polyHas($x,$y,$p))return true;
    return false;
}
try {
    $sp=json_decode((string)file_get_contents($root.'/data/species.json'),true,512,JSON_THROW_ON_ERROR);
    $g=json_decode((string)file_get_contents($root.'/data/boundaries.geojson'),true,512,JSON_THROW_ON_ERROR);
    if(!is_array($sp)||count($sp)!==109)throw new RuntimeException('Expected 109 species');
    $pio=null;
    foreach($g['features']??[] as $f)if(($f['properties']['layer']??'')==='GranicaKAT'){
        if($pio!==null)throw new RuntimeException('Duplicate PIO polygon');$pio=$f['geometry'];
    }
    if(!is_array($pio))throw new RuntimeException('PIO boundary missing');
    $box=[INF,INF,-INF,-INF];
    $visit=function($part)use(&$visit,&$box){
        if(is_array($part)&&isset($part[0],$part[1])&&is_numeric($part[0])&&is_numeric($part[1])){
            $x=(float)$part[0];$y=(float)$part[1];
            $box=[min($box[0],$x),min($box[1],$y),max($box[2],$x),max($box[3],$y)];return;
        }
        if(is_array($part))foreach($part as $one)$visit($one);
    };$visit($pio['coordinates']);
    if($box[0]<18||$box[2]>23||$box[1]<42||$box[3]>46)throw new RuntimeException('Unexpected PIO envelope');
    $resolved=[];
    $resolvedFile=$private.'/taxa_resolved.json';
    if(is_file($resolvedFile)){
        $saved=json_decode((string)file_get_contents($resolvedFile),true);
        if(is_array($saved))$resolved=$saved;
    }
    foreach($sp as $slug=>$row){
        if(!empty($row['taxon_id'])&&is_numeric($row['taxon_id']))$resolved[$slug]=['id'=>(int)$row['taxon_id'],'name'=>$row['taxon_query'],'rank'=>$row['taxon_rank']];
    }
    // Failed/ambiguous searches are deferred, otherwise they would starve later slugs forever.
    $attemptsFile=$private.'/taxa_attempts.json';
    $attempts=[];
    if(is_file($attemptsFile)){
        $old=json_decode((string)file_get_contents($attemptsFile),true);
        if(is_array($old))$attempts=$old;
    }
    // Limit remote taxon lookups per run: no fuzzy first-hit assignment, and no fallback from a subspecies to the whole species.
    $newLookups=0;
    foreach($sp as $slug=>$row){
        if(isset($resolved[$slug]['id']) && (int)$resolved[$slug]['id']>0)continue;
        if(($row['resolution_source']??'')==='aggregate_manual_review')continue;
        if(isset($attempts[$slug]) && time()-(int)$attempts[$slug] < 43200)continue;
        if($newLookups>=15)break;
        $query=(string)($row['taxon_query']??'');$rank=(string)($row['taxon_rank']??'species');
        if($query==='')continue;
        try {
            $taxa=getApi('taxa/autocomplete',['q'=>$query,'per_page'=>30]);
            $matches=[];
            foreach($taxa['results'] as $t){
                if(!is_numeric($t['id']??null)||strtolower(trim((string)($t['name']??'')))!==strtolower(trim($query)))continue;
                if(($t['rank']??'')!==$rank||($t['is_active']??true)===false)continue;
                $matches[(int)$t['id']]=$t;
            }
            if(count($matches)===1){$id=(int)array_key_first($matches);$resolved[$slug]=['id'=>$id,'name'=>$query,'rank'=>$rank];}
            else { fwrite(STDERR,'Taxon unresolved (no unique exact active '.$rank.' match): '.$slug."\n"); }
        } catch(Throwable $e){fwrite(STDERR,'Taxon lookup warning '.$slug.': '.$e->getMessage()."\n");}
        $attempts[$slug]=time();
        $newLookups++;usleep(1250000);
    }
    // Only successfully enumerated, un-obscured observations with known positional accuracy can support a point-in-boundary statement.
    $rows=[];$seen=[];$maxPages=50;$perPage=200;$complete=false;
    for($page=1;$page<=$maxPages;$page++){
        $q=['iconic_taxa'=>'Plantae','swlat'=>$box[1]-0.001,'swlng'=>$box[0]-0.001,'nelat'=>$box[3]+0.001,'nelng'=>$box[2]+0.001,
            'per_page'=>$perPage,'page'=>$page,'order_by'=>'id','order'=>'desc'];
        $res=getApi('observations',$q);
        foreach($res['results'] as $ob){
            $id=$ob['id']??0;if(!is_numeric($id)||(int)$id<1||isset($seen[(int)$id]))continue;
            $seen[(int)$id]=true;$rows[]=$ob;
        }
        if(count($res['results'])<$perPage){$complete=true;break;}
        usleep(1250000);
    }
    if(!$complete)throw new RuntimeException('Observation page cap reached: preserving last good cache');
    $state=['status'=>'ok','updated_at'=>gmdate('c'),'version'=>'13','species'=>[],'api_observation_count'=>count($rows)];
    foreach($sp as $slug=>$row){
        $id=(int)($resolved[$slug]['id']??0);
        $state['species'][$slug]=['status'=>$id>0?'ok':'unresolved_taxon','taxon_id'=>$id?:null,
          'research_count'=>0,'unverified_count'=>0,'points'=>[],
          'privacy'=>!empty($row['show_precise_points'])?'public':'restricted'];
    }
    foreach($rows as $ob){
        $taxon=$ob['taxon']??[];if(!is_array($taxon)||!is_numeric($taxon['id']??null))continue;
        if(!empty($ob['coordinates_obscured'])||in_array((string)($ob['geoprivacy']??''),['obscured','private'],true)
        ||in_array((string)($taxon['geoprivacy']??''),['obscured','private'],true))continue;
        $coords=$ob['geojson']['coordinates']??null;
        if(!is_array($coords)||!is_numeric($coords[0]??null)||!is_numeric($coords[1]??null))continue;
        $accuracy=$ob['positional_accuracy']??null;
        if(!is_numeric($accuracy)||$accuracy<=0||$accuracy>100)continue; // null/zero/low precision excluded
        $lon=(float)$coords[0];$lat=(float)$coords[1];
        if(!isWithin($lon,$lat,$pio))continue;
        $grade=(string)($ob['quality_grade']??'');
        if(!in_array($grade,['research','needs_id','casual'],true))continue;
        $ancestorIds=array_map('intval',is_array($taxon['ancestor_ids']??null)?$taxon['ancestor_ids']:[]);
        $ancestorIds[]=(int)$taxon['id'];
        foreach($sp as $slug=>$row){
            $id=(int)($resolved[$slug]['id']??0);
            if($id<1||!in_array($id,$ancestorIds,true))continue;
            // A matched ID must have been resolved to the exact rank of this profile; subspecies never inherits species-wide observations.
            $entry=&$state['species'][$slug];
            if($grade==='research'){
                $entry['research_count']++;
                if($entry['privacy']==='public'){
                    $entry['points'][]=['id'=>(int)$ob['id'],'lat'=>round($lat,6),'lng'=>round($lon,6),
                    'name'=>(string)($taxon['name']??''),'date'=>substr((string)($ob['observed_on']??''),0,10),
                    'accuracy_m'=>(int)$accuracy,'grade'=>'research'];
                }
            }else {$entry['unverified_count']++;}
            unset($entry);
        }
    }
    // All output goes to protected directory; legacy v11 cache paths must be denied by /cache/.htaccess.
    atomicJSON($private.'/state.json',$state);
    atomicJSON($resolvedFile,$resolved);
    atomicJSON($attemptsFile,$attempts);
    $ready=count(array_filter($state['species'],static function($v){return $v['status']==='ok';}));
    echo 'OK '.gmdate('c').' taxa='.$ready.'/'.count($sp).' observations='.count($rows)."\n";
} catch(Throwable $e){
    fwrite(STDERR,'FAIL '.gmdate('c').' '.$e->getMessage()."\n");exit(1);
} finally {flock($lock,LOCK_UN);fclose($lock);}
