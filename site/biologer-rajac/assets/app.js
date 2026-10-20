(() => {
'use strict';
const $=id=>document.getElementById(id);
const months=['','јануар','фебруар','март','април','мај','јун','јул','август','септембар','октобар','новембар','децембар'];
const colors={
 'Дневни лептири':'#d97706','Ноћни лептири':'#8e5b3a','Стенице':'#9e9d24','Биљке':'#2e7d32','Птице':'#1565c0','Сисари':'#6d4c41',
 'Гмизавци':'#7b1fa2','Водоземци':'#00838f','Вилински коњици':'#0083b8',
 'Правокрилци':'#8d6e00','Тврдокрилци':'#455a64','Пчеле, осе и мрави':'#c2185b',
 'Двокрилци':'#5d4037','Гљиве':'#6a1b9a','Мекушци':'#607d8b','Рибе':'#0277bd','Остало':'#546e7a'
};
let currentGeo={type:'FeatureCollection',features:[]}, monthlyChart, topChart, pointLayer, heatLayer;
const map=L.map('map',{zoomControl:true,minZoom:9,maxZoom:22,preferCanvas:true}).setView([44.143,20.226],13);
const osm=L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:22,maxNativeZoom:19,detectRetina:false,keepBuffer:4,attribution:'© OpenStreetMap'});
// Над Рајцем Esri нема употребљиве плочице на нивоу 19; ниво 18 се безбедно увећава до зума 22.
const sat=L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:22,maxNativeZoom:18,detectRetina:false,keepBuffer:4,updateWhenZooming:false,updateWhenIdle:true,attribution:'Esri World Imagery'});
(window.APP_CONFIG.defaultBasemap==='satellite'?sat:osm).addTo(map);
L.control.layers({'Сателитски снимак':sat,'Улична карта':osm},{}).addTo(map);
const zoneStyles={0:{color:'#d32f2f',weight:3,fillOpacity:0},1:{color:'#6a1b9a',weight:2,fillColor:'#6a1b9a',fillOpacity:.12},2:{color:'#ef6c00',weight:2,fillColor:'#ef6c00',fillOpacity:.10},3:{color:'#2e7d32',weight:2,fillColor:'#2e7d32',fillOpacity:.08}};
fetch(window.APP_CONFIG.zonesUrl).then(r=>r.json()).then(g=>{
 const layer=L.geoJSON(g,{style:f=>zoneStyles[f.properties.zone]||zoneStyles[0],onEachFeature:(f,l)=>l.bindTooltip(`${f.properties.name} — ${f.properties.area_ha} ha`)});
 layer.addTo(map);
 layer.eachLayer(l=>{if(l.feature?.properties?.id==='boundary' && l.bringToFront)l.bringToFront()});
 map.fitBounds(layer.getBounds(),{padding:[12,12]});
});

for(let i=1;i<=12;i++) $('month').insertAdjacentHTML('beforeend',`<option value="${i}">${months[i]}</option>`);

const escape=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const query=()=>{
 const p=new URLSearchParams({action:'observations'});
 ['q','group','species','year','month','zone'].forEach(id=>{if($(id).value)p.set(id,$(id).value)});
 if($('uncertain').checked)p.set('uncertain','1');
 return p;
};
const groupColor=g=>colors[g]||colors['Остало'];

function renderMap(geo){
 currentGeo=geo;
 if(pointLayer){map.removeLayer(pointLayer);pointLayer=null}
 if(heatLayer){map.removeLayer(heatLayer);heatLayer=null}
 const mode=$('mode').value;
 if(mode==='heat'){
   const pts=geo.features.map(f=>[f.geometry.coordinates[1],f.geometry.coordinates[0],1]);
   heatLayer=L.heatLayer(pts,{radius:24,blur:18,maxZoom:22,minOpacity:.28}).addTo(map);
 } else {
   const group=mode==='clusters'?L.markerClusterGroup({chunkedLoading:true,disableClusteringAtZoom:18,spiderfyOnMaxZoom:true,showCoverageOnHover:false}):L.layerGroup();
   geo.features.forEach(f=>{
      const p=f.properties,c=groupColor(p.taxon_group);
      const lat=f.geometry.coordinates[1],lng=f.geometry.coordinates[0],coarse=p.coarse==1;
      const marker=L.circleMarker([lat,lng],coarse?{radius:7,color:'#fff',weight:2,dashArray:'3 3',fillColor:c,fillOpacity:.75}:{radius:6,color:'#fff',weight:1.2,fillColor:c,fillOpacity:.9});
      if(coarse){let cellBox=null;
        marker.on('popupopen',()=>{cellBox=L.rectangle([[lat-.005,lng-.005],[lat+.005,lng+.005]],{color:'#fff',weight:2,dashArray:'6 4',fill:true,fillColor:c,fillOpacity:.12,interactive:false}).addTo(map)});
        marker.on('popupclose',()=>{if(cellBox){map.removeLayer(cellBox);cellBox=null}});}
      const photo=p.photo_url?`<img class="popup-photo" src="${escape(p.photo_url)}" alt="">`:'';
      marker.bindPopup(`<div class="popup-title"><i>${escape(p.scientific_name)}</i></div>
      <div class="popup-native">${escape(p.native_name||'')}</div>
      <div class="popup-meta"><b>Група:</b> ${escape(p.taxon_group)}<br><b>Датум:</b> ${escape(p.event_date||'непотпун датум')}<br>
      <b>Зона:</b> ${escape(p.zone_name||'—')}<br><b>Прецизност:</b> ${coarse?'поље од око 1 km':(p.accuracy?escape(p.accuracy)+' m':'није наведена')}
      ${p.observer?'<br><b>Посматрач:</b> '+escape(p.observer):''}${p.uncertain==1?'<br><b>Напомена:</b> непрецизан/осетљив налаз':''}${coarse?'<br><span class="popup-coarse">'+escape(p.coarse_note||'')+'</span>':''}</div>${photo}`);
      group.addLayer(marker);
   });
   pointLayer=group.addTo(map);
 }
 renderTable(geo.features);
 renderLegend(geo.features);
}

