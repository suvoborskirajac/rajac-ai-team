(() => {
  "use strict";

  const REGISTRY_URL = "/ptice/ptice-photo-credits.json";

  const norm = (s) => String(s || "")
    .trim()
    .replace(/^http:/i, "https:")
    .replace(/\/+$/, "")
    .replace(/%20/g, "_");

  const basename = (src) => {
    try {
      const u = new URL(src, location.href);
      return decodeURIComponent(u.pathname.split("/").pop() || "").toLowerCase();
    } catch (_) {
      return String(src || "").split("/").pop().split("?")[0].toLowerCase();
    }
  };

  const safeURL = (value) => {
    try { const u = new URL(value); return /^https?:$/.test(u.protocol) && !u.username && !u.password; }
    catch (_) { return false; }
  };

  const isUsable = (r) =>
    r && r.author && safeURL(r.source_url) && r.license &&
    !/потребна ручна провера/i.test(r.status || "") &&
    !/недостаје аутор/i.test(r.status || "");

  const makeOverlay = (r) => {
    const box = document.createElement("span");
    box.className = "pio-photo-credit";
    const label = document.createElement("strong");
    label.textContent = `Фото: ${r.author}`;
    box.append(label, ` / ${r.source || "Wikimedia Commons"}, `);
    const link = (text, url) => {
      const a = document.createElement("a");
      a.textContent = text;
      a.href = url;
      a.rel = "noopener noreferrer";
      a.style.color = "inherit";
      a.style.textDecoration = "underline";
      return a;
    };
    if (safeURL(r.license_url)) box.append(link(r.license, r.license_url));
    else box.append(r.license);
    box.append(" — ", link("оригинал", r.source_url));
    if (r.modifications) box.append(` (${r.modifications})`);
    return box;
  };



  const TOUCH_QUERY = window.matchMedia("(hover: none), (pointer: coarse)");
  const CREDIT_SELECTOR = ".pio-photo-credit,.fig-photo-source,.bird-photo-source-overlay,.pio-hero-credit";
  const CREDIT_HOST_SELECTOR = ".pio-photo-credit-host,.pio-hero-credit-wrap,.hero-photo,.fig-photo-wrap,.bird-card-media";
  let creditIdSeq = 0;

  const directChildCredit = (host) => {
    if (!host || !host.children) return null;
    return [...host.children].find(el => el.matches?.(CREDIT_SELECTOR)) || null;
  };

  const closeMobileCredits = (exceptHost = null) => {
    document.querySelectorAll(".pio-credit-mobile-open").forEach(host => {
      if (host === exceptHost) return;
      host.classList.remove("pio-credit-mobile-open");
      const toggle = [...host.children].find(el => el.classList?.contains("pio-photo-credit-toggle"));
      if (toggle) {
        toggle.setAttribute("aria-expanded", "false");
        toggle.setAttribute("aria-label", "Прикажи извор фотографије");
      }
    });
  };

  const ensureMobileToggle = (host) => {
    const credit = directChildCredit(host);
    if (!credit) return;
    if (!host.matches("a,button") && !host.hasAttribute("tabindex")) host.tabIndex = 0;

    if (!credit.id) credit.id = `pio-photo-credit-${++creditIdSeq}`;

    let toggle = [...host.children].find(el => el.classList?.contains("pio-photo-credit-toggle"));
    if (!toggle) {
      toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "pio-photo-credit-toggle";
      toggle.setAttribute("aria-label", "Прикажи извор фотографије");
      toggle.setAttribute("aria-expanded", "false");
      toggle.textContent = "ⓘ Фото";

      const activate = (ev) => {
        if (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          ev.stopImmediatePropagation?.();
        }
        if (!TOUCH_QUERY.matches) return;
        const opening = !host.classList.contains("pio-credit-mobile-open");
        closeMobileCredits(host);
        host.classList.toggle("pio-credit-mobile-open", opening);
        toggle.setAttribute("aria-expanded", opening ? "true" : "false");
        toggle.setAttribute("aria-label", opening ? "Сакриј извор фотографије" : "Прикажи извор фотографије");
        /* Android уме да задржи :focus након tap-а; blur спречава да focus-within поново открије кредит. */
        if (ev?.type === "touchend") { try { toggle.blur(); } catch (_) {} }
      };

      let touchActivatedAt = 0;
      toggle.addEventListener("touchstart", ev => {
        if (!TOUCH_QUERY.matches) return;
        /* Спречава browser long-press мени / preview линка на Android/iOS. */
        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation?.();
      }, {passive:false});

      toggle.addEventListener("touchend", ev => {
        if (!TOUCH_QUERY.matches) return;
        touchActivatedAt = Date.now();
        activate(ev);
      }, {passive:false});

      toggle.addEventListener("contextmenu", ev => {
        ev.preventDefault();
        ev.stopPropagation();
        ev.stopImmediatePropagation?.();
      });
      toggle.addEventListener("selectstart", ev => ev.preventDefault());
      toggle.addEventListener("dragstart", ev => ev.preventDefault());

      toggle.addEventListener("click", ev => {
        /* touchend већ обрађује tap; игнориши синтетички click који следи одмах после њега. */
        if (Date.now() - touchActivatedAt < 700) {
          ev.preventDefault();
          ev.stopPropagation();
          ev.stopImmediatePropagation?.();
          return;
        }
        activate(ev);
      });
      host.appendChild(toggle);
    }

    toggle.setAttribute("aria-controls", credit.id);

    if (!credit.dataset.pioMobileClickBound) {
      credit.dataset.pioMobileClickBound = "1";
      credit.addEventListener("click", ev => {
        if (TOUCH_QUERY.matches) ev.stopPropagation();
      });
    }
  };

  const scanExistingCreditHosts = (root = document) => {
    if (root instanceof Element && root.matches?.(CREDIT_HOST_SELECTOR)) ensureMobileToggle(root);
    root.querySelectorAll?.(CREDIT_HOST_SELECTOR).forEach(ensureMobileToggle);
  };

  /* Ради и за већ постојеће inline кредите пре учитавања registry-ја. */
  scanExistingCreditHosts(document);

  document.addEventListener("click", ev => {
    if (!TOUCH_QUERY.matches) return;
    if (ev.target.closest?.(CREDIT_HOST_SELECTOR)) return;
    closeMobileCredits();
  }, true);

  document.addEventListener("keydown", ev => {
    if (ev.key === "Escape") closeMobileCredits();
  });

  const findHost = (img) => img.closest(
    ".bird-card-media,.hero-photo,.rfw-head-img,.figure,.rfw-fig,figure," +
    ".photo,.bird-photo,.species-photo,.hero-image,.gallery-item"
  ) || img.parentElement;

  const oldCreditAnchor = (host) => {
    if (!host) return null;
    const anchors = [...host.querySelectorAll("a")];
    const found = anchors.find(a => {
      const t = (a.textContent || "").toLowerCase();
      const h = (a.getAttribute("href") || "").toLowerCase();
      return t.includes("извор фотографије") || t.includes("wikimedia commons") ||
             t.includes("cc by") || t.includes("public domain") ||
             t.trim().startsWith("©") || h.includes("commons.wikimedia.org/wiki/file:");
    });
    if (found) return found;

    const parent = host.parentElement;
    if (parent) {
      return [...parent.querySelectorAll(":scope > a")].find(a => {
        const t = (a.textContent || "").toLowerCase();
        return t.includes("извор фотографије") || t.includes("wikimedia commons") ||
               t.includes("cc by") || t.trim().startsWith("©");
      }) || null;
    }
    return null;
  };

  fetch(REGISTRY_URL, {cache:"no-cache"})
    .then(r => {
      if (!r.ok) throw new Error("credits registry HTTP " + r.status);
      return r.json();
    })
    .then(rows => {
      if (!Array.isArray(rows)) throw new Error("Invalid credits registry");
      const exact = new Map();
      for (const r of rows) {
        if (!r || typeof r !== "object") continue;
        const fn = String(r.filename || "").toLowerCase();
        if (fn) { if (!exact.has(fn)) exact.set(fn, []); exact.get(fn).push(r); }
      }

      document.querySelectorAll("a").forEach(a => {
        if ((a.textContent || "").trim() === "Лиценце фотографија у плагину") {
          a.textContent = "Лиценце фотографија";
        }
      });

      const processed = new WeakMap();
      const generated = new WeakMap();

      // Only an unambiguous exact filename may replace a published credit.
      // Species names, order and suffixes do not identify a photograph.
      const chooseCredit = (img) => {
        const name = basename(img.currentSrc || img.src);
        const pool = (exact.get(name) || []).filter(isUsable);
        if (!pool.length) return null;
        const identities = new Set(pool.map(r => JSON.stringify([
          r.author, norm(r.source_url), r.license
        ])));
        return identities.size === 1 ? pool[0] : null;
      };

      const processImage = (img) => {
        if (!(img instanceof HTMLImageElement)) return;
        const signature = img.currentSrc || img.src;
        if (processed.get(img) === signature) return;
        processed.set(img, signature);
        let host = findHost(img);
        if (host?.tagName === "A" && host.parentElement?.classList.contains("pio-photo-credit-host")) host = host.parentElement;
        if (!host) return;
        const previous = generated.get(img);
        if (previous) {
          previous.remove();
          generated.delete(img);
          if (!directChildCredit(host)) {
            [...host.children].find(el => el.classList?.contains("pio-photo-credit-toggle"))?.remove();
            host.classList.remove("pio-credit-mobile-open");
          }
        }
        const old = oldCreditAnchor(host);
        const credit = chooseCredit(img);
        if (!credit) return;
        // Catalog media is itself a link: credits and buttons must be siblings.
        if (host.tagName === "A") {
          const wrap = document.createElement("div");
          host.parentNode.insertBefore(wrap, host);
          wrap.appendChild(host);
          host = wrap;
        }
        host.classList.add("pio-photo-credit-host");
        if (old) old.remove();
        const existing = host.querySelector(":scope > .pio-photo-credit");
        if (existing) existing.remove();
        const overlay = makeOverlay(credit);
        host.appendChild(overlay);
        generated.set(img, overlay);
        ensureMobileToggle(host);
      };

      const scan = (root = document) => {
        root.querySelectorAll?.(
          ".bird-card-media img,.hero-photo img,.rfw-head-img img,.figure img,.rfw-fig img,figure img," +
          ".photo img,.bird-photo img,.species-photo img,.hero-image img,.gallery-item img"
        ).forEach(processImage);
        scanExistingCreditHosts(root);
      };

      scan(document);
      const mo = new MutationObserver(mutations => {
        for (const m of mutations) {
          if (m.type === "attributes") { processImage(m.target); continue; }
          for (const node of m.addedNodes) {
            if (!(node instanceof Element)) continue;
            if (node.matches?.("img")) processImage(node);
            scan(node);
          }
        }
      });
      mo.observe(document.body, {childList:true, subtree:true, attributes:true, attributeFilter:["src", "srcset"]});
      document.addEventListener("load", ev => {
        if (ev.target instanceof HTMLImageElement) processImage(ev.target);
      }, true);
      document.documentElement.classList.add("pio-photo-credits-ready");
    })
    .catch(err => console.error("[PIO PTICE] Photo credits:", err));
})();
