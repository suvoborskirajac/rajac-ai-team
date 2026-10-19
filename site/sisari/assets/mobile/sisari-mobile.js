(function () {
  "use strict";

  var MOBILE_MAX = 1100;
  var body = document.body;
  var nav = document.querySelector(".rajac-ds-nav");
  var navInner = nav && nav.querySelector(".rajac-ds-nav-inner");

  if (!body || !nav || !navInner) return;

  body.classList.add("pio-mobile-enhanced");

  var toggle = document.createElement("button");
  toggle.type = "button";
  toggle.className = "pio-mobile-menu-toggle";
  toggle.setAttribute("aria-expanded", "false");
  toggle.setAttribute("aria-controls", "pio-mobile-menu-links");
  toggle.setAttribute("aria-label", "Отвори мени");
  toggle.textContent = "МЕНИ";

  var links = Array.prototype.slice.call(navInner.querySelectorAll(".rajac-ds-nav-item"));
  links.forEach(function (link, index) {
    if (index === 0) link.id = "pio-mobile-menu-links";
  });
  navInner.insertBefore(toggle, links[0] || null);

  var backdrop = document.createElement("button");
  backdrop.type = "button";
  backdrop.className = "pio-mobile-menu-backdrop";
  backdrop.setAttribute("aria-label", "Затвори мени");
  document.body.appendChild(backdrop);

  var toTop = document.createElement("button");
  toTop.type = "button";
  toTop.className = "pio-mobile-to-top";
  toTop.setAttribute("aria-label", "Врати се на врх странице");
  toTop.textContent = "Врх ↑";
  document.body.appendChild(toTop);

  function isMobile() {
    return window.matchMedia("(max-width: " + MOBILE_MAX + "px)").matches;
  }

  function closeMenu(restoreFocus) {
    if (!body.classList.contains("pio-mobile-menu-open")) return;
    body.classList.remove("pio-mobile-menu-open");
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-label", "Отвори мени");
    if (restoreFocus) toggle.focus();
  }

  function openMenu() {
    if (!isMobile()) return;
    body.classList.add("pio-mobile-menu-open");
    toggle.setAttribute("aria-expanded", "true");
    toggle.setAttribute("aria-label", "Затвори мени");
  }

  toggle.addEventListener("click", function () {
    if (body.classList.contains("pio-mobile-menu-open")) closeMenu(false);
    else openMenu();
  });

  backdrop.addEventListener("click", function () {
    closeMenu(true);
  });

  links.forEach(function (link) {
    link.addEventListener("click", function () {
      closeMenu(false);
    });
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") closeMenu(true);
  });

  document.addEventListener("click", function (event) {
    if (!body.classList.contains("pio-mobile-menu-open")) return;
    if (!nav.contains(event.target) && event.target !== backdrop) closeMenu(false);
  });

  var ticking = false;
  function updateToTop() {
    toTop.classList.toggle("is-visible", isMobile() && window.scrollY > 520);
    ticking = false;
  }

  window.addEventListener("scroll", function () {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(updateToTop);
    }
  }, { passive: true });

  window.addEventListener("resize", function () {
    if (!isMobile()) closeMenu(false);
    updateToTop();
  });

  toTop.addEventListener("click", function () {
    window.scrollTo({ top: 0, behavior: "smooth" });
  });

  /* Каталог: ограничен почетни број картица и дугме „Учитај још”. */
  var speciesGrid = document.getElementById("speciesGrid");
  if (speciesGrid) {
    var speciesCards = Array.prototype.slice.call(speciesGrid.querySelectorAll(".species-card"));
    var activeGroup = "sve";
    var visibleLimit = 0;
    var loadWrap = document.createElement("div");
    var loadMore = document.createElement("button");

    loadWrap.className = "sisari-mobile-load-more-wrap";
    loadMore.type = "button";
    loadMore.className = "sisari-mobile-load-more";
    loadMore.textContent = "УЧИТАЈ ЈОШ";
    loadWrap.appendChild(loadMore);
    speciesGrid.insertAdjacentElement("afterend", loadWrap);

    function pageSize() {
      return window.matchMedia("(max-width: 480px)").matches ? 6 : 8;
    }

    function cardMatches(card) {
      if (activeGroup === "sve") return true;
      if (activeGroup === "potencijalne") return card.dataset.presence === "potencijalne";
      return card.dataset.group === activeGroup;
    }

    function renderSpecies() {
      if (!isMobile()) {
        speciesCards.forEach(function (card) {
          card.style.display = cardMatches(card) ? "" : "none";
        });
        loadWrap.hidden = true;
        return;
      }

      var matching = speciesCards.filter(cardMatches);
      speciesCards.forEach(function (card) { card.style.display = "none"; });
      matching.slice(0, visibleLimit).forEach(function (card) { card.style.display = ""; });
      loadWrap.hidden = matching.length <= visibleLimit;
      loadMore.setAttribute("aria-label", "Учитај још врста. Преостало: " + Math.max(0,matching.length-visibleLimit));
    }

    window.filterGroup = function (group, btn) {
      activeGroup = group || "sve";
      visibleLimit = pageSize();
      document.querySelectorAll(".filter-bar .filter-btn").forEach(function (button) {
        button.classList.toggle("active", button === btn);
      });
      renderSpecies();
    };

    loadMore.addEventListener("click", function () {
      visibleLimit += pageSize();
      renderSpecies();
    });

    visibleLimit = pageSize();
    renderSpecies();

    window.addEventListener("resize", function () {
      if (visibleLimit < pageSize()) visibleLimit = pageSize();
      renderSpecies();
    });
  }

  updateToTop();
}());
