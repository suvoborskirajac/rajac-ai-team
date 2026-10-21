import re,sys,collections,json,html
from html.parser import HTMLParser
ROOT=sys.argv[1]; L=[l for l in open('/tmp/scope.txt').read().split('\n') if l]
SR=set('''se je i u na od do za sa da ne kao ili su koji koja koje koju to po iz pri nije kod ali a o prema posle pre sve samo može mogu jer kada kad gde biti bio bila bilo ima nema već još vrlo često retko oko između preko kroz tokom svoje svoj njegov njena njihov ova ovaj ove ovo taj ta te ti mu joj ih ga smo ste sam će bi bude budu treba mnogo malo više manje dok zatim takođe ovde tu tamo jedan jedna jedno dva tri godine godina dana minuta sati vrsta vrste uz bez među ispod iznad pored'''.split())
EN=set('the and of is are to in for with from by this that on at as be or an it not your you we our was were have has which'.split())
SKIP={'script','style','code','pre','kbd','samp','i','em','svg','math','textarea','noscript','title'}
class P(HTMLParser):
    def __init__(s): super().__init__(convert_charrefs=True); s.st=[]; s.out=[]; s.skipd=0
    def handle_starttag(s,t,a):
        if t in ('br','img','meta','link','input','hr','wbr','source','area','col','base','param','track','embed'): return
        cls=dict(a).get('class') or ''
        sk= t in SKIP or re.search(r'sci|latin|lat-|mono|code|binom',cls) is not None or dict(a).get('lang','').startswith(('la','en'))
        s.st.append((t,sk)); s.skipd+=sk
    def handle_endtag(s,t):
        for i in range(len(s.st)-1,-1,-1):
            if s.st[i][0]==t:
                for _,sk in s.st[i:]: s.skipd-=sk
                del s.st[i:]; break
    def handle_data(s,d):
        if not s.skipd and d.strip(): s.out.append(d)
W=re.compile(r"[A-Za-zČĆŠŽĐčćšžđ]+")
res=collections.defaultdict(list); cnt=collections.Counter()
for f in L:
    try: t=open(ROOT+'/'+f,encoding='utf-8').read()
    except: continue
    p=P(); 
    try: p.feed(t)
    except Exception: continue
    txt=' '.join(p.out)
    cyr=len(re.findall('[А-Яа-яЂЈЉЊЋЏђјљњћџ]',txt)); lat=len(re.findall('[A-Za-z]',txt))
    if cyr<200 or cyr<lat: continue
    for seg in p.out:
        # runs of Latin words (allow spaces, punctuation, digits)
        for m in re.finditer(r"[A-Za-zČĆŠŽĐčćšžđ][A-Za-zČĆŠŽĐčćšžđ0-9 ,.;:()–\-’'!?„“”\"/%°]*[A-Za-zČĆŠŽĐčćšžđ.]",seg):
            r=m.group(0); ws=[w.lower() for w in W.findall(r)]
            if len(ws)<2: continue
            sr=sum(w in SR for w in ws); en=sum(w in EN for w in ws); dia=bool(re.search('[čćšžđČĆŠŽĐ]',r))
            if en>=1 and en>=sr: continue
            if (sr>=1 and len(ws)>=3) or (dia and len(ws)>=2) or sr>=2:
                res[f].append(r.strip()); cnt[f.split('/')[0]]+=1
json.dump(res,open(sys.argv[2],'w'),ensure_ascii=False,indent=0)
print(sum(len(v) for v in res.values()),'segmenata na',len(res),'strana'); print(cnt.most_common(30))
