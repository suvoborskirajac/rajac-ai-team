<?php
declare(strict_types=1);
// Двострука заштита: без cPanel Directory Privacy (HTTP Basic) страница се не отвара.
if(empty($_SERVER['REMOTE_USER']) && empty($_SERVER['REDIRECT_REMOTE_USER'])){
    http_response_code(403);header('Content-Type: text/plain; charset=utf-8');
    exit('Управљачки приступ није омогућен: заштитите директоријум преко cPanel Directory Privacy.');
}
header('Cache-Control: no-store, private, max-age=0');
header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
?><!doctype html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>ПИО „Рајац“ — интерна евиденција налаза</title>
<link rel="stylesheet" href="/biljke/assets/vendor/leaflet/leaflet.min.css">
<style>
body{margin:0;background:#f6f8f6;color:#213d2d;font:16px/1.5 system-ui,sans-serif}
main{max-width:1180px;margin:auto;padding:22px 16px}
h1{font-size:24px;margin:0 0 6px}.small{font-size:14px;color:#435449}
#map{height:62vh;min-height:340px;border-radius:12px}
.toolbar{margin:14px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.toolbar label{font-size:13px;color:#435449;display:flex;flex-direction:column;gap:2px}
select,input{padding:8px;font:inherit;border:1px solid #c9d3cb;border-radius:6px;background:#fff;max-width:100%}
.notice{background:#fff7e5;border:1px solid #e0d1ab;border-radius:8px;padding:12px;margin:12px 0;font-size:14px}
.legend{display:flex;gap:16px;flex-wrap:wrap;font-size:13px;margin:8px 0}
.legend i{display:inline-block;width:12px;height:12px;border-radius:50%;margin-right:5px;vertical-align:-1px;border:2px solid #fff;box-shadow:0 0 0 1px #888}
a{color:#14683b}
.tbl{overflow:auto;background:#fff;border-radius:8px}
table{border-collapse:collapse;width:100%;font-size:13px}
th,td{padding:7px 8px;border-bottom:1px solid #e3e7e3;text-align:left;white-space:nowrap}
th{background:#eef2ee;position:sticky;top:0}
.st-tacna{color:#0b6b3a}.st-zamagljena{color:#9a5b00}.st-nepoznata{color:#7a7a7a}
</style>
</head>
<body>
<main>
<h1>ПИО „Рајац“ — интерна евиденција посматрања</h1>
<p class="small">Само за овлашћене кориснике управљача. Не делити снимке екрана ни извоз координата осетљивих врста.</p>
<div class="notice"><strong>Координате:</strong> „тачна“ — извор није замаглио локацију; „замагљена“ — iNaturalist је намерно померио тачку (стварна локација је у ширем кругу, није позната управљачу без сагласности аутора); „непозната“ — стари запис без података о замагљивању. „Needs ID“ није потврђена идентификација.</div>
<div class="toolbar">
<label>Група<select id="gr">
<option value="sve">Све групе</option><option value="Plantae">Биљке</option><option value="Aves">Птице</option>
<option value="leptiri">Лептири</option><option value="Insecta">Инсекти (без лептира)</option><option value="Fungi">Гљиве</option>
<option value="Mammalia">Сисари</option><option value="Reptilia">Гмизавци</option><option value="Amphibia">Водоземци</option><option value="ostalo">Остало</option>
</select></label>
<label>Квалитет<select id="q"><option value="sve">Сви</option><option value="research">Research</option><option value="needs_id">Needs ID</option></select></label>
<label>Координате<select id="loc"><option value="sve">Све</option><option value="tacna">Тачне</option><option value="zamagljena">Замагљене</option><option value="nepoznata">Непознате</option></select></label>
<label>Од датума<input id="od" type="date"></label>
<label>До датума<input id="do" type="date"></label>
<label>Врста / посматрач<input id="find" placeholder="нпр. Dactylorhiza"></label>
</div>
<div class="legend"><span><i style="background:#0b6b3a"></i>Тачна локација</span><span><i style="background:#e0a100"></i>Замагљена (приближна)</span><span><i style="background:#9a9a9a"></i>Непозната прецизност</span><span><i style="background:transparent;border-radius:0;border:2px solid #c8961e;box-shadow:none"></i>Граница ПИО</span></div>
<div id="meta" class="small" role="status" aria-live="polite">Учитавање…</div>
<div id="map" role="region" aria-label="Интерна карта налаза"></div>
<h2>Списак налаза</h2>
<div class="tbl"><table>
<thead><tr><th>Датум</th><th>Таксон</th><th>Група</th><th>Квалитет</th><th>Координате</th><th>Статус локације</th><th>GPS прецизност</th><th>Посматрач</th><th>iNaturalist</th></tr></thead>
<tbody id="rows"></tbody>
</table></div>
</main>
<script src="/biljke/assets/vendor/leaflet/leaflet.min.js"></script>
<script>
(function(){'use strict';
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const BOJA={tacna:'#0b6b3a',zamagljena:'#e0a100',nepoznata:'#9a9a9a'};
const NAZIV={tacna:'тачна',zamagljena:'замагљена',nepoznata:'непозната'};
const OSTALO=['Animalia','Arachnida','Mollusca','Protozoa','Chromista','Unknown'];
const m=L.map('map',{scrollWheelZoom:false}).setView([44.14,20.225],12);
L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:18,attribution:'Esri World Imagery'}).addTo(m);
fetch('/inat/granica_mapa.json',{cache:'force-cache'}).then(r=>r.ok?r.json():null).then(g=>{if(Array.isArray(g)&&g.length>2){const b=L.polygon(g,{color:'#c8961e',weight:2.5,fill:false}).addTo(m);m.fitBounds(b.getBounds().pad(0.05));}}).catch(()=>{});
const pts=L.layerGroup().addTo(m);let all=[];
const $=id=>document.getElementById(id);
function grupaOk(o,g){if(g==='sve')return true;if(g==='leptiri')return o.je_leptir===1;if(g==='Insecta')return o.grupa_taxon==='Insecta'&&o.je_leptir!==1;if(g==='ostalo')return OSTALO.includes(o.grupa_taxon);return o.grupa_taxon===g;}
function draw(){
 pts.clearLayers();
 const g=$('gr').value,q=$('q').value,lc=$('loc').value,od=$('od').value,dd=$('do').value,s=$('find').value.trim().toLowerCase();
 const sel=all.filter(o=>grupaOk(o,g)&&(q==='sve'||o.quality_grade===q)&&(lc==='sve'||o.location_status===lc)
   &&(!od||(o.datum&&o.datum>=od))&&(!dd||(o.datum&&o.datum<=dd))
   &&((o.naucni+' '+o.narodni+' '+o.korisnik).toLowerCase().includes(s)));
 let naKarti=0;
 for(const o of sel){
  if(typeof o.lat!=='number'||typeof o.lng!=='number')continue;
  L.circleMarker([o.lat,o.lng],{radius:o.location_status==='tacna'?6:8,color:'#fff',weight:2,fillColor:BOJA[o.location_status]||'#9a9a9a',fillOpacity:o.location_status==='tacna'?0.95:0.6})
   .bindPopup('<strong>'+esc(o.naucni)+'</strong><br>'+esc(o.datum)+' · '+esc(o.quality_grade)+'<br>Локација: '+esc(NAZIV[o.location_status]||'')+(o.positional_accuracy!=null?' · ±'+esc(Math.round(o.positional_accuracy))+' m':'')+'<br><a href="'+esc(o.url)+'">Отвори на iNaturalist-у</a>').addTo(pts);
  naKarti++;
 }
 $('meta').textContent=sel.length+' записа · на карти '+naKarti;
 $('rows').innerHTML=sel.map(o=>'<tr><td>'+esc(o.datum)+'</td><td><em>'+esc(o.naucni)+'</em>'+(o.narodni?' — '+esc(o.narodni):'')+'</td><td>'+esc(o.grupa_taxon)+'</td><td>'+esc(o.quality_grade)+'</td><td>'+(typeof o.lat==='number'?esc(o.lat.toFixed(5))+', '+esc(o.lng.toFixed(5)):'—')+'</td><td class="st-'+esc(o.location_status)+'">'+esc(NAZIV[o.location_status]||'')+'</td><td>'+(o.positional_accuracy!=null?esc(Math.round(o.positional_accuracy))+' m':'—')+'</td><td>'+esc(o.korisnik)+'</td><td><a href="'+esc(o.url)+'">'+esc(o.obs_id)+'</a></td></tr>').join('');
}
fetch('records.php',{cache:'no-store',credentials:'same-origin'})
 .then(r=>{if(!r.ok)throw Error('HTTP '+r.status);return r.json();})
 .then(d=>{all=Array.isArray(d.records)?d.records:[];draw();})
 .catch(e=>{$('meta').textContent='Евиденција није доступна: '+e.message;});
['gr','q','loc','od','do'].forEach(id=>$(id).addEventListener('change',draw));$('find').addEventListener('input',draw);
})();
</script>
</body>
</html>
