#!/usr/bin/env bash
# Покреће контролну скрипту (tools/kontrola) над локалном копијом јавних страница.
# $1 = pages.json (резултат прегледа), $2 = радни фолдер, $3 = порт
set -u
PJ="$1"; W="$2"; PORT="$3"; PUB="$W/public_html"; K=/home/claude/rajac-ai-team/tools/kontrola
rm -rf "$W"; mkdir -p "$PUB/kontrola"
python3 - "$PJ" "$PUB" <<'EOF'
import json,sys,os
pj,pub=sys.argv[1],sys.argv[2]
for u,p in json.load(open(pj)).items():
    h=p.get('html')
    if not h or p.get('status')!=200 or p['final']!=u: continue
    path=u.split('piorajac.rs/',1)[1].split('?')[0]
    if path=='' or path.endswith('/'): path+='index.html'
    if not path.endswith(('.html','.htm')): continue
    f=os.path.join(pub,path); os.makedirs(os.path.dirname(f),exist_ok=True)
    open(f,'w',encoding='utf-8').write(h)
EOF
cp "$K/kontrola.php" "$PUB/kontrola/"
URL="http://127.0.0.1:$PORT/kontrola/kontrola.php"
KONTROLA_TEST_KORISNIK=claude php -S 127.0.0.1:$PORT -t "$PUB" "$K/tests/router.php" >/dev/null 2>&1 & SRV=$!; sleep 1
J="$W/k"; CSRF=$(curl -s -c "$J" -b "$J" "$URL" | grep -o 'name="csrf" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$J" -d "akcija=pokreni&csrf=$CSRF" "$URL" >/dev/null
for i in $(seq 1 400); do [ -f "$W/kontrola-podaci/kontrola.json" ] && break; curl -s -b "$J" "$URL?nastavi=1" >/dev/null; done
kill $SRV; ls "$W/kontrola-podaci"
