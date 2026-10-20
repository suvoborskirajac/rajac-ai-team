/*
 * PIO Rajac — Biljke — foto krediti v24
 * Desktop: 1 s hover -> beli tekst izvora; mouseleave -> sakrij.
 * Mobilni: ⓘ Фото tap -> prikazi/sakrij.
 */
(function(){
  'use strict';

  var CREDIT_SELECTOR='.flora-photo-credit, .rfx-page .flora-hero-credit, .rfx-page .flora-card-credit, .rfw .rfw-fig > figcaption';
  var DESKTOP_QUERY='(hover:hover) and (pointer:fine) and (min-width:901px)';

  function isDesktop(){
    return !!(window.matchMedia && window.matchMedia(DESKTOP_QUERY).matches);
  }

  function sourceUrl(credit){
    if(!credit) return '';
    if(credit.matches && credit.matches('a[href]')) return credit.getAttribute('href') || '';
    var d=credit.getAttribute && (credit.getAttribute('data-original') || credit.getAttribute('data-flora-original'));
    if(d) return d;
    var a=credit.querySelector && credit.querySelector('a[href]');
    return a ? (a.getAttribute('href') || '') : '';
  }

  function cleanLabel(s){
    return String(s || '')
      .replace(/^\s*Фото\s*:\s*/iu,'')
      .replace(/\s*[—–-]\s*оригинал\.?\s*$/iu,'')
      .replace(/\s+/g,' ')
      .replace(/\s+([.,;:])/g,'$1')
      .trim();
  }

  function ownerFor(credit){
    if(!credit) return null;
    if(credit.classList && credit.classList.contains('flora-hero-credit')){
      return credit.closest('.rf-hero') || credit.closest('.rf-hero-media') || credit.parentElement;
    }
    if(credit.classList && credit.classList.contains('flora-card-credit')){
      return credit.closest('.rf-card-img') || credit.parentElement;
    }
    if(credit.classList && credit.classList.contains('flora-photo-credit')){
      return credit.closest('figure,.rf-card-img,.rf-hero,.rf-hero-media') || credit.parentElement;
    }
    return credit.closest('.rfw-fig') || credit.parentElement;
  }

  function removeOldRuntime(owner){
    if(!owner) return;
    owner.querySelectorAll(':scope > .flora-mobile-credit-toggle, :scope > .flora-mobile-credit-panel, :scope > [class*="flora-credit-toggle-v"], :scope > [class*="flora-credit-panel-v"]').forEach(function(n){n.remove();});
    Array.prototype.slice.call(owner.classList).forEach(function(c){
      if(/^flora-credit-(?:owner|open)-v\d+$/u.test(c) || c==='flora-credit-owner-v6' || c==='flora-credit-open-v6') owner.classList.remove(c);
    });
  }

  function hideOriginalCredit(credit){
    if(!credit) return;
    credit.style.setProperty('display','none','important');
    credit.style.setProperty('opacity','0','important');
    credit.style.setProperty('visibility','hidden','important');
    credit.style.setProperty('pointer-events','none','important');
    credit.style.setProperty('transition','none','important');
    credit.style.setProperty('animation','none','important');
  }

  function closeOwner(owner){
    if(!owner) return;
    owner.classList.remove('flora-credit-open-v24');
    var b=owner.querySelector(':scope > .flora-credit-toggle-v24');
    if(b){
      b.setAttribute('aria-expanded','false');
      b.setAttribute('aria-label','Прикажи извор фотографије');
    }
  }

  function closeAll(except){
    document.querySelectorAll('.flora-credit-owner-v24.flora-credit-open-v24').forEach(function(owner){
      if(owner!==except) closeOwner(owner);
    });
  }

  function addUI(credit,index){
    var owner=ownerFor(credit);
    if(!owner) return;

    removeOldRuntime(owner);
    owner.classList.add('flora-credit-owner-v24');
    hideOriginalCredit(credit);

    /* Ukloni browser tooltip izvora ako je ranije ostao na wrapperu/sliki. */
    [owner, owner.querySelector && owner.querySelector('img')].forEach(function(el){
      if(!el || !el.getAttribute) return;
      var t=el.getAttribute('title') || '';
      if(/извор|фото|photograph|credit|wikimedia|commons/iu.test(t)) el.removeAttribute('title');
    });

    var url=sourceUrl(credit);
    var label=cleanLabel(credit.textContent);
    if(!label) label='Извор фотографије';

    var panel=document.createElement('span');
    panel.className='flora-credit-panel-v24';
    panel.setAttribute('role',url?'link':'note');
    if(url) panel.setAttribute('tabindex','0');
    panel.setAttribute('data-flora-credit-panel',String(index));
    if(url) panel.setAttribute('data-flora-original',url);
    panel.textContent=label;

    var toggle=document.createElement('button');
    toggle.type='button';
    toggle.className='flora-credit-toggle-v24';
    toggle.setAttribute('aria-label','Прикажи извор фотографије');
    toggle.setAttribute('aria-expanded','false');
    toggle.textContent='ⓘ Фото';

    function setOpen(opening){
      closeAll(owner);
      owner.classList.toggle('flora-credit-open-v24',!!opening);
      toggle.setAttribute('aria-expanded',opening?'true':'false');
      toggle.setAttribute('aria-label',opening?'Сакриј извор фотографије':'Прикажи извор фотографије');
    }

    /* DESKTOP: stari princip — tek posle 1 sekunde hover-a. */
    var hoverTimer=null;
    owner.addEventListener('mouseenter',function(){
      if(!isDesktop()) return;
      if(hoverTimer) clearTimeout(hoverTimer);
      hoverTimer=setTimeout(function(){
        hoverTimer=null;
        setOpen(true);
      },1000);
    });
    owner.addEventListener('mouseleave',function(){
      if(!isDesktop()) return;
      if(hoverTimer){ clearTimeout(hoverTimer); hoverTimer=null; }
      closeOwner(owner);
    });
    owner.addEventListener('focusin',function(){
      if(isDesktop()) setOpen(true);
    });
    owner.addEventListener('focusout',function(ev){
      if(isDesktop() && !owner.contains(ev.relatedTarget)) closeOwner(owner);
    });

    /* MOBILNI/TABLET: tap na ⓘ Фото. */
    function activate(ev){
      if(ev){
        ev.preventDefault();
        ev.stopPropagation();
        if(ev.stopImmediatePropagation) ev.stopImmediatePropagation();
      }
      if(isDesktop()) return;
      setOpen(!owner.classList.contains('flora-credit-open-v24'));
      try{toggle.blur();}catch(_e){}
    }

    var touchActivatedAt=0;
    toggle.addEventListener('touchstart',function(ev){
      ev.preventDefault(); ev.stopPropagation();
      if(ev.stopImmediatePropagation) ev.stopImmediatePropagation();
    },{passive:false});
    toggle.addEventListener('touchend',function(ev){
      touchActivatedAt=Date.now(); activate(ev);
    },{passive:false});
    toggle.addEventListener('click',function(ev){
      if(Date.now()-touchActivatedAt<700){
        ev.preventDefault(); ev.stopPropagation();
        if(ev.stopImmediatePropagation) ev.stopImmediatePropagation();
        return;
      }
      activate(ev);
    });
    ['contextmenu','selectstart','dragstart'].forEach(function(type){
      toggle.addEventListener(type,function(ev){ev.preventDefault();ev.stopPropagation();});
    });

    function openPanel(ev){
      if(ev){ev.preventDefault();ev.stopPropagation();}
      var u=panel.getAttribute('data-flora-original');
      if(u) window.location.href=u;
    }
    if(url){
      panel.addEventListener('click',openPanel);
      panel.addEventListener('keydown',function(ev){
        if(ev.key==='Enter' || ev.key===' ') openPanel(ev);
      });
    }

    owner.appendChild(panel);
    owner.appendChild(toggle);
  }

  function init(){
    Array.prototype.slice.call(document.querySelectorAll(CREDIT_SELECTOR)).forEach(addUI);
  }

  document.addEventListener('click',function(ev){
    if(isDesktop()) return;
    if(ev.target.closest && ev.target.closest('.flora-credit-toggle-v24,.flora-credit-panel-v24')) return;
    if(ev.target.closest && ev.target.closest('.flora-credit-owner-v24')) return;
    closeAll(null);
  },true);

  document.addEventListener('keydown',function(ev){
    if(ev.key==='Escape') closeAll(null);
  },true);

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
