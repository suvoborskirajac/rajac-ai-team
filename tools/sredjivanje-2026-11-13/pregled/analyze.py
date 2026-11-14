import json, re, collections, hashlib
from urllib.parse import urljoin, urlparse, urldefrag
from html.parser import HTMLParser

pages = json.load(open("audit/pages.json"))
sitemap = set(u.strip() for u in json.load(open("audit/sitemap.json")))
R = {}

def strip_tags(html):
    html = re.sub(r'(?is)<(script|style|noscript|svg|code|pre)\b.*?</\1>', ' ', html)
    return html

class TextP(HTMLParser):
    def __init__(s):
        super().__init__(); s.stack=[]; s.out=[]
    def handle_starttag(s,t,a):
        if t in ('br','img','input','meta','link','hr','source','wbr'): return
        s.stack.append(t)
    def handle_endtag(s,t):
        if t in s.stack:
            while s.stack and s.stack.pop()!=t: pass
    def handle_data(s,d):
        ital = any(t in ('i','em','cite','code','kbd','var') for t in s.stack)
        s.out.append((d, ital))

CYR = re.compile(r'[Ѐ-ӿ]')
LAT = re.compile(r'[A-Za-zčćšžđČĆŠŽĐ]')
WORD = re.compile(r'[\wЀ-ӿ]+', re.U)

ok = {u:p for u,p in pages.items() if p.get("status")==200 and "html" in p.get("ct","")}
bad = {u:p for u,p in pages.items() if u not in ok}

# referrers
refs = collections.defaultdict(set)
inlinks = collections.Counter()
for u,p in ok.items():
    for m in re.finditer(r'''<a\b[^>]*?href\s*=\s*["']([^"'#][^"']*)["']''', p["html"], re.I):
        t = urldefrag(urljoin(p["final"], m.group(1).strip()))[0]
        pu = urlparse(t)
        if pu.netloc in ("piorajac.rs","www.piorajac.rs"):
            t = "https://piorajac.rs"+(pu.path or "/")
            refs[t].add(u)
            if t != u: inlinks[t]+=1

rows = []
menus = collections.Counter(); menu_ex = {}
homelinks = collections.Counter()
fontsets = collections.Counter(); font_ex = {}
for u,p in ok.items():
    h = p["html"]; r = {"url":u}
    low = h.lower()
    r["title"] = (re.search(r'(?is)<title[^>]*>(.*?)</title>', h) or [None,""])[1].strip()
    r["h1"] = len(re.findall(r'(?i)<h1\b', h))
    r["viewport"] = 'name="viewport"' in low or "name='viewport'" in low
    r["desc"] = bool(re.search(r'(?i)<meta[^>]+name=["\']description["\']', h))
    r["lang"] = (re.search(r'(?i)<html[^>]*\blang=["\']([^"\']+)', h) or [None,""])[1]
    r["blank"] = len(re.findall(r'(?i)\starget\s*=\s*["\']_blank', h))
    r["size"] = p["size"]
    r["b64"] = sum(len(m) for m in re.findall(r'data:image/[a-z+]+;base64,[A-Za-z0-9+/=]{2000,}', h))
    r["http"] = len(re.findall(r'''(?i)(?:src|href)\s*=\s*["']http://(?!localhost)''', h))
    r["ext_img"] = sorted(set(urlparse(x).netloc for x in re.findall(r'''(?i)<img[^>]+src\s*=\s*["'](https?://[^"']+)''', h) if 'piorajac.rs' not in x))
    r["wp"] = bool(re.search(r'wp-content|wp-includes', h))
    # fonts
    gf = re.findall(r'family=([A-Za-z0-9+]+)', h)
    ff = re.findall(r'font-family\s*:\s*["\']?([A-Za-z0-9 \-]+)', h)
    fs = frozenset([g.replace('+',' ') for g in gf] + [f.strip() for f in ff if f.strip().lower() not in ('inherit','var','sans-serif','serif','monospace','system-ui','-apple-system','blinkmacsystemfont','segoe ui','georgia','arial','helvetica','times new roman','courier new','roboto','ui-monospace','menlo','consolas','helvetica neue')])
    r["fonts"] = sorted(fs)
    # header / home link
    hdr = re.search(r'(?is)<(header|nav)\b.*?</\1>', h)
    hdrhtml = hdr.group(0) if hdr else h[:6000]
    hm = re.search(r'''(?is)<a\b[^>]*href\s*=\s*["'](?:https://piorajac\.rs)?/?["'][^>]*>(.*?)</a>''', hdrhtml)
    ht = re.sub(r'<[^>]+>','',hm.group(1)).strip() if hm else None
    ht = re.sub(r'\s+',' ',ht) if ht else None
    r["home"] = ht; homelinks[ht]+=1
    # menu signature: links in first nav
    nav = re.search(r'(?is)<nav\b.*?</nav>', h)
    sig = None
    if nav:
        ls = re.findall(r'''(?is)<a\b[^>]*href\s*=\s*["']([^"']+)["']''', nav.group(0))
        sig = hashlib.md5("|".join(ls).encode()).hexdigest()[:8] + f"/{len(ls)}"
        menus[sig]+=1; menu_ex.setdefault(sig, u)
    r["menu"] = sig
    # text
    tp = TextP();
    try: tp.feed(strip_tags(h))
    except Exception: pass
    txt = " ".join(d for d,_ in tp.out)
    ncy = len(CYR.findall(txt)); nla = len(re.findall(r'[A-Za-z]', txt))
    r["script"] = "cyr" if ncy > nla else "lat"
    mixed = set(); latw = collections.Counter()
    for d,ital in tp.out:
        for w in WORD.findall(d):
            if CYR.search(w) and LAT.search(w) and not w.isdigit(): mixed.add(w)
            elif r["script"]=="cyr" and not ital and LAT.search(w) and not CYR.search(w) and len(w)>2 and not re.fullmatch(r'[A-Z0-9]+', w):
                latw[w]+=1
    r["mixed"] = sorted(mixed)[:15]; r["nmixed"]=len(mixed)
    r["latw"] = latw.most_common(12); r["nlat"] = sum(latw.values())
    r["badquotes"] = len(re.findall(r'„[^“”"\n]{1,60}”|"[А-Ша-ш][^"\n]{0,40}"|”[А-Ша-ш]', txt))
    r["pio_variants"] = sorted(set(re.findall(r'ПИО\s*[„"”“]?\s*Р(?:ајац|АЈАЦ)[“"”]?|PIO\s*[„"”“]?Rajac[“"”]?', txt)))
    r["todo"] = sorted(set(re.findall(r'(?i)\b(lorem ipsum|TODO|FIXME|XXX|placeholder|у изради|uskoro|ускоро|coming soon|test stranica|тест)\b', txt)))[:5]
    r["insitemap"] = u in sitemap
    r["inlinks"] = inlinks[u]
    rows.append(r)