function renderLegend(features){
 const groups=[...new Set(features.map(f=>f.properties.taxon_group))].sort();
 $('legend').innerHTML=groups.length?groups.map(g=>`<span><i class="swatch" style="background:${groupColor(g)}"></i>${escape(g)}</span>`).join('')+(features.some(f=>f.properties.coarse==1)?'<span class="legend-coarse"><i class="swatch swatch-coarse"></i>Заштићена врста — тачка означава поље од око 1 km</span>':''):'<span>Нема приказаних налаза.</span>';
}

function renderTable(features){
 $('obsTable').innerHTML=features.slice(0,500).map(f=>{const p=f.properties;return `<tr>
 <td class="species-name"><i>${escape(p.scientific_name)}</i><span>${escape(p.native_name||'')}</span></td>
 <td>${escape(p.taxon_group)}</td><td>${escape(p.event_date||'—')}</td><td>${escape(p.zone_name||'—')}</td>
 <td>${p.coarse==1?'поље ~1 km':(p.uncertain==1?'непрецизан':(p.accuracy?escape(p.accuracy)+' m':'—'))}</td></tr>`}).join('')||'<tr><td colspan="5" class="empty">Нема налаза за изабране филтере.</td></tr>';
}

async function loadFilters(){
 const r=await fetch('api.php?action=filters'); const d=await r.json();
 if(d.error)throw new Error(d.message);
 d.groups.forEach(v=>$('group').insertAdjacentHTML('beforeend',`<option>${escape(v)}</option>`));
 d.species.forEach(v=>$('species').insertAdjacentHTML('beforeend',`<option value="${escape(v.scientific_name)}">${escape(v.scientific_name)}${v.native_name?' — '+escape(v.native_name):''} (${v.records})</option>`));
 d.years.forEach(v=>$('year').insertAdjacentHTML('beforeend',`<option>${escape(v)}</option>`));
 $('syncStatus').textContent=d.last_sync?`Последња синхронизација: ${new Date(d.last_sync.finished_at).toLocaleString('sr-RS')} — ${d.last_sync.status}`:'Подаци још нису синхронизовани.';
}

async function loadData(){
 $('apply').disabled=true;$('syncStatus').textContent='Учитавање података…';
 try{
   const p=query();
   const [g,s]=await Promise.all([fetch('api.php?'+p).then(r=>r.json()),fetch('api.php?'+new URLSearchParams([...p.entries()].filter(([k])=>k!=='action').concat([['action','summary']]))).then(r=>r.json())]);
   if(g.error)throw new Error(g.message);
   renderMap(g); renderCharts(s);
   $('recordsCount').textContent=Number(s.totals.records||0).toLocaleString('sr-RS');
   $('speciesCount').textContent=Number(s.totals.species||0).toLocaleString('sr-RS');
   $('latestDate').textContent=s.totals.latest||'—';
   $('syncStatus').textContent=`Приказано ${g.count.toLocaleString('sr-RS')} налаза.`;
 }catch(e){$('syncStatus').textContent='Грешка: '+e.message;console.error(e)}
 finally{$('apply').disabled=false}
}

function renderCharts(s){
 const m=Array(12).fill(0), labels=months.slice(1);
 (s.monthly||[]).forEach(x=>{if(x.month>=1&&x.month<=12)m[x.month-1]=Number(x.records)});
 if(monthlyChart)monthlyChart.destroy();
 monthlyChart=new Chart($('monthlyChart'),{type:'bar',data:{labels,datasets:[{label:'Број налаза',data:m}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
 const top=(s.top||[]);
 if(topChart)topChart.destroy();
 topChart=new Chart($('topChart'),{type:'bar',data:{labels:top.map(x=>x.scientific_name),datasets:[{label:'Налази',data:top.map(x=>Number(x.records))}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0}}}}});
}

$('apply').addEventListener('click',loadData);
$('reset').addEventListener('click',()=>{['q','group','species','year','month','zone'].forEach(id=>$(id).value='');$('uncertain').checked=false;$('mode').value='clusters';loadData()});
$('mode').addEventListener('change',()=>renderMap(currentGeo));
$('species').addEventListener('change',()=>{$('heatHint').textContent=$('species').value?'Топлотна карта приказује просторну густину изабране врсте.':'За научно исправну топлотну карту изаберите једну врсту.'});
$('downloadGeojson').addEventListener('click',()=>{
 const blob=new Blob([JSON.stringify(currentGeo,null,2)],{type:'application/geo+json'});
 const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='pio-rajac-biologer.geojson';a.click();URL.revokeObjectURL(a.href);
});
loadFilters().then(loadData).catch(e=>$('syncStatus').textContent='Грешка: '+e.message);
})();