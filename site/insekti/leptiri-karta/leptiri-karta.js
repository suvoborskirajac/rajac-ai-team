/* ПИО „Рајац“ — карте налаза дневних лептира (Biologer). Статички подаци, исти изглед као карте биљака. */
(()=>{'use strict';
const leafletCss='/biljke/assets/vendor/leaflet/leaflet.min.css';
const leafletJs='/biljke/assets/vendor/leaflet/leaflet.min.js';
const boundariesUrl='/biljke-map-live/data/boundaries.geojson';
const dataBase='/insekti/leptiri-karta/data/';
function leaf(){
 if(!document.querySelector('link[data-rfm-leaflet]')){const c=document.createElement('link');c.rel='stylesheet';c.href=leafletCss;c.dataset.rfmLeaflet='yes';document.head.appendChild(c);}
 if(window.L)return Promise.resolve(window.L);
 if(window.__rfmLeaflet)return window.__rfmLeaflet;
 window.__rfmLeaflet=new Promise((res,rej)=>{const s=document.createElement('script');s.src=leafletJs;s.onload=()=>window.L?res(window.L):rej(new Error('Leaflet'));s.onerror=()=>rej(new Error('Leaflet'));document.head.appendChild(s);});
 return window.__rfmLeaflet;
}
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
async function json(u){const r=await fetch(u,{credentials:'same-origin'});if(!r.ok)throw Error('HTTP '+r.status);return r.json();}
function datum(s){const m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(s||'');return m?(+m[3])+'. '+(+m[2])+'. '+m[1]+'.':'Датум није наведен';}
async function show(section){
 const slug=section.dataset.lkSlug;if(!/^[a-z0-9-]{1,100}$/.test(slug))return;
 const canvas=section.querySelector('.rfm-map'),status=section.querySelector('.rfm-status');
 try{
  const L=await leaf();
  const map=L.map(canvas,{zoomControl:true,scrollWheelZoom:true,preferCanvas:true});
  map.setView([44.143,20.225],12);
  L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{attribution:'Tiles © Esri, Maxar, Earthstar Geographics; Leaflet',maxZoom:19}).addTo(map);
  const [geo,data]=await Promise.all([json(boundariesUrl),json(dataBase+slug+'.json')]);
  const layers=L.geoJSON(geo,{style:f=>({color:f.properties?.color||'#e5af29',weight:f.properties?.layer==='GranicaKAT'?2.7:1.6,opacity:.95,fillOpacity:f.properties?.layer==='GranicaKAT'?0:.07})}).addTo(map);
  const pts=L.featureGroup().addTo(map);let ok=0,wait=0;const years=new Set();
  if(data.coarse){
   const cells=new Map();
   for(const p of data.points||[]){if(!Number.isFinite(p.lat)||!Number.isFinite(p.lng))continue;
    const k=p.lat.toFixed(2)+','+p.lng.toFixed(2);const c=cells.get(k)||{lat:p.lat,lng:p.lng,n:0,ok:0,y:new Set()};
    c.n++;if(p.ok)c.ok++;if(p.date)c.y.add(p.date.slice(0,4));cells.set(k,c);p.ok?ok++:wait++;if(p.date)years.add(p.date.slice(0,4));}
   for(const c of cells.values()){
    L.rectangle([[c.lat-.005,c.lng-.005],[c.lat+.005,c.lng+.005]],{color:'#fff',weight:2,fillColor:c.ok?'#007f68':'#d47c18',fillOpacity:.55})
     .bindPopup('<strong>'+esc(data.name)+'</strong> <em>'+esc(data.sci)+'</em><p>Налаза у овом пољу: '+c.n+' ('+[...c.y].sort().join(', ')+')</p><p>Заштићена врста — тачно место се не објављује.</p>').addTo(pts);}
  }else{
  for(const p of data.points||[]){
   if(!Number.isFinite(p.lat)||!Number.isFinite(p.lng))continue;
   L.circleMarker([p.lat,p.lng],{radius:7,color:'#fff',weight:2,fillColor:p.ok?'#007f68':'#d47c18',fillOpacity:1})
    .bindPopup('<strong>'+esc(data.name)+'</strong> <em>'+esc(data.sci)+'</em>'+(p.agg?'<p>Налаз групе <em>'+esc(p.agg)+'</em></p>':'')+'<p>'+esc(datum(p.date))+(p.elev?' · '+esc(p.elev)+' m н.в.':'')+'</p><p>'+(p.ok?'Налаз потврђен на Biologer-у':'Налаз чека потврду стручњака')+'</p>').addTo(pts);
   p.ok?ok++:wait++;if(p.date)years.add(p.date.slice(0,4));
  }}
  if(pts.getLayers().length){map.fitBounds(pts.getBounds().pad(.5),{maxZoom:data.coarse?13:15});}
  else{const b=layers.getBounds();if(b.isValid())map.fitBounds(b.pad(.05));}
  if(ok)section.querySelector('.rfm-legend-point').hidden=false;
  if(wait)section.querySelector('.lk-legend-wait').hidden=false;
  const y=[...years].sort();
  const n=ok+wait,naz=(n%10===1&&n%100!==11)?' налаз':' налаза';
  status.textContent='На карти: '+n+naz+(y.length?' ('+y.join(', ')+')':'')+' — '+(wait?ok+' потврђених и '+wait+' на чекању':(ok==1?'потврђен':'сви потврђени'))+'.'+' Извор: '+data.source+'.';
  setTimeout(()=>map.invalidateSize(),150);
 }catch(e){status.textContent='Карта налаза тренутно није доступна. Покушај поново касније.';console.warn('Leptiri karta:',e.message);}
}
function init(){for(const s of document.querySelectorAll('.lk-section[data-lk-slug]')){
 if('IntersectionObserver' in window){const io=new IntersectionObserver(i=>{if(i.some(x=>x.isIntersecting)){io.disconnect();show(s);}},{rootMargin:'250px'});io.observe(s);}else show(s);}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
