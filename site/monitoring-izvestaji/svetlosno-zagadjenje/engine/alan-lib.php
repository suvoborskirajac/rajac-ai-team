<?php
declare(strict_types=1);
function fail(string $msg): void { echo "ERROR: $msg\n"; exit(1); }
function ensure_dir(string $path): void { if (!is_dir($path) && !mkdir($path, 0755, true)) fail("Не могу да направим директоријум: $path"); }
function read_json_file(string $path): ?array {
  if (!is_file($path)) return null;
  $raw = file_get_contents($path);
  if ($raw === false || trim($raw) === '') return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}
function write_json_file(string $path, array $data): void {
  file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function lower_utf8(string $s): string { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); }
function month_label(string $period): string {
  $names = ['01'=>'јануар','02'=>'фебруар','03'=>'март','04'=>'април','05'=>'мај','06'=>'јун','07'=>'јул','08'=>'август','09'=>'септембар','10'=>'октобар','11'=>'новембар','12'=>'децембар'];
  if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) return ($names[$m[2]] ?? $m[2]) . ' ' . $m[1] . '.';
  return $period;
}
function previous_period(string $period): string {
  $dt = DateTime::createFromFormat('!Y-m-d', $period . '-01');
  if (!$dt) return '';
  $dt->modify('-1 month');
  return $dt->format('Y-m');
}
function norm_num($v): ?float {
  if ($v === null || $v === '') return null;
  if (is_string($v)) $v = str_replace(',', '.', trim($v));
  return is_numeric($v) ? (float)$v : null;
}

function is_example_path(string $path): bool {
  $p = strtolower(str_replace('\\','/', $path));
  if (strpos($p, '.example.json') !== false) return true;
  if (strpos($p, '/example') !== false || strpos($p, '/examples/') !== false) return true;
  if (preg_match('/(?:^|\/)(source-light|history-input|manual-input)\.example\.json$/i', $p)) return true;
  return false;
}
function is_generated_output_path(string $path): bool {
  $p = strtolower(str_replace('\\','/', $path));
  if (preg_match('#/monitoring-izvestaji/svetlosno-zagadjenje/data/reports\.json$#', $p)) return true;
  if (preg_match('#/monitoring-izvestaji/svetlosno-zagadjenje/data/series-[0-9]{4}-[0-9]{2}_[0-9]{4}-[0-9]{2}\.json$#', $p)) return true;
  if (preg_match('#/monitoring-izvestaji/svetlosno-zagadjenje/latest\.json$#', $p)) return true;
  if (preg_match('#/monitoring-izvestaji/svetlosno-zagadjenje/reports/[0-9]{4}-[0-9]{2}/report\.json$#', $p)) return true;
  return false;
}
function should_ignore_input_path(string $path, array $config): bool {
  if (!empty($config['ignore_example_inputs']) && is_example_path($path)) return true;
  if (!empty($config['ignore_generated_outputs']) && is_generated_output_path($path)) return true;
  return false;
}
function looks_like_bad_numeric_path(string $path): bool {
  $p = strtolower(str_replace('\\','/', $path));
  $bad = ['coordinates','coord','geometry','bbox','bounds','extent','boundary','granica','ring','rings','polygon','polygons','lon','lng','latitude','longitude','lat','x_coords','y_coords','labels','dates','timestamp','time','year','month'];
  foreach ($bad as $b) if (strpos($p, $b) !== false) return true;
  return false;
}
function looks_like_radiance_path(string $path): bool {
  $p = strtolower(str_replace('\\','/', $path));
  $good = ['radiance','radijans','радијанс','avg_rad','avg-rad','avg rad','value','values','pixel','pixels','grid','raster','matrix','heatmap','cells','samples','data','raw','smooth','smoothed','intensity','night','viirs','dnb','vcmsl'];
  foreach ($good as $g) if (strpos($p, $g) !== false) return true;
  return false;
}
function looks_like_summary_numeric_path(string $path): bool {
  $p = strtolower(str_replace('\\','/', $path));
  $summary = ['.stats','.statistics','.summary','.hotspot','.hotspots','.meta','.metadata','.index','.periods','.yearlyperiods'];
  foreach ($summary as $part) if (strpos($p, $part) !== false) return true;
  return false;
}
function safe_radiance_value($v): ?float {
  $n = norm_num($v);
  if ($n === null) return null;
  if (!is_finite($n)) return null;
  if ($n < 0 || $n > 100000) return null;
  return $n;
}
function collect_candidate_values(array $node, string $path='root', int $depth=0): array {
  if ($depth > 40) return [];
  $vals = [];
  // Сажети статистички блокови (нарочито stats.hotspots) понављају вредности
  // из примарних пиксела и не смеју бити део резервног обрачуна.
  if (looks_like_bad_numeric_path($path) || looks_like_summary_numeric_path($path)) return [];
  $pixelKeys = ['avg_rad','avgRad','radiance','radiance_value','rad','radijansa','radiјansa','value','val','v','z','intensity','raw_value','smooth_value','smoothed_value'];
  foreach ($pixelKeys as $k) {
    if (array_key_exists($k, $node)) {
      $n = safe_radiance_value($node[$k]);
      if ($n !== null && !looks_like_bad_numeric_path($path . '.' . $k)) $vals[] = $n;
    }
  }
  $allNumeric = true; $nums = [];
  foreach ($node as $v) {
    if (is_array($v)) { $allNumeric = false; break; }
    $n = safe_radiance_value($v);
    if ($n === null && $v !== null && $v !== '') { $allNumeric = false; break; }
    if ($n !== null) $nums[] = $n;
  }
  if ($allNumeric && $nums) {
    $cnt = count($nums);
    if ($cnt >= 10 && looks_like_radiance_path($path)) return array_merge($vals, $nums);
    if (($cnt === 3 || $cnt === 4) && looks_like_radiance_path($path)) {
      $last = $nums[$cnt-1];
      if ($last >= 0 && $last <= 100000) $vals[] = $last;
    }
  }
  foreach ($node as $k=>$v) {
    if (is_array($v)) {
      $childPath = $path . '.' . (is_string($k) ? $k : (string)$k);
      $vals = array_merge($vals, collect_candidate_values($v, $childPath, $depth+1));
    }
  }
  return $vals;
}
function radiance_stats_from_values(array $vals): ?array {
  $clean = [];
  foreach ($vals as $v) {
    $n = safe_radiance_value($v);
    if ($n !== null) $clean[] = $n;
  }
  if (count($clean) < 3) return null;
  sort($clean, SORT_NUMERIC);
  $n = count($clean); $sum = array_sum($clean);
  $q = function(float $p) use ($clean, $n): float {
    if ($n <= 1) return $clean[0];
    $pos = ($n - 1) * $p; $lo = (int)floor($pos); $hi = (int)ceil($pos);
    if ($lo === $hi) return $clean[$lo];
    return $clean[$lo] + ($clean[$hi]-$clean[$lo]) * ($pos-$lo);
  };
  return ['mean'=>$sum/$n,'median'=>$q(0.5),'p90'=>$q(0.9),'min'=>$clean[0],'max'=>$clean[$n-1],'count'=>$n];
}
function infer_radiance_stats(array $raw, array $config): ?array {
  if (empty($config['enable_infer_stats_from_pixels'])) return null;
  return radiance_stats_from_values(collect_candidate_values($raw));
}

function fmt_num($v, int $dec=2): string { return is_numeric($v) ? number_format((float)$v, $dec, '.', '') : '—'; }
function pct_change(?float $current, ?float $previous): ?float {
  if ($current === null || $previous === null || abs($previous) < 1e-12) return null;
  return (($current - $previous) / abs($previous)) * 100.0;
}
function trend_label(?float $abs, ?float $pct, array $config): string {
  if ($abs === null && $pct === null) return 'без довољно података';
  $tp = (float)($config['change_threshold_pct'] ?? 5.0);
  $ta = (float)($config['change_threshold_abs'] ?? 0.05);
  // Када имамо и апсолутну и процентуалну промену, тражимо да оба прага буду испуњена.
  // То спречава да врло мали бројеви постану „значајни” само због великог процента.
  if ($abs !== null && $pct !== null) {
    if ($abs >= $ta && $pct >= $tp) return 'пораст радијансе';
    if ($abs <= -$ta && $pct <= -$tp) return 'смањење радијансе';
    return 'стабилно';
  }
  if ($pct !== null) {
    if ($pct >= $tp) return 'пораст радијансе';
    if ($pct <= -$tp) return 'смањење радијансе';
  }
  if ($abs !== null) {
    if ($abs >= $ta) return 'пораст радијансе';
    if ($abs <= -$ta) return 'смањење радијансе';
  }
  return 'стабилно';
}
function severity_label(?float $abs, ?float $pct): string {
  $a = $abs === null ? 0.0 : abs($abs);
  $p = $pct === null ? 0.0 : abs($pct);
  if ($p >= 20.0 || $a >= 0.20) return 'изражена промена';
  if ($p >= 10.0 || $a >= 0.10) return 'умерена промена';
  if ($p > 0.0 || $a > 0.0) return 'блага промена';
  return 'без промене';
}
function get_first_num(array $a, array $keys): ?float {
  foreach ($keys as $k) {
    if (array_key_exists($k, $a)) {
      $v = norm_num($a[$k]);
      if ($v !== null) return $v;
    }
  }
  return null;
}
function get_first_str(array $a, array $keys): ?string {
  foreach ($keys as $k) {
    if (array_key_exists($k, $a) && $a[$k] !== null && $a[$k] !== '') return (string)$a[$k];
  }
  return null;
}
function load_input(array $config): ?array {
  foreach ($config['candidate_input_files'] as $path) {
    $data = read_json_file($path);
    if ($data) { $data['_source_file'] = $path; return $data; }
  }
  return null;
}
function normalize_zone(array $z, array $config): array {
  $name = get_first_str($z, ['name','zone','sector','label','title']) ?? 'Неименована зона';
  $type = get_first_str($z, ['type','category','group']) ?? '';
  $current = get_first_num($z, ['current_mean','current_mean_radiance','mean_radiance','mean','radiance_mean','current']);
  $previous = get_first_num($z, ['previous_mean','previous_mean_radiance','prev_mean','prev_mean_radiance','previous','previous_radiance']);
  $abs = ($current !== null && $previous !== null) ? ($current - $previous) : get_first_num($z, ['abs_diff','difference','diff']);
  $pct = get_first_num($z, ['change_pct','change_percent','pct_diff','percent_diff']);
  if ($pct === null && $current !== null && $previous !== null) $pct = pct_change($current, $previous);
  $trend = trend_label($abs, $pct, $config);
  $severity = severity_label($abs, $pct);
  return [
    'name'=>$name,
    'type'=>$type,
    'current_mean'=>$current,
    'previous_mean'=>$previous,
    'abs_diff'=>$abs,
    'pct_diff'=>$pct,
    'trend'=>$trend,
    'severity'=>$severity,
    'point_count'=>(int)($z['point_count'] ?? 0),
    'share_pct'=>get_first_num($z, ['share_pct']),
    'centroid_lon'=>get_first_num($z, ['centroid_lon']),
    'centroid_lat'=>get_first_num($z, ['centroid_lat']),
    'bbox'=>is_array($z['bbox'] ?? null) ? $z['bbox'] : null,
    'mean_pixel_diff'=>get_first_num($z, ['mean_pixel_diff']),
    'mean_pixel_pct'=>get_first_num($z, ['mean_pixel_pct']),
    'max_abs_pixel_diff'=>get_first_num($z, ['max_abs_pixel_diff']),
    'cluster_radius_m'=>get_first_num($z, ['cluster_radius_m']),
    'change_direction'=>get_first_str($z, ['change_direction']) ?? '',
    'change_degree'=>get_first_str($z, ['change_degree','degree','degree_key']) ?? '',
    'change_degree_label'=>get_first_str($z, ['change_degree_label','degree_label']) ?? '',
    'geometry'=>is_array($z['geometry'] ?? null) ? $z['geometry'] : null,
    'member_points'=>is_array($z['member_points'] ?? null) ? $z['member_points'] : [],
    'note'=>get_first_str($z, ['note','comment','explanation','opis']) ?? ''
  ];
}
function normalize_zones(array $in, array $config): array {
  $zones = [];
  $raw = [];
  foreach (['zones','zone_stats','zonal_stats','sectors','areas'] as $k) {
    if (isset($in[$k]) && is_array($in[$k])) { $raw = $in[$k]; break; }
  }
  foreach ($raw as $z) if (is_array($z)) $zones[] = normalize_zone($z, $config);
  usort($zones, function($a, $b) {
    $an=(int)($a['point_count'] ?? 0); $bn=(int)($b['point_count'] ?? 0);
    if ($an !== $bn) return $bn <=> $an;
    $aa = isset($a['pct_diff']) && $a['pct_diff'] !== null ? abs((float)$a['pct_diff']) : (isset($a['abs_diff']) ? abs((float)$a['abs_diff']) : 0);
    $bb = isset($b['pct_diff']) && $b['pct_diff'] !== null ? abs((float)$b['pct_diff']) : (isset($b['abs_diff']) ? abs((float)$b['abs_diff']) : 0);
    return $bb <=> $aa;
  });
  return $zones;
}
/* =========================
   v14 — VIIRS статистичке зоне промене
   Зоне НИСУ режими заштите нити унапред задати сектори.
   Оне настају статистичким просторним груписањем суседних
   VIIRS тачака које су између два месеца имале значајан раст
   или значајан пад радијансе.
   ========================= */
