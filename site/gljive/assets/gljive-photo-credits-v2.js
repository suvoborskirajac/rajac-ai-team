/* PIO Rajac — Gljive photo credits v2, 2026-08-17 */
(function(){
  'use strict';
  var DELAY = 1000;
  var hosts = document.querySelectorAll('.glj-hdr-img, .glj-card-photo, .glj-hero');
  hosts.forEach(function(host){
    var timer = null;
    function showSoon(){
      clearTimeout(timer);
      timer = setTimeout(function(){ host.classList.add('glj-credit-visible'); }, DELAY);
    }
    function hideNow(){
      clearTimeout(timer);
      host.classList.remove('glj-credit-visible');
    }
    host.addEventListener('mouseenter', showSoon);
    host.addEventListener('mouseleave', hideNow);
    host.addEventListener('focusin', showSoon);
    host.addEventListener('focusout', function(e){
      if(!host.contains(e.relatedTarget)) hideNow();
    });
  });

  function openCredit(el, ev){
    var url = el.getAttribute('data-credit-url');
    if(!url) return;
    if(ev){ ev.preventDefault(); ev.stopPropagation(); }
    var w = window.open(url, '_blank', 'noopener,noreferrer');
    if(w) w.opener = null;
  }

  document.addEventListener('click', function(e){
    var el = e.target.closest('.glj-card-credit[data-credit-url], .glj-hero-credit[data-credit-url]');
    if(el) openCredit(el, e);
  }, true);

  document.addEventListener('keydown', function(e){
    var el = e.target.closest && e.target.closest('.glj-card-credit[data-credit-url], .glj-hero-credit[data-credit-url]');
    if(!el) return;
    if(e.key === 'Enter' || e.key === ' '){ openCredit(el, e); }
  });
})();
