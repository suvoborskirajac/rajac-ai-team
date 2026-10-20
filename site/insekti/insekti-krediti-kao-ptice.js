(function(){
  "use strict";

  var CREDITS = window.PIO_INSEKTI_PHOTO_CREDITS || {};
  var CREDIT_CLASS = "pio-insect-photo-credit";

  function slugFromHref(href){
    try{
      var u = new URL(href, location.href);
      var m = u.pathname.match(/\/insekti\/([^\/]+)\/?$/i);
      return m ? decodeURIComponent(m[1]) : "";
    }catch(e){ return ""; }
  }

  function escHtml(s){
    return String(s||"").replace(/[&<>\"']/g,function(c){
      return ({"&":"&amp;","<":"&lt;",">":"&gt;",'\"':"&quot;","'":"&#039;"})[c];
    });
  }

  function creditHtml(c){
    if(!c) return "";
    var label = c.kind === "illustration" ? "Илустрација:" : "Фото:";
    var who = "";
    if(c.author && c.source) who = escHtml(c.author)+" / "+escHtml(c.source);
    else if(c.author) who = escHtml(c.author);
    else if(c.source) who = escHtml(c.source);
    else who = "извор није утврђен";
    var s = "<strong>"+label+" "+who+"</strong>";
    if(c.license) s += ", "+escHtml(c.license);
    if(c.original) s += " — <span class=\"pio-original\">оригинал</span>";
    return s;
  }

  function makeCredit(c){
    var span = document.createElement("span");
    span.className = CREDIT_CLASS;
    if(c.original){
      span.setAttribute("role","link");
      span.setAttribute("tabindex","0");
      span.setAttribute("aria-label", c.kind === "illustration" ? "Отвори оригиналну страницу илустрације" : "Отвори оригиналну страницу фотографије");
      span.dataset.url = c.original;
    }
    span.innerHTML = creditHtml(c);
    return span;
  }

  function decorateCard(card){
    if(!card || card.dataset.pioCreditDone === "1") return;
    var href = card.getAttribute("href") || "";
    var slug = slugFromHref(href);
    if(!slug || !CREDITS[slug]) return;
    var holder = card.querySelector(".card-img-wrap,.card-img");
    if(!holder || holder.querySelector("."+CREDIT_CLASS)) return;
    holder.appendChild(makeCredit(CREDITS[slug]));
    card.dataset.pioCreditDone = "1";
  }

  function decorate(){
    document.querySelectorAll("a.species-card,a.card[href*='/insekti/']").forEach(decorateCard);
    document.querySelectorAll(".hero-photo,.hero-photo img").forEach(function(el){
      el.removeAttribute("title");
      el.removeAttribute("data-credit");
    });
  }

  function openCredit(el){
    var url = el && el.dataset ? el.dataset.url : "";
    if(url) window.location.href=url;
  }

  document.addEventListener("click",function(ev){
    var credit = ev.target.closest("."+CREDIT_CLASS+"[role='link']");
    if(credit){
      ev.preventDefault();
      ev.stopPropagation();
      openCredit(credit);
      return;
    }
  },true);

  document.addEventListener("keydown",function(ev){
    var credit = ev.target.closest && ev.target.closest("."+CREDIT_CLASS+"[role='link']");
    if(credit && (ev.key === "Enter" || ev.key === " ")){
      ev.preventDefault();
      ev.stopPropagation();
      openCredit(credit);
    }
  },true);

  /* На touch уређајима први додир слике открива кредит, без промене layout-а. */
  document.addEventListener("pointerup",function(ev){
    if(ev.pointerType !== "touch" && ev.pointerType !== "pen") return;
    if(ev.target.closest("."+CREDIT_CLASS)) return;
    var holder = ev.target.closest(".hero-photo,.card-img-wrap,.card-img,.insect-home-hero-media");
    if(!holder || !holder.querySelector(".hero-photo-cap,."+CREDIT_CLASS)) return;
    if(!holder.classList.contains("pio-credit-open")){
      ev.preventDefault();
      ev.stopPropagation();
      document.querySelectorAll(".pio-credit-open").forEach(function(x){ if(x!==holder) x.classList.remove("pio-credit-open"); });
      holder.classList.add("pio-credit-open");
    }
  },true);

  document.addEventListener("pointerdown",function(ev){
    if(ev.pointerType !== "touch" && ev.pointerType !== "pen") return;
    if(ev.target.closest(".hero-photo,.card-img-wrap,.card-img,.insect-home-hero-media,."+CREDIT_CLASS)) return;
    document.querySelectorAll(".pio-credit-open").forEach(function(x){ x.classList.remove("pio-credit-open"); });
  },true);

  if(document.readyState === "loading") document.addEventListener("DOMContentLoaded",decorate,{once:true});
  else decorate();

  var mo = new MutationObserver(function(){ decorate(); });
  mo.observe(document.documentElement,{childList:true,subtree:true});
})();
