#!/usr/bin/env python3
"""Други пролаз: српске речи латиницом на ћириличним страницама → ћирилица.
Мења само видљиви текст (текстуални чворови ван script/style/code/i/em, plus изричито наведени JS низови
и атрибути на апликацији теренских пријава). Имена аутора у заслугама за фотографије, латински називи,
енглески термини, јединице и бројеви се не дирају.
Употреба: latinica.py ИЗВОР ОДРЕДИШТЕ ИЗВЕШТАЈ.json
"""
import re, sys, os, json, collections

LAT2CYR = [('DŽ','Џ'),('Dž','Џ'),('dž','џ'),('LJ','Љ'),('Lj','Љ'),('lj','љ'),('NJ','Њ'),('Nj','Њ'),('nj','њ')]
SINGLE = dict(zip('ABVGDĐEŽZIJKLMNOPRSTĆUFHCČŠabvgdđežzijklmnoprstćufhcčš',
                  'АБВГДЂЕЖЗИЈКЛМНОПРСТЋУФХЦЧШабвгдђежзијклмнопрстћуфхцчш'))
def cyr(s):
    for a, b in LAT2CYR: s = s.replace(a, b)
    return ''.join(SINGLE.get(ch, ch) for ch in s)

C = 'А-Яа-яЂЈЉЊЋЏђјљњћџ'
# Српске речи које су у књизи и извештајима остале латиницом усред ћириличне реченице
RECI = set('''koji koja koje kako dok što je od oko kao prvi jedne jedna jednog jeftin emitovanu površina površine
između elemenata izloženost bili perioda nakon prema godišnje doprinese ovakve inicijative uvek kod kada se jedva
kojih kojima kojoj kojem koju kojeg'''.split())
POSEBNO = {'often': 'често', 'durante': 'током'}

# изричите замене по страници (стари текст → нови), само у видљивом тексту
STRANICE = {
 'kopindeksi/vegetacioni-pokrivac/index.html': [('>Krompir<','>Кромпир<'),('>Ostale žitarice<','>Остале житарице<'),('>Kukuruz<','>Кукуруз<'),
     ('>Pšenica<','>Пшеница<'),('>Sveže povrće<','>Свеже поврће<'),('>Soja<','>Соја<')],
 'rajac-oblaci/index.html': [('без SVG/crtež fallback замена','без замене SVG цртежима')],
 'vodic/index.html': [('PRE/POSLE','ПРЕ/ПОСЛЕ')],
 'knjiga/metodologija/index.html': [('od SQM-a koji','од SQM-а који'),('объектив','објектив')],
 'leaflet-terenski-izvestaji/index.html': [('ПИО „Рајац“ - Sistem za prijavljivanje problema','ПИО „Рајац“ – Систем за пријављивање проблема'),('🌿 Коров (opšte)','🌿 Коров (опште)')],
 'kalendar/index.html': [('Дивља свиња — активност, роv (Март)','Дивља свиња — активност, ровање (март)')],
 'trofodinamika/vrsta/syrphidae.html': [('Ларве су хищне','Ларве су грабљиве')],
}
for f in ('agroportal/ndmi_viewer.html','agroportal/ndmi-final/ndmi_viewer.html','ndmi_viewer.html','ndmi-final/ndmi_viewer.html'):
    STRANICE[f] = [('Područje:','Подручје:'),('коју је генерисала skripta','коју је генерисала скрипта')]

# апликација теренских пријава: ознаке у HTML-у и поруке у JavaScript-у
LEAFLET = 'leaflet-terenski-izvestaji/index.html'
LEAF_TEKST = ['Prijava','Registracija','Korisničko ime / Email','Lozinka','Uloguj se','Posetilac','Korisničko ime','Vlasnik',
 'Puno ime i prezime','Puno ime i prezime (obavezno za vlasnike)','Lozinka (min. 6 karaktera)','Registruj se','Uloga:',
 'Proverava se lokacija...','Poruke','Komunikacija sa upravljačem','Odjavi se','📋 Registrovani korisnici','🗑️ Obriši izabrane',
 'Učitavanje...','Коров (opšte)','Prijava problema']
