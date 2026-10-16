<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Мониторинг биодиверзитета ПИО „Рајац"</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<style>
  :root{ --navy:#0e1c2f; --gold:#c9a84c; --parchment:#faf7f0; }
  *{box-sizing:border-box;margin:0;padding:0}
  body{ background:var(--parchment); color:var(--navy);
    font-family:'Outfit',system-ui,sans-serif; padding:2rem 1rem; }
  .wrap{max-width:1150px;margin:0 auto}
  h1{ font-family:'Cormorant Garamond',Georgia,serif; font-size:2.6rem; font-weight:600; margin-bottom:.3rem; }
  .sub{color:#5a5a4a; margin-bottom:1rem}
  .switch{display:flex; gap:.45rem; flex-wrap:wrap; margin-bottom:1rem}
  .switch button{ font-family:'Outfit',sans-serif; font-size:.85rem; cursor:pointer;
    padding:.45rem 1rem; border-radius:999px; border:1px solid var(--gold);
    background:#fff; color:var(--navy); transition:.15s; }
  .switch button.active{ background:var(--navy); color:var(--parchment); border-color:var(--navy); }
  .switch button .cnt{font-family:'DM Mono',monospace;font-size:.72rem;opacity:.75;margin-left:.25rem}
  .meta{font-family:'DM Mono',monospace; font-size:.78rem; color:#8a8a72; margin-bottom:1rem; line-height:1.5}
  #map{ height:440px; border-radius:14px; border:1px solid #e7e0cf; margin-bottom:1.6rem;
    box-shadow:0 2px 10px rgba(14,28,47,.08); }
  .legenda{display:flex; gap:1rem; flex-wrap:wrap; font-size:.78rem; color:#5a5a4a; margin:-1rem 0 1.4rem; font-family:'DM Mono',monospace}
  .legenda span{display:inline-flex; align-items:center; gap:.35rem}
  .dot{width:11px; height:11px; border-radius:50%; display:inline-block; border:1px solid rgba(0,0,0,.2)}
  .grid{ display:grid; gap:1.1rem; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); }
  .card.sakriveno{ display:none; }
  .card{ background:#fff; border:1px solid #e7e0cf; border-radius:12px; overflow:hidden;
    box-shadow:0 2px 8px rgba(14,28,47,.06); text-decoration:none; color:inherit; display:block; }
  .card img{width:100%; height:150px; object-fit:cover; background:#eee; display:block}
  .noimg{height:150px;background:linear-gradient(135deg,#eee,#f8f3e6);display:flex;align-items:center;justify-content:center;color:#a69363;font-size:.82rem}
  .card .body{padding:.7rem .9rem}
  .card .nar{font-weight:600; font-size:1rem; line-height:1.2}
  .card .lat{font-style:italic; color:#777; font-size:.82rem; margin:.12rem 0 .4rem}
  .card .info{font-family:'DM Mono',monospace; font-size:.72rem; color:#999; line-height:1.35}
  .card .tag{display:inline-block; font-size:.68rem; background:var(--gold); color:var(--navy);
    padding:.1rem .45rem; border-radius:5px; margin-top:.35rem; margin-right:.25rem; font-family:'DM Mono',monospace}
  .card .tag2{background:#edf0f4;color:#5b6472}
  .loading,.empty{padding:3rem; text-align:center; color:#8a8a72; grid-column:1/-1}
  .leaflet-popup-content{font-family:'Outfit',sans-serif;min-width:200px}
  .leaflet-popup-content b{font-style:italic}
  @media(max-width:700px){body{padding:1rem .7rem}h1{font-size:2rem}#map{height:360px}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Мониторинг биодиверзитета ПИО „Рајац"</h1>
  <p class="sub">Теренска евиденција врста са iNaturalist платформе, унутар тачне границе Заштићеног предела „Рајац".</p>
  <div class="switch" id="switch">
    <button data-g="sve" class="active">Све</button>
    <button data-g="biljke">Биљке</button>
    <button data-g="ptice">Птице</button>
    <button data-g="leptiri">Лептири</button>
    <button data-g="insekti">Инсекти</button>
    <button data-g="gmizavci">Гмизавци</button>
    <button data-g="vodozemci">Водоземци</button>
    <button data-g="gljive">Гљиве</button>
    <button data-g="sisari">Сисари</button>
    <button data-g="ostalo">Остало</button>
  </div>
  <p class="meta" id="meta">Учитавање…</p>
  <div id="map"></div>
  <div class="legenda">
    <span><i class="dot" style="background:#3a7d3a"></i> Биљке</span>
    <span><i class="dot" style="background:#2d6cb3"></i> Птице</span>
    <span><i class="dot" style="background:#d4a017"></i> Лептири/Инсекти</span>
    <span><i class="dot" style="background:#b3502d"></i> Гмизавци/Водоземци</span>
    <span><i class="dot" style="background:#7d4a9e"></i> Гљиве</span>
    <span><i class="dot" style="background:#777"></i> Остало</span>
  </div>
  <div class="grid" id="grid"><div class="loading">Учитавам…</div></div>
  <div style="text-align:center;margin-top:1.2rem">
    <button id="prikazi-sve" style="display:none;font-family:'Outfit',sans-serif;font-size:.9rem;
      cursor:pointer;padding:.6rem 1.6rem;border-radius:999px;border:1px solid var(--navy);
      background:var(--navy);color:var(--parchment)">Прикажи још ↓</button>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
var map, sloj_granica, sloj_tacke;
var aktivnaGrupa = 'sve';
var API_VERZIJA = '2026-10-16-public-maps-v15';
var sveOpservacije = [];
var opservacijeZaPrikaz = [];
var prikazanoKartica = 0;
var KORAK_KARTICA = 10;
var poslednjeOsvezeno = null;
var BOJE = {
  Plantae:'#3a7d3a', Aves:'#2d6cb3', Insecta:'#d4a017',
  Reptilia:'#b3502d', Amphibia:'#b3502d', Fungi:'#7d4a9e', Mammalia:'#777'
};
var NAZIV_GRUPE = {
  sve:'све', biljke:'биљке', ptice:'птице', leptiri:'лептири', insekti:'инсекти',
  gmizavci:'гмизавци', vodozemci:'водоземци', gljive:'гљиве', sisari:'сисари', ostalo:'остало'
};
function boja(gt){ return BOJE[gt] || '#777'; }
function esc(v){ return String(v == null ? '' : v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
function dozvoljenaInatVeza(v){ var u=String(v||''); return /^https:\/\/www\.inaturalist\.org\/observations\/[0-9]+$/.test(u) ? u : ''; }
function qgLabel(q){ if(q==='research') return 'research'; if(q==='needs_id') return 'needs ID'; return q||''; }
function rankLabel(r){
  var m={species:'врста',subspecies:'подврста',variety:'варијетет',genus:'род',family:'фамилија'};
  return m[r] || r || '';
}
function kategorija(o){
  if(String(o.je_leptir || '0') === '1') return 'leptiri';
  var gt = o.grupa_taxon || '';
  if(gt === 'Plantae') return 'biljke';
  if(gt === 'Aves') return 'ptice';
  if(gt === 'Insecta') return 'insekti';
  if(gt === 'Reptilia') return 'gmizavci';
  if(gt === 'Amphibia') return 'vodozemci';
  if(gt === 'Fungi') return 'gljive';
  if(gt === 'Mammalia') return 'sisari';
  return 'ostalo';
}
function filtrirajPoGrupi(list, g){
  if(g === 'sve') return list.slice();
  return list.filter(function(o){ return kategorija(o) === g; });
}
function redosledZaPrikaz(list){
  // За „Све” први ред остаје мешовит: биљке, птице, лептири, инсекти, гљиве...
  // Када се кликне посебна категорија, редослед остаје по датуму унутар те категорије.
  if(aktivnaGrupa !== 'sve') return list.slice();
  var prioritet = ['biljke','ptice','leptiri','insekti','gmizavci','vodozemci','gljive','sisari','ostalo'];
  var kante = {};
  prioritet.forEach(function(k){ kante[k] = []; });
  list.forEach(function(o){
    var k = kategorija(o);
    if(!kante[k]) kante[k] = [];
    kante[k].push(o);
  });
  var out = [];
  var ima = true;
  while(ima){
    ima = false;
    prioritet.forEach(function(k){
      if(kante[k] && kante[k].length){
        out.push(kante[k].shift());
        ima = true;
      }
    });
  }
  return out;
}
function izracunajBrojeve(list){
  var b = {sve:list.length, biljke:0, ptice:0, leptiri:0, insekti:0, gmizavci:0, vodozemci:0, gljive:0, sisari:0, ostalo:0};
  list.forEach(function(o){ var k=kategorija(o); if(b[k] == null) b.ostalo++; else b[k]++; });
  return b;
}
function osveziBrojeve(brojevi){
  if(!brojevi) return;
  document.querySelectorAll('#switch button').forEach(function(b){
    if(!b.dataset.base) b.dataset.base = b.textContent.replace(/\s*\(\d+\)\s*$/,'').trim();
    var g = b.dataset.g;
    var n = (brojevi[g] == null) ? 0 : brojevi[g];
    b.innerHTML = esc(b.dataset.base) + ' <span class="cnt">('+esc(n)+')</span>';
  });
}
function karticaHtml(o){
  var naziv = o.narodni || o.naucni || '?';
  var img = o.foto_url ? '<img loading="lazy" decoding="async" src="'+esc(o.foto_url)+'" alt="'+esc(naziv)+'">' : '<div class="noimg">Без фотографије</div>';
  var tags = '';
  if(o.grupa_taxon) tags += '<span class="tag">'+esc(o.grupa_taxon)+'</span>';
  if(o.rank) tags += '<span class="tag tag2">'+esc(rankLabel(o.rank))+'</span>';
  if(o.quality_grade) tags += '<span class="tag tag2">'+esc(qgLabel(o.quality_grade))+'</span>';
  // A hidden/sensitive record must never become a clickable empty-link card.
  var url = dozvoljenaInatVeza(o.url);
  var element = url ? 'a' : 'div';
  return '<'+element+' class="card"'+(url?' href="'+esc(url)+'" rel="noopener noreferrer"':'')+'>'+ 
    img+
    '<div class="body"><div class="nar">'+esc(naziv)+'</div>'+ 
    '<div class="lat">'+esc(o.naucni||'')+'</div>'+ 
    '<div class="info">'+esc(o.datum||'')+' · '+esc(o.korisnik||'')+'</div>'+tags+
    '</div></'+element+'>';
}
function renderKartice(reset){
  var grid = document.getElementById('grid');
  var dugme = document.getElementById('prikazi-sve');
  if(reset){
    grid.innerHTML = '';
    prikazanoKartica = 0;
  }
  var ukupno = opservacijeZaPrikaz.length;
  if(ukupno === 0){
    grid.innerHTML = '<div class="empty">Нема опсервација у овој групи унутар границе Рајца.</div>';
    dugme.style.display = 'none';
    return;
  }
  var od = prikazanoKartica;
  var doKle = Math.min(od + KORAK_KARTICA, ukupno);
  var html = opservacijeZaPrikaz.slice(od, doKle).map(karticaHtml).join('');
  if(html) grid.insertAdjacentHTML('beforeend', html);
  prikazanoKartica = doKle;
  if(prikazanoKartica < ukupno){
    var ostalo = ukupno - prikazanoKartica;
    var sledece = Math.min(KORAK_KARTICA, ostalo);
    dugme.style.display = 'inline-block';
    dugme.textContent = 'Прикажи још '+sledece+' ('+prikazanoKartica+'/'+ukupno+') ↓';
  } else {
    dugme.style.display = 'none';
  }
}
function renderMape(list){
  if(!map) return;
  if(sloj_tacke) map.removeLayer(sloj_tacke);
  sloj_tacke = L.layerGroup();
  (list||[]).forEach(function(o){
    if(!o.lat||!o.lng) return;
    var m = L.circleMarker([o.lat,o.lng], {
      radius:6, color:'#fff', weight:1.5,
      fillColor:boja(o.grupa_taxon), fillOpacity:0.9
    });
    var foto = o.foto_url ? '<img src="'+esc(o.foto_url)+'" style="width:100%;max-width:200px;height:110px;object-fit:cover;border-radius:6px;margin-bottom:.4rem;display:block" alt="">' : '';
    m.bindPopup(
      foto+
      '<b>'+esc(o.naucni||'?')+'</b>'+(o.narodni?'<br>'+esc(o.narodni):'')+
      '<br><small>'+(esc(o.datum||''))+' · '+esc(o.korisnik||'')+'</small>'+ 
      '<br><small>'+esc(rankLabel(o.rank))+(o.quality_grade?' · '+esc(qgLabel(o.quality_grade)):'')+'</small>'+ 
      (dozvoljenaInatVeza(o.url) ? '<br><a href="'+esc(dozvoljenaInatVeza(o.url))+'" rel="noopener noreferrer">Отвори на iNaturalist →</a>' : ''));
    sloj_tacke.addLayer(m);
  });
  sloj_tacke.addTo(map);
}
function osveziMeta(){
  var ukupno = sveOpservacije.length;
  var prikaz = opservacijeZaPrikaz.length;
  var datum = poslednjeOsvezeno ? new Date(poslednjeOsvezeno).toLocaleDateString('sr-RS') : '';
  document.getElementById('meta').innerHTML =
    esc(prikaz)+' опсервација · група: '+esc(NAZIV_GRUPE[aktivnaGrupa] || aktivnaGrupa)+
    ' · укупно у бази: '+esc(ukupno)+' · извор: iNaturalist'+
    (datum ? ' · освежено: '+esc(datum) : '');
}
function prikaziGrupu(g){
  aktivnaGrupa = g;
  document.querySelectorAll('#switch button').forEach(function(b){
    b.classList.toggle('active', b.dataset.g===g);
  });
  var filtrirane = filtrirajPoGrupi(sveOpservacije, g);
  opservacijeZaPrikaz = redosledZaPrikaz(filtrirane);
  osveziBrojeve(izracunajBrojeve(sveOpservacije));
  osveziMeta();
  renderMape(opservacijeZaPrikaz);
  renderKartice(true);
}

fetch('/inat/granica_mapa.json?v='+API_VERZIJA,{cache:'force-cache'}).then(function(r){return r.json()}).then(function(granica){
  map = L.map('map').setView([44.1438, 20.2351], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom:18, attribution:'© OpenStreetMap'
  }).addTo(map);
  sloj_granica = L.polygon(granica, {
    color:'#c9a84c', weight:2.5, fillColor:'#c9a84c', fillOpacity:0.06
  }).addTo(map);
  map.fitBounds(sloj_granica.getBounds());
  ucitaj();
}).catch(function(){
  map = L.map('map').setView([44.1438, 20.2351], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18, attribution:'© OpenStreetMap'}).addTo(map);
  ucitaj();
});

function ucitaj(){
  document.getElementById('grid').innerHTML='<div class="loading">Учитавам…</div>';
  document.getElementById('meta').innerHTML='Учитавање…';
  var dugmeTokomUcitavanja = document.getElementById('prikazi-sve');
  if(dugmeTokomUcitavanja) dugmeTokomUcitavanja.style.display = 'none';

  // Увек се повлачи цела база, а категорије се после филтрирају у browser-у.
  // Тако кликом на „Лептири”, „Птице”, „Инсекти” не може да се изгуби део записа.
  var url='/inat/rajac_inat_api.php?grupa=sve&v='+encodeURIComponent(API_VERZIJA)+'&t='+(Date.now());

  fetch(url,{cache:'no-store'})
    .then(function(r){return r.json()})
    .then(function(d){
      poslednjeOsvezeno = d.osvezeno || null;
      sveOpservacije = d.opservacije || [];
      prikaziGrupu(aktivnaGrupa || 'sve');
    })
    .catch(function(){
      document.getElementById('meta').textContent='Грешка при учитавању iNaturalist података.';
      document.getElementById('grid').innerHTML='<div class="empty">Грешка при учитавању.</div>';
    });
}
document.getElementById('switch').addEventListener('click',function(e){
  var b = e.target.closest ? e.target.closest('button[data-g]') : null;
  if(b && b.dataset.g) prikaziGrupu(b.dataset.g);
});
document.getElementById('prikazi-sve').addEventListener('click',function(){
  renderKartice(false);
});
</script>
</body>
</html>