bad_list = []
for u,p in bad.items():
    bad_list.append({"url":u,"status":p["status"],"final":p.get("final"),"ct":p.get("ct"),"refs":sorted(refs.get(u,[]))[:5],"nrefs":len(refs.get(u,[]))})
sm_missing = sorted(s for s in sitemap if s.strip() not in pages)
json.dump({"rows":rows,"bad":bad_list,"menus":menus.most_common(),"menu_ex":menu_ex,"home":homelinks.most_common(),"sm_missing":sm_missing}, open("audit/result.json","w"), ensure_ascii=False, indent=0)

def P(*a): print(*a)
P("pages ok", len(ok), "bad", len(bad))
P("status counts", collections.Counter(b["status"] for b in bad_list))
P("non-html 200:", sum(1 for b in bad_list if b["status"]==200))
P("redirected off-site", [ (u,p["final"]) for u,p in ok.items() if "piorajac.rs" not in p["final"]][:5])
P("\nMENU variants", len(menus)); [P(" ",k,v,menu_ex[k]) for k,v in menus.most_common(25)]
P("no nav:", sum(1 for r in rows if not r["menu"]))
P("\nHOME link texts"); [P(" ",repr(k),v) for k,v in homelinks.most_common(30)]
P("\nno viewport", sum(not r["viewport"] for r in rows), "h1!=1", sum(r["h1"]!=1 for r in rows), "no title", sum(not r["title"] for r in rows), "no desc", sum(not r["desc"] for r in rows))
P("blank pages", sum(r["blank"]>0 for r in rows), "total", sum(r["blank"] for r in rows))
P("mixed pages", sum(r["nmixed"]>0 for r in rows), "words", sum(r["nmixed"] for r in rows))
P("latin-on-cyr pages", sum(r["nlat"]>0 for r in rows))
P("lat pages", sum(r["script"]=="lat" for r in rows))
P("badquotes pages", sum(r["badquotes"]>0 for r in rows))
P("b64>100k", [(r["url"],r["b64"]) for r in rows if r["b64"]>100000][:10])
P("big >800k", [(r["url"],r["size"]) for r in rows if r["size"]>800000][:15])
P("http refs pages", sum(r["http"]>0 for r in rows))
P("wp refs", [r["url"] for r in rows if r["wp"]][:10], sum(r["wp"] for r in rows))
P("ext img hosts", collections.Counter(h for r in rows for h in r["ext_img"]).most_common(10))
P("not in sitemap", sum(not r["insitemap"] for r in rows), "orphans (in sitemap, 0 inlinks)", sum(r["insitemap"] and r["inlinks"]==0 for r in rows))
P("sitemap urls not fetched/bad", len([b for b in bad_list if b["url"] in sitemap]))
P("todo", [(r["url"],r["todo"]) for r in rows if r["todo"]][:15])
fc = collections.Counter(tuple(r["fonts"]) for r in rows); P("font combos", len(fc)); [P(" ",k,v) for k,v in fc.most_common(12)]
