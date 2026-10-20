(function(){
  'use strict';
  var MAX=1100;
  function init(){
    document.querySelectorAll('.rajac-ds-nav').forEach(function(nav){
      if(nav.querySelector('.ptice-mobile-toggle')) return;
      var button=document.createElement('button');
      button.type='button';
      button.className='ptice-mobile-toggle';
      button.setAttribute('aria-expanded','false');
      button.innerHTML='<span class="ptice-mobile-toggle-label"><span aria-hidden="true">🦉</span><span>ПИО „РАЈАЦ“ · Птице</span></span><span class="ptice-mobile-toggle-icon" aria-hidden="true">☰</span>';
      nav.insertBefore(button,nav.firstChild);
      button.addEventListener('click',function(){
        var open=nav.classList.toggle('is-open');
        button.setAttribute('aria-expanded',String(open));
        button.querySelector('.ptice-mobile-toggle-icon').textContent=open?'×':'☰';
      });
      nav.querySelectorAll('a').forEach(function(link){link.addEventListener('click',function(){nav.classList.remove('is-open');button.setAttribute('aria-expanded','false');button.querySelector('.ptice-mobile-toggle-icon').textContent='☰';});});
    });
    document.addEventListener('click',function(e){
      if(innerWidth>MAX) return;
      document.querySelectorAll('.rajac-ds-nav.is-open').forEach(function(nav){if(!nav.contains(e.target)){nav.classList.remove('is-open');var b=nav.querySelector('.ptice-mobile-toggle');if(b){b.setAttribute('aria-expanded','false');b.querySelector('.ptice-mobile-toggle-icon').textContent='☰';}}});
    });
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
