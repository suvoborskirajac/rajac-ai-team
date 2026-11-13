import asyncio, json, os
from playwright.async_api import async_playwright
URLS = """/ biljke/ ptice/ insekti/ sisari/ gljive/ trofodinamika/ istrazivacki-park-rajac/biljke/ ptice/prirucnik/ zvezdani-park/ svetlosno-zagadjenje/ rajac-vesti/ amsrajac/ pozarna-opasnost/ politika-privatnosti/ digitalni-centar/ smestaj/ staze/ za-posetioce/ za-vlasnike/ o-platformi/ aplikacije/ kontakt/ ko-upravlja/ geologija/ bioakustika/ gis-rajac/ monitoring-izvestaji/ izvestaj-2025/ staponeti/ biljke/abies-alba-mill/ ptice/buteo-buteo/ sisari/apodemus-agrarius/ insekti/dnevni-leptiri/ gljive/amanita-rubescens/ kodeks/ mesec/ planiranje-posete/""".split()
async def main():
    out = []
    async with async_playwright() as p:
        b = await p.chromium.launch(proxy={"server": os.environ.get("HTTPS_PROXY")} if os.environ.get("HTTPS_PROXY") else None, args=["--ignore-certificate-errors"])
        ctx = await b.new_context(viewport={"width": 390, "height": 844}, ignore_https_errors=True)
        sem = asyncio.Semaphore(4)
        async def one(path):
            async with sem:
                pg = await ctx.new_page(); errs = []
                pg.on("pageerror", lambda e: errs.append(str(e)[:120]))
                try:
                    await pg.goto("https://piorajac.rs/" + path.lstrip("/"), wait_until="load", timeout=60000)
                    await pg.wait_for_timeout(1500)
                    r = await pg.evaluate("""() => {const w=document.documentElement.scrollWidth; let wide=[]; for (const e of document.querySelectorAll('body *')) {const r=e.getBoundingClientRect(); if (r.right>window.innerWidth+2 && r.width>0 && getComputedStyle(e).position!=='fixed') {wide.push(e.tagName.toLowerCase()+(e.className&&typeof e.className==='string'?'.'+e.className.split(' ')[0]:'')); if (wide.length>3) break;}}
                    const ff=s=>s? getComputedStyle(s).fontFamily.split(',')[0].replace(/["']/g,''):null;
                    return {sw:w, wide, h1:ff(document.querySelector('h1')), body:ff(document.querySelector('p')), h2:ff(document.querySelector('h2'))}}""")
                    out.append({"url": path, **r, "errs": errs[:3]})
                except Exception as e:
                    out.append({"url": path, "fail": str(e)[:100]})
                await pg.close()
        await asyncio.gather(*[one(u) for u in URLS])
        await b.close()
    json.dump(out, open("audit/mobile.json", "w"), ensure_ascii=False, indent=0)
    for o in sorted(out, key=lambda x: x["url"]):
        print(o)
asyncio.run(main())
