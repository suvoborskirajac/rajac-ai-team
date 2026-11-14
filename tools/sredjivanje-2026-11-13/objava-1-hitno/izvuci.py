"""Тачка 1: извлачење уграђених base64 датотека и мале исправке. Ради над копијом у orig/, пише у novo/."""
import re, base64, os, shutil, hashlib

SRC = "/home/claude/paket/orig"
DST = "/home/claude/paket/novo"
shutil.rmtree(DST, ignore_errors=True)
log = []

def rd(p):
    with open(os.path.join(SRC, p), encoding="utf-8", newline="") as f:
        return f.read()

def wr(p, s):
    full = os.path.join(DST, p); os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "w", encoding="utf-8", newline="") as f:
        f.write(s)

def wrb(p, b):
    full = os.path.join(DST, p); os.makedirs(os.path.dirname(full), exist_ok=True)
    with open(full, "wb") as f:
        f.write(b)

EXT = {"image/png": "png", "image/jpeg": "jpg", "image/webp": "webp", "image/svg+xml": "svg", "video/mp4": "mp4"}
B64 = re.compile(r'data:([a-z0-9/+.-]+);base64,([A-Za-z0-9+/=\s]{3000,}?)(?=["\')])')

def extract(page, folder_url, names):
    """names: list of base file names in order of appearance (without extension)."""
    h = rd(page); i = 0; out = []; pos = 0
    for m in B64.finditer(h):
        mime = m.group(1); data = base64.b64decode(re.sub(r"\s", "", m.group(2)))
        name = f"{names[i]}.{EXT[mime]}"; i += 1
        rel = os.path.join(folder_url.strip("/"), name)
        wrb(rel, data)
        out.append(h[pos:m.start()]); out.append("/" + rel); pos = m.end()
        log.append(f"{page}: {mime} {len(data)//1024} KB -> /{rel}")
    out.append(h[pos:])
    assert i == len(names), (page, i, len(names))
    new = "".join(out); wr(page, new)
    log.append(f"{page}: {len(h)//1024} KB -> {len(new.encode())//1024} KB")
    return new

# 1. Дигитални центар — видео и постер
extract("digitalni-centar/index.html", "/digitalni-centar/media/", ["digitalni-centar-pregled-poster", "digitalni-centar-pregled"])

# 2. Вести и Лептири
extract("rajac-vesti/ams-rajac-avgust-2026-klimatoloski-izvestaj/index.html",
        "/rajac-vesti/ams-rajac-avgust-2026-klimatoloski-izvestaj/grafikoni/",
        ["01-pokrivenost-merenja", "02-dnevna-temperatura", "03-dnevni-hod-temperature", "04-srednja-avgustovska-temperatura-1950-2020",
         "05-minimalne-temperature-p10-p90", "06-avgustovske-padavine-1950-2020", "07-dnevni-vpd", "08-dnevni-hod-vlaznosti-vpd",
         "09-padavine", "10-ruza-vetrova", "11-pritisak"])
extract("rajac-vesti/prvi-istrazivacki-rad-svetlosna-zagadjenost-pio-rajac/index.html",
        "/rajac-vesti/prvi-istrazivacki-rad-svetlosna-zagadjenost-pio-rajac/grafikoni/",
        [f"grafikon-{n}" for n in range(1, 6)])
extract("rajac-vesti/svetlosno-zagadjenje-pio-rajac-januar-jun-2026/index.html",
        "/rajac-vesti/svetlosno-zagadjenje-pio-rajac-januar-jun-2026/slike/", ["radijansa-jun-2026"])
extract("rajac-leptiri/index.html", "/rajac-leptiri/slike/", ["rajac-leptiri-naslovna"])

# 3. Почетна — лого и слике из wp-content/uploads
h = extract("index.html", "/assets/img/pocetna/", ["pocetna-logo"])
n1 = len(re.findall(r"(?:https://piorajac\.rs)?/wp-content/uploads/", h))
h = re.sub(r"(?:https://piorajac\.rs)?/wp-content/uploads/", "/assets/img/arhiva/", h)
wr("index.html", h); log.append(f"index.html: {n1} адреса wp-content/uploads/ -> /assets/img/arhiva/")
for line in open("/home/claude/paket/wp-slike.txt"):
    p = line.strip(); dst = p.replace("wp-content/uploads/", "assets/img/arhiva/")
    os.makedirs(os.path.dirname(os.path.join(DST, dst)), exist_ok=True)
    shutil.copyfile(os.path.join("/home/claude/paket/wpdl", p), os.path.join(DST, dst))

# 4. Звездани парк — линк за е-пошту
for p in ["zvezdani-park/07-opservatorija-i-oprema.html", "zvezdani-park/pravila-ponasanja.html"]:
    h = rd(p); bad = 'href="[mailto:zvezdanipark@piorajac.rs](mailto:zvezdanipark@piorajac.rs)"'
    assert h.count(bad) == 1; wr(p, h.replace(bad, 'href="mailto:zvezdanipark@piorajac.rs"')); log.append(f"{p}: mailto исправљен")

# 5. Стазе — главни наслов h2 -> h1, исти изглед
h = rd("staze/index.html")
old = '<h2 id="mainTitle">ПЕШАЧКЕ СТАЗЕ НА РАЈЦУ</h2>'; assert h.count(old) == 1
h = h.replace(old, '<h1 id="mainTitle">ПЕШАЧКЕ СТАЗЕ НА РАЈЦУ</h1>')
c1 = h.count(".rta-header .rta-minihero h2{"); c2 = h.count(".rta-header h2{")
h = h.replace(".rta-header .rta-minihero h2{", ".rta-header .rta-minihero :is(h1,h2){").replace(".rta-header h2{", ".rta-header :is(h1,h2){")
wr("staze/index.html", h); log.append(f"staze/index.html: h1; CSS правила проширена ({c1}+{c2})")

# 6. Приручник птица — заглавље на уском екрану
css = rd("ptice/prirucnik/assets/style.css")
css += ("\n\n/* 2026-11-13: заглавље на телефону (до 430 px) без бочног скрола */\n"
        "@media (max-width:430px){.topbar .wrap{gap:8px}.topbar .wrap .brand{min-width:0;flex:1 1 auto}"
        ".topbar .wrap .brand strong{white-space:normal;line-height:1.1}"
        ".field-mode-toggle64{padding:6px 8px;font-size:10.5px;flex:0 0 auto}.topbar .navbtn{flex:0 0 auto}}\n")
wr("ptice/prirucnik/assets/style.css", css); log.append("ptice/prirucnik/assets/style.css: додато правило за ≤430 px")

print("\n".join(log))
