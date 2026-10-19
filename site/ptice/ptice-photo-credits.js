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

  const pageSlug = () => {
    const p = location.pathname.split("/").filter(Boolean);
    const i = p.indexOf("ptice");
    if (i >= 0 && p[i + 1]) return p[i + 1].replace(/\.html?$/i, "");
    return "";
  };

  const cardSlug = (img) => {
    const card = img.closest(".bird-card");
    if (!card) return "";
    const a = card.querySelector("a.bird-card-link[href]");
    if (!a) return "";
    try {
      const p = new URL(a.href, location.href).pathname.split("/").filter(Boolean);
      const i = p.indexOf("ptice");
      return (i >= 0 && p[i + 1]) ? p[i + 1].replace(/\.html?$/i, "") : "";
    } catch (_) { return ""; }
  };

  const isUsable = (r) =>
    r && r.author && r.source_url && r.license &&
    !/потребна ручна провера/i.test(r.status || "") &&
    !/недостаје аутор/i.test(r.status || "");

  const candidateNames = (name) => {
    const out = [name];
    const extm = name.match(/(\.[a-z0-9]+)$/i);
    const ext = extm ? extm[1] : ".jpg";
    const stem = name.replace(/\.[a-z0-9]+$/i, "");
    const suffixes = [
      "-hero","-card","-obala","-potok","-kora","-muzjak","-zenka",
      "-male","-female","-naslovna","-header","-cover","-main",
      "-gallery-1-new","-gallery-2-new","-gallery-3-new"
    ];
    for (const suf of suffixes) {
      if (stem.endsWith(suf)) out.push(stem.slice(0, -suf.length) + ext);
    }
    out.push(stem.replace(/-\d+$/, "") + ext);
    return [...new Set(out.map(x => x.toLowerCase()))];
  };

  const creditText = (r) => {
    const source = r.source || "Wikimedia Commons";
    return `Фото: ${r.author} / ${source}, ${r.license} — оригинал`;
  };

  const escapeHtml = (s) => String(s || "")
    .replace(/&/g,"&amp;").replace(/</g,"&lt;")
    .replace(/>/g,"&gt;").replace(/"/g,"&quot;");

  const makeOverlay = (r) => {
    const a = document.createElement("a");
    a.className = "pio-photo-credit";
    a.href = r.source_url;
    a.target = "_blank";
    a.rel = "noopener noreferrer";
    a.setAttribute("aria-label", creditText(r));
    a.innerHTML =
      `<strong>Фото: ${escapeHtml(r.author)}</strong> / ` +
      `${escapeHtml(r.source || "Wikimedia Commons")}, ` +
      `${escapeHtml(r.license)} — <span class="pio-credit-original">оригинал</span>`;
    return a;
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
      if (toggle) toggle.setAttribute("aria-expanded", "false");
    });
  };

  const ensureMobileToggle = (host) => {
    const credit = directChildCredit(host);
    if (!credit) return;

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
        try { toggle.blur(); } catch (_) {}
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
      toggle.addEventListener("keydown", ev => {
        if (ev.key === "Enter" || ev.key === " ") activate(ev);
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

  fetch(REGISTRY_URL, {cache:"no-store"})
    .then(r => {
      if (!r.ok) throw new Error("credits registry HTTP " + r.status);
      return r.json();
    })
    .then(rows => {
      const exact = new Map(), bySource = new Map(), bySlug = new Map();
      for (const r of rows) {
        const fn = String(r.filename || "").toLowerCase();
        if (fn) { if (!exact.has(fn)) exact.set(fn, []); exact.get(fn).push(r); }
        const su = norm(r.source_url);
        if (su) { if (!bySource.has(su)) bySource.set(su, []); bySource.get(su).push(r); }
        const slug = String(r.page_slug || "").toLowerCase();
        if (slug) { if (!bySlug.has(slug)) bySlug.set(slug, []); bySlug.get(slug).push(r); }
      }
      for (const list of bySlug.values()) {
        list.sort((a,b) => String(a.filename).localeCompare(String(b.filename), "sr"));
      }

      document.querySelectorAll("a").forEach(a => {
        if ((a.textContent || "").trim() === "Лиценце фотографија у плагину") {
          a.textContent = "Лиценце фотографија";
        }
      });

      const usedForSlug = new Map();
      const processed = new WeakSet();

      const chooseCredit = (img, oldAnchor) => {
        if (oldAnchor) {
          const href = norm(oldAnchor.href);
          if (href && bySource.has(href)) {
            const r = bySource.get(href).find(isUsable);
            if (r) return r;
          }
        }
        for (const n of candidateNames(basename(img.currentSrc || img.src))) {
          const r = (exact.get(n) || []).find(isUsable);
          if (r) return r;
        }
        const slug = pageSlug() || cardSlug(img);
        const bn = basename(img.currentSrc || img.src);
        if (slug && bn.startsWith(slug)) {
          const pool = (bySlug.get(slug) || []).filter(isUsable);
          if (pool.length) {
            const used = usedForSlug.get(slug) || 0;
            const r = pool[Math.min(used, pool.length - 1)];
            usedForSlug.set(slug, used + 1);
            return r;
          }
        }
        return null;
      };

      const processImage = (img) => {
        if (!(img instanceof HTMLImageElement) || processed.has(img)) return;
        processed.add(img);
        const host = findHost(img);
        if (!host) return;
        const old = oldCreditAnchor(host);
        const credit = chooseCredit(img, old);
        if (!credit) return;
        host.classList.add("pio-photo-credit-host");
        if (old) old.remove();
        const existing = host.querySelector(":scope > .pio-photo-credit");
        if (existing) existing.remove();
        host.appendChild(makeOverlay(credit));
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
          for (const node of m.addedNodes) {
            if (!(node instanceof Element)) continue;
            if (node.matches?.("img")) processImage(node);
            scan(node);
          }
        }
      });
      mo.observe(document.body, {childList:true, subtree:true});
      document.documentElement.classList.add("pio-photo-credits-ready");
    })
    .catch(err => console.error("[PIO PTICE] Photo credits:", err));
})();