LEAF_JS = ['Server je vratio neispravan odgovor: ','Greška u komunikaciji: ','Geolokacija nije podržana.','Ne mogu da proverim lokaciju.',
 'Lokacija nije dostupna.','Admin - lokacija nije potrebna ✓','Proverava se lokacija...','Nalazite se unutar granica PIO Rajac ✓',
 'Nalazite se van granica PIO Rajac','Administrator','Vlasnik nepokretnosti','Posetilac','Prijava',
 '<div class="admin-user-loading">Učitavanje...</div>','<div class="admin-user-empty">Greška pri učitavanju.</div>',
 '<div class="admin-user-empty">Nema registrovanih korisnika.</div>','Vlasnik','<div class="admin-user-empty">Greška: ',
 '🗑️ Obriši (${checkedBoxes.length})','🗑️ Obriši izabrane','Greška: ','Neuspešno brisanje','Unesite korisničko ime i lozinku.',
 'Uloguj se','Greška pri prijavi.','Uspešna prijava!','Vlasnici moraju uneti puno ime i prezime.','Lozinka mora imati najmanje 6 karaktera.',
 'Registruj se','Greška pri registraciji.','Nalog kreiran! Možete se prijaviti.','Izaberi lokaciju problema.',
 'Morate uključiti lokaciju u pregledaču.','Možete prijaviti problem samo unutar granica PIO Rajac.','Šaljem...','Greška',
 'Prijava poslata!','Prijava...','Brisanje...','Registracija...',' Zahvalnica je poslata na vaš email.',' Možete pratiti status u Porukama.',
 'Da li ste sigurni da želite da obrišete ${usernames.length} korisnika?\\n\\nKorisnici: ${usernames.join(\', \')}']
LEAF_ATTR = [('placeholder="npr. Petar Petrović"','placeholder="нпр. Петар Петровић"'),('title="Novi izveštaj"','title="Нови извештај"'),
 ('title="Moja lokacija"','title="Моја локација"')]

def leaf_cyr(v):
    v = v.replace('PIO Rajac', 'ПИО „Рајац“').replace('Admin - ', 'Администратор — ').replace('email', 'имејл').replace('Email', 'имејл')
    # ${...} и HTML ознаке остају нетакнути
    delovi = re.split(r'(\$\{[^}]*\}|<[^>]+>|\\n)', v)
    return ''.join(d if (d.startswith('${') or d.startswith('<') or d == '\\n') else cyr(d) for d in delovi)

SKIP = re.compile(r'<(script|style|code|pre|kbd|samp|textarea|svg|math)\b.*?</\1>', re.S | re.I)
TAG = re.compile(r'<[^>]+>')

def tekst_cvorovi(html):
    """Враћа (почетак, крај) видљивих текстуалних делова ван script/style/code; i/em се посебно прескачу."""
    zab = [(m.start(), m.end()) for m in SKIP.finditer(html)]
    out = []; poz = 0; zi = 0; dubina_i = 0
    for m in TAG.finditer(html):
        while zi < len(zab) and zab[zi][1] <= m.start(): zi += 1
        if zi < len(zab) and zab[zi][0] <= m.start() < zab[zi][1]:
            poz = m.end(); continue
        if m.start() > poz and dubina_i == 0: out.append((poz, m.start()))
        t = m.group(0).lower()
        if re.match(r'<(i|em)\b', t): dubina_i += 1
        elif re.match(r'</(i|em)\s*>', t): dubina_i = max(0, dubina_i - 1)
        poz = m.end()
    return [(a, b) for a, b in out if not any(za <= a < zk for za, zk in zab)]

