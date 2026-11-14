import asyncio, re, json, sys, time
from urllib.parse import urljoin, urlparse, urldefrag
import aiohttp, os, ssl

BASE = "https://piorajac.rs/"
SKIP = re.compile(r'^/(ris|baza|kopindeksi|monitoring-izvestaji|inventar|kontrola|wp-content|wp-admin|wp-includes|cgi-bin|_seo_backup|agro-kopernikus/copernicus_data|amsrajac/dnevna-statistika|vlasnici)(/|$)')
NONHTML = re.compile(r'\.(jpg|jpeg|png|gif|webp|svg|pdf|mp3|ogg|wav|mp4|zip|css|js|json|xml|csv|geojson|tif|tiff|kml|kmz|ico|woff2?|ttf|txt|docx?|xlsx?|pptx?)$', re.I)
MAX = int(sys.argv[1]) if len(sys.argv) > 1 else 2500

pages = {}
linkstatus = {}
queue = asyncio.Queue()
seen = set()

def norm(u):
    u, _ = urldefrag(u)
    p = urlparse(u)
    if p.netloc not in ("piorajac.rs", "www.piorajac.rs"):
        return None
    path = p.path or "/"
    return "https://piorajac.rs" + path + (("?" + p.query) if p.query else "")

async def fetch(session, url):
    try:
        async with session.get(url, allow_redirects=True, timeout=aiohttp.ClientTimeout(total=40)) as r:
            ct = r.headers.get("content-type", "")
            body = await r.read() if "html" in ct else b""
            return r.status, str(r.url), ct, body, [str(h.url) for h in r.history]
    except Exception as e:
        return 0, url, "", b"", [repr(e)[:80]]

async def worker(session):
    while True:
        url = await queue.get()
        try:
            if len(pages) >= MAX:
                continue
            st, final, ct, body, hist = await fetch(session, url)
            rec = {"status": st, "final": final, "ct": ct, "redirects": hist, "size": len(body)}
            pages[url] = rec
            if st == 200 and "html" in ct:
                html = body.decode("utf-8", "replace")
                rec["html"] = html
                fp = urlparse(final).path
                if final.startswith("https://piorajac.rs") or final.startswith("https://www.piorajac.rs"):
                    for m in re.finditer(r'''<a\b[^>]*?href\s*=\s*["']([^"'#][^"']*)["']''', html, re.I):
                        href = m.group(1).strip()
                        if href.startswith(("mailto:", "tel:", "javascript:", "data:")):
                            continue
                        u = norm(urljoin(final, href))
                        if not u: continue
                        pth = urlparse(u).path
                        if SKIP.match(pth) or "?" in u: continue
                        if NONHTML.search(pth): continue
                        if u not in seen and len(seen) < MAX:
                            seen.add(u); queue.put_nowait(u)
        finally:
            queue.task_done()

async def main():
    conn = aiohttp.TCPConnector(limit=8, ssl=ssl.create_default_context(cafile=os.path.expanduser("~/.ccr/ca-bundle.crt")))
    async with aiohttp.ClientSession(connector=conn, trust_env=True, headers={"User-Agent": "PIO-Rajac-audit/1.0"}) as s:
        # sitemap
        st, f, ct, b, h = await fetch(s, BASE + "sitemap-static.xml")
        async with s.get(BASE + "sitemap-static.xml") as r:
            sm = await r.text()
        locs = re.findall(r"<loc>([^<]+)</loc>", sm)
        json.dump(locs, open("audit/sitemap.json", "w"))
        for u in [BASE] + locs:
            u = norm(u.strip())
            if u and u not in seen:
                seen.add(u); queue.put_nowait(u)
        ws = [asyncio.create_task(worker(s)) for _ in range(8)]
        t0 = time.time()
        while not queue.empty() or queue._unfinished_tasks:
            await asyncio.sleep(10)
            print(f"{time.time()-t0:.0f}s pages={len(pages)} queue={queue.qsize()}", flush=True)
            if queue._unfinished_tasks == 0: break
        for w in ws: w.cancel()
    json.dump(pages, open("audit/pages.json", "w"), ensure_ascii=False)
    print("done", len(pages), "sitemap", len(locs))

asyncio.run(main())
