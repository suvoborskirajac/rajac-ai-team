/* PIO Rajac — SISARI photo credits v2, 2026-08-17 */
(function(){
  'use strict';
  var HOVER_DELAY = 1000;

  function makeCredit(host){
    if(!host || host.querySelector(':scope > .rajac-photo-credit-hover')) return;
    var text = (host.getAttribute('data-photo-source') || '').trim();
    var url  = (host.getAttribute('data-source-page') || '').trim();
    if(!text) return;

    var credit = document.createElement('span');
    credit.className = 'rajac-photo-credit-hover';
    credit.textContent = text;
    credit.setAttribute('aria-label', text + (url ? '. Otvori originalnu stranicu fotografije.' : ''));
    if(url){
      credit.setAttribute('role','link');
      credit.setAttribute('tabindex','0');
      credit.setAttribute('data-credit-url',url);
      var openOriginal = function(ev){
        if(ev){ ev.preventDefault(); ev.stopPropagation(); }
        window.location.href = url;
      };
      credit.addEventListener('click', openOriginal, true);
      credit.addEventListener('keydown', function(ev){
        if(ev.key === 'Enter' || ev.key === ' '){ openOriginal(ev); }
      });
    }
    host.appendChild(credit);

    var timer = null;
    host.addEventListener('mouseenter', function(){
      clearTimeout(timer);
      timer = setTimeout(function(){ host.classList.add('rajac-photo-credit-visible'); }, HOVER_DELAY);
    });
    host.addEventListener('mouseleave', function(){
      clearTimeout(timer);
      host.classList.remove('rajac-photo-credit-visible');
    });
    host.addEventListener('focusin', function(){
      clearTimeout(timer);
      host.classList.add('rajac-photo-credit-visible');
    });
    host.addEventListener('focusout', function(ev){
      if(!host.contains(ev.relatedTarget)) host.classList.remove('rajac-photo-credit-visible');
    });
  }

  function init(){
    document.querySelectorAll('.rajac-photo-source-wrap, .page-hero.rajac-photo-source-hero').forEach(makeCredit);
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
  else init();
})();
