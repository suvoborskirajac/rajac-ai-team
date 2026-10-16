/* RAJAC Flora maps v13 - shared visual module. No HTML writing and no external Leaflet CDN. */
(()=>{'use strict';
const base='/biljke-map-live/';
const leafletCss='/biljke/assets/vendor/leaflet/leaflet.min.css';
const leafletJs='/biljke/assets/vendor/leaflet/leaflet.min.js';
function leaf(){
 if(!document.querySelector('link[data-rfm-leaflet]')){
  const css=document.createElement('link');css.rel='stylesheet';css.href=leafletCss;css.dataset.rfmLeaflet='yes';document.head.appendChild(css);
 }
 if(window.L)return Promise.resolve(window.L);
 if(window.__rfmLeaflet)return window.__rfmLeaflet;
 window.__rfmLeaflet=new Promise((resolve,reject)=>{
  const script=document.createElement('script');script.src=leafletJs;
  script.onload=()=>window.L?resolve(window.L):reject(new Error('Leaflet unavailable'));
  script.onerror=()=>reject(new Error('Local Leaflet not found'));
  document.head.appendChild(script);
 });return window.__rfmLeaflet;
}
function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
async function json(url){const r=await fetch(url,{cache:'no-store',credentials:'same-origin'});if(!r.ok)throw Error('HTTP '+r.status);return r.json();}
async function show(section){
 const slug=section.dataset.rfmSlug;if(!/^[a-z0-9-]{1,100}$/.test(slug))return;
 const canvas=section.querySelector('.rfm-map'),status=section.querySelector('.rfm-status');
 const callout=section.querySelector('.rfm-cta'),pointLegend=section.querySelector('.rfm-legend-point');
 if(!canvas||!status||!callout||!pointLegend)return;
 callout.hidden=true;pointLegend.hidden=true;
 try{
  const [L,geo,data]=await Promise.all([leaf(),json(base+'data/boundaries.geojson'),json(base+'data.php?slug='+encodeURIComponent(slug))]);
  const map=L.map(canvas,{zoomControl:true,scrollWheelZoom:false,preferCanvas:true});
  L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{
   attribution:'Tiles © Esri, Maxar, Earthstar Geographics; Leaflet',maxZoom:19
  }).addTo(map);
  const layers=L.geoJSON(geo,{style:f=>({color:f.properties?.color||'#e5af29',weight:f.properties?.layer==='GranicaKAT'?2.7:1.6,opacity:0.95,fillOpacity:f.properties?.layer==='GranicaKAT'?0:0.07})}).addTo(map);
  const boundary=L.geoJSON(geo,{filter:f=>f.properties?.layer==='GranicaKAT'});
  const bounds=boundary.getBounds().isValid()?boundary.getBounds():layers.getBounds();
  if(bounds.isValid())map.fitBounds(bounds.pad(.08),{maxZoom:14});
  if(data.status==='not_ready'){
   status.textContent='Карта је спремна. Чека се прво успешно серверско ажурирање iNaturalist података.';
  }else if(data.status==='unresolved_taxon'){
   status.textContent='Научни назив ове врсте још се повезује са iNaturalist таксоном. Налази се до тада не приказују.';
  }else if(data.status!=='ok'){
   status.textContent='Подаци о посматрањима тренутно нису доступни.';
  }else{
   const n=Number(data.research_count)||0;const other=Number(data.unverified_count)||0;
   const detail=other>0?' Додатно постоји '+other+' записа који захтевају идентификацију или допуну; нису приказани као потврђени налази.':'';
   const stale=data.stale?' Последње ажурирање је старије од 24 часа.':'';
   if(n===0){
    status.textContent='Још нема посматрања истраживачког квалитета са проверљивом GPS прецизношћу у границама ПИО.'+detail+stale;
    if(!data.stale&&other===0)callout.hidden=false;
   }else if(data.privacy==='restricted'){
    status.textContent='Посматрања истраживачког квалитета у границама ПИО: '+n+'. Тачне локације нису приказане ради заштите природе.'+detail+stale;
   }else{
    const points=L.featureGroup().addTo(map);
    for(const obs of data.points||[]){
     if(!Number.isSafeInteger(obs.id)||!Number.isFinite(obs.lat)||!Number.isFinite(obs.lng)||obs.grade!=='research')continue;
     const loc=L.circleMarker([obs.lat,obs.lng],{radius:7,color:'#fff',weight:2,fillColor:'#007f68',fillOpacity:1}).addTo(points);
     loc.bindPopup('<strong>'+escapeHtml(obs.name)+'</strong><p>'+escapeHtml(obs.date||'Датум није наведен')+'</p><p>Истраживачки квалитет</p><p>GPS прецизност: '+escapeHtml(obs.accuracy_m)+' m</p><p><a rel="noopener noreferrer" href="https://www.inaturalist.org/observations/'+obs.id+'">Изворни iNaturalist запис</a></p>');
    }
    status.textContent='Посматрања истраживачког квалитета у границама ПИО: '+n+'. Јавно приказане тачке: '+points.getLayers().length+'.'+detail+stale;
    if(points.getLayers().length>0){pointLegend.hidden=false;const b=points.getBounds();if(b.isValid())map.fitBounds(b.pad(.4),{maxZoom:15});}
   }
  }
  setTimeout(()=>map.invalidateSize(),150);
 }catch(e){status.textContent='Карта тренутно није доступна. Покушај поново касније.';console.warn('RAJAC Flora map:',e.message);}
}
function init(){for(const s of document.querySelectorAll('.rfm-section[data-rfm-slug]')){
 if('IntersectionObserver' in window){const io=new IntersectionObserver(items=>{if(items.some(i=>i.isIntersecting)){io.disconnect();show(s);}},{rootMargin:'250px'});io.observe(s);}
 else show(s);
}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