function viirs_pixel_rows(array $raw): array {
  $rows = [];
  $pixels = [];
  if (isset($raw['pixels']) && is_array($raw['pixels'])) $pixels = $raw['pixels'];
  elseif (isset($raw['data']['pixels']) && is_array($raw['data']['pixels'])) $pixels = $raw['data']['pixels'];
  foreach ($pixels as $p) {
    if (!is_array($p)) continue;
    $lon = get_first_num($p, ['lon','lng','longitude','x']);
    $lat = get_first_num($p, ['lat','latitude','y']);
    $val = get_first_num($p, ['value','radiance','avg_rad','avgRad','rad','intensity','v','z']);
    if ($lon === null || $lat === null || $val === null) continue;
    $key = sprintf('%.6f|%.6f', $lon, $lat);
    $bbox = null;
    if (isset($p['bbox']) && is_array($p['bbox']) && count($p['bbox'])===4) {
      $bb=array_values($p['bbox']);
      $bbox=[norm_num($bb[0]),norm_num($bb[1]),norm_num($bb[2]),norm_num($bb[3])];
      if (in_array(null,$bbox,true)) $bbox=null;
    }
    $rows[$key] = ['lon'=>$lon,'lat'=>$lat,'value'=>$val,'bbox'=>$bbox,'id'=>get_first_str($p,['id','pixel_id','key']) ?? $key];
  }
  return $rows;
}
function viirs_common_pixel_pairs(array $currentRaw, array $previousRaw): array {
  $c = viirs_pixel_rows($currentRaw); $p = viirs_pixel_rows($previousRaw); $out = [];
  foreach ($c as $k=>$row) {
    if (!isset($p[$k])) continue;
    $cur=(float)$row['value']; $prev=(float)$p[$k]['value']; $diff=$cur-$prev;
    $pct = abs($prev) < 1e-12 ? null : ($diff/abs($prev))*100.0;
    $out[] = ['lon'=>(float)$row['lon'],'lat'=>(float)$row['lat'],'bbox'=>$row['bbox'] ?? null,'current'=>$cur,'previous'=>$prev,'diff'=>$diff,'pct'=>$pct,'key'=>$k];
  }
  return $out;
}
function viirs_zone_has_input(array $raw): bool {
  foreach (['zones','zone_stats','zonal_stats','sectors','areas'] as $k) {
    if (isset($raw[$k]) && is_array($raw[$k]) && count($raw[$k]) > 0) return true;
  }
  return false;
}
function viirs_has_statistical_change_zones(array $raw): bool {
  $zones = [];
  foreach (['zones','zone_stats','zonal_stats','sectors','areas'] as $k) {
    if (isset($raw[$k]) && is_array($raw[$k])) { $zones=$raw[$k]; break; }
  }
  foreach ($zones as $z) {
    if (!is_array($z)) continue;
    $type=(string)($z['type'] ?? '');
    $degree=(string)($z['change_degree'] ?? '');
    if ($degree !== '' && (strpos($type,'истог степена промене') !== false || !empty($z['member_points']))) return true;
  }
  return false;
}
function viirs_haversine_m(float $lon1,float $lat1,float $lon2,float $lat2): float {
  $r=6371000.0; $p1=deg2rad($lat1); $p2=deg2rad($lat2); $dp=deg2rad($lat2-$lat1); $dl=deg2rad($lon2-$lon1);
  $a=sin($dp/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2;
  return 2*$r*asin(min(1.0,sqrt($a)));
}
function viirs_point_is_significant(array $p, array $config): int {
  $abs=(float)($p['diff'] ?? 0.0); $pct=$p['pct'] ?? null;
  $tp=(float)($config['change_threshold_pct'] ?? 5.0);
  $ta=(float)($config['change_threshold_abs'] ?? 0.05);
  if ($pct === null) return 0;
  if ($abs >= $ta && (float)$pct >= $tp) return 1;
  if ($abs <= -$ta && (float)$pct <= -$tp) return -1;
  return 0;
}
function viirs_change_degree(array $p, array $config): array {
  $pct=abs((float)($p['pct'] ?? 0.0));
  $t1=(float)($config['change_degree_moderate_pct'] ?? 15.0);
  $t2=(float)($config['change_degree_strong_pct'] ?? 30.0);
  if ($pct < $t1) return ['key'=>'mild','label'=>'блага','range'=>'5–15%','rank'=>1];
  if ($pct < $t2) return ['key'=>'moderate','label'=>'умерена','range'=>'15–30%','rank'=>2];
  return ['key'=>'strong','label'=>'изражена','range'=>'≥30%','rank'=>3];
}
function viirs_dbscan_clusters(array $points,float $epsM,int $minPts): array {
  $n=count($points); if ($n===0) return [];
  $visited=array_fill(0,$n,false); $assigned=array_fill(0,$n,-1); $clusters=[];
  $neighbors=function(int $i) use ($points,$epsM,$n): array {
    $out=[]; for($j=0;$j<$n;$j++) if (viirs_haversine_m((float)$points[$i]['lon'],(float)$points[$i]['lat'],(float)$points[$j]['lon'],(float)$points[$j]['lat']) <= $epsM) $out[]=$j;
    return $out;
  };
  for($i=0;$i<$n;$i++) {
    if ($visited[$i]) continue;
    $visited[$i]=true; $seed=$neighbors($i);
    if (count($seed)<$minPts) { $assigned[$i]=-2; continue; }
    $cid=count($clusters); $clusters[$cid]=[]; $queue=$seed; $seen=[];
    while($queue) {
      $j=array_shift($queue); if(isset($seen[$j])) continue; $seen[$j]=true;
      if(!$visited[$j]) {
        $visited[$j]=true; $n2=$neighbors($j);
        if(count($n2)>=$minPts) foreach($n2 as $k) if(!isset($seen[$k])) $queue[]=$k;
      }
      if($assigned[$j]<0) { $assigned[$j]=$cid; $clusters[$cid][]=$points[$j]; }
    }
  }
  return array_values(array_filter($clusters,fn($c)=>count($c)>=$minPts));
}
function viirs_cluster_member_export(array $cluster): array {
  $out=[];
  foreach($cluster as $p){
    $out[]=[
      'lon'=>(float)($p['lon'] ?? 0.0),
      'lat'=>(float)($p['lat'] ?? 0.0),
      'bbox'=>is_array($p['bbox'] ?? null) ? $p['bbox'] : null,
      'current'=>(float)($p['current'] ?? 0.0),
      'previous'=>(float)($p['previous'] ?? 0.0),
      'diff'=>(float)($p['diff'] ?? 0.0),
      'pct'=>isset($p['pct']) && $p['pct'] !== null ? (float)$p['pct'] : null,
      'change_degree'=>(string)($p['change_degree'] ?? ''),
      'change_degree_label'=>(string)($p['change_degree_label'] ?? ''),
    ];
  }
  return $out;
}
function viirs_change_cluster_summary(array $cluster,int $sign,int $totalSignificant,int $rank,float $epsM,array $degree): array {
  $n=count($cluster); $cs=0.0;$ps=0.0;$sumDiff=0.0;$sumPct=0.0;$pctN=0;$w=0.0;$wLon=0.0;$wLat=0.0;$maxAbs=0.0;
  $lons=[];$lats=[];
  foreach($cluster as $p){
    $cur=(float)$p['current'];$prev=(float)$p['previous'];$d=(float)$p['diff'];$aw=max(abs($d),1e-9);
    $cs+=$cur;$ps+=$prev;$sumDiff+=$d;$maxAbs=max($maxAbs,abs($d));$w+=$aw;$wLon+=(float)$p['lon']*$aw;$wLat+=(float)$p['lat']*$aw;$lons[]=(float)$p['lon'];$lats[]=(float)$p['lat'];
    if($p['pct']!==null){$sumPct+=(float)$p['pct'];$pctN++;}
  }
  $centLon=$w>0?$wLon/$w:array_sum($lons)/$n; $centLat=$w>0?$wLat/$w:array_sum($lats)/$n;
  $share=$totalSignificant>0?($n/$totalSignificant*100.0):0.0;
  $kind=$sign>0?'пораста':'смањења'; $kindInstr=$sign>0?'порастом':'смањењем';
  $degLabel=(string)($degree['label'] ?? ''); $degRange=(string)($degree['range'] ?? '');
  $note='Просторно повезана зона од '.$n.' суседних VIIRS тачака истог степена промене ('.$degLabel.', '.$degRange.'). '.number_format($share,1,'.','').'% свих значајно промењених тачака истог смера. Центар: '.number_format($centLat,5,'.','').', '.number_format($centLon,5,'.','').'. Суседство: до '.number_format($epsM,0,'.','').' m.';
  return [
    'name'=>'Зона '.$kind.' — '.$degLabel.' '.$rank,
    'type'=>'статистички кластер истог степена промене · VIIRS ~500 m',
    'current_mean'=>$cs/$n,'previous_mean'=>$ps/$n,
    'point_count'=>$n,'share_pct'=>$share,
    'centroid_lon'=>$centLon,'centroid_lat'=>$centLat,
    'bbox'=>[min($lons),min($lats),max($lons),max($lats)],
    'mean_pixel_diff'=>$sumDiff/$n,'mean_pixel_pct'=>$pctN?$sumPct/$pctN:null,'max_abs_pixel_diff'=>$maxAbs,
    'cluster_radius_m'=>$epsM,'change_direction'=>$sign>0?'increase':'decrease',
    'change_degree'=>(string)($degree['key'] ?? ''),'change_degree_label'=>$degLabel,'change_degree_range'=>$degRange,
    'member_points'=>viirs_cluster_member_export($cluster),
    'note'=>$note
  ];
}
function derive_viirs_change_clusters(array $currentRaw,array $previousRaw,array $config): array {
  $pairs=viirs_common_pixel_pairs($currentRaw,$previousRaw); if(count($pairs)<3) return [];
  $eps=(float)($config['change_neighbor_radius_m'] ?? 650.0);
  $minPts=(int)($config['change_cluster_min_points'] ?? 2); if($minPts<2)$minPts=2;
  $groups=[]; $totals=[1=>0,-1=>0];
  foreach($pairs as $p){
    $sgn=viirs_point_is_significant($p,$config); if(!$sgn) continue;
    $degree=viirs_change_degree($p,$config);
    $p['change_degree']=$degree['key']; $p['change_degree_label']=$degree['label']; $p['change_degree_range']=$degree['range'];
    $gk=($sgn>0?'up':'down').'|'.$degree['key'];
    if(!isset($groups[$gk])) $groups[$gk]=['sign'=>$sgn,'degree'=>$degree,'points'=>[]];
    $groups[$gk]['points'][]=$p; $totals[$sgn]++;
  }
  $out=[];
  foreach($groups as $g){
    $sign=(int)$g['sign']; $degree=$g['degree']; $pts=$g['points'];
    $clusters=viirs_dbscan_clusters($pts,$eps,$minPts);
    usort($clusters,function($a,$b){
      $na=count($a);$nb=count($b);if($na!==$nb)return $nb<=>$na;
      $sa=array_sum(array_map(fn($p)=>abs((float)$p['diff']),$a));$sb=array_sum(array_map(fn($p)=>abs((float)$p['diff']),$b));return $sb<=>$sa;
    });
    $rank=1;foreach($clusters as $c)$out[]=viirs_change_cluster_summary($c,$sign,$totals[$sign]??count($pts),$rank++,$eps,$degree);
  }
  usort($out,function($a,$b){
    $da=['strong'=>3,'moderate'=>2,'mild'=>1];
    $ra=$da[$a['change_degree']??'']??0; $rb=$da[$b['change_degree']??'']??0;
    if($ra!==$rb)return $rb<=>$ra;
    $na=(int)($a['point_count']??0);$nb=(int)($b['point_count']??0);if($na!==$nb)return $nb<=>$na;
    return abs((float)($b['mean_pixel_diff']??0))<=>abs((float)($a['mean_pixel_diff']??0));
  });
  return $out;
}
function derive_viirs_zones(array $currentRaw,array $previousRaw,array $config): array {
  return derive_viirs_change_clusters($currentRaw,$previousRaw,$config);
}

function normalize_input(array $in, array $config): array {
  $period = $in['period'] ?? $in['month'] ?? null;
  if (!$period && isset($in['year'], $in['month_num'])) $period = sprintf('%04d-%02d', (int)$in['year'], (int)$in['month_num']);
  if (!$period && isset($in['date']) && preg_match('/^(\d{4}-\d{2})/', (string)$in['date'], $m)) $period = $m[1];
  if (!$period) $period = date('Y-m', strtotime('first day of previous month'));
  $period = preg_replace('/[^0-9\-]/', '', (string)$period);
  if (!preg_match('/^\d{4}-\d{2}$/', $period)) fail('Улазни JSON нема исправан period формата YYYY-MM.');
  $prev_period = get_first_str($in, ['previous_period','prev_period','compare_period']) ?? previous_period($period);
  $label = $in['period_label'] ?? month_label($period);
  $prev_label = $in['previous_period_label'] ?? month_label($prev_period);
  $mean = get_first_num($in, ['current_mean_radiance','mean_radiance','mean','radiance_mean']);
  $median = get_first_num($in, ['current_median_radiance','median_radiance','median']);
  $min = get_first_num($in, ['current_min_radiance','min_radiance','min']);
  $max = get_first_num($in, ['current_max_radiance','max_radiance','max']);
  $prev_mean = get_first_num($in, ['previous_mean_radiance','prev_mean_radiance','previous_mean','prev_mean']);
  $prev_median = get_first_num($in, ['previous_median_radiance','prev_median_radiance','previous_median','prev_median']);
  $prev_min = get_first_num($in, ['previous_min_radiance','prev_min_radiance']);
  $prev_max = get_first_num($in, ['previous_max_radiance','prev_max_radiance']);
  $abs_change = get_first_num($in, ['abs_change','difference','diff_radiance']);
  if ($abs_change === null && $mean !== null && $prev_mean !== null) $abs_change = $mean - $prev_mean;
  $change = get_first_num($in, ['change_pct','change_percent','trend_pct']);
  if ($change === null) $change = pct_change($mean, $prev_mean);
  $trend = $in['trend'] ?? trend_label($abs_change, $change, $config);
  $zones = normalize_zones($in, $config);
  $inc = array_values(array_filter($zones, fn($z) => ($z['trend'] ?? '') === 'пораст радијансе'));
  $dec = array_values(array_filter($zones, fn($z) => ($z['trend'] ?? '') === 'смањење радијансе'));
  $stable = array_values(array_filter($zones, fn($z) => ($z['trend'] ?? '') === 'стабилно'));
  $summary = $in['summary'] ?? $in['overall_text'] ?? null;
  if (!$summary) {
    if ($change === null) {
      $summary = 'Месечни извештај је припремљен на основу доступне VIIRS карте ноћне радијансе. За поређење је потребан и претходни месец.';
    } elseif ($change > 5) {
      $summary = 'У односу на претходни месец бележи се пораст средње ноћне радијансе, што захтева проверу извора осветљења у зонама са највећим растом.';
    } elseif ($change < -5) {
      $summary = 'У односу на претходни месец бележи се смањење средње ноћне радијансе, што је повољан сигнал за очување тамног неба.';
    } else {
      $summary = 'Средња ноћна радијанса је стабилна у односу на претходни месец, уз могуће локалне разлике по зонама.';
    }
  }
  $title = $in['title'] ?? ('Извештај о светлосном загађењу — ' . $label);
  return [
    'period'=>$period,
    'period_label'=>$label,
    'previous_period'=>$prev_period,
    'previous_period_label'=>$prev_label,
    'title'=>$title,
    'summary'=>$summary,
    'map_url'=>$in['map_url'] ?? $config['public_map_url'],
    'image_url'=>$in['image_url'] ?? $in['map_image'] ?? $in['current_image_url'] ?? ($config['public_base_url'] . '/assets/alan-map-placeholder.webp'),
    'previous_image_url'=>$in['previous_image_url'] ?? $in['prev_image_url'] ?? null,
    'difference_image_url'=>$in['difference_image_url'] ?? $in['diff_image_url'] ?? null,
    'mean_radiance'=>$mean,
    'median_radiance'=>$median,
    'min_radiance'=>$min,
    'max_radiance'=>$max,
    'previous_mean_radiance'=>$prev_mean,
    'previous_median_radiance'=>$prev_median,
    'previous_min_radiance'=>$prev_min,
    'previous_max_radiance'=>$prev_max,
    'abs_change'=>$abs_change,
    'change_pct'=>$change,
    'trend'=>(string)$trend,
    'zones'=>$zones,
    'increased_zones'=>$inc,
    'decreased_zones'=>$dec,
    'stable_zones'=>$stable,
    'p90_radiance'=>get_first_num($in, ['p90_radiance','p90','percentile90']),
    'pixel_count'=>get_first_num($in, ['pixel_count','count','valid_pixels']),
    'data_quality_warnings'=>is_array($in['data_quality_warnings'] ?? null) ? array_values($in['data_quality_warnings']) : [],
    'source'=>$in['source'] ?? 'VIIRS DNB monthly composite',
    'generated_at'=>date('c')
  ];
}
function merge_previous_report_data(array $r, string $root, array $config): array {
  $prev = $r['previous_period'] ?? '';
  if (!$prev) return $r;
  $prev_report = read_json_file($root . '/reports/' . $prev . '/report.json');
  if (!$prev_report) return $r;
  if ($r['previous_mean_radiance'] === null && isset($prev_report['mean_radiance'])) $r['previous_mean_radiance'] = norm_num($prev_report['mean_radiance']);
  if ($r['previous_median_radiance'] === null && isset($prev_report['median_radiance'])) $r['previous_median_radiance'] = norm_num($prev_report['median_radiance']);
  if (!$r['previous_image_url'] && isset($prev_report['image_url'])) $r['previous_image_url'] = $prev_report['image_url'];
  if ($r['abs_change'] === null && $r['mean_radiance'] !== null && $r['previous_mean_radiance'] !== null) $r['abs_change'] = $r['mean_radiance'] - $r['previous_mean_radiance'];
  if ($r['change_pct'] === null) $r['change_pct'] = pct_change($r['mean_radiance'], $r['previous_mean_radiance']);
  $r['trend'] = trend_label($r['abs_change'], $r['change_pct'], $config);
  // Ако текући JSON има зоне без претходне вредности, покушај да их упоредиш са зонама из претходног report.json.
  if (!empty($r['zones']) && !empty($prev_report['zones']) && is_array($prev_report['zones'])) {
    $prev_by_name = [];
    foreach ($prev_report['zones'] as $pz) {
      if (is_array($pz) && isset($pz['name'])) $prev_by_name[lower_utf8((string)$pz['name'])] = $pz;
    }
    foreach ($r['zones'] as &$z) {
      if (($z['previous_mean'] ?? null) === null) {
        $key = lower_utf8((string)$z['name']);
        if (isset($prev_by_name[$key])) {
          $pz = $prev_by_name[$key];
          $pm = get_first_num($pz, ['current_mean','mean_radiance','mean','current_mean_radiance']);
          if ($pm !== null) $z['previous_mean'] = $pm;
        }
      }
      if (($z['abs_diff'] ?? null) === null && $z['current_mean'] !== null && $z['previous_mean'] !== null) $z['abs_diff'] = $z['current_mean'] - $z['previous_mean'];
      if (($z['pct_diff'] ?? null) === null) $z['pct_diff'] = pct_change($z['current_mean'], $z['previous_mean']);
      $z['trend'] = trend_label($z['abs_diff'], $z['pct_diff'], $config);
      $z['severity'] = severity_label($z['abs_diff'], $z['pct_diff']);
    }
    unset($z);
    $r['increased_zones'] = array_values(array_filter($r['zones'], fn($z) => ($z['trend'] ?? '') === 'пораст радијансе'));
    $r['decreased_zones'] = array_values(array_filter($r['zones'], fn($z) => ($z['trend'] ?? '') === 'смањење радијансе'));
    $r['stable_zones'] = array_values(array_filter($r['zones'], fn($z) => ($z['trend'] ?? '') === 'стабилно'));
  }
  return $r;
}
function zone_rows_html(array $zones, string $empty): string {
  if (!$zones) return '<p class="muted">' . e($empty) . '</p>';
  $out = '<div class="zone-table">';
  $out .= '<div class="zone-head"><span>Зона промене</span><span>Тачке</span><span>Удео</span><span>Радијанса</span><span>Промена</span></div>';
  foreach ($zones as $z) {
    $cls = ($z['trend'] === 'пораст радијансе') ? 'up' : (($z['trend'] === 'смањење радијансе') ? 'down' : 'flat');
    $degreeText = trim((string)($z['change_degree_label'] ?? ''));
    $type = $z['type'] ? '<small>' . e($z['type']) . ($degreeText!=='' ? ' · степен: '.e($degreeText) : '') . '</small>' : ($degreeText!=='' ? '<small>степен: '.e($degreeText).'</small>' : '');
    $n=(int)($z['point_count'] ?? 0);
    $share=($z['share_pct'] ?? null)===null?'—':fmt_num($z['share_pct'],1).'%';
    $diff = fmt_num($z['abs_diff'], 3);
    $pct = $z['pct_diff'] === null ? '—' : fmt_num($z['pct_diff'], 1) . '%';
    $rad=fmt_num($z['current_mean'],3).'<small>претходно: '.fmt_num($z['previous_mean'],3).'</small>';
    $note = $z['note'] ? '<small>' . e($z['note']) . '</small>' : '';
    $out .= '<div class="zone-row ' . $cls . '"><span><b>' . e($z['name']) . '</b>' . $type . $note . '</span><span><b>'.$n.'</b></span><span><b>'.$share.'</b></span><span>'.$rad.'</span><span><b>'.$diff.'</b><small>'.$pct.'</small></span></div>';
  }
  $out .= '</div>';
  return $out;
}

function monthly_radiance_map_period_html(string $period,string $label,array $config,string $headingPrefix='Карта ноћне радијансе'): string {
  $periodEsc=e($period); $labelEsc=e($label); $heading=e($headingPrefix);
  $base=e($config['remote_results_base_url'] ?? 'https://raw.githubusercontent.com/suvoborskirajac/rajac-light-data/main/public/results');
  $boundary=e($config['boundary_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/pio-rajac.geojson');
  $id='rlm-monthly-' . preg_replace('/[^0-9]/','',$period) . '-' . substr(md5($headingPrefix),0,6);
  return <<<HTML
<section class="annual-map-only monthly-map-only" aria-label="{$heading} за {$labelEsc}">
  <h2>{$heading} — {$labelEsc}</h2>
  <p class="lead small-lead">Приказана је стварна месечна карта радијансе из исте JSON базе која се користи за јавну карту светлосног загађења.</p>
  <section class="rlm-wrap annual-embed annual-embed-maponly monthly-embed-maponly" data-base-url="{$base}" data-boundary-url="{$boundary}" data-height="340" data-default-period="{$periodEsc}" id="{$id}">
    <div class="rlm-panel">
      <div aria-label="Контроле карте" class="rlm-toolbar annual-hidden-toolbar">
        <button class="rlm-btn is-active" data-toggle-boundary="1" type="button">Граница ПИО</button>
        <label class="rlm-select-label"><span>Период</span><select aria-label="Избор периода" class="rlm-period"><option value="{$periodEsc}" selected>{$labelEsc}</option></select></label>
      </div>
      <div class="rlm-map-shell" style="--rlm-height:340px"><div class="rlm-status" role="status">Учитавање месечне карте радијансе…</div><svg aria-label="Карта ноћне радијансе ПИО Рајац" class="rlm-svg" preserveAspectRatio="xMidYMid meet" role="img"></svg></div>
      <div aria-label="Легенда" class="rlm-legend"><div class="rlm-grad"></div><div class="rlm-legend-scale"><span>ниско</span><span>умерено</span><span>повишено</span><span>високо</span></div></div>
    </div>
  </section>
</section>
HTML;
}
function monthly_radiance_map_html(array $r, array $config): string {
  return monthly_radiance_map_period_html((string)$r['period'],(string)$r['period_label'],$config,'Карта ноћне радијансе');
}
function previous_radiance_map_html(array $r, array $config): string {
  $period=(string)($r['previous_period'] ?? '');
  if(!preg_match('/^\\d{4}-\\d{2}$/',$period)) return '';
  return monthly_radiance_map_period_html($period,(string)($r['previous_period_label'] ?? $period),$config,'Претходни месец — карта ноћне радијансе');
}

function monthly_radiance_map_assets_html(array $config, string $period): string {
  $css=e($config['light_monitor_css_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/rajac-light-monitor.css?ver=0.5.2');
  $js=e($config['light_monitor_js_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/rajac-light-monitor.js?ver=0.5.2');
  $base=e($config['remote_results_base_url'] ?? 'https://raw.githubusercontent.com/suvoborskirajac/rajac-light-data/main/public/results');
  $boundary=e($config['boundary_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/pio-rajac.geojson');
  $target=e($period);
  return <<<HTML
<link rel="preload" as="style" href="{$css}"><link rel="stylesheet" href="{$css}">
<script>window.RajacLightMonitorCfg={defaultBaseUrl:"{$base}",localBoundaryUrl:"{$boundary}",pluginVersion:"0.5.2"};</script>
<script src="{$js}"></script>
<script>(function(){var target="{$target}";function setPeriod(){document.querySelectorAll('.monthly-embed-maponly,.annual-embed-maponly').forEach(function(w){var p=w.getAttribute('data-default-period')||target;var s=w.querySelector('select.rlm-period');if(!s)return;var has=false;for(var i=0;i<s.options.length;i++){if(s.options[i].value===p){has=true;break;}}if(!has){var o=document.createElement('option');o.value=p;o.textContent=p;s.appendChild(o);}if(s.value!==p){s.value=p;s.dispatchEvent(new Event('change',{bubbles:true}));}});}window.addEventListener('load',function(){var n=0;setPeriod();var timer=setInterval(function(){setPeriod();if(++n>24)clearInterval(timer);},450);});})();</script>
HTML;
}


function zones_change_map_html(array $r, array $config): string {
  $period=(string)($r['period'] ?? '');
  $previous=(string)($r['previous_period'] ?? '');
  if(!preg_match('/^\\d{4}-\\d{2}$/',$period) || !preg_match('/^\\d{4}-\\d{2}$/',$previous)) {
    return '<section class="panel"><h2>Карта промене ноћне радијансе</h2><p class="muted">Нема довољно података за просторни приказ промене.</p></section>';
  }
  $base = $config['remote_results_base_url'] ?? 'https://raw.githubusercontent.com/suvoborskirajac/rajac-light-data/main/public/results';
  $boundary = $config['boundary_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/pio-rajac.geojson';
  $baseJson=json_encode((string)$base,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  $boundaryJson=json_encode((string)$boundary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  $periodJson=json_encode($period,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $previousJson=json_encode($previous,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $periodLabel=e((string)($r['period_label'] ?? $period));
  $previousLabel=e((string)($r['previous_period_label'] ?? $previous));
  $mapId='change-heatmap-'.preg_replace('/[^0-9]/','',$period);
  return <<<HTML
<section class="panel change-heatmap-panel"><h2>Карта промене ноћне радијансе — {$periodLabel} према {$previousLabel}</h2>
<p class="lead">Ова карта није карта кластера нити скуп кругова. За сваки VIIRS пиксел директно се рачуна <strong>промена радијансе између два месеца</strong>, а суседни пиксели истог смера и сличног степена промене визуелно се спајају у непрекидне зоне. Просторни обухват ПИО „Рајац” остаје у истој размери и облику као на месечним картама.</p>
<style>
.change-heatmap-panel{width:100%;box-sizing:border-box}
.change-map-wrap{width:100%;box-sizing:border-box;background:#fffdf8;border:1px solid #eadfc8;border-radius:20px;padding:18px 18px 14px}
.change-map-svg{display:block;width:100%;height:auto;aspect-ratio:1000/340;background:#06281e;border-radius:4px}
.change-map-status{padding:12px 0;color:#405746;font:600 14px/1.4 system-ui}
.change-map-legend{margin:14px 2% 0}
.change-map-grad{height:14px;border-radius:999px;background:linear-gradient(90deg,#245c36 0%,#6f9744 25%,#c9df58 49%,#e7df59 58%,#efb34f 78%,#ef7057 100%);box-shadow:inset 0 0 0 1px rgba(0,0,0,.08)}
.change-map-scale{display:flex;justify-content:space-between;gap:10px;margin-top:8px;font:13px/1.2 system-ui;color:#526259}
.change-map-note{margin-top:12px}
@media(max-width:700px){.change-map-wrap{padding:10px}.change-map-scale{font-size:11px}}
</style>
<div class="change-map-wrap"><div id="{$mapId}-status" class="change-map-status">Учитавање карте промене…</div><svg id="{$mapId}" class="change-map-svg" viewBox="0 0 1000 340" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Карта промене ноћне радијансе ПИО Рајац"></svg><div class="change-map-legend"><div class="change-map-grad"></div><div class="change-map-scale"><span>јаче смањење</span><span>смањење</span><span>стабилно</span><span>повећање</span><span>јаче повећање</span></div></div><p class="note change-map-note">Боје приказују разлику <strong>{$periodLabel} − {$previousLabel}</strong>. Тамније зелене означавају смањење радијансе, жуто‑зелена стабилне делове, а жута, наранџаста и црвена повећање. Приказ је исечен тачно по оригиналној граници ПИО.</p></div>
<script>(function(){
  var currentPeriod={$periodJson}, previousPeriod={$previousJson}, baseUrl={$baseJson}, boundaryUrl={$boundaryJson};
  var svg=document.getElementById('{$mapId}'), status=document.getElementById('{$mapId}-status');
  if(!svg)return;
  var W=1000,H=340,PX=58,PY=24;
  function pixelArray(raw){if(raw&&Array.isArray(raw.pixels))return raw.pixels;if(raw&&raw.data&&Array.isArray(raw.data.pixels))return raw.data.pixels;return []}
  function key(p){return Number(p.lon).toFixed(6)+'|'+Number(p.lat).toFixed(6)}
  function getVal(p){var v=p&&(p.value!=null?p.value:(p.radiance!=null?p.radiance:p.avg_rad));return v==null?null:Number(v)}
  function getBBox(p){if(p&&Array.isArray(p.bbox)&&p.bbox.length===4)return p.bbox.map(Number);var lon=Number(p.lon),lat=Number(p.lat);return[lon-.00313,lat-.002246,lon+.00313,lat+.002246]}
  function ringsFromGeoJSON(gj){var out=[];function add(c){if(!Array.isArray(c))return;if(Array.isArray(c[0])&&typeof c[0][0]==='number'){out.push(c);return}c.forEach(add)}if(!gj)return out;if(gj.type==='FeatureCollection'&&Array.isArray(gj.features))gj.features.forEach(function(f){if(f&&f.geometry)add(f.geometry.coordinates)});else if(gj.type==='Feature'&&gj.geometry)add(gj.geometry.coordinates);else if(gj.coordinates)add(gj.coordinates);return out}
  function boundaryBBox(rings){var b=[Infinity,Infinity,-Infinity,-Infinity];rings.forEach(function(r){r.forEach(function(c){var x=Number(c[0]),y=Number(c[1]);if(!isFinite(x)||!isFinite(y))return;b[0]=Math.min(b[0],x);b[1]=Math.min(b[1],y);b[2]=Math.max(b[2],x);b[3]=Math.max(b[3],y)})});return isFinite(b[0])?b:null}
  function projector(b){var mid=(b[1]+b[3])/2*Math.PI/180,cos=Math.cos(mid),ww=(b[2]-b[0])*cos,wh=b[3]-b[1],sx=(W-2*PX)/(ww||1),sy=(H-2*PY)/(wh||1),s=Math.min(sx,sy),cw=ww*s,ch=wh*s,ox=(W-cw)/2,oy=(H-ch)/2;return function(lon,lat){return[ox+(lon-b[0])*cos*s,oy+(b[3]-lat)*s]}}
  function pathFor(rings,pr){return rings.map(function(r){return r.map(function(c,i){var p=pr(Number(c[0]),Number(c[1]));return(i?'L':'M')+p[0].toFixed(2)+' '+p[1].toFixed(2)}).join(' ')+' Z'}).join(' ')}
  function classify(diff,pct){var ap=Math.abs(pct==null?0:pct),ad=Math.abs(diff);if(pct==null||ad<.05||ap<5)return{key:'stable',color:'#c9df58'};if(diff<0){if(ap>=30)return{key:'down-strong',color:'#245c36'};if(ap>=15)return{key:'down-mid',color:'#507c3f'};return{key:'down-mild',color:'#7fa24a'}}if(ap>=30)return{key:'up-strong',color:'#ef7057'};if(ap>=15)return{key:'up-mid',color:'#efb34f'};return{key:'up-mild',color:'#e7df59'}}
  function render(current,previous,boundary){var rs=ringsFromGeoJSON(boundary),bb=boundaryBBox(rs);if(!rs.length||!bb){status.textContent='Граница ПИО није доступна.';return}var pr=projector(bb),boundaryPath=pathFor(rs,pr),pm={};pixelArray(previous).forEach(function(p){pm[key(p)]=p});var cells=[],counts={up:0,down:0,stable:0},sumDiff=0,n=0;
    pixelArray(current).forEach(function(c){var p=pm[key(c)];if(!p)return;var cv=getVal(c),pv=getVal(p);if(cv==null||pv==null)return;var diff=cv-pv,pct=Math.abs(pv)<1e-12?null:diff/Math.abs(pv)*100,cl=classify(diff,pct),b=getBBox(c),a=pr(b[0],b[3]),z=pr(b[2],b[1]),x=Math.min(a[0],z[0]),y=Math.min(a[1],z[1]),w=Math.abs(z[0]-a[0]),h=Math.abs(z[1]-a[1]);cells.push({x:x,y:y,w:w,h:h,color:cl.color,kind:cl.key,diff:diff,pct:pct});if(cl.key.indexOf('up-')===0)counts.up++;else if(cl.key.indexOf('down-')===0)counts.down++;else counts.stable++;sumDiff+=diff;n++});
    var defs='<defs><clipPath id="{$mapId}-clip"><path d="'+boundaryPath+'" fill-rule="evenodd"/></clipPath><filter id="{$mapId}-soft" x="-10%" y="-10%" width="120%" height="120%"><feGaussianBlur stdDeviation="8"/></filter></defs>';
    var out=defs+'<rect x="0" y="0" width="'+W+'" height="'+H+'" fill="#06281e"/>'+'<path d="'+boundaryPath+'" fill="#c9df58" fill-rule="evenodd"/>';
    out+='<g clip-path="url(#{$mapId}-clip)" filter="url(#{$mapId}-soft)">';cells.forEach(function(c){out+='<rect x="'+(c.x-3).toFixed(1)+'" y="'+(c.y-3).toFixed(1)+'" width="'+(c.w+6).toFixed(1)+'" height="'+(c.h+6).toFixed(1)+'" rx="10" ry="10" fill="'+c.color+'" fill-opacity=".96"/>'});out+='</g>';
    out+='<path d="'+boundaryPath+'" fill="none" stroke="#ffffff" stroke-width="2.4" stroke-linejoin="round" fill-rule="evenodd"/>';svg.innerHTML=out;
    var upPct=n?counts.up/n*100:0,downPct=n?counts.down/n*100:0,stPct=n?counts.stable/n*100:0,mean=n?sumDiff/n:0;status.innerHTML='<strong>Пикселско поређење:</strong> повећање '+counts.up+' ('+upPct.toFixed(1)+'%), смањење '+counts.down+' ('+downPct.toFixed(1)+'%), стабилно '+counts.stable+' ('+stPct.toFixed(1)+'%). Средња промена упарених пиксела: '+(mean>=0?'+':'')+mean.toFixed(3)+' nW/cm²/sr.';
  }
  Promise.all([
    fetch(baseUrl.replace(/\\/$/,'')+'/'+currentPeriod+'.json').then(function(r){if(!r.ok)throw new Error('current');return r.json()}),
    fetch(baseUrl.replace(/\\/$/,'')+'/'+previousPeriod+'.json').then(function(r){if(!r.ok)throw new Error('previous');return r.json()}),
    fetch(boundaryUrl,{credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('boundary');return r.json()})
  ]).then(function(x){render(x[0],x[1],x[2])}).catch(function(){status.textContent='Карта промене тренутно не може да учита један од извора података.'});
})();</script></section>
HTML;
}

function report_html(array $r, array $config): string {
  $period = e($r['period']); $title=e($r['title']); $label=e($r['period_label']); $prevLabel=e($r['previous_period_label']);
  $summary=e($r['summary']); $map=e($r['map_url']); $source=e($r['source']);
  $mapEmbed = monthly_radiance_map_html($r, $config);
  $prevMapEmbed = previous_radiance_map_html($r, $config);
  $mapAssets = monthly_radiance_map_assets_html($config, (string)$r['period']);
  $mean=fmt_num($r['mean_radiance']); $prevMean=fmt_num($r['previous_mean_radiance']);
  $median=fmt_num($r['median_radiance']); $min=fmt_num($r['min_radiance']); $max=fmt_num($r['max_radiance']);
  $abs=fmt_num($r['abs_change'], 3); $change=($r['change_pct'] === null ? '—' : fmt_num($r['change_pct'], 1)); $trend=e($r['trend']);
  $incCount=count($r['increased_zones']); $decCount=count($r['decreased_zones']);
  $clusterPoints=0; foreach(($r['zones'] ?? []) as $zz) $clusterPoints+=(int)($zz['point_count'] ?? 0);
  $clusterRadius=(int)($config['change_neighbor_radius_m'] ?? 650);
  $increaseRows = zone_rows_html($r['increased_zones'], 'Није издвојена просторна концентрација најмање две суседне VIIRS тачке са значајним порастом.');
  $decreaseRows = zone_rows_html($r['decreased_zones'], 'Није издвојена просторна концентрација најмање две суседне VIIRS тачке са значајним смањењем.');
  $allRows = zone_rows_html($r['zones'], 'Нису издвојени статистички кластери значајне промене. Појединачне изоловане тачке се намерно не проглашавају зоном.');
  $zoneMethod = '<p class="note"><strong>Како се одређују зоне промене:</strong> за сваки VIIRS пиксел упарује се исти положај у текућем и претходном месецу. Значајне тачке се затим деле по смеру и степену промене: <strong>блага 5–15%</strong>, <strong>умерена 15–30%</strong> и <strong>изражена ≥30%</strong>. Зона настаје само спајањем суседних тачака <strong>истог смера и истог степена</strong>, на међусобном растојању до '.e((string)$clusterRadius).' m. Зато контуре прате распоред промењених VIIRS пиксела и не попуњавају простор између неповезаних тачака.</p>';
  $generated=e($r['generated_at']);
  $zonesMap = zones_change_map_html($r, $config);
  return <<<HTML
<!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title} — ПИО Рајац</title><meta name="description" content="{$summary}"><link rel="stylesheet" href="../../assets/alan-report.css">{$mapAssets}</head><body>
<header class="top"><a class="brand" href="https://piorajac.rs/"><span class="mark">△</span><span>ПИО РАЈАЦ</span></a><nav class="nav"><a href="https://piorajac.rs/monitoring-izvestaji/">Мониторинг извештаји</a><a class="active" href="https://piorajac.rs/monitoring-izvestaji/svetlosno-zagadjenje/">Светлосно загађење</a><a href="https://piorajac.rs/monitoring-svetlosno-zagadjenje/">Јавна карта</a></nav></header>
<section class="hero"><div class="wrap"><div class="kicker">Светлосно загађење · {$label}</div><h1>{$title}</h1><p>{$summary}</p><div class="btns"><a class="btn green" href="{$map}">Отвори јавну карту</a><a class="btn ghost" href="../../">Архива извештаја</a></div></div></section>
<main class="wrap">
<article class="panel report-layout"><div class="report-text"><h2>Кратак преглед</h2><p>{$summary}</p><p>Овај извештај користи месечни композит ноћне радијансе као индикатор притиска вештачког осветљења на тамно небо, ноћне екосистеме и визуелни квалитет предела ПИО „Рајац”.</p><div class="meta"><div class="metric"><small>{$label}</small><b>{$mean}</b></div><div class="metric"><small>{$prevLabel}</small><b>{$prevMean}</b></div><div class="metric"><small>Разлика</small><b>{$abs}</b></div><div class="metric"><small>Промена</small><b>{$change}%</b></div></div>{$mapEmbed}<div class="comparison-note"><p class="lead"><strong>Просторно поређење са претходним месецом:</strong> испод је приказана карта за {$prevLabel} у истој скали и из исте VIIRS базе, како би се промена могла визуелно упоредити пре карте издвојених зона.</p></div>{$prevMapEmbed}<h2>Тумачење</h2><p>Тренд: <strong>{$trend}</strong>. Позитивна разлика значи појачану ноћну радијансу, а негативна разлика значи смањење радијансе у односу на претходни месец.</p><p class="note">Извор: {$source}. Извештај је аутоматски генерисан: {$generated}.</p></div></article>
<section class="panel"><h2>Поређење са претходним месецом</h2><p class="lead">Поређење се ради по принципу: <strong>радијанса текућег месеца − радијанса претходног месеца</strong>. За {$label} се пореди са периодом {$prevLabel}. Резултат је приказан као апсолутна промена и као проценат у односу на претходни месец. Изнад су приказане карте текућег и претходног месеца у истој визуелној скали, а испод је директна карта пикселске промене радијансе.</p>{$zoneMethod}<div class="meta"><div class="metric"><small>Зоне пораста</small><b>{$incCount}</b></div><div class="metric"><small>Зоне смањења</small><b>{$decCount}</b></div><div class="metric"><small>Тачке у кластерима</small><b>{$clusterPoints}</b></div><div class="metric"><small>Радијус груписања</small><b>{$clusterRadius} m</b></div></div></section>
{$zonesMap}
<section class="panel zones"><h2>Зоне концентрације пораста радијансе</h2><p class="lead">Ово су статистички издвојени кластери суседних VIIRS тачака на којима се пораст радијансе просторнo концентрише. Већи број тачака и већи удео значе јачу просторну концентрацију сигнала.</p>{$increaseRows}</section>
<section class="panel zones"><h2>Зоне концентрације смањења радијансе</h2><p class="lead">Ово су статистички издвојени кластери суседних VIIRS тачака на којима се смањење радијансе просторнo концентрише. Сигнал треба тумачити заједно са атмосферским условима и сезонским факторима.</p>{$decreaseRows}</section>
<section class="panel zones"><h2>Све статистички издвојене зоне промене</h2>{$allRows}</section>
<section class="panel"><h2>Методолошка напомена</h2><p>Аутоматски закључак не треба тумачити као једини доказ промене извора осветљења. Месечни композити могу бити под утицајем облачности, снежног покривача, влажности ваздуха, аеросола, угла снимања и сезонске активности. Зато је најбољи поступак да се статистички кластер промене провери на карти, упореди са теренским познавањем простора и, када је могуће, са all-sky/SQM мерењима. Кластер показује просторну концентрацију промене у VIIRS мрежи, а не административну или управљачку зону.</p></section>
</main><footer class="footer"><div class="wrap"><b>ПИО РАЈАЦ</b><span>Мониторинг светлосног загађења · {$period}</span></div></footer></body></html>
HTML;
}
function rebuild_index(string $root, array $reports): void {
  file_put_contents($root . '/reports/index.html', '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=../"><title>Извештаји</title>');
}


function generate_report_from_input(array $input, string $root, array $config): array {
  $r = normalize_input($input, $config);
  $r = merge_previous_report_data($r, $root, $config);
  $period = $r['period'];
  $report_dir = $root . '/reports/' . $period;
  ensure_dir($report_dir);
  $report_url = rtrim($config['public_base_url'], '/') . '/reports/' . rawurlencode($period) . '/';
  $r['url'] = $report_url;
  $r['report_url'] = $report_url;
  $r['status'] = 'објављено';
  file_put_contents($report_dir . '/index.html', report_html($r, $config));
  write_json_file($report_dir . '/report.json', $r);
  $reports_file = $root . '/data/reports.json';
  $reports = read_json_file($reports_file) ?: ['reports'=>[]];
  $list = is_array($reports['reports'] ?? null) ? $reports['reports'] : [];
  $list = array_values(array_filter($list, fn($x) => ($x['period'] ?? '') !== $period));
  array_unshift($list, [
    'period'=>$period,
    'period_label'=>$r['period_label'],
    'previous_period'=>$r['previous_period'],
    'previous_period_label'=>$r['previous_period_label'],
    'title'=>$r['title'],
    'summary'=>$r['summary'],
    'url'=>$r['url'],
    'status'=>$r['status'],
    'mean_radiance'=>$r['mean_radiance'],
    'previous_mean_radiance'=>$r['previous_mean_radiance'],
    'abs_change'=>$r['abs_change'],
    'change_pct'=>$r['change_pct'],
    'trend'=>$r['trend'],
    'increased_zones_count'=>count($r['increased_zones']),
    'decreased_zones_count'=>count($r['decreased_zones']),
    'generated_at'=>$r['generated_at']
  ]);
  usort($list, fn($a,$b) => strcmp($b['period'] ?? '', $a['period'] ?? ''));
  write_json_file($reports_file, ['reports'=>$list, 'updated_at'=>date('c')]);
  write_json_file($root . '/latest.json', [
    'status'=>'published',
    'category'=>'Светлосно загађење',
    'period'=>$r['period'],
    'period_label'=>$r['period_label'],
    'previous_period'=>$r['previous_period'],
    'previous_period_label'=>$r['previous_period_label'],
    'date'=>$r['period'],
    'title'=>$r['title'],
    'summary'=>$r['summary'],
    'overall_text'=>$r['summary'],
    'report_url'=>$r['url'],
    'public_map_url'=>$r['map_url'],
    'image_url'=>$r['image_url'],
    'previous_image_url'=>$r['previous_image_url'],
    'difference_image_url'=>$r['difference_image_url'],
    'mean_radiance'=>$r['mean_radiance'],
    'previous_mean_radiance'=>$r['previous_mean_radiance'],
    'median_radiance'=>$r['median_radiance'],
    'abs_change'=>$r['abs_change'],
    'change_pct'=>$r['change_pct'],
    'trend'=>$r['trend'],
    'increased_zones'=>$r['increased_zones'],
    'decreased_zones'=>$r['decreased_zones'],
    'updated_at'=>date('c')
  ]);
  rebuild_index($root, $list);
  return $r;
}

function month_range_list(string $from, string $to): array {
  if (!preg_match('/^\\d{4}-\\d{2}$/', $from) || !preg_match('/^\\d{4}-\\d{2}$/', $to)) return [];
  $a = DateTime::createFromFormat('!Y-m-d', $from . '-01');
  $b = DateTime::createFromFormat('!Y-m-d', $to . '-01');
  if (!$a || !$b || $a > $b) return [];
  $out = [];
  while ($a <= $b) { $out[] = $a->format('Y-m'); $a->modify('+1 month'); }
  return $out;
}

function dig_num(array $a, array $paths): ?float {
  foreach ($paths as $path) {
    $cur = $a; $ok = true;
    foreach (explode('.', $path) as $p) {
      if (is_array($cur) && array_key_exists($p, $cur)) $cur = $cur[$p]; else { $ok=false; break; }
    }
    if ($ok) { $v = norm_num($cur); if ($v !== null) return $v; }
  }
  return null;
}
function dig_str(array $a, array $paths): ?string {
  foreach ($paths as $path) {
    $cur = $a; $ok = true;
    foreach (explode('.', $path) as $p) {
      if (is_array($cur) && array_key_exists($p, $cur)) $cur = $cur[$p]; else { $ok=false; break; }
    }
    if ($ok && $cur !== null && $cur !== '') return (string)$cur;
  }
  return null;
}
function radiance_data_quality_warnings(array $raw): array {
  $warnings = [];
  $mean = dig_num($raw, ['stats.overall.mean']);
  $sum = dig_num($raw, ['stats.overall.sum']);
  $count = dig_num($raw, ['stats.overall.count']);
  if ($mean !== null && $sum !== null && $count !== null && $count > 0) {
    $expected = $mean * $count;
    $tol = max(0.0001, max(abs($expected), abs($sum)) * 0.005);
    if (abs($expected - $sum) > $tol) {
      $warnings[] = 'stats.overall није интерно доследан: mean × count се не слаже са sum.';
    }
  }
  if (isset($raw['pixels']) && is_array($raw['pixels'])) {
    $pixelStats = radiance_stats_from_values(collect_candidate_values(['pixels'=>$raw['pixels']]));
    if ($pixelStats && $count !== null && (int)round($count) !== (int)$pixelStats['count']) {
      $warnings[] = 'stats.overall.count се не слаже са бројем вредности у pixels.';
    } elseif ($pixelStats && $mean !== null) {
      $tol = max(0.0001, abs($mean) * 0.005);
      if (abs($mean - (float)$pixelStats['mean']) > $tol) {
        $warnings[] = 'stats.overall.mean се не слаже са средњом вредношћу примарних пиксела.';
      }
    }
  }
  return array_values(array_unique($warnings));
}
function period_from_record(array $r, ?string $fallback=null): ?string {
  $p = $r['period'] ?? $r['month'] ?? $r['ym'] ?? $r['id'] ?? $fallback;
  if (!is_scalar($p) && $p !== null) $p = $fallback;
  if (!$p && isset($r['meta']) && is_array($r['meta'])) {
    $p = $r['meta']['id'] ?? $r['meta']['period'] ?? $r['meta']['month'] ?? null;
    if (!$p && isset($r['meta']['date_start']) && preg_match('/^(\d{4}-\d{2})/', (string)$r['meta']['date_start'], $m)) $p = $m[1];
  }
  if (!$p && isset($r['properties']) && is_array($r['properties'])) {
    $p = $r['properties']['period'] ?? $r['properties']['month'] ?? $r['properties']['id'] ?? null;
  }
  if (!$p && isset($r['year'], $r['month_num'])) $p = sprintf('%04d-%02d', (int)$r['year'], (int)$r['month_num']);
  if (!$p && isset($r['date']) && preg_match('/^(\d{4}-\d{2})/', (string)$r['date'], $m)) $p = $m[1];
  if (!is_scalar($p) || $p === '') return null;
  if (preg_match('/^(\d{4})[\/_.-](\d{1,2})$/', (string)$p, $m)) return sprintf('%04d-%02d', (int)$m[1], (int)$m[2]);
  if (preg_match('/^(\d{4}-\d{2})/', (string)$p, $m)) return $m[1];
  return null;
}
function flatten_month_input(array $raw, string $period, array $config): array {
  $x = $raw;
  $x['period'] = period_from_record($raw, $period) ?: $period;
  $x['previous_period'] = $x['previous_period'] ?? $x['prev_period'] ?? previous_period($x['period']);
  $inferred = infer_radiance_stats($raw, $config);
  if (!isset($x['mean_radiance']) && !isset($x['mean']) && !isset($x['radiance_mean'])) {
    $v = dig_num($raw, ['stats.overall.mean','stats.mean','stats.mean_radiance','stats.avg','stats.avg_rad','stats.mean_value','stats.radiance.mean','statistics.mean','statistics.mean_radiance','statistics.avg_rad','summary.mean','summary.avg_rad','properties.mean','properties.mean_radiance','properties.avg_rad','raw.mean','raw.mean_radiance','meta.stats.mean']);
    if ($v === null && $inferred) $v = $inferred['mean'] ?? null;
    if ($v !== null) $x['mean_radiance'] = $v;
  }
  if (!isset($x['median_radiance']) && !isset($x['median'])) {
    $v = dig_num($raw, ['stats.overall.median','stats.overall.p50','stats.median','stats.p50','stats.median_radiance','statistics.median','summary.median','properties.median']);
    if ($v === null && $inferred) $v = $inferred['median'] ?? null;
    if ($v !== null) $x['median_radiance'] = $v;
  }
  if (!isset($x['min_radiance']) && !isset($x['min'])) {
    $v = dig_num($raw, ['stats.overall.min','stats.min','stats.min_radiance','statistics.min','summary.min','properties.min']);
    if ($v === null && $inferred) $v = $inferred['min'] ?? null;
    if ($v !== null) $x['min_radiance'] = $v;
  }
  if (!isset($x['max_radiance']) && !isset($x['max'])) {
    $v = dig_num($raw, ['stats.overall.max','stats.max','stats.max_radiance','statistics.max','summary.max','properties.max']);
    if ($v === null && $inferred) $v = $inferred['max'] ?? null;
    if ($v !== null) $x['max_radiance'] = $v;
  }
  if (!isset($x['p90_radiance'])) {
    $v = dig_num($raw, ['stats.overall.p90','stats.overall.percentile90','stats.p90','stats.percentile90','statistics.p90','summary.p90','properties.p90']);
    if ($v === null && $inferred) $v = $inferred['p90'] ?? null;
    if ($v !== null) $x['p90_radiance'] = $v;
  }
  if (!isset($x['pixel_count'])) {
    $v = dig_num($raw, ['stats.overall.count','stats.count','stats.pixel_count','statistics.count','summary.count','properties.count','valid_pixels','pixel_count']);
    if ($v === null && $inferred) $v = $inferred['count'] ?? null;
    if ($v !== null) $x['pixel_count'] = $v;
  }
  $qualityWarnings = radiance_data_quality_warnings($raw);
  if ($qualityWarnings) $x['data_quality_warnings'] = $qualityWarnings;
  if (!isset($x['image_url'])) {
    $s = dig_str($raw, ['image_url','map_image','map.image_url','public_image','png_url','paths.image_url','paths.png','urls.image','url_image','meta.image_url']);
    if ($s) $x['image_url'] = $s;
  }
  if (!isset($x['map_url'])) $x['map_url'] = $config['public_map_url'];
  if (!isset($x['source'])) $x['source'] = $raw['source'] ?? ($raw['meta']['source'] ?? 'VIIRS DNB monthly composite / постојећи месечни подаци са сервера');
  if (!isset($x['unit']) && isset($raw['meta']['unit'])) $x['unit'] = $raw['meta']['unit'];
  if (!isset($x['title'])) $x['title'] = 'Извештај о светлосном загађењу — ' . month_label($x['period']);
  return $x;
}
function monthly_record_quality(array $record): int {
  $score = 0;
  if (dig_num($record, ['stats.overall.mean']) !== null) $score += 10000;
  elseif (dig_num($record, ['mean_radiance','current_mean_radiance','mean','radiance_mean','stats.mean','statistics.mean']) !== null) $score += 8000;
  if (isset($record['pixels']) && is_array($record['pixels']) && count($record['pixels']) >= 3) $score += 2000;
  $kind = strtolower((string)($record['meta']['kind'] ?? $record['kind'] ?? ''));
  if ($kind === 'monthly') $score += 200;
  if (!empty($record['meta']['placeholder']) || !empty($record['placeholder'])) $score -= 5000;
  return $score;
}
function store_month_record(array &$records, string $period, array $record): void {
  $candidate = $record;
  $candidate['period'] = $period;
  if (!isset($records[$period]) || monthly_record_quality($candidate) > monthly_record_quality($records[$period])) {
    $records[$period] = $candidate;
  }
}
function add_records_from_json(array &$records, array $json, ?string $fallbackPeriod=null): void {
  // map: {"2025-01": {...}, "2025-02": {...}}
  foreach ($json as $k=>$v) {
    if (is_array($v) && is_string($k) && preg_match('/^\\d{4}-\\d{2}$/', $k)) {
      $p = period_from_record($v, $k); if ($p) store_month_record($records, $p, $v);
    }
  }
  foreach (['months','monthly','items','reports','data','features'] as $key) {
    if (!isset($json[$key]) || !is_array($json[$key])) continue;
    foreach ($json[$key] as $k=>$v) {
      if (!is_array($v)) continue;
      if ($key === 'features' && isset($v['properties']) && is_array($v['properties'])) $v = $v['properties'] + $v;
      $p = period_from_record($v, is_string($k) ? $k : null);
      if ($p) store_month_record($records, $p, $v);
    }
  }
  $p = period_from_record($json, $fallbackPeriod);
  if ($p) store_month_record($records, $p, $json);
}
function historical_aggregate_candidates(array $config): array {
  $public_html = dirname(__DIR__, 3);
  return [
    __DIR__ . '/../data/history-input.json',
    __DIR__ . '/../data/monthly/history.json',
    __DIR__ . '/../data/monthly.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/history.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/monthly.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/reports.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/history.json',
    $public_html . '/svetlosno-zagadjenje/data/history.json',
    $public_html . '/viirs/data/history.json',
  ];
}
function monthly_file_candidates(string $period): array {
  $public_html = dirname(__DIR__, 3);
  return [
    __DIR__ . '/../data/monthly/' . $period . '.json',
    __DIR__ . '/../data/manual-inputs/' . $period . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/monthly/' . $period . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/' . $period . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/monthly/' . $period . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/reports/' . $period . '/report.json',
    $public_html . '/svetlosno-zagadjenje/data/monthly/' . $period . '.json',
    $public_html . '/viirs/data/monthly/' . $period . '.json',
    $public_html . '/viirs/data/' . $period . '.json',
  ];
}
function discovery_roots(array $config): array {
  $roots = $config['discovery_roots'] ?? [];
  $out = [];
  foreach ($roots as $r) {
    if (is_string($r) && $r !== '' && is_dir($r)) $out[] = realpath($r) ?: $r;
  }
  return array_values(array_unique($out));
}
function path_has_skip_segment(string $path): bool {
  $skip = ['/.git/','/node_modules/','/vendor/','/cache/','/tmp/','/logs/','/backup/','/backups/','/wp-content/cache/'];
  $norm = str_replace('\\','/',$path);
  foreach ($skip as $s) if (stripos($norm, $s) !== false) return true;
  return false;
}
function discover_server_files(array $config, string $from='2025-01', string $to='2026-06'): array {
  $maxBytes = (int)($config['discovery_max_file_bytes'] ?? 12000000);
  $maxFiles = (int)($config['discovery_max_files'] ?? 2500);
  $json = []; $hints = []; $scanned = 0; $roots = discovery_roots($config);
  $periodRegex = '/(20\d{2})[\-_\.\/](0[1-9]|1[0-2])/';
  $keywords = ['viirs','black','marble','radiance','radiance','radiјанс','radijans','svetlos','zagad','alan','night','dnb','vcmsl','nasa'];
  foreach ($roots as $root) {
    try {
      $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
      foreach ($it as $file) {
        if ($scanned >= $maxFiles) break 2;
        if (!$file->isFile()) continue;
        $path = $file->getPathname();
        if (should_ignore_input_path($path, $config)) continue;
        if (path_has_skip_segment($path)) continue;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['json','geojson','js','html','htm'], true)) continue;
        $size = (int)$file->getSize();
        if ($size <= 0 || $size > $maxBytes) continue;
        $scanned++;
        $lower = mb_strtolower(str_replace('\\','/',$path), 'UTF-8');
        $pathHit = false;
        foreach ($keywords as $kw) { if (stripos($lower, $kw) !== false) { $pathHit = true; break; } }
        $periodHit = preg_match($periodRegex, $path) === 1;
        if ($ext === 'json' || $ext === 'geojson') {
          if (!$pathHit && !$periodHit) {
            // Не отварамо сваки случајни JSON; само оне који личе на овај систем.
            continue;
          }
          $json[] = $path;
        } else {
          $raw = @file_get_contents($path, false, null, 0, min($size, 300000));
          if ($raw === false) continue;
          if (stripos($raw, '.json') !== false || stripos($raw, 'fetch(') !== false || stripos($raw, 'radiance') !== false || stripos($raw, 'радијанс') !== false) {
            $hints[] = $path;
          }
        }
      }
    } catch (Throwable $e) { continue; }
  }
  return ['roots'=>$roots, 'json'=>array_values(array_unique($json)), 'hints'=>array_values(array_unique($hints)), 'scanned'=>$scanned];
}
function add_records_from_discovered_json(array &$records, array &$foundSources, array $config, string $from, string $to): array {
  if (empty($config['enable_recursive_discovery'])) return ['roots'=>[], 'json'=>[], 'hints'=>[], 'scanned'=>0, 'usable'=>[]];
  $disc = discover_server_files($config, $from, $to);
  $usable = [];
  foreach ($disc['json'] as $path) {
    if (should_ignore_input_path($path, $config)) continue;
    $before = count($records);
    $j = read_json_file($path);
    if (!$j) continue;
    add_records_from_json($records, $j);
    // Ако је фајл једномесечни, али нема period унутра, извлачимо период из имена путање.
    if (count($records) === $before && preg_match('/(20\d{2})[\-_\.\/](0[1-9]|1[0-2])/', $path, $m)) {
      add_records_from_json($records, $j, sprintf('%04d-%02d', (int)$m[1], (int)$m[2]));
    }
    if (count($records) > $before) { $foundSources[] = $path; $usable[] = $path; }
  }
  $disc['usable'] = $usable;
  return $disc;
}

function http_get_text(string $url, int $timeout=12): ?string {
  if (!preg_match('/^https?:\/\//i', $url)) return null;
  $headers = [
    'User-Agent: Rajac-ALAN-Report-Engine/7.0',
    'Accept: application/json,text/plain,*/*'
  ];
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_CONNECTTIMEOUT => $timeout,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($raw !== false && $code >= 200 && $code < 300 && trim((string)$raw) !== '') return (string)$raw;
    return null;
  }
  $ctx = stream_context_create(['http'=>[
    'method'=>'GET',
    'timeout'=>$timeout,
    'header'=>implode("\r\n", $headers) . "\r\n",
    'ignore_errors'=>false,
  ]]);
  $raw = @file_get_contents($url, false, $ctx);
  return ($raw !== false && trim($raw) !== '') ? (string)$raw : null;
}
function read_json_url(string $url, int $timeout=12): ?array {
  $raw = http_get_text($url, $timeout);
  if ($raw === null) return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}
function remote_url_join(string $base, string $part): string {
  return rtrim($base, '/') . '/' . ltrim($part, '/');
}
function github_api_url_from_raw_base(string $rawBase): ?string {
  // https://raw.githubusercontent.com/owner/repo/branch/path/to/dir
  if (!preg_match('#^https://raw\.githubusercontent\.com/([^/]+)/([^/]+)/([^/]+)/(.*)$#', $rawBase, $m)) return null;
  $owner = rawurlencode($m[1]); $repo = rawurlencode($m[2]); $branch = rawurlencode($m[3]); $path = implode('/', array_map('rawurlencode', explode('/', trim($m[4], '/'))));
  return "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}?ref={$branch}";
}
function discover_github_json_urls(array $config, string $from, string $to): array {
  if (empty($config['enable_remote_github_upstream'])) return ['api'=>null,'json'=>[],'errors'=>['remote upstream disabled']];
  $base = rtrim((string)($config['remote_results_base_url'] ?? ''), '/');
  if ($base === '') return ['api'=>null,'json'=>[],'errors'=>['remote_results_base_url није подешен']];
  $api = github_api_url_from_raw_base($base);
  $jsonUrls = []; $errors = [];

  // 1) Познати manifest/index кандидати
  foreach (($config['remote_index_candidates'] ?? []) as $name) {
    if (!is_string($name) || $name==='') continue;
    $jsonUrls[] = remote_url_join($base, $name);
  }

  // 2) Типични месечни кандидати за сваки период
  foreach (month_range_list($from, $to) as $period) {
    [$year, $month] = explode('-', $period);
    foreach (($config['remote_monthly_patterns'] ?? []) as $pat) {
      if (!is_string($pat) || $pat==='') continue;
      $url = strtr($pat, ['{base}'=>$base,'{period}'=>$period,'{year}'=>$year,'{month}'=>$month]);
      $jsonUrls[] = $url;
    }
  }

  // 3) GitHub API попис директоријума public/results, ако сервер може да приступи GitHub API-ју
  if ($api) {
    $list = read_json_url($api, (int)($config['remote_timeout_seconds'] ?? 12));
    if (is_array($list)) {
      foreach ($list as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? '';
        $name = (string)($item['name'] ?? '');
        $download = (string)($item['download_url'] ?? '');
        $htmlUrl = (string)($item['url'] ?? '');
        if ($type === 'file' && preg_match('/\.(json|geojson)$/i', $name) && $download) $jsonUrls[] = $download;
        // Плитко скенирање једног нивоа поддиректоријума — довољно за public/results/YYYY-MM или monthly.
        if ($type === 'dir' && $htmlUrl) {
          $sub = read_json_url($htmlUrl, (int)($config['remote_timeout_seconds'] ?? 12));
          if (is_array($sub)) {
            foreach ($sub as $si) {
              if (!is_array($si)) continue;
              $sn = (string)($si['name'] ?? ''); $sd = (string)($si['download_url'] ?? '');
              if (($si['type'] ?? '') === 'file' && preg_match('/\.(json|geojson)$/i', $sn) && $sd) $jsonUrls[] = $sd;
            }
          }
        }
      }
    } else {
      $errors[] = 'GitHub API listing није успео: ' . $api;
    }
  }
  return ['api'=>$api,'json'=>array_values(array_unique($jsonUrls)),'errors'=>$errors];
}
function add_records_from_remote_upstream(array &$records, array &$foundSources, array $config, string $from, string $to): array {
  $disc = discover_github_json_urls($config, $from, $to);
  $usable = []; $checked = 0;
  foreach (($disc['json'] ?? []) as $url) {
    $checked++;
    $before = count($records);
    $j = read_json_url($url, (int)($config['remote_timeout_seconds'] ?? 12));
    if (!$j) continue;
    add_records_from_json($records, $j);
    if (count($records) === $before && preg_match('/(20\d{2})[\-_\.\/](0[1-9]|1[0-2])/', $url, $m)) {
      add_records_from_json($records, $j, sprintf('%04d-%02d', (int)$m[1], (int)$m[2]));
    }
    if (count($records) > $before) { $foundSources[] = $url; $usable[] = $url; }
  }
  $disc['checked'] = $checked;
  $disc['usable'] = $usable;
  return $disc;
}

function collect_historical_inputs(array $config, string $from, string $to): array {
  $records = []; $foundSources = [];
  foreach (historical_aggregate_candidates($config) as $path) {
    if (should_ignore_input_path($path, $config)) continue;
    $j = read_json_file($path);
    if ($j) { add_records_from_json($records, $j); $foundSources[] = $path; }
  }
  foreach (month_range_list($from, $to) as $period) {
    if (isset($records[$period])) continue;
    foreach (monthly_file_candidates($period) as $path) {
      if (should_ignore_input_path($path, $config)) continue;
      $j = read_json_file($path);
      if ($j) { add_records_from_json($records, $j, $period); $foundSources[] = $path; break; }
    }
  }
  $remote = add_records_from_remote_upstream($records, $foundSources, $config, $from, $to);
  $discovery = add_records_from_discovered_json($records, $foundSources, $config, $from, $to);
  ksort($records);
  return ['records'=>$records, 'sources'=>array_values(array_unique($foundSources)), 'discovery'=>$discovery, 'remote'=>$remote];
}
function build_history_overview(string $root, array $config, string $from, string $to): ?string {
  $reportsFile = $root . '/data/reports.json';
  $data = read_json_file($reportsFile);
  $list = is_array($data['reports'] ?? null) ? $data['reports'] : [];
  $items = array_values(array_filter($list, fn($r) => ($r['period'] ?? '') >= $from && ($r['period'] ?? '') <= $to));
  if (!$items) return null;
  usort($items, fn($a,$b) => strcmp($a['period'], $b['period']));
  $means = array_values(array_filter(array_map(fn($r)=>norm_num($r['mean_radiance'] ?? null), $items), fn($v)=>$v!==null));
  $first = $items[0]; $last = $items[count($items)-1];
  $firstMean = norm_num($first['mean_radiance'] ?? null); $lastMean = norm_num($last['mean_radiance'] ?? null);
  $abs = ($firstMean!==null && $lastMean!==null) ? ($lastMean-$firstMean) : null;
  $pct = pct_change($lastMean, $firstMean);
  $highest = null; $lowest = null;
  foreach ($items as $r) {
    $m = norm_num($r['mean_radiance'] ?? null); if ($m===null) continue;
    if (!$highest || $m > norm_num($highest['mean_radiance'])) $highest = $r;
    if (!$lowest || $m < norm_num($lowest['mean_radiance'])) $lowest = $r;
  }
  $rangeSlug = $from . '_' . $to;
  $dir = $root . '/reports/' . $rangeSlug; ensure_dir($dir);
  $url = rtrim($config['public_base_url'], '/') . '/reports/' . rawurlencode($rangeSlug) . '/';
  $rows = '';
  foreach ($items as $r) {
    $change = isset($r['change_pct']) && $r['change_pct'] !== null ? fmt_num($r['change_pct'],1).'%' : '—';
    $rows .= '<a class="zone-row flat" href="'.e($r['url'] ?? '#').'"><span><b>'.e($r['period_label'] ?? $r['period']).'</b><small>'.e($r['trend'] ?? '').'</small></span><span>'.fmt_num($r['mean_radiance'] ?? null,3).'</span><span>'.fmt_num($r['previous_mean_radiance'] ?? null,3).'</span><span><b>'.fmt_num($r['abs_change'] ?? null,3).'</b><small>'.$change.'</small></span><span>'.e($r['title'] ?? '').'</span></a>';
  }
  $title = 'Историјски преглед светлосног загађења — ' . month_label($from) . ' до ' . month_label($to);
  $summary = 'Преглед обједињује месечне VIIRS извештаје за период од ' . month_label($from) . ' до ' . month_label($to) . ' и приказује кретање средње ноћне радијансе, месечне разлике и сигнале пораста или смањења.';
  $trend = trend_label($abs, $pct, $config);
  $html = '<!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' — ПИО Рајац</title><link rel="stylesheet" href="../../assets/alan-report.css"></head><body>'
    . '<header class="top"><a class="brand" href="https://piorajac.rs/"><span class="mark">△</span><span>ПИО РАЈАЦ</span></a><nav class="nav"><a href="https://piorajac.rs/monitoring-izvestaji/">Мониторинг извештаји</a><a class="active" href="../../">Светлосно загађење</a><a href="https://piorajac.rs/monitoring-svetlosno-zagadjenje/">Јавна карта</a></nav></header>'
    . '<section class="hero"><div class="wrap"><div class="kicker">Историјски преглед</div><h1>Светлосно<br>загађење</h1><p>'.e($summary).'</p></div></section><main class="wrap">'
    . '<article class="panel"><h2>'.e($title).'</h2><p class="lead">'.e($summary).'</p><div class="meta"><div class="metric"><small>Број месеци</small><b>'.count($items).'</b></div><div class="metric"><small>Почетак</small><b>'.fmt_num($firstMean,3).'</b></div><div class="metric"><small>Крај</small><b>'.fmt_num($lastMean,3).'</b></div><div class="metric"><small>Укупна промена</small><b>'.fmt_num($pct,1).'%</b></div></div><p>Општи тренд за цео период: <strong>'.e($trend).'</strong>.</p></article>'
    . '<section class="panel"><h2>Месечни извештаји у серији</h2><div class="zone-table"><div class="zone-head"><span>Период</span><span>Средња радијанса</span><span>Претходни месец</span><span>Промена</span><span>Извештај</span></div>'.$rows.'</div></section>'
    . '<section class="panel"><h2>Кључни сигнали</h2><p>Највећа средња радијанса у серији: <strong>'.e($highest['period_label'] ?? '—').'</strong> ('.fmt_num($highest['mean_radiance'] ?? null,3).'). Најнижа средња радијанса у серији: <strong>'.e($lowest['period_label'] ?? '—').'</strong> ('.fmt_num($lowest['mean_radiance'] ?? null,3).').</p><p class="note">Овај преглед је аутоматски изведен из месечних report.json датотека. За стручни закључак потребно је упоредити резултате са облачношћу, снегом, атмосферским условима и теренским познавањем извора осветљења.</p></section>'
    . '</main><footer class="footer"><div class="wrap"><b>ПИО РАЈАЦ</b><span>Историјска серија светлосног загађења</span></div></footer></body></html>';
  file_put_contents($dir . '/index.html', $html);
  write_json_file($root . '/data/series-' . $rangeSlug . '.json', [
    'from'=>$from,'to'=>$to,'url'=>$url,'title'=>$title,'summary'=>$summary,'months'=>count($items),
    'first_mean'=>$firstMean,'last_mean'=>$lastMean,'abs_change'=>$abs,'change_pct'=>$pct,'trend'=>$trend,
    'highest'=>$highest,'lowest'=>$lowest,'updated_at'=>date('c')
  ]);
  return $url;
}

/* =========================
   v8 — годишњи извештаји
   ========================= */
function year_range_list(string $fromYear, string $toYear): array {
  if (!preg_match('/^\d{4}$/', $fromYear) || !preg_match('/^\d{4}$/', $toYear)) return [];
  $a=(int)$fromYear; $b=(int)$toYear; if ($a>$b) return [];
  return range($a,$b);
}
function year_label($year): string { return (string)$year . '. година'; }
function year_from_record(array $r, ?string $fallback=null): ?string {
  $candidates = [];
  foreach (['year','godina','period','id','ym'] as $k) {
    if (isset($r[$k]) && is_scalar($r[$k])) $candidates[] = (string)$r[$k];
  }
  foreach (['meta','properties','summary','stats','statistics'] as $k) {
    if (isset($r[$k]) && is_array($r[$k])) {
      foreach (['year','godina','period','id','date_start','start','from'] as $kk) {
        if (isset($r[$k][$kk]) && is_scalar($r[$k][$kk])) $candidates[] = (string)$r[$k][$kk];
      }
    }
  }
  if ($fallback) $candidates[] = $fallback;
  foreach ($candidates as $p) {
    if (preg_match('/^(20\d{2})$/', $p, $m)) return $m[1];
    if (preg_match('/^(20\d{2})-(?:annual|year|godisnji)$/i', $p, $m)) return $m[1];
    if (preg_match('/^(20\d{2})-\d{2}$/', $p)) continue; // месечни запис није годишњи
    if (preg_match('/\b(20\d{2})\b/', $p, $m) && !preg_match('/20\d{2}[-_\.\/](0[1-9]|1[0-2])/', $p)) return $m[1];
  }
  return null;
}
function add_annual_records_from_json(array &$records, array $json, ?string $fallbackYear=null): void {
  foreach ($json as $k=>$v) {
    if (is_array($v) && is_string($k) && preg_match('/^20\d{2}$/', $k)) {
      $y = year_from_record($v, $k); if ($y) store_annual_record($records, $y, $v);
    }
  }
  foreach (['years','annual','yearly','godisnji','items','reports','data','features'] as $key) {
    if (!isset($json[$key]) || !is_array($json[$key])) continue;
    foreach ($json[$key] as $k=>$v) {
      if (!is_array($v)) continue;
      if ($key === 'features' && isset($v['properties']) && is_array($v['properties'])) $v = $v['properties'] + $v;
      $y = year_from_record($v, is_string($k) ? $k : null);
      if ($y) store_annual_record($records, $y, $v);
    }
  }
  $y = year_from_record($json, $fallbackYear);
  if ($y) store_annual_record($records, $y, $json);
}
function annual_record_quality(array $record): int {
  $score = 0;
  if (dig_num($record, ['stats.overall.mean']) !== null) $score += 10000;
  elseif (dig_num($record, ['mean_radiance','mean','radiance_mean','stats.mean','statistics.mean','annual.mean']) !== null) $score += 8000;
  if (isset($record['pixels']) && is_array($record['pixels']) && count($record['pixels']) >= 3) $score += 2000;
  $kind = strtolower((string)($record['meta']['kind'] ?? $record['kind'] ?? ''));
  if ($kind === 'yearly' || $kind === 'annual') $score += 200;
  if (!empty($record['meta']['placeholder']) || !empty($record['placeholder'])) $score -= 5000;
  return $score;
}
function store_annual_record(array &$records, string $year, array $record): void {
  $candidate = $record;
  $candidate['year'] = $year;
  if (!isset($records[$year]) || annual_record_quality($candidate) > annual_record_quality($records[$year])) {
    $records[$year] = $candidate;
  }
}
function annual_local_candidates(string $year): array {
  $public_html = dirname(__DIR__, 3);
  return [
    __DIR__ . '/../data/annual/' . $year . '.json',
    __DIR__ . '/../data/godisnji/' . $year . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/annual/' . $year . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/yearly/' . $year . '.json',
    $public_html . '/monitoring-svetlosno-zagadjenje/data/' . $year . '.json',
    $public_html . '/svetlosno-zagadjenje/data/annual/' . $year . '.json',
    $public_html . '/viirs/data/annual/' . $year . '.json',
    $public_html . '/viirs/data/' . $year . '.json',
  ];
}
function remote_annual_urls(array $config, string $fromYear, string $toYear): array {
  $base = rtrim((string)($config['remote_results_base_url'] ?? ''), '/');
  $urls = [];
  if ($base === '') return ['api'=>null,'json'=>[],'usable'=>[],'checked'=>0,'errors'=>['remote base није подешен']];
  foreach (year_range_list($fromYear, $toYear) as $year) {
    foreach ([
      '{base}/{year}.json',
      '{base}/annual/{year}.json',
      '{base}/yearly/{year}.json',
      '{base}/reports/{year}/report.json'
    ] as $pat) $urls[] = strtr($pat, ['{base}'=>$base,'{year}'=>(string)$year]);
  }
  $api = github_api_url_from_raw_base($base);
  if ($api) {
    $list = read_json_url($api, (int)($config['remote_timeout_seconds'] ?? 12));
    if (is_array($list)) {
      foreach ($list as $item) {
        if (!is_array($item)) continue;
        $type = $item['type'] ?? ''; $name = (string)($item['name'] ?? ''); $download=(string)($item['download_url'] ?? ''); $url=(string)($item['url'] ?? '');
        if ($type === 'file' && preg_match('/\.(json|geojson)$/i', $name) && $download) $urls[] = $download;
        if ($type === 'dir' && $url) {
          $sub = read_json_url($url, (int)($config['remote_timeout_seconds'] ?? 12));
          if (is_array($sub)) foreach ($sub as $si) {
            if (!is_array($si)) continue;
            $sn=(string)($si['name'] ?? ''); $sd=(string)($si['download_url'] ?? '');
            if (($si['type'] ?? '') === 'file' && preg_match('/\.(json|geojson)$/i', $sn) && $sd) $urls[] = $sd;
          }
        }
      }
    }
  }
  return ['api'=>$api,'json'=>array_values(array_unique($urls)),'usable'=>[],'checked'=>0,'errors'=>[]];
}
function collect_annual_inputs(array $config, string $fromYear, string $toYear): array {
  $records=[]; $sources=[]; $remote=['api'=>null,'json'=>[],'usable'=>[],'checked'=>0,'errors'=>[]];
  foreach (year_range_list($fromYear, $toYear) as $year) {
    foreach (annual_local_candidates((string)$year) as $path) {
      if (should_ignore_input_path($path, $config)) continue;
      $j = read_json_file($path);
      if ($j) { $before=count($records); add_annual_records_from_json($records, $j, (string)$year); if (count($records)>$before) $sources[]=$path; }
      if (isset($records[(string)$year])) break;
    }
  }
  if (!empty($config['enable_remote_github_upstream'])) {
    $remote = remote_annual_urls($config, $fromYear, $toYear);
    $usable=[]; $checked=0;
    foreach ($remote['json'] as $url) {
      $checked++;
      $j = read_json_url($url, (int)($config['remote_timeout_seconds'] ?? 12));
      if (!$j) continue;
      $fallback = null;
      if (preg_match('/(?:^|[^0-9])(20\d{2})(?:[^0-9]|$)/', $url, $m) && !preg_match('/20\d{2}[-_\.\/](0[1-9]|1[0-2])/', $url)) $fallback = $m[1];
      $before=count($records); add_annual_records_from_json($records, $j, $fallback);
      if (count($records)>$before) { $sources[]=$url; $usable[]=$url; }
    }
    $remote['checked']=$checked; $remote['usable']=array_values(array_unique($usable));
  }
  ksort($records);
  return ['records'=>$records,'sources'=>array_values(array_unique($sources)),'remote'=>$remote];
}
function median_of(array $vals): ?float {
  $v=[]; foreach ($vals as $x) { $n=norm_num($x); if ($n!==null) $v[]=$n; }
  if (!$v) return null; sort($v,SORT_NUMERIC); $n=count($v); $mid=intdiv($n,2); return ($n%2) ? $v[$mid] : (($v[$mid-1]+$v[$mid])/2);
}
function aggregate_annual_from_month_records(array $monthRecords, string $year, array $config): ?array {
  $monthly=[];
  foreach (range(1,12) as $m) {
    $period = sprintf('%s-%02d', $year, $m);
    if (!isset($monthRecords[$period]) || !is_array($monthRecords[$period])) continue;
    $flat = flatten_month_input($monthRecords[$period], $period, $config);
    $norm = normalize_input($flat, $config);
    if ($norm['mean_radiance'] === null) continue;
    $monthly[] = [
      'period'=>$period,
      'mean'=>$norm['mean_radiance'],
      'median'=>$norm['median_radiance'],
      'min'=>$norm['min_radiance'],
      'max'=>$norm['max_radiance'],
      'p90'=>get_first_num($flat, ['p90_radiance','p90','percentile90']),
      'pixel_count'=>get_first_num($flat, ['pixel_count','count','valid_pixels','n_pixels'])
    ];
  }
  if (!$monthly) return null;
  $sum=0.0; $wSum=0.0; $means=[]; $mins=[]; $maxs=[]; $p90s=[]; $meds=[];
  foreach ($monthly as $m) {
    $means[]=$m['mean']; if ($m['median']!==null) $meds[]=$m['median']; if ($m['min']!==null) $mins[]=$m['min']; if ($m['max']!==null) $maxs[]=$m['max']; if ($m['p90']!==null) $p90s[]=$m['p90'];
    $w = $m['pixel_count']!==null && $m['pixel_count']>0 ? (float)$m['pixel_count'] : 1.0;
    $sum += $m['mean']*$w; $wSum += $w;
  }
  return [
    'year'=>$year,
    'period'=>$year,
    'mean_radiance'=>$wSum>0 ? $sum/$wSum : array_sum($means)/count($means),
    'median_radiance'=>median_of($meds ?: $means),
    'min_radiance'=>$mins ? min($mins) : min($means),
    'max_radiance'=>$maxs ? max($maxs) : max($means),
    'p90_radiance'=>median_of($p90s ?: $means),
    'coverage_months'=>count($monthly),
    'months_used'=>array_map(fn($x)=>$x['period'], $monthly),
    'monthly_means'=>$monthly,
    'source'=>'VIIRS DNB monthly composites — годишња статистика израчуната из доступних месечних JSON података',
    'derived_from_monthly'=>true
  ];
}
function flatten_annual_input(array $raw, string $year, array $config): array {
  $x=$raw; $x['year']=year_from_record($raw,$year) ?: $year; $x['period']=$x['year'];
  $inferred = infer_radiance_stats($raw, $config);
  $map = [
    'mean_radiance'=>['stats.overall.mean','stats.mean','stats.mean_radiance','stats.avg','stats.avg_rad','statistics.mean','statistics.mean_radiance','summary.mean','summary.avg_rad','properties.mean','properties.mean_radiance','properties.avg_rad','annual.mean','annual.mean_radiance'],
    'median_radiance'=>['stats.overall.median','stats.overall.p50','stats.median','stats.p50','statistics.median','summary.median','properties.median','annual.median'],
    'min_radiance'=>['stats.overall.min','stats.min','statistics.min','summary.min','properties.min','annual.min'],
    'max_radiance'=>['stats.overall.max','stats.max','statistics.max','summary.max','properties.max','annual.max'],
    'p90_radiance'=>['stats.overall.p90','stats.overall.percentile90','stats.p90','stats.percentile90','statistics.p90','summary.p90','properties.p90','annual.p90'],
    'pixel_count'=>['stats.overall.count','stats.count','stats.pixel_count','statistics.count','summary.count','properties.count','valid_pixels','pixel_count']
  ];
  foreach ($map as $key=>$paths) {
    if (!isset($x[$key])) {
      $v = dig_num($raw,$paths);
      if ($v===null && $inferred) {
        $ik = ['mean_radiance'=>'mean','median_radiance'=>'median','min_radiance'=>'min','max_radiance'=>'max','p90_radiance'=>'p90','pixel_count'=>'count'][$key] ?? '';
        if ($ik && isset($inferred[$ik])) $v=$inferred[$ik];
      }
      if ($v!==null) $x[$key]=$v;
    }
  }
  $qualityWarnings = radiance_data_quality_warnings($raw);
  if ($qualityWarnings) $x['data_quality_warnings'] = $qualityWarnings;
  if (!isset($x['image_url'])) {
    $s=dig_str($raw,['image_url','map_image','map.image_url','public_image','png_url','paths.image_url','paths.png','urls.image','url_image','meta.image_url']);
    if ($s) $x['image_url']=$s;
  }
  if (!isset($x['map_url'])) $x['map_url']=$config['public_map_url'];
  if (!isset($x['source'])) $x['source']=$raw['source'] ?? ($raw['meta']['source'] ?? 'VIIRS DNB annual / yearly composite');
  if (!isset($x['title'])) $x['title']='Годишњи извештај о светлосном загађењу — ' . year_label($x['year']);
  return $x;
}
function normalize_annual_input(array $in, array $config): array {
  $year = (string)($in['year'] ?? $in['period'] ?? date('Y'));
  if (!preg_match('/^20\d{2}$/',$year)) fail('Годишњи JSON нема исправну годину YYYY.');
  $prevYear = (string)((int)$year-1);
  $mean = get_first_num($in,['mean_radiance','mean','radiance_mean','annual_mean_radiance']);
  $median = get_first_num($in,['median_radiance','median','annual_median_radiance']);
  $min = get_first_num($in,['min_radiance','min']);
  $max = get_first_num($in,['max_radiance','max']);
  $p90 = get_first_num($in,['p90_radiance','p90','percentile90']);
  $count = get_first_num($in,['pixel_count','count','valid_pixels']);
  $prevMean = get_first_num($in,['previous_mean_radiance','prev_mean_radiance','previous_mean','prev_mean']);
  $abs = ($mean!==null && $prevMean!==null) ? $mean-$prevMean : get_first_num($in,['abs_change','difference','diff_radiance']);
  $pct = get_first_num($in,['change_pct','change_percent','trend_pct']); if ($pct===null) $pct=pct_change($mean,$prevMean);
  $trend = $in['trend'] ?? trend_label($abs,$pct,$config);
  $coverage = (int)($in['coverage_months'] ?? $in['months_count'] ?? 0);
  $summary = $in['summary'] ?? null;
  if (!$summary) {
    $summary = 'Годишњи извештај користи VIIRS / NASA Black Marble податке о ноћној радијанси за оцену светлосног притиска на ПИО „Рајац”. Годишњи ниво је поузданији за дугорочни тренд од појединачних месечних вредности.';
  }
  return [
    'year'=>$year,'period'=>$year,'year_label'=>year_label($year),'previous_year'=>$prevYear,'previous_year_label'=>year_label($prevYear),
    'title'=>$in['title'] ?? ('Годишњи извештај о светлосном загађењу — ' . year_label($year)),
    'summary'=>$summary,'map_url'=>$in['map_url'] ?? $config['public_map_url'],'image_url'=>$in['image_url'] ?? ($config['public_base_url'].'/assets/alan-map-placeholder.webp'),
    'mean_radiance'=>$mean,'median_radiance'=>$median,'min_radiance'=>$min,'max_radiance'=>$max,'p90_radiance'=>$p90,'pixel_count'=>$count,
    'previous_mean_radiance'=>$prevMean,'abs_change'=>$abs,'change_pct'=>$pct,'trend'=>(string)$trend,
    'coverage_months'=>$coverage,'months_used'=>$in['months_used'] ?? [],'monthly_means'=>$in['monthly_means'] ?? [],'derived_from_monthly'=>!empty($in['derived_from_monthly']),
    'data_quality_warnings'=>is_array($in['data_quality_warnings'] ?? null) ? array_values($in['data_quality_warnings']) : [],
    'source'=>$in['source'] ?? 'VIIRS DNB annual composite','generated_at'=>date('c')
  ];
}
function merge_previous_annual_data(array $r, string $root, array $config): array {
  $prev=$r['previous_year'] ?? ''; if (!$prev) return $r;
  $prevReport = read_json_file($root . '/godisnji/' . $prev . '/report.json');
  if ($prevReport && $r['previous_mean_radiance']===null && isset($prevReport['mean_radiance'])) $r['previous_mean_radiance']=norm_num($prevReport['mean_radiance']);
  if ($r['abs_change']===null && $r['mean_radiance']!==null && $r['previous_mean_radiance']!==null) $r['abs_change']=$r['mean_radiance']-$r['previous_mean_radiance'];
  if ($r['change_pct']===null) $r['change_pct']=pct_change($r['mean_radiance'],$r['previous_mean_radiance']);
  $r['trend']=trend_label($r['abs_change'],$r['change_pct'],$config);
  return $r;
}

function annual_radiance_map_html(array $r, array $config): string {
  $year=e($r['year']);
  $yl=e($r['year_label']);
  $base=e($config['remote_results_base_url'] ?? 'https://raw.githubusercontent.com/suvoborskirajac/rajac-light-data/main/public/results');
  $boundary=e($config['boundary_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/pio-rajac.geojson');
  $id='rlm-annual-' . preg_replace('/[^0-9]/','',$year);
  return <<<HTML
<section class="annual-map-only" aria-label="Карта ноћне радијансе за {$yl}">
  <h2>Карта ноћне радијансе — {$yl}</h2>
  <p class="lead small-lead">Приказана је стварна годишња карта радијансе из исте JSON базе која се користи за јавну карту светлосног загађења.</p>
  <section class="rlm-wrap annual-embed annual-embed-maponly" data-base-url="{$base}" data-boundary-url="{$boundary}" data-height="340" data-default-period="{$year}" id="{$id}">
    <div class="rlm-panel">
      <div aria-label="Контроле карте" class="rlm-toolbar annual-hidden-toolbar">
        <button class="rlm-btn is-active" data-toggle-boundary="1" type="button">Граница ПИО</button>
        <label class="rlm-select-label"><span>Период</span><select aria-label="Избор периода" class="rlm-period"><option value="{$year}" selected>{$yl}</option></select></label>
      </div>
      <div class="rlm-map-shell" style="--rlm-height:340px"><div class="rlm-status" role="status">Учитавање годишње карте радијансе…</div><svg aria-label="Карта ноћне радијансе ПИО Рајац" class="rlm-svg" preserveAspectRatio="xMidYMid meet" role="img"></svg></div>
      <div aria-label="Легенда" class="rlm-legend"><div class="rlm-grad"></div><div class="rlm-legend-scale"><span>ниско</span><span>умерено</span><span>повишено</span><span>високо</span></div></div>
    </div>
  </section>
</section>
HTML;
}
function annual_radiance_map_assets_html(array $config, string $year): string {
  $css=e($config['light_monitor_css_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/rajac-light-monitor.css?ver=0.5.2');
  $js=e($config['light_monitor_js_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/rajac-light-monitor.js?ver=0.5.2');
  $base=e($config['remote_results_base_url'] ?? 'https://raw.githubusercontent.com/suvoborskirajac/rajac-light-data/main/public/results');
  $boundary=e($config['boundary_url'] ?? 'https://piorajac.rs/wp-content/plugins/rajac-light-monitor-static/assets/pio-rajac.geojson');
  $y=e($year);
  return <<<HTML
<link rel="preload" as="style" href="{$css}"><link rel="stylesheet" href="{$css}">
<script>window.RajacLightMonitorCfg={defaultBaseUrl:"{$base}",localBoundaryUrl:"{$boundary}",pluginVersion:"0.5.2"};</script>
<script src="{$js}"></script>
<script>(function(){var target="{$y}";function setPeriod(){document.querySelectorAll('.annual-embed').forEach(function(w){var p=w.getAttribute('data-default-period')||target;var s=w.querySelector('select.rlm-period');if(!s)return;var has=false;for(var i=0;i<s.options.length;i++){if(s.options[i].value===p){has=true;break;}}if(!has){var o=document.createElement('option');o.value=p;o.textContent=p+'. (годишњи просек)';s.appendChild(o);}if(s.value!==p){s.value=p;s.dispatchEvent(new Event('change',{bubbles:true}));}});}window.addEventListener('load',function(){var n=0;setPeriod();var timer=setInterval(function(){setPeriod();if(++n>20)clearInterval(timer);},450);});})();</script>
HTML;
}

function annual_report_html(array $r, array $config): string {
  $title=e($r['title']); $summary=e($r['summary']); $year=e($r['year']); $yl=e($r['year_label']); $map=e($r['map_url']); $source=e($r['source']); $mapEmbed=annual_radiance_map_html($r,$config); $mapAssets=annual_radiance_map_assets_html($config,(string)$r['year']);
  $mean=fmt_num($r['mean_radiance'],3); $median=fmt_num($r['median_radiance'],3); $p90=fmt_num($r['p90_radiance'],3); $max=fmt_num($r['max_radiance'],3); $prev=fmt_num($r['previous_mean_radiance'],3); $abs=fmt_num($r['abs_change'],3); $pct=$r['change_pct']===null?'—':fmt_num($r['change_pct'],1).'%'; $trend=e($r['trend']); $coverage=(int)$r['coverage_months']; $generated=e($r['generated_at']);
  $method = $r['derived_from_monthly'] ? 'Годишња вредност је израчуната из доступних месечних JSON резултата за ту годину. Када је доступан број валидних пиксела, примењен је пондерисани просек; у супротном аритметички просек месечних средњих вредности.' : 'Извештај користи годишњи JSON / годишњу статистику из постојећег извора података.';
  $rows=''; foreach (($r['monthly_means'] ?? []) as $m) { $rows.='<div class="zone-row flat"><span><b>'.e($m['period']).'</b></span><span>'.fmt_num($m['mean'],3).'</span><span>'.fmt_num($m['median'],3).'</span><span><b>'.fmt_num($m['pixel_count'],0).'</b></span><span>месечни улаз</span></div>'; }
  if ($rows==='') $rows='<p class="muted">Годишњи извештај је добијен из годишњег извора; месечни улази нису приказани.</p>';
  return <<<HTML
<!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$title} — ПИО Рајац</title><meta name="description" content="{$summary}"><link rel="stylesheet" href="../../assets/alan-report.css">{$mapAssets}</head><body>
<header class="top"><a class="brand" href="https://piorajac.rs/"><span class="mark">△</span><span>ПИО РАЈАЦ</span></a><nav class="nav"><a href="https://piorajac.rs/monitoring-izvestaji/">Мониторинг извештаји</a><a class="active" href="../../">Светлосно загађење</a><a href="https://piorajac.rs/monitoring-svetlosno-zagadjenje/">Јавна карта</a></nav></header>
<section class="hero"><div class="wrap"><div class="kicker">Годишњи VIIRS / ALAN извештај · {$yl}</div><h1>Светлосно<br>загађење</h1><p>{$summary}</p><div class="btns"><a class="btn green" href="{$map}">Отвори јавну карту</a><a class="btn ghost" href="../2015-2025/">Тренд 2015–2025.</a></div></div></section>
<main class="wrap"><article class="panel annual-report-single"><h2>Кратак преглед</h2><p>{$summary}</p><div class="meta"><div class="metric"><small>Средња радијанса</small><b>{$mean}</b></div><div class="metric"><small>Медијана</small><b>{$median}</b></div><div class="metric"><small>P90</small><b>{$p90}</b></div><div class="metric"><small>Максимум</small><b>{$max}</b></div></div>{$mapEmbed}<p class="note source-note">Извор: {$source}. Обухват месеци: {$coverage}. Извештај је генерисан: {$generated}.</p><h2>Промена у односу на претходну годину</h2><p>Претходна година: <strong>{$prev}</strong>. Апсолутна промена: <strong>{$abs}</strong>. Процентуална промена: <strong>{$pct}</strong>. Тумачење: <strong>{$trend}</strong>.</p></article>
<section class="panel"><h2>Методолошко тумачење</h2><p class="lead">{$method}</p><p>Годишњи извештај је основа за стручну оцену тренда, јер умањује утицај појединачних месечних одступања: облачности, снега, сезонске вегетације, аеросола, влажности ваздуха и краткотрајних извора осветљења.</p></section>
<section class="panel"><h2>Месечни улази коришћени за годишњу статистику</h2><div class="zone-table"><div class="zone-head"><span>Период</span><span>Средња радијанса</span><span>Медијана</span><span>Пиксели</span><span>Напомена</span></div>{$rows}</div></section>
</main><footer class="footer"><div class="wrap"><b>ПИО РАЈАЦ</b><span>Годишњи мониторинг светлосног загађења · {$year}</span></div></footer></body></html>
HTML;
}

function annual_trend_chart_html(array $items): string {
  $points=[];
  foreach ($items as $r) {
    $m=norm_num($r['mean_radiance'] ?? null);
    $year=(string)($r['year'] ?? '');
    if ($year!=='' && $m!==null) $points[]=['year'=>$year,'mean'=>$m,'url'=>(string)($r['url'] ?? '#')];
  }
  if (!$points) return '<section class="panel chart-panel"><h2>Графикон средње годишње радијансе</h2><div class="chart-empty">Нема довољно података за графикон.</div></section>';
  usort($points, fn($a,$b)=>strcmp($a['year'],$b['year']));
  $W=980; $H=390; $L=64; $R=28; $T=34; $B=58; $cw=$W-$L-$R; $ch=$H-$T-$B;
  $vals=array_map(fn($x)=>(float)$x['mean'],$points); $min=min($vals); $max=max($vals);
  if (abs($max-$min)<1e-12) { $min=max(0,$min-0.1); $max=$max+0.1; } else { $pad=($max-$min)*0.12; $min=max(0,$min-$pad); $max=$max+$pad; }
  $n=count($points);
  $x=function(int $i) use($L,$cw,$n){ return $L + ($n===1 ? $cw/2 : ($cw*$i/($n-1))); };
  $y=function(float $v) use($T,$ch,$min,$max){ return $T+$ch-(($v-$min)/($max-$min))*$ch; };
  $coord=[]; foreach($points as $i=>$pt){ $coord[]=[$x($i),$y((float)$pt['mean']),$pt]; }
  $poly=implode(' ', array_map(fn($p)=>number_format($p[0],1,'.','').','.number_format($p[1],1,'.',''), $coord));
  $area='M '.number_format($coord[0][0],1,'.','').' '.($T+$ch).' L '.implode(' L ', array_map(fn($p)=>number_format($p[0],1,'.','').' '.number_format($p[1],1,'.',''), $coord)).' L '.number_format($coord[count($coord)-1][0],1,'.','').' '.($T+$ch).' Z';
  $ticks=''; for($i=0;$i<=4;$i++){ $val=$min+($max-$min)*($i/4); $yy=$y($val); $ticks.='<line class="chart-grid" x1="'.$L.'" y1="'.number_format($yy,1,'.','').'" x2="'.($W-$R).'" y2="'.number_format($yy,1,'.','').'"></line><text class="axis-text" x="'.($L-10).'" y="'.number_format($yy+4,1,'.','').'" text-anchor="end">'.number_format($val,2,'.','').'</text>'; }
  $labels=''; foreach($coord as $p){ $labels.='<text class="chart-year-label" x="'.number_format($p[0],1,'.','').'" y="'.($H-24).'" text-anchor="middle">'.e($p[2]['year']).'</text>'; }
  $vmax=max($vals); $vmin=min($vals);
  $circles=''; foreach($coord as $i=>$p){ $cls=$i===count($coord)-1?' latest':''; $label=''; if($i===0||$i===count($coord)-1||abs($p[2]['mean']-$vmax)<1e-12||abs($p[2]['mean']-$vmin)<1e-12){ $label='<text class="chart-value-label" x="'.number_format($p[0],1,'.','').'" y="'.number_format($p[1]-10,1,'.','').'" text-anchor="middle">'.fmt_num($p[2]['mean'],3).'</text>'; } $circles.='<g><circle class="chart-point'.$cls.'" cx="'.number_format($p[0],1,'.','').'" cy="'.number_format($p[1],1,'.','').'" r="5.5"><title>'.e($p[2]['year']).': '.fmt_num($p[2]['mean'],3).' nW/cm²/sr</title></circle>'.$label.'</g>'; }
  $maxBar=max($vals); $mini='<div class="chart-table-mini"><div class="mini-row"><span>Година</span><span>Однос у серији</span><span>Средња радијанса</span></div>';
  foreach($points as $pt){ $w=$maxBar>0 ? max(4,($pt['mean']/$maxBar)*100) : 4; $mini.='<a class="mini-row" href="'.e($pt['url']).'"><strong>'.e($pt['year']).'</strong><span class="mini-bar"><i style="width:'.number_format($w,1,'.','').'%"></i></span><span>'.fmt_num($pt['mean'],3).' nW/cm²/sr</span></a>'; }
  $mini.='</div>';
  $svg='<svg class="annual-chart" viewBox="0 0 '.$W.' '.$H.'" role="img" aria-label="Графикон средње годишње радијансе"><defs><linearGradient id="annualArea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d8b55b"></stop><stop offset="1" stop-color="#fffdf8"></stop></linearGradient></defs><rect x="0" y="0" width="'.$W.'" height="'.$H.'" rx="14" fill="#fffdf8"></rect>'.$ticks.'<line class="chart-axis" x1="'.$L.'" y1="'.$T.'" x2="'.$L.'" y2="'.($T+$ch).'"></line><line class="chart-axis" x1="'.$L.'" y1="'.($T+$ch).'" x2="'.($W-$R).'" y2="'.($T+$ch).'"></line><text class="axis-text axis-title" x="'.$L.'" y="18">nW/cm²/sr</text><path class="chart-area" d="'.e($area).'"></path><polyline class="chart-line" points="'.e($poly).'"></polyline>'.$circles.$labels.'</svg>';
  return '<section class="panel chart-panel"><h2>Графикон средње годишње радијансе</h2><p class="lead">Графикон приказује промену средње годишње ноћне радијансе кроз целу серију. Вредности су у nW/cm²/sr.</p><div class="chart-card"><div class="chart-head"><div><h3>Средња годишња радијанса по годинама</h3><p>Линија приказује годишњи тренд светлосног притиска на ПИО „Рајац”.</p></div><div class="chart-legend"><i></i><span>Средња радијанса</span></div></div>'.$svg.$mini.'<p class="chart-foot">Годишњи ниво је главни за стручно тумачење, јер умањује утицај облачности, снега, сезонске вегетације и краткотрајних месечних одступања.</p></div></section>';
}

function generate_annual_report_from_input(array $input, string $root, array $config): array {
  $r=normalize_annual_input($input,$config); $r=merge_previous_annual_data($r,$root,$config); $year=$r['year'];
  $dir=$root.'/godisnji/'.$year; ensure_dir($dir); $url=rtrim($config['public_base_url'],'/').'/godisnji/'.rawurlencode($year).'/';
  $r['url']=$url; $r['report_url']=$url; $r['status']='објављено';
  file_put_contents($dir.'/index.html', annual_report_html($r,$config)); write_json_file($dir.'/report.json',$r);
  $file=$root.'/data/annual-reports.json'; $data=read_json_file($file) ?: ['reports'=>[]]; $list=is_array($data['reports'] ?? null)?$data['reports']:[];
  $list=array_values(array_filter($list, fn($x)=>($x['year'] ?? '')!==$year));
  $list[]=['year'=>$year,'year_label'=>$r['year_label'],'title'=>$r['title'],'summary'=>$r['summary'],'url'=>$url,'status'=>$r['status'],'mean_radiance'=>$r['mean_radiance'],'median_radiance'=>$r['median_radiance'],'p90_radiance'=>$r['p90_radiance'],'previous_mean_radiance'=>$r['previous_mean_radiance'],'abs_change'=>$r['abs_change'],'change_pct'=>$r['change_pct'],'trend'=>$r['trend'],'coverage_months'=>$r['coverage_months'],'generated_at'=>$r['generated_at']];
  usort($list, fn($a,$b)=>strcmp($b['year'] ?? '', $a['year'] ?? '')); write_json_file($file,['reports'=>$list,'updated_at'=>date('c')]);
  write_json_file($root.'/latest.json',['status'=>'published','category'=>'Светлосно загађење','type'=>'annual','year'=>$year,'period'=>$year,'date'=>$year,'title'=>$r['title'],'summary'=>$r['summary'],'overall_text'=>$r['summary'],'report_url'=>$url,'public_map_url'=>$r['map_url'],'image_url'=>$r['image_url'],'mean_radiance'=>$r['mean_radiance'],'previous_mean_radiance'=>$r['previous_mean_radiance'],'abs_change'=>$r['abs_change'],'change_pct'=>$r['change_pct'],'trend'=>$r['trend'],'updated_at'=>date('c')]);
  return $r;
}


/* =========================
   v11 — обнова годишњег индекса из већ направљених report.json
   ========================= */
function annual_report_list_from_existing_reports(string $root, string $fromYear, string $toYear): array {
  $items = [];
  foreach (year_range_list($fromYear, $toYear) as $year) {
    $ys = (string)$year;
    $path = $root . '/godisnji/' . $ys . '/report.json';
    $r = read_json_file($path);
    if (!$r || norm_num($r['mean_radiance'] ?? null) === null) continue;
    if (empty($r['year'])) $r['year'] = $ys;
    if (empty($r['year_label'])) $r['year_label'] = year_label($ys);
    if (empty($r['url'])) $r['url'] = rtrim((string)($GLOBALS['RAJAC_ALAN_PUBLIC_BASE_URL'] ?? ''), '/') . '/godisnji/' . rawurlencode($ys) . '/';
    $items[$ys] = [
      'year'=>(string)$r['year'],
      'year_label'=>(string)($r['year_label'] ?? year_label($ys)),
      'title'=>(string)($r['title'] ?? ('Годишњи извештај о светлосном загађењу — ' . year_label($ys))),
      'summary'=>(string)($r['summary'] ?? ''),
      'url'=>(string)($r['url'] ?? ''),
      'status'=>(string)($r['status'] ?? 'објављено'),
      'mean_radiance'=>norm_num($r['mean_radiance'] ?? null),
      'median_radiance'=>norm_num($r['median_radiance'] ?? null),
      'p90_radiance'=>norm_num($r['p90_radiance'] ?? null),
      'previous_mean_radiance'=>norm_num($r['previous_mean_radiance'] ?? null),
      'abs_change'=>norm_num($r['abs_change'] ?? null),
      'change_pct'=>norm_num($r['change_pct'] ?? null),
      'trend'=>(string)($r['trend'] ?? ''),
      'coverage_months'=>(int)($r['coverage_months'] ?? 0),
      'generated_at'=>(string)($r['generated_at'] ?? '')
    ];
  }
  ksort($items);
  return array_values($items);
}
function rebuild_annual_reports_index_from_report_files(string $root, array $config, string $fromYear, string $toYear): array {
  $GLOBALS['RAJAC_ALAN_PUBLIC_BASE_URL'] = rtrim((string)($config['public_base_url'] ?? ''), '/');
  $fromReports = annual_report_list_from_existing_reports($root, $fromYear, $toYear);
  $file = $root . '/data/annual-reports.json';
  $data = read_json_file($file) ?: ['reports'=>[]];
  $existing = is_array($data['reports'] ?? null) ? $data['reports'] : [];
  $byYear = [];
  foreach ($existing as $r) {
    if (!is_array($r)) continue;
    $y = (string)($r['year'] ?? '');
    if (preg_match('/^20\d{2}$/', $y) && $y >= $fromYear && $y <= $toYear && norm_num($r['mean_radiance'] ?? null) !== null) $byYear[$y] = $r;
  }
  foreach ($fromReports as $r) $byYear[(string)$r['year']] = $r;
  ksort($byYear);
  $ascending = array_values($byYear);
  $descending = $ascending;
  usort($descending, fn($a,$b)=>strcmp((string)($b['year'] ?? ''), (string)($a['year'] ?? '')));
  ensure_dir($root . '/data');
  write_json_file($file, ['reports'=>$descending, 'updated_at'=>date('c'), 'rebuilt_from'=>'godisnji/YYYY/report.json']);
  return $ascending;
}
function build_annual_trend_overview_resilient(string $root, array $config, string $fromYear, string $toYear): ?string {
  rebuild_annual_reports_index_from_report_files($root, $config, $fromYear, $toYear);
  return build_annual_trend_overview($root, $config, $fromYear, $toYear);
}
function build_annual_trend_overview(string $root, array $config, string $fromYear, string $toYear): ?string {
  $data=read_json_file($root.'/data/annual-reports.json'); $list=is_array($data['reports'] ?? null)?$data['reports']:[];
  $items=array_values(array_filter($list, fn($r)=>($r['year'] ?? '')>=$fromYear && ($r['year'] ?? '')<=$toYear && norm_num($r['mean_radiance'] ?? null)!==null));
  if (!$items) return null; usort($items, fn($a,$b)=>strcmp($a['year'],$b['year']));
  $first=$items[0]; $last=$items[count($items)-1]; $firstMean=norm_num($first['mean_radiance']); $lastMean=norm_num($last['mean_radiance']); $abs=($firstMean!==null&&$lastMean!==null)?$lastMean-$firstMean:null; $pct=pct_change($lastMean,$firstMean); $trend=trend_label($abs,$pct,$config);
  $highest=null; $lowest=null; foreach ($items as $r) { $m=norm_num($r['mean_radiance']); if ($m===null) continue; if(!$highest||$m>norm_num($highest['mean_radiance']))$highest=$r; if(!$lowest||$m<norm_num($lowest['mean_radiance']))$lowest=$r; }
  $range=$fromYear.'-'.$toYear; $dir=$root.'/godisnji/'.$range; ensure_dir($dir); $url=rtrim($config['public_base_url'],'/').'/godisnji/'.rawurlencode($range).'/';
  $rows=''; foreach($items as $r){ $chg=isset($r['change_pct'])&&$r['change_pct']!==null?fmt_num($r['change_pct'],1).'%':'—'; $rows.='<a class="zone-row flat" href="'.e($r['url'] ?? '#').'"><span><b>'.e($r['year_label'] ?? $r['year']).'</b><small>'.e($r['trend'] ?? '').'</small></span><span>'.fmt_num($r['mean_radiance'] ?? null,3).'</span><span>'.fmt_num($r['previous_mean_radiance'] ?? null,3).'</span><span><b>'.fmt_num($r['abs_change'] ?? null,3).'</b><small>'.$chg.'</small></span><span>'.e($r['title'] ?? '').'</span></a>'; }
  $chart=annual_trend_chart_html($items);
  $title='Тренд светлосног загађења ПИО „Рајац” ' . $fromYear . '–' . $toYear;
  $summary='Дугорочни годишњи преглед ноћне радијансе омогућава стручну оцену светлосног притиска на предео, јер умањује утицај облачности, снега, сезонске вегетације и краткотрајних месечних одступања.';
  $html='<!DOCTYPE html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($title).' — ПИО Рајац</title><link rel="stylesheet" href="../../assets/alan-report.css"></head><body><header class="top"><a class="brand" href="https://piorajac.rs/"><span class="mark">△</span><span>ПИО РАЈАЦ</span></a><nav class="nav"><a href="https://piorajac.rs/monitoring-izvestaji/">Мониторинг извештаји</a><a class="active" href="../../">Светлосно загађење</a><a href="https://piorajac.rs/monitoring-svetlosno-zagadjenje/">Јавна карта</a></nav></header><section class="hero"><div class="wrap"><div class="kicker">Годишњи тренд · '.$range.'</div><h1>Светлосно<br>загађење</h1><p>'.e($summary).'</p></div></section><main class="wrap"><article class="panel"><h2>'.e($title).'</h2><p class="lead">'.e($summary).'</p><div class="meta"><div class="metric"><small>Број година</small><b>'.count($items).'</b></div><div class="metric"><small>Почетак</small><b>'.fmt_num($firstMean,3).'</b></div><div class="metric"><small>Крај</small><b>'.fmt_num($lastMean,3).'</b></div><div class="metric"><small>Укупна промена</small><b>'.($pct===null?'—':fmt_num($pct,1).'%').'</b></div></div><p>Општи тренд: <strong>'.e($trend).'</strong>. Највећа средња радијанса: <strong>'.e($highest['year_label'] ?? '—').'</strong> ('.fmt_num($highest['mean_radiance'] ?? null,3).'). Најнижа средња радијанса: <strong>'.e($lowest['year_label'] ?? '—').'</strong> ('.fmt_num($lowest['mean_radiance'] ?? null,3).').</p></article>'.$chart.'<section class="panel"><h2>Годишња серија</h2><div class="zone-table"><div class="zone-head"><span>Година</span><span>Средња радијанса</span><span>Претходна година</span><span>Промена</span><span>Извештај</span></div>'.$rows.'</div></section><section class="panel"><h2>Стручно тумачење</h2><p class="lead">Годишња серија је главни ниво за оцену стања светлосног загађења. Месечни билтени су корисни за оперативно праћење, али не смеју бити једини основ за закључак о дугорочном погоршању или побољшању.</p></section></main><footer class="footer"><div class="wrap"><b>ПИО РАЈАЦ</b><span>Годишња серија светлосног загађења</span></div></footer></body></html>';
  file_put_contents($dir.'/index.html',$html); write_json_file($root.'/data/annual-series-'.$range.'.json',['from_year'=>$fromYear,'to_year'=>$toYear,'url'=>$url,'title'=>$title,'summary'=>$summary,'years'=>count($items),'first_mean'=>$firstMean,'last_mean'=>$lastMean,'abs_change'=>$abs,'change_pct'=>$pct,'trend'=>$trend,'highest'=>$highest,'lowest'=>$lowest,'updated_at'=>date('c')]); return $url;
}


/* =========================
   v9 — брзи годишњи режим без 504
   ========================= */
function annual_index_url(array $config): string {
  return rtrim((string)($config['remote_results_base_url'] ?? ''), '/') . '/index.json';
}
function read_remote_index(array $config): ?array {
  $url = annual_index_url($config);
  return read_json_url($url, (int)($config['remote_timeout_seconds'] ?? 10));
}
function exact_annual_url(array $config, string $year): string {
  return rtrim((string)($config['remote_results_base_url'] ?? ''), '/') . '/' . rawurlencode($year) . '.json';
}
function years_from_remote_index(array $config, string $fromYear, string $toYear): array {
  $idx = read_remote_index($config);
  $years = [];
  if (is_array($idx)) {
    foreach (['yearlyPeriods','annual','years','yearly','periods'] as $key) {
      if (!isset($idx[$key]) || !is_array($idx[$key])) continue;
      foreach ($idx[$key] as $item) {
        if (!is_array($item)) continue;
        $id = (string)($item['id'] ?? $item['year'] ?? $item['period'] ?? '');
        $kind = strtolower((string)($item['kind'] ?? ''));
        if (preg_match('/^20\d{2}$/', $id) && ($kind === '' || $kind === 'yearly' || $kind === 'annual')) {
          if ($id >= $fromYear && $id <= $toYear) $years[$id] = $item + ['url'=>exact_annual_url($config,$id)];
        }
      }
    }
  }
  foreach (year_range_list($fromYear,$toYear) as $y) {
    $ys=(string)$y;
    if (!isset($years[$ys])) $years[$ys] = ['id'=>$ys,'label'=>year_label($ys),'kind'=>'yearly','url'=>exact_annual_url($config,$ys),'from_index'=>false];
    else $years[$ys]['from_index']=true;
  }
  ksort($years);
  return ['index'=>$idx,'years'=>$years,'index_url'=>annual_index_url($config)];
}
function load_exact_annual_input(array $config, string $year): ?array {
  // 1) локални годишњи JSON ако је стварно постављен
  foreach (annual_local_candidates($year) as $path) {
    if (should_ignore_input_path($path,$config)) continue;
    $j=read_json_file($path);
    if ($j) {
      $records=[]; add_annual_records_from_json($records,$j,$year);
      if (isset($records[$year])) return flatten_annual_input($records[$year],$year,$config);
    }
  }
  // 2) директан GitHub yearly JSON: public/results/YYYY.json
  $url = exact_annual_url($config,$year);
  $j = read_json_url($url, (int)($config['remote_timeout_seconds'] ?? 10));
  if ($j) {
    $records=[]; add_annual_records_from_json($records,$j,$year);
    if (isset($records[$year])) return flatten_annual_input($records[$year],$year,$config);
    // fallback: сам JSON је већ годишњи али parser није препознао годину
    return flatten_annual_input($j,$year,$config);
  }
  return null;
}
function build_year_links_text(array $years, string $basePath, string $token): string {
  $out='';
  foreach ($years as $ys=>$info) {
    $out .= ' - '.$basePath.'?token='.$token.'&year='.$ys."\n";
  }
  return $out;
}
