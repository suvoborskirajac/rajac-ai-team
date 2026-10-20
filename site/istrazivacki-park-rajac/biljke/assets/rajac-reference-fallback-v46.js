(function(){
'use strict';
function attachFallbacks(){
  document.querySelectorAll('.ref-photo-frame img').forEach(function(img){
    if(img.dataset.fallbackAttached)return;
    img.dataset.fallbackAttached='1';
    const frame=img.closest('.ref-photo-frame');
    const card=img.closest('.ref-photo-card');
    if(!frame||!card)return;
    const a=card.querySelector('.ref-photo-credit a[href*="commons.wikimedia.org/wiki/File"], .ref-photo-credit a[href*="piorajac.rs/biljke/"]');
    const fallback=document.createElement('div');fallback.className='ref-img-failure';fallback.setAttribute('role','status');
    const title=document.createElement('strong');title.textContent='Фотографија тренутно није доступна';
    const p=document.createElement('small');p.textContent='Провери интернет везу и отвори изворни запис. Не приказујемо заменску слику друге врсте.';
    fallback.append(title,p);
    if(a){const link=document.createElement('a');link.href=a.href;link.rel='noopener noreferrer';link.textContent='Отвори извор →';fallback.append(link);}
    frame.append(fallback);
    const fail=()=>{frame.classList.add('is-unavailable');card.dataset.load='failed';document.dispatchEvent(new Event('reference-photo-state-changed'))};
    const good=()=>{card.dataset.load='ok';frame.classList.remove('is-unavailable');document.dispatchEvent(new Event('reference-photo-state-changed'))};
    img.addEventListener('error',fail);
    img.addEventListener('load',good);
    if(img.complete){if(img.naturalWidth===0)fail();else good()}
  });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',attachFallbacks);else attachFallbacks();
})();