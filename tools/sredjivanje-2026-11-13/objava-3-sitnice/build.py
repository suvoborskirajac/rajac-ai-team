import json,hashlib,re,os,shutil,glob
S=lambda b:hashlib.sha256(b).hexdigest()[:20]
ops={}
def add(p,lst): ops[p]=lst
add('digitalni-centar/index.html',[['str','узорковaних','узоркованих',1]])
add('sisari/myotis-myotis/index.html',[['str','сонотeка','сонотека',1]])
add('sisari/myotis-mystacinus/index.html',[['str','проверa','провера',1]])
add('sisari/rhinolophus-ferrumequinum/index.html',[['str','контролa','контрола',1]])
add('planiranje-posete/index.html',[['str','размерa','размера',1]])
add('biljke/index.html',[['re',r'href="([a-z0-9-]+)/index\.html"',r'href="$1/"',272]])
for f in sorted(glob.glob('orig/ptice/prirucnik/*.html')):
    p=f[5:]; n=open(f,encoding='utf-8').read().count('assets/style.css?v=6510'); add(p,[['str','assets/style.css?v=6510','assets/style.css?v=6511',n]])
new_urls=['amsrajac/weathernext3/o-servisu.php','gis-rajac/','gis-rajac/osobine/planer.html','gis-rajac/osobine/posetioci.html','kako-stici/','planiranje-posete/','planiranje-posete/vidikovci/','rajac-mreza/','rajac-vesti/generator-karata-vegetacije-pio-rajac/','rajac-vesti/novi-nalazi-dnevnih-leptira-rajac-2026/','rajac-vesti/radovi/dnevni-leptiri-pio-rajac-2026/','rajac-vesti/zvezdani-park-rajac-pocetak-projekta-2026/','smestaj/','smestaj/apartmani-kolubara/','smestaj/bukov-raj/','smestaj/planinarski-dom/','smestaj/rajacki-konaci/','smestaj/stari-konak-suvobor/','smestaj/zoranov-vajat/','za-vlasnike/saradnja/']
sm=open('orig/sitemap-static.xml',encoding='utf-8').read()
assert all(f'<loc>https://piorajac.rs/{u}</loc>' not in sm for u in new_urls)
pri=lambda u: '0.8' if u.count('/')<=1 or u.startswith('smestaj/') and u.count('/')==1 else '0.6'
block="".join(f'  <url><loc>https://piorajac.rs/{u}</loc><lastmod>2026-11-13</lastmod><priority>{pri(u)}</priority></url>\n' for u in new_urls)
add('sitemap-static.xml',[['str','</urlset>',block+'</urlset>',1]])
smi=open('orig/sitemap.xml',encoding='utf-8').read()
add('sitemap.xml',[['str','sitemap-static.xml</loc><lastmod>2026-10-31</lastmod>','sitemap-static.xml</loc><lastmod>2026-11-13</lastmod>',1]])
out=[]
shutil.rmtree('novo',ignore_errors=True)
for p,lst in ops.items():
    b=open('orig/'+p,'rb').read(); s=b.decode('utf-8')
    for t,a,c,n in lst:
        if t=='str': assert s.count(a)==n,(p,a,s.count(a)); s=s.replace(a,c)
        else:
            s,k=re.subn(a,c.replace('$1',r'\1'),s); assert k==n,(p,k)
    nb=s.encode('utf-8'); os.makedirs(os.path.dirname('novo/'+p) or 'novo',exist_ok=True); open('novo/'+p,'wb').write(nb)
    out.append({'p':p,'b':S(b),'a':S(nb),'ops':lst})
json.dump(out,open('ops.json','w'),ensure_ascii=False)
print(len(out), sum(len(o['ops']) for o in out))
