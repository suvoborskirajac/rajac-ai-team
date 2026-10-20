const { chromium } = require(process.env.NM + '/playwright'); const fs = require('fs');
const router = require(process.env.OUT + '/fonts.js')(process.env.NM);
const lines = fs.readFileSync(process.argv[2], 'utf8').trim().split('\n').map(l => l.split('|'));
const SHOTS = process.env.OUT + '/' + (process.env.SHOTDIR || 'shots2');
fs.mkdirSync(SHOTS, { recursive: true });
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' }); const out = []; let i = 0;
  for (const [name, url] of lines) {
    i++; const rec = { name, url, w: {} };
    for (const w of [1440, 820, 390]) {
      const p = await b.newPage({ viewport: { width: w, height: 900 } }); await p.route('**/*', router);
      try { await p.goto(url, { waitUntil: 'networkidle', timeout: 45000 }); } catch (e) { }
      try { await p.evaluate(() => document.fonts.ready); } catch (e) { }
      await p.waitForTimeout(1200);
      const d = await p.evaluate(() => {
        const GEN = ['serif', 'sans-serif', 'monospace', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'inherit', 'ui-sans-serif', 'ui-serif'];
        const fam = ff => { const fams = ff.split(',').map(s => s.trim().replace(/^["']|["']$/g, '')); for (const f of fams) { if (GEN.includes(f)) return f + ' (системски)'; const ok = [...document.fonts].some(x => x.family.replace(/["']/g, '') === f && x.status === 'loaded'); if (ok) return f; if (['Georgia', 'Arial', 'Times New Roman', 'Courier New', 'Helvetica', 'Verdana'].includes(f)) return f; } return fams[0] + ' (није учитан)'; };
        const rgb = s => { const m = s.match(/[\d.]+/g); return m ? m.map(Number) : [0, 0, 0, 1]; };
        const lum = c => { const a = c.slice(0, 3).map(v => { v /= 255; return v <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }); return .2126 * a[0] + .7152 * a[1] + .0722 * a[2]; };
        const bgOf = e => { while (e) { const cs = getComputedStyle(e); if (cs.backgroundImage !== 'none') return null; const c = rgb(cs.backgroundColor); if (cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && (c[3] ?? 1) > .5) return c; e = e.parentElement; } return [255, 255, 255, 1]; };
        const contrast = e => { const bg = bgOf(e); if (!bg) return null; const a = lum(rgb(getComputedStyle(e).color)), c = lum(bg); return +((Math.max(a, c) + .05) / (Math.min(a, c) + .05)).toFixed(2); };
        const hex = s => { const c = rgb(s); return '#' + c.slice(0, 3).map(v => Math.round(v).toString(16).padStart(2, '0')).join(''); };
        const st = el => { if (!el) return null; const c = getComputedStyle(el); return { t: (el.innerText || '').trim().replace(/\s+/g, ' ').slice(0, 36), f: fam(c.fontFamily), s: c.fontSize, wt: c.fontWeight, tt: c.textTransform, col: hex(c.color), deco: c.textDecorationLine, cr: contrast(el) }; };
        const vis = e => { const r = e.getBoundingClientRect(); const c = getComputedStyle(e); return r.width > 0 && r.height > 0 && c.visibility !== 'hidden' && c.display !== 'none'; };
        const q = sel => [...document.querySelectorAll(sel)].filter(vis);
        const home = q('a').find(a => /ПИО|PIO/.test(a.innerText || '') && a.getBoundingClientRect().top < 160);
        const nav = q('header a, nav a').filter(a => a !== home && a.getBoundingClientRect().top < 160 && (a.innerText || '').trim().length > 1)[0];
        const p = q('p').find(e => (e.innerText || '').trim().length > 90 && !e.closest('header,footer,nav'));
        const pa = q('p a, li a, td a').find(a => !a.closest('header,nav,footer') && (a.innerText || '').trim().length > 2);
        const btn = q('button, .chip, .btn, [class*="chip"], [class*="pill"], [class*="tag"]').find(e => !e.closest('header,nav') && (e.innerText || '').trim().length > 1);
        const card = q('[class*="card"] h2, [class*="card"] h3, [class*="card-title"], [class*="card-name"]')[0];
        const sci = q('i, em, [class*="sci"], [class*="latin"]').find(e => /^[A-Z][a-z]+ [a-z]+/.test((e.innerText || '').trim()) && !e.closest('header,nav'));
        const fb = q('footer *').find(e => e.children.length === 0 && (e.innerText || '').trim().length > 8);
        let imp = 0, rules = 0, sheets = 0;
        for (const sh of document.styleSheets) { sheets++; try { for (const r of sh.cssRules) { rules++; if (r.style) for (const k of r.style) if (r.style.getPropertyPriority(k) === 'important') imp++; } } catch (e) { } }
        const fc = {}; q('body *').forEach(e => { if (![...e.childNodes].some(n => n.nodeType === 3 && n.textContent.trim().length > 1)) return; const f = fam(getComputedStyle(e).fontFamily); fc[f] = (fc[f] || 0) + 1; });
        const hdr = home ? home.closest('header') || home.parentElement : null;
        return { home: st(home), nav: st(nav), h1: st(q('h1')[0]), h2: st(q('h2')[0]), h3: st(q('h3')[0]), card: st(card), p: st(p), pa: st(pa), sci: st(sci), btn: st(btn), foot: st(fb),
          h1n: document.querySelectorAll('h1').length, lang: document.documentElement.lang, imp, rules, sheets, styleBlocks: document.querySelectorAll('style').length, inlineStyle: document.querySelectorAll('[style]').length, fc,
          hscroll: document.documentElement.scrollWidth > window.innerWidth + 2, bodyBg: hex(getComputedStyle(document.body).backgroundColor), headerH: hdr ? Math.round(hdr.getBoundingClientRect().height) : null, title: document.title };
      });
      if (w === 1440) { let ok = null; try { await p.keyboard.press('Tab'); await p.keyboard.press('Tab'); ok = await p.evaluate(() => { const e = document.activeElement; if (!e || e === document.body) return null; const c = getComputedStyle(e); return (c.outlineStyle !== 'none' && parseFloat(c.outlineWidth) > 0) || c.boxShadow !== 'none'; }); } catch (e) { } d.focusVisible = ok; }
      rec.w[w] = d;
      if (w !== 820) { await p.evaluate(() => window.scrollTo(0, 0)); await p.screenshot({ path: `${SHOTS}/${i}-${w}.png` }); }
      await p.close();
    }
    out.push(rec); console.error(i, name);
  }
  fs.writeFileSync(process.env.OUT + '/' + (process.env.JSON || 'audit.json'), JSON.stringify(out, null, 1)); await b.close();
})();
