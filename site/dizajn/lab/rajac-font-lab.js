/* ПИО „РАЈАЦ“ — лабораторија фонтова за пробне странице (/dizajn/lab/), 2026-10-21.
   Сви понуђени фонтови имају ћирилицу (Google Fonts). Избор се памти у овом прегледачу
   и важи на свим пробним страницама. Ништа се не шаље на сервер. */
(() => {
  'use strict';
  const SERIF = ['Cormorant Garamond', 'Cormorant', 'EB Garamond', 'Playfair Display', 'Lora', 'PT Serif', 'Merriweather', 'Noto Serif', 'Literata', 'Source Serif 4', 'Alegreya', 'Spectral'];
  const SANS = ['Source Sans 3', 'Manrope', 'Inter', 'Golos Text', 'Onest', 'Montserrat', 'Raleway', 'Nunito Sans', 'Open Sans', 'Roboto', 'PT Sans', 'Fira Sans', 'IBM Plex Sans', 'Rubik', 'Commissioner', 'Noto Sans'];
  const SVI = [...SERIF, ...SANS];
  const GEN = { 'Cormorant Garamond': 'serif', 'Cormorant': 'serif', 'EB Garamond': 'serif', 'Playfair Display': 'serif', 'Lora': 'serif', 'PT Serif': 'serif', 'Merriweather': 'serif', 'Noto Serif': 'serif', 'Literata': 'serif', 'Source Serif 4': 'serif', 'Alegreya': 'serif', 'Spectral': 'serif' };
  const IZV = 'izvorno';
  const P = 'html:root:not(#_) body ';

  // Улоге: шта се мења и којим селекторима
  const ULOGE = [
    { id: 'h1', naziv: 'Главни наслов странице (H1)', sel: ['h1', 'h1 *'], polja: ['font', 'tezina', 'skala', 'razmak'] },
    { id: 'h2', naziv: 'Поднаслови (H2, H3)', sel: ['h2', 'h2 *', 'h3', 'h3 *'], polja: ['font', 'tezina', 'skala', 'razmak'] },
    { id: 'kartica', naziv: 'Наслови картица', sel: ['[class*="card-title"]', '[class*="card-title"] *', '[class*="card-name"]', '[class*="card-name"] *'], polja: ['font', 'tezina', 'velicina', 'razmak'] },
    { id: 'tekst', naziv: 'Обичан текст', sel: null, polja: ['font', 'tezina', 'velicina', 'prored'] },
    { id: 'natpis', naziv: 'Натпис ПИО „РАЈАЦ“', sel: ['.rajac-ds-home > span:not(.rajac-ds-home-mark)', '.rfx-nav-home', '.rfx-nav-home > span'], polja: ['font', 'tezina', 'velicina', 'razmak'] },
    { id: 'meni', naziv: 'Ставке менија', sel: ['.rajac-ds-nav-item', '.rajac-ds-nav-item *', '.rfx-chapter-nav a:not(.rfx-nav-home)', '.rfx-chapter-nav a:not(.rfx-nav-home) *'], polja: ['font', 'tezina', 'velicina', 'razmak'] },
    { id: 'oznake', naziv: 'Ознаке, чипови, дугмад', sel: ['[class*="chip"]', '[class*="pill"]', '[class*="badge"]', '[class*="kicker"]', '.kicker', 'button', '[class*="tab"]'], polja: ['font', 'tezina', 'velicina', 'razmak', 'velika'] },
    { id: 'latinski', naziv: 'Латински називи', sel: ['[class*="sci"]', '[class*="latin"]'], polja: ['font', 'tezina', 'kurziv'] },
  ];
  const TEKST_SEL = '*:where(:not(code,pre,kbd,samp,svg,svg *,.leaflet-container *))';
  const TEKST_VEL = ['p', 'li', 'td', 'dd', 'blockquote', 'figcaption', '[class*="copy"]', '[class*="blurb"]'];
  const LINK_SEL = ['p a:not(nav *):not(header *):not(footer *)', 'li a:not(nav *):not(header *):not(footer *):not([class*="nav"])', 'td a'];

  const PRESETI = {
    sada: { naziv: 'Садашњи изглед', v: {} },
    A: { naziv: 'Стил А', v: {
      h1: { font: 'Cormorant Garamond', tezina: '600' }, h2: { font: 'Cormorant Garamond', tezina: '600' }, kartica: { font: 'Cormorant Garamond', tezina: '600' },
      tekst: { font: 'Source Sans 3', 'prored': '1.6' }, natpis: { font: 'Cormorant Garamond', tezina: '700', velicina: '18', razmak: '0.06' },
      meni: { font: 'Source Sans 3', tezina: '600', velicina: '14', razmak: '0.06' }, oznake: { font: 'Source Sans 3', tezina: '600' },
      latinski: { font: 'Source Sans 3', kurziv: 'da' }, link: { boja: '#1f5b8a', podvuceno: 'da', tezina: 'kao-tekst' } } },
    B: { naziv: 'Стил Б', v: {
      h1: { font: 'Cormorant Garamond', tezina: '600' }, h2: { font: 'Manrope', tezina: '700', razmak: '-0.01' }, kartica: { font: 'Manrope', tezina: '700', velicina: '21', razmak: '-0.01' },
      tekst: { font: 'Manrope', 'prored': '1.65' }, natpis: { font: 'Cormorant Garamond', tezina: '700', velicina: '18', razmak: '0.06' },
      meni: { font: 'Manrope', tezina: '600', velicina: '12.5', razmak: '0.09' }, oznake: { font: 'Manrope', tezina: '700', razmak: '0.04' },
      latinski: { font: 'Cormorant Garamond', tezina: '500', kurziv: 'da' }, link: { boja: '#1f5b8a', podvuceno: 'da', tezina: 'kao-tekst' } } },
  };

  const KLJUC = 'rajac-font-lab-v1';
  const ucitaj = () => { try { const s = localStorage.getItem(KLJUC); if (s) return JSON.parse(s); } catch (e) { } return JSON.parse(JSON.stringify(PRESETI.A.v)); };
  const sacuvaj = () => { try { localStorage.setItem(KLJUC, JSON.stringify(stanje)); } catch (e) { } };
  let stanje = ucitaj();

  // Учитавање фонтова: посебан захтев за сваку тежину (ако тежина не постоји, не квари остале)
  const ucitano = new Set();
  function ucitajFont(font, tezina, kurziv) {
    if (!font || font === IZV) return;
    const w = tezina && tezina !== IZV ? tezina : '400';
    const kljuc = font + '|' + w + '|' + (kurziv ? 1 : 0);
    if (ucitano.has(kljuc)) return; ucitano.add(kljuc);
    const l = document.createElement('link'); l.rel = 'stylesheet';
    l.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(font).replace(/%20/g, '+') + ':' + (kurziv ? 'ital,wght@1,' : 'wght@') + w + '&display=swap';
    document.head.appendChild(l);
  }
  const fam = f => `"${f}", ${GEN[f] ? 'Georgia, serif' : 'system-ui, sans-serif'}`;
  const sels = arr => arr.map(s => P + s).join(',\n');

  function css() {
    let out = '';
    const tekst = stanje.tekst || {};
    if (tekst.font && tekst.font !== IZV) { ucitajFont(tekst.font, tekst.tezina); ucitajFont(tekst.font, '600'); ucitajFont(tekst.font, '700'); out += `${P}${TEKST_SEL}{font-family:${fam(tekst.font)}!important}\n`; }
    const tv = [];
    if (tekst.tezina && tekst.tezina !== IZV) tv.push(`font-weight:${tekst.tezina}!important`);
    if (tekst.velicina && tekst.velicina !== IZV) tv.push(`font-size:${tekst.velicina}px!important`);
    if (tekst['prored'] && tekst['prored'] !== IZV) tv.push(`line-height:${tekst['prored']}!important`);
    if (tv.length) out += `${sels(TEKST_VEL)}{${tv.join(';')}}\n`;
    for (const u of ULOGE) {
      if (u.id === 'tekst') continue;
      const v = stanje[u.id] || {}; const d = [];
      if (v.font && v.font !== IZV) { ucitajFont(v.font, v.tezina, false); if (u.id === 'latinski') ucitajFont(v.font, v.tezina || '400', true); d.push(`font-family:${fam(v.font)}!important`); }
      if (v.tezina && v.tezina !== IZV) d.push(`font-weight:${v.tezina}!important`);
      if (v.velicina && v.velicina !== IZV) d.push(`font-size:${v.velicina}px!important`);
      if (v.razmak && v.razmak !== IZV) d.push(`letter-spacing:${v.razmak}em!important`);
      if (v.velika === 'da') d.push('text-transform:uppercase!important'); else if (v.velika === 'ne') d.push('text-transform:none!important');
      if (v.kurziv === 'da') d.push('font-style:italic!important'); else if (v.kurziv === 'ne') d.push('font-style:normal!important');
      if (d.length) out += `${sels(u.sel)}{${d.join(';')}}\n`;
    }
    const l = stanje.link || {}; const ld = [];
    if (l.boja && l.boja !== IZV) ld.push(`color:${l.boja}!important`);
    if (l.podvuceno === 'da') ld.push('text-decoration:underline!important;text-underline-offset:3px!important;text-decoration-thickness:1px!important'); else if (l.podvuceno === 'ne') ld.push('text-decoration:none!important');
    if (l.tezina === 'kao-tekst') ld.push('font-weight:inherit!important'); else if (l.tezina && l.tezina !== IZV) ld.push(`font-weight:${l.tezina}!important`);
    if (ld.length) out += `${sels(LINK_SEL)}{${ld.join(';')}}\n`;
    return out;
  }
  // Скала наслова: множи изворну величину (измерену при учитавању)
  function primeniSkalu() {
    for (const [id, q] of [['h1', 'h1'], ['h2', 'h2, h3']]) {
      const s = (stanje[id] || {}).skala;
      document.querySelectorAll(q).forEach(el => {
        if (el.matches('[class*="card-title"],[class*="card-name"]')) return;
        if (!el.dataset.labIzvorno) el.dataset.labIzvorno = parseFloat(getComputedStyle(el).fontSize);
        el.style.setProperty('font-size', s && s !== IZV ? (el.dataset.labIzvorno * s / 100) + 'px' : '', s && s !== IZV ? 'important' : '');
      });
    }
  }
  let stil = null;
  function primeni() {
    if (!stil) { stil = document.createElement('style'); stil.id = 'rajac-font-lab-css'; document.head.appendChild(stil); }
    stil.textContent = css(); primeniSkalu(); sacuvaj(); osveziSazetak();
  }

  // ===== Панел (у shadow DOM-у, да на њега не утиче стил странице) =====
  const host = document.createElement('div'); host.id = 'rajac-lab';
  const sh = host.attachShadow({ mode: 'open' });
  const opcije = (lista, izabrano, sIzvornim = true) => (sIzvornim ? [`<option value="${IZV}">— као сада —</option>`] : []).concat(lista.map(([v, t]) => `<option value="${v}"${String(izabrano) === String(v) ? ' selected' : ''}>${t}</option>`)).join('');
  const fontOpc = izb => `<option value="${IZV}">— као сада —</option><optgroup label="Са серифима">${SERIF.map(f => `<option${izb === f ? ' selected' : ''}>${f}</option>`).join('')}</optgroup><optgroup label="Без серифа">${SANS.map(f => `<option${izb === f ? ' selected' : ''}>${f}</option>`).join('')}</optgroup>`;
  const TEZ = [['300', '300 — танко'], ['400', '400 — обично'], ['500', '500 — средње'], ['600', '600 — полуподебљано'], ['700', '700 — подебљано'], ['800', '800 — јако']];
  const VEL = n => Array.from({ length: n[1] - n[0] + 1 }, (_, i) => n[0] + i).map(x => [String(x), x + ' px']);
  const POLJE = {
    font: (u, v) => `<label>Фонт<select data-u="${u}" data-k="font">${fontOpc(v.font)}</select></label>`,
    tezina: (u, v) => `<label>Дебљина<select data-u="${u}" data-k="tezina">${opcije(TEZ, v.tezina)}</select></label>`,
    velicina: (u, v) => `<label>Величина<select data-u="${u}" data-k="velicina">${opcije(u === 'natpis' || u === 'meni' || u === 'oznake' ? [...VEL([10, 22]), ['12.5', '12,5 px'], ['13.5', '13,5 px']].sort((a, b) => a[0] - b[0]) : u === 'kartica' ? VEL([16, 34]) : VEL([13, 22]), v.velicina)}</select></label>`,
    skala: (u, v) => `<label>Величина<select data-u="${u}" data-k="skala">${opcije([['70', '70 %'], ['80', '80 %'], ['90', '90 %'], ['100', '100 %'], ['110', '110 %'], ['120', '120 %'], ['130', '130 %']], v.skala)}</select></label>`,
    razmak: (u, v) => `<label>Размак слова<select data-u="${u}" data-k="razmak">${opcije([['-0.02', 'збијено −0,02'], ['-0.01', 'мало збијено −0,01'], ['0', 'обично 0'], ['0.02', '0,02'], ['0.04', '0,04'], ['0.06', '0,06'], ['0.09', '0,09'], ['0.12', 'широко 0,12']], v.razmak)}</select></label>`,
    'prored': (u, v) => `<label>Проред<select data-u="${u}" data-k="prored">${opcije([['1.4', '1,4'], ['1.5', '1,5'], ['1.55', '1,55'], ['1.6', '1,6'], ['1.65', '1,65'], ['1.7', '1,7'], ['1.8', '1,8'], ['1.9', '1,9']], v['prored'])}</select></label>`,
    velika: (u, v) => `<label>Велика слова<select data-u="${u}" data-k="velika">${opcije([['da', 'да'], ['ne', 'не']], v.velika)}</select></label>`,
    kurziv: (u, v) => `<label>Курзив<select data-u="${u}" data-k="kurziv">${opcije([['da', 'да'], ['ne', 'не']], v.kurziv)}</select></label>`,
  };
  const STRANE = [['ptice', 'Птице — каталог'], ['ptice-suri-orao', 'Сури орао'], ['biljke', 'Биљке — каталог'], ['biljke-obicna-jela', 'Обична јела']];
  const ovaStrana = (location.pathname.match(/\/dizajn\/lab\/([^/]+)\//) || [])[1] || '';
  const ORIG = { 'ptice': '/ptice/', 'ptice-suri-orao': '/ptice/suri-orao/', 'biljke': '/biljke/', 'biljke-obicna-jela': '/biljke/abies-alba-mill/' };

  function render() {
    const grupe = ULOGE.map(u => `<details${u.id === 'tekst' || u.id === 'h2' ? ' open' : ''}><summary>${u.naziv}</summary><div class="g">${u.polja.map(p => POLJE[p](u.id, stanje[u.id] || {})).join('')}</div></details>`).join('');
    const l = stanje.link || {};
    sh.innerHTML = `<style>
      :host{all:initial}
      *{box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
      .dug{position:fixed;right:14px;bottom:14px;z-index:2147483646;background:#0f2744;color:#e8cf85;border:0;border-radius:999px;padding:12px 16px;font-weight:700;font-size:15px;box-shadow:0 6px 18px rgba(0,0,0,.3);cursor:pointer}
      .pan{position:fixed;right:14px;bottom:70px;width:350px;max-height:calc(100vh - 90px);overflow:auto;z-index:2147483647;background:#fff;color:#1d2a22;border:1px solid #d9d3c4;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.28);padding:12px 14px;font-size:14px}
      .pan[hidden]{display:none}
      h2{font-size:16px;margin:2px 0 8px;color:#0f2744}
      .pre{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px}
      .pre button,.akc button{border:1px solid #c9c2b2;background:#f7f4ee;border-radius:8px;padding:7px 10px;font-size:13px;font-weight:600;cursor:pointer;color:#1d2a22}
      .pre button:hover,.akc button:hover{background:#efe8da}
      details{border-top:1px solid #eee;padding:6px 0}summary{cursor:pointer;font-weight:700;color:#0f2744;padding:3px 0}
      .g{display:grid;grid-template-columns:1fr 1fr;gap:6px 8px;margin-top:6px}
      label{display:flex;flex-direction:column;font-size:12px;color:#4a5a50;gap:2px}
      select,input{font-size:13px;padding:5px;border:1px solid #cfc8b8;border-radius:6px;background:#fff;color:#1d2a22;width:100%}
      .str{margin:6px 0 4px;font-size:13px}.str a{color:#1f5b8a;margin-right:8px}.str b{margin-right:8px}
      .akc{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
      textarea{width:100%;height:120px;font-family:ui-monospace,Consolas,monospace;font-size:11.5px;margin-top:8px;border:1px solid #cfc8b8;border-radius:6px;padding:6px}
      .nap{font-size:11.5px;color:#6b6353;margin-top:6px}
      @media (max-width:520px){.pan{left:8px;right:8px;width:auto;bottom:64px;max-height:62vh}.dug{right:10px;bottom:10px}}
    </style>
    <button class="dug" type="button" aria-expanded="false">Аа Фонтови</button>
    <div class="pan" hidden role="dialog" aria-label="Лабораторија фонтова">
      <h2>Лабораторија фонтова</h2>
      <div class="pre">${Object.entries(PRESETI).map(([k, p]) => `<button type="button" data-pre="${k}">${p.naziv}</button>`).join('')}</div>
      <div class="str">${STRANE.map(([k, t]) => k === ovaStrana ? `<b>${t}</b>` : `<a href="/dizajn/lab/${k}/">${t}</a>`).join('')}${ovaStrana ? `<a href="${ORIG[ovaStrana]}">← садашња страница</a>` : ''}</div>
      ${grupe}
      <details><summary>Линкови у тексту</summary><div class="g">
        <label>Боја<select data-u="link" data-k="boja">${opcije([['#1f5b8a', 'плава #1f5b8a'], ['#18553b', 'зелена #18553b'], ['#7a5f1c', 'златна тамна #7a5f1c'], ['#0f2744', 'тамноплава #0f2744']], l.boja)}</select></label>
        <label>Подвучено<select data-u="link" data-k="podvuceno">${opcije([['da', 'да'], ['ne', 'не']], l.podvuceno)}</select></label>
        <label>Дебљина<select data-u="link" data-k="tezina">${opcije([['kao-tekst', 'као текст'], ...TEZ.slice(2)], l.tezina)}</select></label>
      </div></details>
      <div class="akc"><button type="button" data-a="kopiraj">Копирај избор</button><button type="button" data-a="zatvori">Затвори</button></div>
      <textarea readonly aria-label="Сажетак избора"></textarea>
      <div class="nap">Избор се памти у овом прегледачу и важи на све четири пробне странице. Пошаљи сажетак (дугме „Копирај избор“) Claude-у.</div>
    </div>`;
    const dug = sh.querySelector('.dug'), pan = sh.querySelector('.pan');
    let otv = false; try { otv = sessionStorage.getItem(KLJUC + '-otvoren') === '1'; } catch (e) { }
    pan.hidden = !otv; dug.setAttribute('aria-expanded', String(otv));
    dug.onclick = () => { pan.hidden = !pan.hidden; dug.setAttribute('aria-expanded', String(!pan.hidden)); try { sessionStorage.setItem(KLJUC + '-otvoren', pan.hidden ? '0' : '1'); } catch (e) { } };
    sh.querySelectorAll('select').forEach(s => s.onchange = () => { const u = s.dataset.u, k = s.dataset.k; stanje[u] = stanje[u] || {}; if (s.value === IZV) delete stanje[u][k]; else stanje[u][k] = s.value; primeni(); });
    sh.querySelectorAll('[data-pre]').forEach(b => b.onclick = () => { stanje = JSON.parse(JSON.stringify(PRESETI[b.dataset.pre].v)); primeni(); render(); sh.querySelector('.pan').hidden = false; });
    sh.querySelector('[data-a="zatvori"]').onclick = () => dug.onclick();
    sh.querySelector('[data-a="kopiraj"]').onclick = async () => { const t = sh.querySelector('textarea'); t.select(); try { await navigator.clipboard.writeText(t.value); } catch (e) { try { document.execCommand('copy'); } catch (e2) { } } const b = sh.querySelector('[data-a="kopiraj"]'); b.textContent = 'Копирано ✓'; setTimeout(() => b.textContent = 'Копирај избор', 1500); };
    osveziSazetak();
  }
  const OZN = { font: 'фонт', tezina: 'дебљина', velicina: 'величина', skala: 'величина', razmak: 'размак слова', prored: 'проред', velika: 'велика слова', kurziv: 'курзив', boja: 'боја', podvuceno: 'подвучено' };
  const VRED = { da: 'да', ne: 'не', 'kao-tekst': 'као текст' };
  function osveziSazetak() {
    const t = sh.querySelector && sh.querySelector('textarea'); if (!t) return;
    const red = [];
    for (const u of [...ULOGE, { id: 'link', naziv: 'Линкови у тексту' }]) {
      const v = stanje[u.id] || {}; const k = Object.entries(v).map(([a, b]) => `${OZN[a] || a}: ${VRED[b] || (a === 'velicina' ? b + ' px' : a === 'skala' ? b + ' %' : a === 'razmak' ? b + ' em' : b)}`).join(', ');
      red.push(`${u.naziv}: ${k || 'као сада'}`);
    }
    t.value = 'ПИО „РАЈАЦ“ — избор фонтова\n' + red.join('\n');
  }
  function start() { document.body.appendChild(host); render(); primeni(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
  // странице које цртају садржај скриптом (каталог птица) — поново примени скалу кад се садржај промени
  let rafLab = 0;
  new MutationObserver(() => { if (rafLab) return; rafLab = requestAnimationFrame(() => { rafLab = 0; primeniSkalu(); }); }).observe(document.documentElement, { childList: true, subtree: true });
})();
