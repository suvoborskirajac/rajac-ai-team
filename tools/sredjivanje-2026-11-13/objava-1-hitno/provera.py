import asyncio, os, sys
from playwright.async_api import async_playwright
from PIL import Image, ImageChops

NOVO = "/home/claude/paket/novo"
PAGES = ["", "digitalni-centar/", "rajac-vesti/ams-rajac-avgust-2026-klimatoloski-izvestaj/",
         "rajac-vesti/prvi-istrazivacki-rad-svetlosna-zagadjenost-pio-rajac/",
         "rajac-vesti/svetlosno-zagadjenje-pio-rajac-januar-jun-2026/", "rajac-leptiri/",
         "zvezdani-park/pravila-ponasanja.html", "staze/", "ptice/prirucnik/"]
os.makedirs("snimci", exist_ok=True)

def local_for(url):
    p = url.split("piorajac.rs/", 1)[1].split("?")[0]
    if p == "" or p.endswith("/"): p += "index.html"
    f = os.path.join(NOVO, p)
    return f if os.path.isfile(f) else None

CT = {"html": "text/html; charset=utf-8", "css": "text/css", "png": "image/png", "jpg": "image/jpeg", "webp": "image/webp", "svg": "image/svg+xml", "mp4": "video/mp4"}

async def shot(b, page, new, width):
    ctx = await b.new_context(viewport={"width": width, "height": 900}, ignore_https_errors=True)
    errs, missing = [], []
    if new:
        async def handler(route):
            f = local_for(route.request.url)
            if f:
                await route.fulfill(path=f, headers={"content-type": CT[f.rsplit(".", 1)[1]]})
            else:
                await route.continue_()
        await ctx.route("https://piorajac.rs/**", handler)
    pg = await ctx.new_page()
    pg.on("pageerror", lambda e: errs.append(str(e)[:100]))
    pg.on("response", lambda r: missing.append(r.url) if r.status >= 400 and "piorajac.rs" in r.url else None)
    await pg.goto("https://piorajac.rs/" + page, wait_until="load", timeout=90000)
    await pg.evaluate("document.querySelectorAll('video').forEach(v=>{v.pause&&v.pause()})")
    await pg.wait_for_timeout(2500)
    info = await pg.evaluate("""() => ({sw: document.documentElement.scrollWidth,
        h1: document.querySelectorAll('h1').length,
        broken: [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.src.includes('piorajac')).map(i => i.src).slice(0,5),
        vid: [...document.querySelectorAll('video source, video')].map(v => v.currentSrc || v.src).filter(Boolean).slice(0,2)})""")
    tag = page.strip("/").replace("/", "_") or "pocetna"
    fn = f"snimci/{tag}_{width}_{'novo' if new else 'staro'}.png"
    await pg.screenshot(path=fn, full_page=False)
    await ctx.close()
    return fn, info, errs, missing

async def main():
    async with async_playwright() as p:
        b = await p.chromium.launch(proxy={"server": os.environ["HTTPS_PROXY"]}, args=["--ignore-certificate-errors"])
        for page in PAGES:
            for w in (390, 1440):
                a, ia, ea, ma = await shot(b, page, False, w)
                n, inn, en, mn = await shot(b, page, True, w)
                A, N = Image.open(a).convert("RGB"), Image.open(n).convert("RGB")
                diff = ImageChops.difference(A, N).getbbox() if A.size == N.size else "size"
                print(f"{page or '/'} {w}px diff={diff} staro={ia} novo={inn} greske_novo={en} 404_novo={mn[:3]}", flush=True)
        await b.close()
asyncio.run(main())
