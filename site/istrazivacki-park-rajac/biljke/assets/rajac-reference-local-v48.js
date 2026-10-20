/* v4.8: локална фотографија има предност; стара адреса је резервна. Без слања приватних података. */
(function(){
'use strict';
function ready(){
 document.querySelectorAll('.ref-photo-frame img[data-remote-src]').forEach(function(img){
  if(img.dataset.localFallbackAttached)return;
  img.dataset.localFallbackAttached='1';
  const frame=img.closest('.ref-photo-frame');
  const card=img.closest('.ref-photo-card');
  if(!frame)return;
  const fallback=document.createElement('div');
  fallback.className='ref-img-failure';fallback.setAttribute('role','status');
  const title=document.createElement('strong');title.textContent='Фотографија тренутно није доступна';
  const msg=document.createElement('small');msg.textContent='Отвори изворни запис фотографије. Не приказујемо слику друге врсте.';
  fallback.append(title,msg);
  let creditLink=card?.querySelector('.ref-photo-credit a[href*="commons.wikimedia.org/wiki/File"],.ref-photo-credit a[href*="piorajac.rs/biljke/"]');
  if(!creditLink){creditLink=frame.parentElement?.querySelector('a[href*="commons.wikimedia.org/wiki/File"],a[href*="piorajac.rs/biljke/"]');}
  if(creditLink){
   const link=document.createElement('a');link.href=creditLink.href;link.rel='noopener noreferrer';link.textContent='Отвори извор →';fallback.append(link);
  }
  frame.append(fallback);
  function update(status){
    if(card)card.dataset.load=status;
    document.dispatchEvent(new Event('reference-photo-state-changed'));
  }
  const fail=function(){
   if(!img.dataset.remoteAttempted){
     img.dataset.remoteAttempted='1';
     // Приступ локалном фајлу није успео; резервна фотографија само за овај таксон.
     img.src=img.dataset.remoteSrc;
     return;
   }
   frame.classList.add('is-unavailable');update('failed');
  };
  const good=function(){
   frame.classList.remove('is-unavailable');
   if(card)card.dataset.origin=img.dataset.remoteAttempted?'external':'local';
   update('ok');
  };
  img.addEventListener('error',fail);
  img.addEventListener('load',good);
  if(img.complete){if(img.naturalWidth===0)fail();else good();}
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready);else ready();
})();
