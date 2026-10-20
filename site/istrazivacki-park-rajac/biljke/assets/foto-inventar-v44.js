(async function(){
'use strict';
const C=window.PhotoCore;
if(!C)return;
const taxa=C.taxa(),out=document.getElementById('fi-grid'),search=document.getElementById('fi-search'),group=document.getElementById('fi-group'),filter=document.getElementById('fi-filter'),msg=document.getElementById('fi-message');
const photosByTaxon=new Map(),photos=[];
let storageError='';
try{const data=await C.all('photos');for(const p of data){if(!p||!p.taxonId)continue;photos.push(p);if(!photosByTaxon.has(p.taxonId))photosByTaxon.set(p.taxonId,[]);photosByTaxon.get(p.taxonId).push(p);}}
catch(e){storageError=String(e?.message||e);}
const licenseOK=p=>['CC BY 4.0','CC BY-SA 4.0','CC0','дозвола за пројекат'].includes(p.license)&&!!String(p.source||'').trim();
const metaOK=p=>!!String(p.author||'').trim()&&licenseOK(p)&&!!String(p.origin||'').trim()&&p.origin!=='непознато'&&!!String(p.organ||'').trim()&&p.file instanceof Blob;
const status=t=>{const arr=photosByTaxon.get(t.id)||[];if(!arr.length)return 'missing';return arr.some(metaOK)?'meta':'partial';};
const groups=[...new Set(taxa.map(t=>t.group))].sort((a,b)=>a.localeCompare(b,'sr'));
for(const g of groups){const opt=document.createElement('option');opt.value=g;opt.textContent=g;group.append(opt);}
document.getElementById('fi-photos').textContent=storageError?'н/д':String(photos.length);
document.getElementById('fi-with').textContent=storageError?'н/д':String(photosByTaxon.size);
document.getElementById('fi-meta').textContent=storageError?'н/д':String(photos.filter(metaOK).length);
const norm=s=>String(s||'').toLocaleLowerCase('sr').normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();
const text=(tag,cls,content)=>{const el=document.createElement(tag);if(cls)el.className=cls;if(content!==undefined)el.textContent=String(content);return el;};
function makeLink(href,label){const a=text('a','',label);a.href=href;return a;}
function render(){out.replaceChildren();const q=norm(search.value),g=group.value,f=filter.value;
const matches=taxa.filter(t=>(!q||norm([t.name,t.scientific,t.family,t.group].join(' ')).includes(q))&&(!g||t.group===g)&&(!f||status(t)===f));
for(const t of matches){const arr=photosByTaxon.get(t.id)||[],st=status(t);
 const card=text('article','fi-card'),head=text('div','fi-card-head');head.append(text('div','fi-family',`ПРОФИЛ ${String(t.no).padStart(2,'0')} · ${t.group}`),text('h3','',t.name),text('p','fi-latin',t.scientific));
 const badge=text('span','fi-status '+st,st==='missing'?'Нема снимка':st==='meta'?'Метаподаци постоје':'Потребна допуна');card.append(head,badge);
 const req=text('p','fi-req');req.append(text('strong','', 'Шта треба фотографисати: '),document.createTextNode(t.checklist||'Дијагностичка обележја и целу биљку.'));card.append(req);
 const count=text('p','fi-count',`${arr.length} локалних фотографија; ${arr.filter(metaOK).length} са основним метаподацима. Није стручна потврда.`);card.append(count);
 const actions=text('div','fi-links');actions.append(makeLink('foto-radionica.html?taxon='+encodeURIComponent(t.id),'Додај фотографију →'),makeLink(t.href,'Профил врсте'),makeLink(t.source,'Изворна страница ↗'));actions.lastChild.target='_blank';actions.lastChild.rel='noopener noreferrer';card.append(actions);out.append(card);
}
msg.textContent=(storageError?'Локална база није доступна ('+storageError+'). Приказ је информативан. ':'')+`Приказано ${matches.length} од 31 профила. Статуси фотографија односе се само на овај прегледач.`;
}
[search,group,filter].forEach(el=>el.addEventListener(el===search?'input':'change',render));render();
document.getElementById('fi-export').addEventListener('click',()=>{
 const rows=taxa.map(t=>{const arr=photosByTaxon.get(t.id)||[];return {profil:t.id,naziv:t.name,naucni_naziv:t.scientific,familija:t.family,grupa:t.group,izvorni_status:t.claim,fotografije_u_ovom_pregledacu:storageError?'nepoznato':arr.length,osnovni_metapodaci:storageError?'nepoznato':arr.filter(metaOK).length,plan_fotografisanja:t.checklist,profil_stranica:t.href,izvorna_stranica:t.source,status:'POTREBNA STRUCNA I PRAVNA PROVERA'};});
 const keys=['profil','naziv','naucni_naziv','familija','grupa','izvorni_status','fotografije_u_ovom_pregledacu','osnovni_metapodaci','plan_fotografisanja','profil_stranica','izvorna_stranica','status'];
 C.download(new Blob([C.csv(rows,keys)],{type:'text/csv;charset=utf-8'}),'BILJKE-RAJCA-fotografski-inventar-v4.4.csv');
});
})();
