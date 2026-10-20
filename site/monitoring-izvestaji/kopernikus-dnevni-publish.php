<?php
/**
 * PIO Rajac — automatski izdavač kompletnog Copernicus dnevnog izveštaja
 * Verzija: 2026-10-11-v22-interactive-maps
 * Instalacija: /public_html/monitoring-izvestaji/kopernikus-dnevni-publish.php
 */
declare(strict_types=1);
@ini_set('display_errors','0');
@ini_set('log_errors','1');
@ini_set('memory_limit','768M');
@set_time_limit(300);
date_default_timezone_set('Europe/Belgrade');

const V18_VERSION = '2026-10-11-v22-interactive-maps';
const SITE = 'https://piorajac.rs';

function v18_h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function v18_valid_date($d): bool { return is_string($d) && preg_match('/^20\d{2}-\d{2}-\d{2}$/', $d); }
function v18_sr_date($s): string { return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',(string)$s,$m) ? $m[3].'.'.$m[2].'.'.$m[1].'.' : (string)$s; }
function v18_fmt($v, int $d=3): string { return is_numeric($v) ? number_format((float)$v, $d, ',', '.') : '—'; }
function v18_pct($v): string { return is_numeric($v) ? number_format((float)$v, 2, ',', '.').'%' : '—'; }
function v18_ha($v): string { return is_numeric($v) ? number_format((float)$v, 2, ',', '.').' ha' : '—'; }
function v18_json(array $payload, int $code=200): void { if(!headers_sent()){ http_response_code($code); header('Content-Type: application/json; charset=utf-8'); } echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT); exit; }
function v18_docroot(): string { return rtrim($_SERVER['DOCUMENT_ROOT'] ?? '/home/piorajac/public_html', '/'); }
function v18_monitor_root(): string { return v18_docroot() . '/monitoring-izvestaji'; }
function v18_ensure_dir(string $dir): void { if(!is_dir($dir)) @mkdir($dir,0755,true); }
function v18_read_json(string $p): ?array { if(!is_file($p) || !is_readable($p)) return null; $raw=@file_get_contents($p); if($raw===false || trim($raw)==='') return null; $j=json_decode($raw,true); return is_array($j)?$j:null; }
function v18_write_json(string $p, array $data): void { v18_ensure_dir(dirname($p)); @file_put_contents($p, json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n", LOCK_EX); @chmod($p,0644); }
function v18_write_html(string $p, string $html): void { v18_ensure_dir(dirname($p)); @file_put_contents($p, $html, LOCK_EX); @chmod($p,0644); }
function v18_index_label(string $idx): string { return ['NDVI'=>'Зелена маса и вегетациона активност','NDMI'=>'Влага у вегетацији и водни стрес','RECI'=>'Хлорофилна активност'][$idx] ?? $idx; }
function v18_index_explain(string $idx, $mean): string {
  if(!is_numeric($mean)) return '';
  $mean=(float)$mean;
  if($idx==='NDVI'){
    if($mean>=0.80) return 'Веома висока вредност NDVI указује на изразито развијену зелену масу и висок степен вегетационе активности на већем делу подручја.';
    if($mean>=0.65) return 'Висока вредност NDVI указује на добро развијену и активну вегетацију.';
    return 'Вредност NDVI указује на умерену или неуједначену зелену активност.';
  }
  if($idx==='NDMI'){
    if($mean>=0.30) return 'Вредност NDMI указује на повољну влагу у вегетацији и низак општи сигнал сушног стреса.';
    if($mean>=0.15) return 'Вредност NDMI указује на умерену влагу и потребу праћења локалних зона са сушним сигналом.';
    return 'Вредност NDMI указује на слабији водни статус и могућ сушни стрес у делу вегетације.';
  }
  if($idx==='RECI'){
    if($mean>=3.0) return 'Висока RECI вредност указује на јаку хлорофилну активност, што је у складу са активном и густом вегетацијом.';
    if($mean>=1.75) return 'RECI указује на стабилну до високу хлорофилну активност.';
    return 'RECI указује на слабију или умерену хлорофилну активност.';
  }
  return '';
}
function v18_overall(array $reports): string {
  $ndvi=$reports['NDVI']['stats']['mean'] ?? null; $ndmi=$reports['NDMI']['stats']['mean'] ?? null; $reci=$reports['RECI']['stats']['mean'] ?? null;
  if(is_numeric($ndvi) && is_numeric($ndmi) && is_numeric($reci)){
    if($ndvi>=0.80 && $ndmi>=0.30 && $reci>=3.0) return 'Сателитски индекси показују веома повољно стање вегетације: високу зелену масу, добру влагу у вегетацији и изражену хлорофилну активност.';
    if($ndvi>=0.70 && $ndmi>=0.20) return 'Сателитски индекси показују повољно и стабилно стање вегетације, уз потребу праћења локалних одступања.';
  }
  return 'Сателитски индекси показују стање које треба тумачити уз локалне услове, облачност, тип покривача и сезонску фазу вегетације.';
}
function v18_stat(array $j, string $k){ return $j['stats'][$k] ?? $j[$k] ?? null; }
function v18_percentile(array $j, string $p){ return $j['stats']['percentiles'][$p] ?? $j['stats']['percentiles'][rtrim($p,'.0')] ?? null; }
function v18_hist_candidates(string $idx, string $date): array {
  $doc=v18_docroot(); $ym=substr($date,0,7); $compact=str_replace('-','',$date);
  $roots=[
    $doc.'/kopindeksi/copernicus_data/reports/statistical_histogram',
    $doc.'/kopindeksi/indeksi/copernicus_data/reports/statistical_histogram',
    $doc.'/kopernikindeks/copernicus_data/reports/statistical_histogram',
  ];
  $out=[];
  foreach($roots as $base){
    $out[]="$base/$ym/{$idx}_{$date}/report.json";
    $out[]="$base/$ym/{$date}_{$idx}/report.json";
    $out[]="$base/{$idx}_{$date}/report.json";
    $out[]="$base/{$idx}_{$date}.json";
    $out[]="$base/$ym/{$idx}_{$date}.json";
    $out[]="$base/{$idx}_{$compact}.json";
    $out[]="$base/$ym/{$idx}_{$compact}.json";
  }
  return array_values(array_unique($out));
}
function v18_find_hist(string $idx, string $date): array {
  foreach(v18_hist_candidates($idx,$date) as $p){
    $j=v18_read_json($p);
    if(!is_array($j)) continue;
    $mean=$j['stats']['mean'] ?? $j['mean'] ?? null;
    if(is_numeric($mean)) return ['ok'=>true,'path'=>$p,'data'=>$j,'mean'=>(float)$mean];
  }
  return ['ok'=>false,'path'=>null,'data'=>null,'mean'=>null];
}
function v18_map_info(string $idx, string $date): array {
  $doc=v18_docroot();
  $idx=strtoupper(trim($idx));
  if(!in_array($idx,['NDVI','NDMI','RECI'],true) || !v18_valid_date($date)){
    return ['ok'=>false,'reason'=>'invalid-index-or-date','url'=>null,'path'=>null];
  }

  $year=substr($date,0,4);
  $base=$doc."/kopindeksi/copernicus_data/$idx/$year";

  /*
   * VAŽNO: /kopindeksi/copernicus_data je jedini kanonski izvor.
   * Stari fallback-i ka /kopindeksi/indeksi/... i /kopernikindeks/...
   * mogu sadržati zastarele ili preslikane rastere i zato se više ne koriste.
   */
  $frameCatalog=v18_read_json($base.'/frames.json');
  $catalogFrame=null;
  if(is_array($frameCatalog)){
    $list=$frameCatalog['frames'] ?? $frameCatalog;
    if(is_array($list)){
      foreach($list as $fr){
        if(!is_array($fr)) continue;
        if((string)($fr['date'] ?? '')===$date){ $catalogFrame=$fr; break; }
      }
    }
  }

  foreach(['png','jpg','jpeg','webp'] as $ext){
    $p=$base."/{$idx}_{$date}.{$ext}";
    if(!is_file($p) || (@filesize($p)<=1000)) continue;

    $baseName=basename($p);
    if(strpos($baseName,$date)===false || stripos($baseName,$idx.'_')!==0) continue;

    /* Ako katalog postoji i ima tačan kadar, proveri da URL/path ne pokazuje na drugi datum. */
    if(is_array($catalogFrame)){
      $frUrl=(string)($catalogFrame['url'] ?? $catalogFrame['path'] ?? $catalogFrame['file'] ?? '');
      if($frUrl!=='' && preg_match('/(20\\d{2}-\\d{2}-\\d{2})/',$frUrl,$m) && $m[1]!==$date){
        continue;
      }
    }

    $mtime=@filemtime($p) ?: 0;
    $url=str_replace($doc,'',$p);
    if($mtime>0) $url.='?v='.$mtime;
    return [
      'ok'=>true,
      'reason'=>'canonical-exact-date',
      'url'=>$url,
      'path'=>$p,
      'size'=>@filesize($p) ?: null,
      'mtime'=>$mtime ?: null,
      'sha256'=>@hash_file('sha256',$p) ?: null,
      'catalog_exact'=>is_array($catalogFrame),
      'catalog_frame'=>$catalogFrame,
    ];
  }

  return [
    'ok'=>false,
    'reason'=>'canonical-exact-date-map-missing',
    'url'=>null,
    'path'=>null,
    'catalog_exact'=>is_array($catalogFrame),
    'catalog_frame'=>$catalogFrame,
  ];
}
function v18_find_map(string $idx, string $date): ?string {
  $m=v18_map_info($idx,$date);
  return !empty($m['ok']) ? (string)$m['url'] : null;
}

/*
 * Granica PIO se iscrtava kao SVG preko tačnog dnevnog rastera.
 * Rasteri iz copernicus_builder.php koriste bbox zvanične PIO granice, pa se
 * koordinata može linearno preslikati u piksele slike bez menjanja samog PNG-a.
 */
function v21_boundary_geojson(): ?array {
  return v18_read_json(v18_docroot().'/kopindeksi/copernicus_data/pio_boundary.geojson');
}
function v21_collect_rings_from_geometry(array $geom): array {
  $type=(string)($geom['type'] ?? '');
  $c=$geom['coordinates'] ?? null;
  if(!is_array($c)) return [];
  if($type==='Polygon') return array_values(array_filter($c,'is_array'));
  if($type==='MultiPolygon'){
    $out=[];
    foreach($c as $poly) if(is_array($poly)) foreach($poly as $ring) if(is_array($ring)) $out[]=$ring;
    return $out;
  }
  return [];
}
function v21_boundary_rings(?array $geo): array {
  if(!is_array($geo)) return [];
  $out=[];
  $type=(string)($geo['type'] ?? '');
  if($type==='FeatureCollection'){
    foreach(($geo['features'] ?? []) as $f){
      if(!is_array($f) || !is_array($f['geometry'] ?? null)) continue;
      foreach(v21_collect_rings_from_geometry($f['geometry']) as $r) $out[]=$r;
    }
  } elseif($type==='Feature' && is_array($geo['geometry'] ?? null)) {
    $out=v21_collect_rings_from_geometry($geo['geometry']);
  } else {
    $out=v21_collect_rings_from_geometry($geo);
  }
  return $out;
}
function v21_frame_bundle(string $idx, string $date): ?array {
  if(!v18_valid_date($date)) return null;
  $year=substr($date,0,4);
  return v18_read_json(v18_docroot()."/kopindeksi/copernicus_data/$idx/$year/frames.json");
}
function v21_boundary_svg(string $idx, string $date, ?string $mapPath=null): string {
  $bundle=v21_frame_bundle($idx,$date);
  $bbox=is_array($bundle['bbox'] ?? null) ? array_values($bundle['bbox']) : null;
  if(!is_array($bbox) || count($bbox)<4) return '';
  [$minLon,$minLat,$maxLon,$maxLat]=array_map('floatval',array_slice($bbox,0,4));
  if($maxLon <= $minLon || $maxLat <= $minLat) return '';

  $w=(int)($bundle['width'] ?? 0); $h=(int)($bundle['height'] ?? 0);
  if(($w<=0 || $h<=0) && $mapPath && is_file($mapPath)){
    $sz=@getimagesize($mapPath); if(is_array($sz)){ $w=(int)($sz[0]??0); $h=(int)($sz[1]??0); }
  }
  if($w<=0 || $h<=0) return '';

  $rings=v21_boundary_rings(v21_boundary_geojson());
  if(!$rings) return '';
  $parts=[];
  foreach($rings as $ring){
    if(!is_array($ring) || count($ring)<2) continue;
    $cmd=[];
    foreach($ring as $i=>$pt){
      if(!is_array($pt) || count($pt)<2 || !is_numeric($pt[0]) || !is_numeric($pt[1])) continue;
      $lon=(float)$pt[0]; $lat=(float)$pt[1];
      $x=(($lon-$minLon)/($maxLon-$minLon))*$w;
      $y=(($maxLat-$lat)/($maxLat-$minLat))*$h;
      $x=max(-2.0,min($w+2.0,$x)); $y=max(-2.0,min($h+2.0,$y));
      $cmd[]=($i===0?'M':'L').number_format($x,2,'.','').' '.number_format($y,2,'.','');
    }
    if(count($cmd)>=2){ $cmd[]='Z'; $parts[]=implode(' ',$cmd); }
  }
  if(!$parts) return '';
  $d=v18_h(implode(' ',$parts));
  return '<svg class="pio-boundary-overlay" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none" aria-hidden="true">'
    .'<path class="pio-boundary-shadow" d="'.$d.'"></path>'
    .'<path class="pio-boundary-line" d="'.$d.'"></path>'
    .'</svg>';
}
function v21_map_html(string $idx, string $date): string {
  $mi = v18_map_info($idx, $date);
  if (empty($mi['ok']) || empty($mi['url'])) return '';
  $svg = v21_boundary_svg($idx, $date, (string)($mi['path'] ?? ''));
  return '<figure class="index-map">'
    . '<div class="map-head"><span>Карта индекса '.v18_h($idx).'</span><small>'.v18_h(v18_sr_date($date)).'</small></div>'
    . '<div class="map-media">'
    .   '<div class="map-viewport" data-panzoom="1">'
    .     '<div class="map-stage">'
    .       '<div class="map-frame"><img src="'.v18_h((string)$mi['url']).'" alt="Карта indeksa '.v18_h($idx).' za '.v18_h(v18_sr_date($date)).'">'.$svg.'</div>'
    .     '</div>'
    .   '</div>'
    .   '<div class="map-help">Точак миша: увећање · превлачење: померање · двоклик: почетни приказ</div>'
    . '</div>'
    . '</figure>';
}
function v21_existing_report_dates(?string $year=null): array {
  $root=v18_monitor_root().'/copernicus'; $out=[];
  foreach(glob($root.'/20??-??-??',GLOB_ONLYDIR) ?: [] as $d){
    $date=basename($d); if(!v18_valid_date($date)) continue;
    if($year!==null && substr($date,0,4)!==$year) continue;
    $out[$date]=true;
  }
  return array_keys($out);
}
function v21_catalog_dates(?string $year=null): array {
  $sets=[];
  foreach(['NDVI','NDMI','RECI'] as $idx){
    $dates=[];
    $years=$year!==null?[$year]:array_map('basename',glob(v18_docroot()."/kopindeksi/copernicus_data/$idx/20??",GLOB_ONLYDIR) ?: []);
    foreach($years as $yr){
      $j=v18_read_json(v18_docroot()."/kopindeksi/copernicus_data/$idx/$yr/frames.json");
      $list=$j['frames'] ?? [];
      if(!is_array($list)) continue;
      foreach($list as $fr){ if(is_array($fr) && v18_valid_date($fr['date']??null)) $dates[(string)$fr['date']]=true; }
    }
    $sets[$idx]=$dates;
  }
  $common=array_intersect_key($sets['NDVI']??[],$sets['NDMI']??[],$sets['RECI']??[]);
  $dates=array_keys($common); sort($dates,SORT_STRING); return $dates;
}
function v21_republish_all(?string $year='2026', ?string $from=null, ?string $to=null): array {
  $pool=[];
  foreach(array_merge(v21_existing_report_dates($year),v21_catalog_dates($year)) as $d) $pool[$d]=true;
  $dates=array_keys($pool); sort($dates,SORT_STRING);
  if(v18_valid_date($from)) $dates=array_values(array_filter($dates,fn($d)=>strcmp($d,$from)>=0));
  if(v18_valid_date($to)) $dates=array_values(array_filter($dates,fn($d)=>strcmp($d,$to)<=0));
  $ok=[]; $failed=[];
  foreach($dates as $d){
    $r=v18_publish_date($d);
    if(!empty($r['ok'])) $ok[]=$d; else $failed[$d]=$r['error']??'nepoznata greška';
  }
  return ['ok'=>count($failed)===0,'version'=>V18_VERSION,'year'=>$year,'from'=>$from,'to'=>$to,'found'=>count($dates),'republished'=>count($ok),'dates'=>$ok,'failed'=>$failed];
}
function v18_clean_public($v){
  if(is_array($v)){ $o=[]; foreach($v as $k=>$vv){ if(in_array($k,['report_dir','source_path','source_report','internal_report'],true)) continue; $o[$k]=v18_clean_public($vv); } return $o; }
  if(is_string($v) && strpos($v,'/home/piorajac/')!==false) return null;
  return $v;
}
function v18_metric_cards(array $reports): string { $html=''; foreach(['NDVI','NDMI','RECI'] as $idx){ $mean=$reports[$idx]['stats']['mean']??null; $html.='<div class="metric metric-'.strtolower($idx).'"><small>'.v18_h($idx).'</small><b>'.v18_h(v18_fmt($mean,3)).'</b><span>'.v18_h(v18_index_label($idx)).'</span></div>'; } return $html; }
function v18_stats_table(array $reports): string { $rows=''; foreach(['NDVI','NDMI','RECI'] as $idx){ $j=$reports[$idx]; $rows.='<tr><th>'.v18_h($idx).'</th><td>'.v18_h(v18_fmt(v18_stat($j,'mean'),4)).'</td><td>'.v18_h(v18_fmt(v18_stat($j,'min'),4)).'</td><td>'.v18_h(v18_fmt(v18_percentile($j,'5.0'),4)).'</td><td>'.v18_h(v18_fmt(v18_percentile($j,'25.0'),4)).'</td><td>'.v18_h(v18_fmt(v18_percentile($j,'50.0'),4)).'</td><td>'.v18_h(v18_fmt(v18_percentile($j,'75.0'),4)).'</td><td>'.v18_h(v18_fmt(v18_percentile($j,'95.0'),4)).'</td><td>'.v18_h(v18_fmt(v18_stat($j,'max'),4)).'</td><td>'.v18_h(v18_fmt($j['total_ha'] ?? null,2)).'</td></tr>'; } return '<table><thead><tr><th>Индекс</th><th>Средња</th><th>Мин.</th><th>P5</th><th>P25</th><th>Медијана</th><th>P75</th><th>P95</th><th>Макс.</th><th>Површина ha</th></tr></thead><tbody>'.$rows.'</tbody></table>'; }
function v18_class_table(string $idx, array $j): string { $classes=$j['classes']??[]; if(!$classes) return '<p class="muted">Нема класа за овај индекс.</p>'; $rows=''; foreach($classes as $c){ if(!is_array($c)) continue; $style=isset($c['color'])?' style="--sw:'.v18_h($c['color']).'"':''; $rows.='<tr><td><span class="swatch"'.$style.'></span>'.v18_h($c['label'] ?? $c['key'] ?? '').'</td><td>'.v18_h(v18_fmt($c['from']??null,2)).' – '.v18_h(v18_fmt($c['to']??null,2)).'</td><td>'.v18_h(v18_ha($c['area_ha']??null)).'</td><td>'.v18_h(v18_pct($c['share_pct']??null)).'</td></tr>'; } return '<table><thead><tr><th>Класа</th><th>Опсег</th><th>Површина</th><th>Удео</th></tr></thead><tbody>'.$rows.'</tbody></table>'; }
function v18_signal_table(array $j): string { $signals=$j['signals']??[]; if(!$signals) return '<p class="muted">Нема издвојених алармних сигнала за овај индекс.</p>'; $rows=''; foreach($signals as $s){ if(!is_array($s)) continue; $rows.='<tr><td>'.v18_h($s['label'] ?? $s['key'] ?? '').'</td><td>'.v18_h($s['threshold'] ?? '').'</td><td>'.v18_h(v18_ha($s['area_ha'] ?? null)).'</td><td>'.v18_h(v18_pct($s['share_pct'] ?? null)).'</td></tr>'; } return '<table><thead><tr><th>Сигнал</th><th>Праг</th><th>Површина</th><th>Удео</th></tr></thead><tbody>'.$rows.'</tbody></table>'; }
function v18_interpret_cards(array $reports): string { $html=''; foreach(['NDVI','NDMI','RECI'] as $idx){ $mean=$reports[$idx]['stats']['mean']??null; $html.='<article class="interpret"><h3>'.v18_h($idx).' — '.v18_h(v18_index_label($idx)).'</h3><p>'.v18_h(v18_index_explain($idx,$mean)).'</p></article>'; } return $html; }
function v18_render_html(string $date, array $reports, array $latest): string {
  $overall=$latest['summary']['overall_text'] ?? v18_overall($reports); $cards=v18_metric_cards($reports); $stats=v18_stats_table($reports); $interps=v18_interpret_cards($reports); $indexSections='';
  foreach(['NDVI','NDMI','RECI'] as $idx){ $j=$reports[$idx]; $mapHtml=v21_map_html($idx,$date); $indexSections .= '<section class="card index-card"><div class="section-title"><div><span class="tag">'.v18_h($idx).'</span><h2>'.v18_h(v18_index_label($idx)).'</h2></div><b class="bigval">'.v18_h(v18_fmt($j['stats']['mean']??null,3)).'</b></div><p class="lead small">'.v18_h(v18_index_explain($idx,$j['stats']['mean']??null)).'</p>'.$mapHtml.'<h3>Класе површина</h3>'.v18_class_table($idx,$j).'<h3>Издвојени сигнали</h3>'.v18_signal_table($j).'</section>'; }
  $resolution=$reports['NDVI']['resolution_m'] ?? 10; $crs=$reports['NDVI']['calculation_crs'] ?? 'EPSG:32634'; $sample=$reports['NDVI']['stats']['sampleCount'] ?? null; $nodata=$reports['NDVI']['stats']['noDataCount'] ?? null; $generated=$latest['source']['generated_at'] ?? date('c');
  return '<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Copernicus извештај — '.v18_h(v18_sr_date($date)).'</title><meta name="description" content="Дневни Copernicus извештај за ПИО Рајац: NDVI, NDMI и RECI."><style>
:root{--bg:#f5f0e6;--paper:#fffaf1;--paper2:#fffdf8;--ink:#16251a;--muted:#647064;--line:#e3d7c0;--gold:#b98a2a;--green:#145f35;--blue:#1d6178;--brown:#8a5a1c;--shadow:0 18px 44px rgba(26,43,30,.10);--serif:Georgia,"Times New Roman",serif;--sans:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}*{box-sizing:border-box}body{margin:0;background:linear-gradient(180deg,#efe6d6,#f8f4eb);color:var(--ink);font-family:var(--sans)}a{color:var(--green);font-weight:800;text-decoration:none}.top{background:#0b1c12;color:#fff;border-bottom:1px solid rgba(255,255,255,.12)}.top-inner{max-width:1180px;margin:auto;padding:14px 22px;display:flex;justify-content:space-between;gap:18px;align-items:center}.brand{font-family:var(--serif);font-size:21px;letter-spacing:.04em;color:#fff}.nav{display:flex;gap:16px;flex-wrap:wrap}.nav a{color:rgba(255,255,255,.78);font-size:13px}.wrap{max-width:1180px;margin:auto;padding:34px 22px 60px}.hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#112c1a,#233b22 48%,#a67a24);border-radius:24px;color:#fff;padding:42px;box-shadow:var(--shadow)}.hero:after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 76% 15%,rgba(255,255,255,.22),transparent 32%),linear-gradient(90deg,rgba(4,18,8,.4),rgba(4,18,8,.05));pointer-events:none}.hero>*{position:relative;z-index:1}.eyebrow{display:inline-flex;gap:8px;align-items:center;color:#f1d790;text-transform:uppercase;letter-spacing:.18em;font-size:12px;font-weight:900}.eyebrow:before{content:"";width:7px;height:7px;border-radius:50%;background:#d7b456}h1,h2,h3{font-family:var(--serif);font-weight:400;line-height:1.05;margin:0}.hero h1{font-size:clamp(38px,6vw,72px);max-width:900px;margin:14px 0}.lead{font-size:18px;line-height:1.72;color:#4b5c50}.hero .lead{color:rgba(255,255,255,.83);max-width:850px}.actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;min-height:44px;padding:0 18px;border:1px solid #d6c49f;background:#fffdf8;color:#1d3b26;font:800 13px var(--sans)}.btn.dark{background:#145f35;color:#fff;border-color:#145f35}.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin:22px 0}.metric{background:var(--paper2);border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:0 8px 22px rgba(26,43,30,.06)}.metric small,.tag{display:inline-flex;text-transform:uppercase;letter-spacing:.11em;font-size:11px;font-weight:900;color:var(--green)}.metric b{display:block;font-family:var(--serif);font-size:44px;font-weight:400;margin:8px 0 4px}.metric span{color:var(--muted);font-size:13px;line-height:1.4}.metric-ndmi small{color:var(--blue)}.metric-reci small{color:var(--brown)}.card{background:rgba(255,253,248,.96);border:1px solid var(--line);border-radius:22px;padding:26px;margin:20px 0;box-shadow:0 10px 28px rgba(26,43,30,.06)}.card h2{font-size:34px;margin-bottom:12px}.card h3{font-size:23px;margin:24px 0 10px}.small{font-size:16px}.interpret-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.interpret{background:#fff;border:1px solid #e9ddc8;border-radius:16px;padding:18px}.interpret h3{font-size:21px;margin:0 0 9px}.interpret p{font-size:14px;color:var(--muted);line-height:1.65;margin:0}table{width:100%;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #eadfcd;border-radius:14px;overflow:hidden;font-size:14px}th,td{padding:12px;border-bottom:1px solid #eee3d2;text-align:left;vertical-align:top}th{background:#fff6e7;color:#344437;font-weight:900}tr:last-child td{border-bottom:0}.swatch{display:inline-block;width:16px;height:16px;background:var(--sw,#ddd);border-radius:4px;vertical-align:-3px;margin-right:8px;border:1px solid rgba(0,0,0,.12)}.section-title{display:flex;justify-content:space-between;align-items:start;gap:18px}.bigval{font-family:var(--serif);font-size:48px;font-weight:400;color:#173c24}.note{background:#fff5dc;border:1px solid #dfc580;border-radius:16px;padding:18px;color:#4e482d;line-height:1.65}.muted{color:var(--muted)}.index-map{margin:20px 0 24px;border:1px solid #e0d3bd;border-radius:18px;overflow:hidden;background:#fff7e9}.index-map .map-head{display:flex;justify-content:space-between;gap:12px;align-items:center;padding:13px 16px;background:#fff2db;border-bottom:1px solid #e7d6b7}.index-map .map-head span{font-weight:900;color:#244331}.index-map .map-head small{color:#80622e;font-weight:800}.map-media{background:#f4eddd;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:260px;padding:18px;gap:10px}.map-viewport{position:relative;width:100%;overflow:hidden;background:#efe5d2;border:1px solid #e3d4ba;border-radius:14px;padding:16px;touch-action:none;cursor:grab}.map-viewport.is-dragging{cursor:grabbing}.map-stage{position:relative;display:block;width:100%;line-height:0;transform-origin:0 0;will-change:transform}.map-frame{position:relative;display:block;width:100%;line-height:0;border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(24,40,27,.12)}.map-frame img{display:block;width:100%;max-width:none;height:auto;user-select:none;-webkit-user-drag:none;pointer-events:none}.pio-boundary-overlay{position:absolute;inset:0;width:100%;height:100%;pointer-events:none}.pio-boundary-shadow,.pio-boundary-line{fill:none;stroke-linejoin:round;stroke-linecap:round;vector-effect:non-scaling-stroke}.pio-boundary-shadow{stroke:rgba(20,25,18,.72);stroke-width:6}.pio-boundary-line{stroke:#ffd24a;stroke-width:3.2}.map-help{font-size:12px;color:#6a735c;font-weight:600}.footer{font-size:13px;color:#6b736d;text-align:center;padding:24px}@media(max-width:820px){.grid3,.interpret-grid{grid-template-columns:1fr}.hero{padding:26px}.section-title{display:block}.top-inner{align-items:flex-start;flex-direction:column}.map-media{min-height:160px;padding:8px}.card{padding:18px}table{font-size:13px}}
</style></head><body><header class="top"><div class="top-inner"><a class="brand" href="/">ПИО „РАЈАЦ“</a><nav class="nav"><a href="/monitoring-izvestaji/">Мониторинг извештаји</a><a href="/monitoring-izvestaji/copernicus/">Copernicus архива</a><a href="/kopernikindeks/">Коперник индекс</a></nav></div></header><main class="wrap"><section class="hero"><div class="eyebrow">Copernicus / Sentinel-2</div><h1>Copernicus извештај за '.v18_h(v18_sr_date($date)).'</h1><p class="lead">'.v18_h($overall).'</p><div class="actions"><a class="btn dark" href="/monitoring-izvestaji/copernicus/">Архива Copernicus извештаја</a><a class="btn" href="/monitoring-izvestaji/">Централна архива мониторинга</a></div></section><section class="grid3">'.$cards.'</section><section class="card"><h2>Сажетак дневне анализе</h2><div class="interpret-grid">'.$interps.'</div></section><section class="card"><h2>Статистички преглед индекса</h2><p class="lead small">Табела приказује основне статистичке показатеље за цело обухваћено подручје ПИО „Рајац”.</p>'.$stats.'</section>'.$indexSections.'<section class="card"><h2>Методолошка напомена</h2><p class="lead small">Извештај је направљен из дневних статистичких резултата (хистограма) за индексе NDVI, NDMI и RECI. Вредности су израчунате за обухват ПИО „Рајац”, у прорачунској пројекцији '.v18_h($crs).', са просторном резолуцијом од приближно '.v18_h($resolution).' m. Дневни извештај је погодан за брзу оцену стања после конкретног сателитског надлета, док месечни и годишњи извештаји имају већу тежину за трендове.</p><div class="note"><b>Извор:</b> Copernicus/Sentinel-2 Statistical API histogram<br><b>Број узорака:</b> '.v18_h(v18_fmt($sample,0)).' · <b>Број пиксела без података:</b> '.v18_h(v18_fmt($nodata,0)).'<br><b>Извештај је генерисан:</b> '.v18_h($generated).'</div></section></main><footer class="footer">© ПИО Рајац 2026. Сва права задржана.</footer><script>(function(){function initPanZoom(vp){var stage=vp.querySelector(".map-stage");if(!stage)return;var state={scale:1,x:0,y:0,min:1,max:8,drag:false,sx:0,sy:0};function apply(){stage.style.transform="translate("+state.x+"px,"+state.y+"px) scale("+state.scale+")";}function clamp(v,min,max){return Math.min(max,Math.max(min,v));}function reset(){state.scale=1;state.x=0;state.y=0;apply();}vp.addEventListener("dragstart",function(e){e.preventDefault();});vp.addEventListener("wheel",function(e){e.preventDefault();var rect=vp.getBoundingClientRect();var mx=e.clientX-rect.left;var my=e.clientY-rect.top;var next=clamp(state.scale*(e.deltaY<0?1.12:1/1.12),state.min,state.max);if(Math.abs(next-1)<0.02){reset();return;}if(next===state.scale)return;var sx=(mx-state.x)/state.scale;var sy=(my-state.y)/state.scale;state.scale=next;state.x=mx-sx*state.scale;state.y=my-sy*state.scale;apply();},{passive:false});vp.addEventListener("pointerdown",function(e){if(state.scale<=1.001)return;state.drag=true;state.sx=e.clientX;state.sy=e.clientY;vp.classList.add("is-dragging");if(vp.setPointerCapture)try{vp.setPointerCapture(e.pointerId);}catch(_){}});vp.addEventListener("pointermove",function(e){if(!state.drag)return;state.x+=e.clientX-state.sx;state.y+=e.clientY-state.sy;state.sx=e.clientX;state.sy=e.clientY;apply();});function endDrag(e){state.drag=false;vp.classList.remove("is-dragging");if(vp.releasePointerCapture&&e&&e.pointerId!=null)try{vp.releasePointerCapture(e.pointerId);}catch(_){}}vp.addEventListener("pointerup",endDrag);vp.addEventListener("pointercancel",endDrag);vp.addEventListener("dblclick",function(e){e.preventDefault();reset();});reset();}document.querySelectorAll("[data-panzoom]").forEach(initPanZoom);})();</script></body></html>';
}
function v18_publish_date(string $date): array {
  if(!v18_valid_date($date)) return ['ok'=>false,'date'=>$date,'error'=>'Погрешан датум.'];
  $hist=[]; $reports=[]; $missing=[]; $paths=[];
  foreach(['NDVI','NDMI','RECI'] as $idx){
    $found=v18_find_hist($idx,$date); $paths[$idx]=$found['path'];
    if(empty($found['ok']) || !is_array($found['data'])){ $missing[]=$idx; continue; }
    $hist[$idx]=$found; $reports[$idx]=v18_clean_public($found['data']);
  }
  if($missing) return ['ok'=>false,'date'=>$date,'error'=>'Недостаје histogram/statistika за: '.implode(', ',$missing),'paths_checked'=>array_map('v18_hist_candidates',['NDVI','NDMI','RECI'],[$date,$date,$date])];
  $root=v18_monitor_root(); $outDir="$root/copernicus/$date"; v18_ensure_dir($outDir); v18_ensure_dir("$root/copernicus/data");
  $generated=date(DateTimeInterface::ATOM); $overall=v18_overall($reports); $reportUrl="/monitoring-izvestaji/copernicus/$date/";
  $latest=['ok'=>true,'type'=>'copernicus_daily','summary'=>['date'=>$date,'title'=>'Copernicus извештај за '.v18_sr_date($date),'report_url'=>$reportUrl,'overall_text'=>$overall],'indices'=>[],'source'=>['service'=>'Copernicus/Sentinel-2 Statistical API histogram','generated_at'=>$generated,'builder'=>V18_VERSION]];
  foreach(['NDVI','NDMI','RECI'] as $idx){ $j=$reports[$idx]; $latest['indices'][$idx]=['mean'=>$j['stats']['mean']??null,'min'=>$j['stats']['min']??null,'max'=>$j['stats']['max']??null,'p90'=>$j['stats']['percentiles']['90.0']??$j['stats']['percentiles']['95.0']??null,'pixel_count'=>$j['total_pixel_count']??$j['stats']['sampleCount']??null,'total_ha'=>$j['total_ha']??null,'public_map'=>v18_find_map($idx,$date)]; }
  $full=$reports; $report=$latest + ['full_indices'=>$full, 'histogram_paths'=>$paths, 'report_url'=>SITE.$reportUrl, 'generated_at'=>$generated];
  v18_write_json("$outDir/report.json", $report); v18_write_json("$root/copernicus/data/latest-$date.json", $latest); v18_write_html("$outDir/index.html", v18_render_html($date,$reports,$latest));
  // latest ide na najnoviji datum koji objavljujemo
  $cur=v18_read_json("$root/copernicus/latest.json"); $curDate=$cur['summary']['date'] ?? $cur['date'] ?? '';
  if(!$curDate || strcmp($date,(string)$curDate)>=0) v18_write_json("$root/copernicus/latest.json", $report);
  $regFile="$root/copernicus/data/daily-reports.json"; $reg=v18_read_json($regFile); if(!is_array($reg)) $reg=[]; $reg[$date]=['date'=>$date,'title'=>'Copernicus извештај за '.v18_sr_date($date),'url'=>$reportUrl,'ndvi'=>$latest['indices']['NDVI']['mean']??null,'ndmi'=>$latest['indices']['NDMI']['mean']??null,'reci'=>$latest['indices']['RECI']['mean']??null,'generated_at'=>$generated]; ksort($reg); v18_write_json($regFile,$reg);
  return ['ok'=>true,'date'=>$date,'url'=>SITE.$reportUrl,'ndvi'=>$latest['indices']['NDVI']['mean']??null,'ndmi'=>$latest['indices']['NDMI']['mean']??null,'reci'=>$latest['indices']['RECI']['mean']??null,'paths'=>$paths];
}
function v18_http_get_json(string $url): array {
  $body=false; $code=0; $err='';
  if(function_exists('curl_init')){ $ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>260,CURLOPT_SSL_VERIFYPEER=>false]); $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch); }
  else { $ctx=stream_context_create(['http'=>['timeout'=>260], 'ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]); $body=@file_get_contents($url,false,$ctx); $code=$body===false?0:200; }
  $j=is_string($body)?json_decode($body,true):null; return ['ok'=>$code>=200&&$code<300&&is_array($j)&&($j['ok']??true)!==false,'http_code'=>$code,'json'=>$j,'error'=>$err ?: (is_string($body)?substr(strip_tags($body),0,600):'')];
}
$action=$_GET['action'] ?? '';
if($action==='publish'){
  $date=(string)($_GET['date']??''); v18_json(v18_publish_date($date));
}
if($action==='republish_all'){
  $year=(string)($_GET['year']??'2026'); if(!preg_match('/^20\d{2}$/',$year)) $year='2026';
  $from=(string)($_GET['from']??''); $to=(string)($_GET['to']??'');
  v18_json(v21_republish_all($year,$from?:null,$to?:null));
}
if($action==='check'){
  $date=(string)($_GET['date']??''); $out=['ok'=>true,'date'=>$date,'version'=>V18_VERSION,'indices'=>[]]; foreach(['NDVI','NDMI','RECI'] as $idx){ $f=v18_find_hist($idx,$date); $mi=v18_map_info($idx,$date); $out['indices'][$idx]=['ok'=>$f['ok'],'path'=>$f['path'],'mean'=>$f['mean'],'map'=>$mi['url']??null,'map_info'=>$mi]; } v18_json($out);
}
?><!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Copernicus дневни извештаји</title><style>
body{margin:0;background:#f7f4eb;color:#17251b;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;line-height:1.55}.hero{background:#0b2817;color:#fff;padding:42px 22px}.wrap{width:min(1080px,calc(100% - 32px));margin:auto}.card{background:#fffdf8;border:1px solid #e3dccb;border-radius:16px;padding:22px;margin:24px 0}.btn{display:inline-block;background:#155f36;color:#fff;text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:850;margin:4px;border:0;cursor:pointer}a{color:#155f36}code{background:#f2ecd9;padding:2px 5px;border-radius:5px}.ok{color:#116136}.bad{color:#8a1f11}.warn{color:#9b6415}.muted{color:#647166}.log{font-family:ui-monospace,Consolas,monospace;background:#0e1712;color:#eef8ef;border-radius:12px;padding:14px;white-space:pre-wrap;min-height:320px;max-height:560px;overflow:auto}progress{width:100%;height:24px}</style></head><body><section class="hero"><div class="wrap"><h1>Copernicus дневни извештаји</h1><p>Ова страница прави јавни Copernicus извештај када постоје комплетне NDVI, NDMI и RECI статистике.</p></div></section><main class="wrap"><article class="card"><p><button id="start" class="btn">Покрени израду извештаја</button><a class="btn" href="/monitoring-izvestaji/copernicus/2026-06-29/">29.06.2026.</a><a class="btn" href="/monitoring-izvestaji/copernicus/2026-07-04/">04.07.2026.</a><a class="btn" href="/monitoring-izvestaji/dnevni/latest.html">Дневни latest</a></p><progress id="prog" max="1" value="0"></progress><p id="status" class="muted">Спремно.</p><div id="log" class="log"></div></article></main><script>
const dates=['2026-06-29','2026-07-04']; const indices=['NDVI','NDMI','RECI']; const logEl=document.getElementById('log'), statusEl=document.getElementById('status'), prog=document.getElementById('prog'), startBtn=document.getElementById('start');
function log(s,cls=''){const d=document.createElement('div'); if(cls)d.className=cls; d.textContent=new Date().toLocaleTimeString()+'  '+s; logEl.appendChild(d); logEl.scrollTop=logEl.scrollHeight;}
async function getJson(url){const res=await fetch(url,{cache:'no-store'}); const txt=await res.text(); let j; try{j=JSON.parse(txt)}catch(e){throw new Error(txt.slice(0,500))} if(!res.ok||j.ok===false) throw new Error(j.error||JSON.stringify(j).slice(0,500)); return j;}
async function run(){startBtn.disabled=true; logEl.textContent=''; let steps=[]; for(const d of dates){ for(const idx of indices)steps.push({type:'hist',date:d,index:idx}); steps.push({type:'publish',date:d}); } steps.push({type:'daily'}); prog.max=steps.length; prog.value=0; let ok=0,bad=0; log('Почињем. Не затварај прозор.'); for(let i=0;i<steps.length;i++){const s=steps[i]; statusEl.textContent=`Корак ${i+1}/${steps.length}`; try{ if(s.type==='hist'){ log(`Статистика ${s.index} ${s.date} ...`); const j=await getJson(`/kopindeksi/generisi-nedostajuce-histograme.php?date=${encodeURIComponent(s.date)}&index=${encodeURIComponent(s.index)}&force=1&json=1&t=${Date.now()}`); const it=(j.items||[])[0]||{}; if(it.ok){ok++; log(`OK ${s.index} ${s.date} · ${it.action||''}`,'ok')} else throw new Error(it.error||'није успело'); } else if(s.type==='publish'){ log(`Јавни извештај ${s.date} ...`); const j=await getJson(`/monitoring-izvestaji/napravi-kopernikus-29-06-i-04-07.php?action=publish&date=${encodeURIComponent(s.date)}&t=${Date.now()}`); ok++; log(`OK извештај ${s.date}: ${j.url}`,'ok'); } else { log('Обнова дневних мониторинг извештаја ...'); try{ const j=await getJson(`/monitoring-izvestaji/generisi-dnevne-izvestaje.php?from=2026-06-29&to=2026-07-04&json=1&t=${Date.now()}`); log(`OK дневни извештаји · made=${(j.made||[]).length}`,'ok'); ok++; }catch(e){ log('Дневни мониторинг није освежен аутоматски: '+e.message,'warn'); } } } catch(e){bad++; log('ГРЕШКА: '+e.message,'bad');} prog.value=i+1; await new Promise(r=>setTimeout(r,600)); } statusEl.textContent=`Завршено · OK ${ok} · грешке ${bad}.`; startBtn.disabled=false; }
startBtn.addEventListener('click',run);
</script></body></html>
