import re,os,sys,json,subprocess,tempfile
a,b=sys.argv[1],sys.argv[2]
files=[]
for dp,_,fs in os.walk(b):
    for f in fs: files.append(os.path.relpath(os.path.join(dp,f),b))
SC=re.compile(r'<script\b([^>]*)>(.*?)</script\s*>',re.S|re.I)
d=tempfile.mkdtemp()
bad=[];jbad=[];n=0
def chk(code,ext):
    p=os.path.join(d,'x'+ext);open(p,'w').write(code)
    r=subprocess.run(['node','--check',p],capture_output=True,text=True);return r.returncode==0,r.stderr[-300:]
for rel in files:
    A=open(os.path.join(a,rel),encoding='utf-8').read();B=open(os.path.join(b,rel),encoding='utf-8').read()
    sa=SC.findall(A);sb=SC.findall(B)
    if len(sa)!=len(sb): bad.append((rel,'број скрипти се променио'));continue
    for (aa,ca),(ab,cb) in zip(sa,sb):
        if ca==cb: continue
        n+=1
        if 'ld+json' in aa.lower() or 'application/json' in aa.lower():
            try: json.loads(ca); okA=True
            except Exception: okA=False
            try: json.loads(cb); okB=True
            except Exception as e: okB=False
            if okA and not okB: jbad.append(rel)
            continue
        if 'module' in aa.lower(): ext='.mjs'
        else: ext='.js'
        okA,_=chk(ca,ext); okB,err=chk(cb,ext)
        if okA and not okB: bad.append((rel,err))
print('измењених скрипти',n,'покварених JS',len(bad),'покварених JSON',len(jbad))
for x in bad[:10]: print(x)
print(jbad[:10])
