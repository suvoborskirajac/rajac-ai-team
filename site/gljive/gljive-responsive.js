(function(){
  "use strict";
  var nav=document.querySelector(".rajac-ds-nav");
  var toggle=nav&&nav.querySelector(".rajac-gljive-menu-toggle");
  function closeMenu(){if(!nav||!toggle)return;nav.classList.remove("is-open");toggle.setAttribute("aria-expanded","false");}
  if(nav&&toggle){
    toggle.addEventListener("click",function(){var open=nav.classList.toggle("is-open");toggle.setAttribute("aria-expanded",open?"true":"false");});
    nav.addEventListener("click",function(e){if(e.target.closest(".rajac-ds-nav-item"))closeMenu();});
    document.addEventListener("keydown",function(e){if(e.key==="Escape")closeMenu();});
    window.addEventListener("resize",function(){if(window.innerWidth>1100)closeMenu();},{passive:true});
  }
  var top=document.getElementById("rajac-gljive-vrh");
  if(top){
    function showTop(){top.classList.toggle("is-visible",window.scrollY>280);}
    window.addEventListener("scroll",showTop,{passive:true});
    top.addEventListener("click",function(){window.scrollTo({top:0,behavior:"smooth"});});
    showTop();
  }
})();
