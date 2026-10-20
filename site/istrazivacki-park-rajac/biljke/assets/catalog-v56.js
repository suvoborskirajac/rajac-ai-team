(()=>{

'use strict';
const escapeHtml=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const data=JSON.parse(document.getElementById('embedded-species').textContent);
const months=['јануару','фебруару','марту','априлу','мају','јуну','јулу','августу','септембру','октобру','новембру','децембру'];
const flowers=x=>x.grupa!=='папрати'&&Number(x.od)>0&&Number(x.do)>=Number(x.od);
const floweringIn=(x,m)=>flowers(x)&&m>=x.od&&m<=x.do;
const monthPeriod=x=>flowers(x)?`Оријентационо цветање: ${x.od}.–${x.do}. месец`:'Папрат не цвета — размножава се спорама';
function card(x,m=null){
 const flowering=m!==null&&floweringIn(x,m);
 const nonseason=m!==null&&!flowering;
 const caption=x.photoAlt?.split('—').slice(1).join('—').trim()||'Дијагностички детаљ';
 const note=x.grupa==='папрати'?'Папрат не цвета и не припада листи цветница.':nonseason?'У изабраном месецу није означена као цветајућа.':'Референтни снимак за препознавање, не доказ са Рајца.';
 return `<article class="v56-card" data-taxon="${escapeHtml(x.latin)}"><a class="v56-photo" href="${escapeHtml(x.profile)}" aria-label="Профил: ${escapeHtml(x.naziv)}"><img loading="lazy" decoding="async" src="${escapeHtml(x.photoLocal)}" data-remote-src="${escapeHtml(x.photoRemote)}" alt="${escapeHtml(x.photoAlt)}"/><span class="v56-organ">${escapeHtml(x.organ)}</span><span class="v56-photo-fallback">Фотографија није доступна. Отвори профил и извор.</span></a><div class="v56-card-body"><span class="v56-eyebrow">${escapeHtml(x.grupa)} · ${escapeHtml(x.staniste)}</span><h3>${escapeHtml(x.naziv)}</h3><em class="v56-latin">${escapeHtml(x.latin)}</em><div class="v56-phenology" data-flowering="${flowers(x)?'yes':'no'}">${escapeHtml(monthPeriod(x))}</div><p class="v56-card-desc">${escapeHtml(x.opis)}</p><p class="v56-card-note">${escapeHtml(caption)}. ${escapeHtml(note)}</p><div class="v56-card-actions"><a href="${escapeHtml(x.profile)}">Фотографски водич →</a><a href="${escapeHtml(x.photoSource)}" rel="noopener noreferrer">Извор фотографије ↗</a></div><span class="v56-card-credits">${escapeHtml(x.credit)}</span></div></article>`;
}
function activatePhotos(scope){
 scope.querySelectorAll('.v56-photo img[data-remote-src]').forEach(img=>{
  let attempted=false;
  img.addEventListener('error',()=>{
   if(!attempted){attempted=true;img.src=img.getAttribute('data-remote-src');return;}
   img.closest('.v56-photo')?.classList.add('is-unavailable');
  });
 });
}
const normalize=v=>String(v||'').toLocaleLowerCase('sr').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[абвгдђежзијклмнопрстћуфхцчџшљњ]/g,c=>({'а':'a','б':'b','в':'v','г':'g','д':'d','ђ':'dj','е':'e','ж':'z','з':'z','и':'i','ј':'j','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','ћ':'c','у':'u','ф':'f','х':'h','ц':'c','ч':'c','џ':'dz','ш':'s','љ':'lj','њ':'nj'}[c]||c));

 const grid=document.getElementById('species');const status=document.getElementById('catalogue-status');
 const search=document.getElementById('search'),group=document.getElementById('group'),hab=document.getElementById('habitat');
 for(const [el,values] of [[group,[...new Set(data.map(x=>x.grupa))]],[hab,[...new Set(data.map(x=>x.staniste))]]]){
  values.sort((a,b)=>a.localeCompare(b,'sr')).forEach(v=>{let o=document.createElement('option');o.value=v;o.textContent=v;el.appendChild(o)});
 }
 function render(){
  const q=normalize(search.value),g=group.value,h=hab.value;
  const list=data.filter(x=>(!q||normalize(x.naziv+' '+x.latin).includes(q))&&(!g||x.grupa===g)&&(!h||x.staniste===h));
  grid.innerHTML=list.length?list.map(x=>card(x)).join(''):'<div class="v56-no-results">Нема резултата за овај избор. Промени филтер или претрагу.</div>';
  activatePhotos(grid);status.textContent=`Приказано ${list.length} од ${data.length} образовних врста`;
 }
 for(const el of [search,group,hab])el.addEventListener(el===search?'input':'change',render);
 render();
})();