TOK = re.compile(r'[A-Za-zčćšžđČĆŠŽĐ%s]+' % C)
def popravi_reci(seg, stat):
    ts = list(TOK.finditer(seg)); zamene = []
    jeC = lambda w: re.fullmatch('[%s]+' % C, w) is not None
    for i, m in enumerate(ts):
        w = m.group(0); wl = w.lower()
        if wl not in RECI and wl not in POSEBNO: continue
        if w != wl and w != w.capitalize(): continue
        pre = seg[max(0, m.start() - 2):m.start()]
        if '(' in pre or '/' in pre: continue
        # сусед мора бити ћирилична реч, или ланац речника који води до ћирилице
        j = i - 1
        while j >= 0 and ts[j].group(0).lower() in RECI: j -= 1
        k = i + 1
        while k < len(ts) and ts[k].group(0).lower() in RECI: k += 1
        lev = j >= 0 and jeC(ts[j].group(0)); des = k < len(ts) and jeC(ts[k].group(0))
        if not (lev or des): continue
        nova = POSEBNO.get(wl) or cyr(w)
        if wl in POSEBNO and w[0].isupper(): nova = nova.capitalize()
        zamene.append((m.start(), m.end(), nova)); stat['реч у ћириличној реченици'] += 1
    for a, b, n in reversed(zamene): seg = seg[:a] + n + seg[b:]
    return seg

def popravi(rel, html, stat):
    if rel in STRANICE:
        for a, b in STRANICE[rel]:
            n = html.count(a)
            if n: html = html.replace(a, b); stat['изричита замена'] += n
    if rel == LEAFLET:
        for a, b in LEAF_ATTR:
            if a in html: html = html.replace(a, b); stat['апликација: атрибут'] += 1
        for v in LEAF_JS:
            for q in ("'", '"', '`'):
                s = q + v + q
                if s in html: html = html.replace(s, q + leaf_cyr(v) + q); stat['апликација: порука'] += 1
        for v in LEAF_TEKST:
            for a, b in ((f'>{v}<', f'>{leaf_cyr(v)}<'), (f'>\n{v}\n', f'>\n{leaf_cyr(v)}\n')):
                n = html.count(a)
                if n: html = html.replace(a, b); stat['апликација: ознака'] += n
            # ознаке са увлачењем (текст у засебном реду)
            html, n = re.subn(r'(>\s*)' + re.escape(v) + r'(\s*<)', lambda m: m.group(1) + leaf_cyr(v) + m.group(2), html)
            stat['апликација: ознака'] += n
        html = html.replace('– Prijava problema</h1>', '– Пријава проблема</h1>')
    # речи у ћириличним реченицама
    delovi = tekst_cvorovi(html); out = []; poz = 0
    for a, b in delovi:
        seg = html[a:b]
        if re.search('[%s]' % C, seg) and re.search('[a-zčćšžđ]', seg):
            seg2 = popravi_reci(seg, stat)
        else: seg2 = seg
        out.append(html[poz:a]); out.append(seg2); poz = b
    out.append(html[poz:])
    return ''.join(out)

def main():
    src, dst, rep = sys.argv[1:4]
    L = [l for l in open('/tmp/scope.txt', encoding='utf-8').read().split('\n') if l]
    dups = set(open('/tmp/dupd.txt').read().split())
    stat = collections.Counter(); per = {}
    for rel in L:
        if rel.split('/')[0] in dups: continue
        try: html = open(os.path.join(src, rel), encoding='utf-8').read()
        except Exception: continue
        pre = collections.Counter(stat)
        new = popravi(rel, html, stat)
        if new != html:
            q = os.path.join(dst, rel); os.makedirs(os.path.dirname(q) or '.', exist_ok=True)
            open(q, 'w', encoding='utf-8', newline='').write(new)
            per[rel] = {k: stat[k] - pre[k] for k in stat if stat[k] - pre[k]}
    json.dump({'izmenjeno': len(per), 'zbir': stat, 'po_stranici': per}, open(rep, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(len(per), dict(stat))

if __name__ == '__main__':
    main()
