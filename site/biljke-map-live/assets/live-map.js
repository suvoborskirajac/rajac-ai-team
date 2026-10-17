/* RAJAC Flora maps v19 - permission-safe KML boundary + role order + wheel zoom. No HTML writing and no external Leaflet CDN. */
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
 // Visitor invitations are based only on successfully refreshed species data.
 // Never claim that a plant is absent; only whether confirmed records are on our map.
 function invitation(kind){
  if(kind==='document'){
   callout.innerHTML='<strong>Помози да документујемо ову биљку на Рајцу!</strong> Ако имаш налаз, фотографиши га и унеси посматрање на <a href="https://www.inaturalist.org/observations/upload">iNaturalist</a>.';
  }else{
   callout.innerHTML='<strong>Настави трагање за овом биљком на Рајцу!</strong> Потражи је и на другим местима, фотографиши и додај ново посматрање на <a href="https://www.inaturalist.org/observations/upload">iNaturalist</a>.';
  }
  callout.hidden=false;
 }
 try{
  // Initialise the satellite map first. A transient GeoJSON/API failure must not leave a blank panel.
  const L=await leaf();
  const map=L.map(canvas,{zoomControl:true,scrollWheelZoom:true,preferCanvas:true});
  map.setView([44.143,20.225],12);
  L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{
   attribution:'Tiles © Esri, Maxar, Earthstar Geographics; Leaflet',maxZoom:19
  }).addTo(map);
  const [geo,data]=await Promise.all([json(base+'data/boundaries.geojson'),json(base+'data.php?slug='+encodeURIComponent(slug))]);
  if (!geo || geo.type!=='FeatureCollection' || !Array.isArray(geo.features) || !geo.features.some(f=>f?.properties?.layer==='GranicaKAT')) {
    throw new Error('Подаци о границама нису исправни');
  }
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
   const n=Number(data.research_count)||0, other=Number(data.unverified_count)||0;
   const stale=data.stale?' Последње ажурирање је старије од 24 часа.':'';
   const recordSummary='Истраживачки квалитет: '+n+'. За додатну идентификацију или допуну: '+other+'.';
   // Count registered findings, not only those promoted to Research Grade.
   // "First documented plant" was misleading when the owner already recorded the plant.
   invitation(n+other>0?'continue':'document');
   if(data.privacy==='restricted'){
    status.textContent=recordSummary+' Јавне GPS тачке за овај профил нису омогућене.'+stale;
   }else{
    const points=L.featureGroup().addTo(map);let researchShown=0,unconfirmedShown=0;
    for(const obs of data.points||[]){
     if(!Number.isSafeInteger(obs.id)||!Number.isFinite(obs.lat)||!Number.isFinite(obs.lng)||
        !['research','needs_id'].includes(obs.grade))continue;
     const checked=obs.grade==='research';
     const loc=L.circleMarker([obs.lat,obs.lng],{radius:7,color:'#fff',weight:2,
       fillColor:checked?'#007f68':'#d47c18',fillOpacity:1}).addTo(points);
     if(checked)researchShown++;else unconfirmedShown++;
     const gradeText=checked?'Истраживачки квалитет — потврђена идентификација':
       'Needs ID — идентификација још није потврђена';
     loc.bindPopup('<strong>'+escapeHtml(obs.name)+'</strong><p>'+escapeHtml(obs.date||'Датум није наведен')+'</p><p>'+gradeText+'</p><p>GPS прецизност: '+escapeHtml(obs.accuracy_m)+' m</p><p><a rel="noopener noreferrer" href="https://www.inaturalist.org/observations/'+obs.id+'">Изворни iNaturalist запис</a></p>');
    }
    status.textContent=recordSummary+' Приказано на карти: '+researchShown+' потврђених и '+unconfirmedShown+' налаза који чекају идентификацију.'+
      (n+other===0?' Ово не доказује одсуство биљке на Рајцу.':'')+stale;
    if(researchShown>0)pointLegend.hidden=false;
    if(unconfirmedShown>0){
      const legend=section.querySelector('.rfm-legend');
      if(legend&&!legend.querySelector('.rfm-legend-needsid')){
       const marker=document.createElement('span');marker.className='rfm-legend-needsid';
       marker.innerHTML='<i style="--c:#d47c18;background:#d47c18;border-radius:50%"></i> Налаз који чека идентификацију';
       legend.appendChild(marker);
      }
    }
    if(points.getLayers().length>0){const b=points.getBounds();if(b.isValid())map.fitBounds(b.pad(.4),{maxZoom:15});}
   }
  }
  setTimeout(()=>map.invalidateSize(),150);
 }catch(e){status.textContent='Подаци о границама или налазима тренутно нису доступни. Покушај поново касније.';console.warn('RAJAC Flora map:',e.message);}
}
// The map belongs above the supplementary visitor/researcher gateway.
// Move existing DOM nodes (not clones) so roles, forms, handlers and text stay intact.
function moveRolesBelowMap(mapSection){
 const parent=mapSection.parentElement;
 if(!parent)return;
 const gateway=document.getElementById('nastavi-prema-ulozi');
 if(!gateway||gateway.parentElement!==parent)return;
 let after=mapSection;
 for(const node of [gateway, document.getElementById('vodici-za-posetioce'), document.getElementById('istrazivaci-rajca')]){
  if(node&&node.parentElement===parent){after.insertAdjacentElement('afterend',node);after=node;}
 }
}
function init(){for(const s of document.querySelectorAll('.rfm-section[data-rfm-slug]')){
 moveRolesBelowMap(s);
 if('IntersectionObserver' in window){const io=new IntersectionObserver(items=>{if(items.some(i=>i.isIntersecting)){io.disconnect();show(s);}},{rootMargin:'250px'});io.observe(s);}
 else show(s);
}}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
