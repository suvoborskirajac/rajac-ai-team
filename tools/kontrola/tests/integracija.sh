#!/usr/bin/env bash
# Тест целог тока контролне скрипте на локалном PHP серверу.
# Покретање: bash tools/kontrola/tests/integracija.sh
set -u
HERE="$(cd "$(dirname "$0")/.." && pwd)"
W="$(mktemp -d)"; PUB="$W/public_html"; PORT=8097; URL="http://127.0.0.1:$PORT/kontrola/kontrola.php"
PASS=0; FAIL=0
ok() { if eval "$2"; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); echo "НЕУСПЕХ: $1"; fi; }

mkdir -p "$PUB/kontrola" "$PUB/ptice/buteo-buteo" "$PUB/ris"
echo '<h1>Птице</h1>' > "$PUB/ptice/index.html"
cp "$HERE/kontrola.php" "$PUB/kontrola/"
cat > "$PUB/index.html" <<'EOF'
<!doctype html><html lang="sr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>ПИО „Рајац“</title>
<link rel="canonical" href="https://piorajac.rs/"><meta name="description" content="Опис"></head><body>
<header><a href="/">ПИО „Рајац“</a><nav><a href="/ptice/">Птице</a></nav></header><main><h1>Рајац</h1><p>Температура 12 °C.</p></main></body></html>
EOF
cat > "$PUB/ptice/buteo-buteo/index.html" <<'EOF'
<!doctype html><html><head><meta charset="utf-8"><title>Мишар</title></head><body>
<header><a href="../../">ПИО Рајац</a><a href="/ptice/">ПТИЦЕ</a></header><h1>Мишар</h1>
<p>Степски с<strong>o</strong>ко. јан C феб.</p><a href="https://ebird.org" target="_blank">eBird</a><a href="/nema/">мртав</a>
<img src="https://rajac.rs/a.jpg"></body></html>
EOF
echo '<p>Тајно Сoко</p>' > "$PUB/ris/a.html"

ROUTER="$HERE/tests/router.php"
start_srv() {  # $1 = корисник кога је "сервер" потврдио, или празно
  [ -n "${SRV:-}" ] && { kill $SRV 2>/dev/null; wait $SRV 2>/dev/null; }
  KONTROLA_TEST_KORISNIK="$1" php -S 127.0.0.1:$PORT -t "$PUB" "$ROUTER" >/dev/null 2>&1 &
  SRV=$!; sleep 1
}
trap 'kill $SRV 2>/dev/null; wait $SRV 2>/dev/null; rm -rf "$W"' EXIT

# 1. сервер НЕ проверава лозинку (као хостинг без заштите или са игнорисаним .htaccess)
start_srv ""
c() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
ok "без лозинке: 403"                         '[ "$(c "$URL")" = "403" ]'
ok "измишљено Authorization заглавље: 403"    '[ "$(c -u napadac:lozinka "$URL")" = "403" ]'
ok "измишљено Remote-User заглавље: 403"      '[ "$(c -H "Remote-User: zoran" "$URL")" = "403" ]'
printf 'AuthType Basic\nAuthName "kontrola"\nAuthUserFile "/home/x/.htpasswds/public_html/kontrola/passwd"\nrequire valid-user\n' > "$PUB/kontrola/.htaccess"
ok ".htaccess постоји, сервер га не примењује: 403" '[ "$(c -u napadac:lozinka "$URL")" = "403" ]'
ok "POST без лозинке: 403"                    '[ "$(c -d "akcija=pokreni&csrf=x" "$URL")" = "403" ]'
ok "преузимање без лозинке: 403"              '[ "$(c "$URL?preuzmi=kontrola.json")" = "403" ]'
ok "без лозинке се ништа не прави ван public_html" '[ ! -d "$W/kontrola-podaci" ]'

# 2. сервер ЈЕСТЕ проверио лозинку (као cPanel Directory Privacy)
start_srv "zoran"
J="$W/kolacic"
PAGE=$(curl -s -c "$J" -b "$J" "$URL")
CSRF=$(printf '%s' "$PAGE" | grep -o 'name="csrf" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//;s/"//')
ok "почетна страна са дугметом" '[ -n "$CSRF" ]'

# 3. GET не може да покрене ни да обрише
curl -s -b "$J" "$URL?reset=1&obrisi=1&akcija=pokreni" >/dev/null
ok "GET не покреће контролу" '[ ! -f "$W/kontrola-podaci/stanje.json" ]'

# 4. POST без исправног CSRF кода — одбија
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$J" -d "akcija=pokreni&csrf=pogresno" "$URL")
ok "POST са погрешним CSRF одбијен (400)" '[ "$CODE" = "400" ]'

# 5. исправан ток
curl -s -b "$J" -d "akcija=pokreni&csrf=$CSRF" "$URL" >/dev/null
for i in 1 2 3 4 5; do curl -s -b "$J" "$URL?nastavi=1" >/dev/null; done
D="$W/kontrola-podaci"
ok "резултати ван public_html" '[ -f "$D/kontrola.json" ] && [ -f "$D/stranice.csv" ] && [ -f "$D/izvestaj.html" ]'
ok "у public_html нема резултата" '[ -z "$(find "$PUB" -name "*.json" -o -name "*.csv" -o -name "izvestaj.html")" ]'
ok "нема заосталих привремених фајлова" '[ -z "$(ls "$D" | grep tmp)" ]'

python3 - "$D/kontrola.json" > "$W/py.txt" <<'EOF'
import json,sys
j=json.load(open(sys.argv[1])); z=j['zbir']; p=j['stranice']['ptice/buteo-buteo/index.html']
print('stranica', z['stranica']); print('mesano', z['mesano']); print('latin', ' '.join(p['latin']))
print('blank', z['blank']); print('nedostaje', z['nedostaje']); print('pocetna_naziv', z['pocetna_naziv'])
print('home', p['home_link']); print('ext', ' '.join(p['ext_img'])); print('alt', z['alt_nedostaje'])
print('ris', 'ris/a.html' in j['stranice']); print('skip', ' '.join(j['preskoceni_folderi']))
EOF
v() { grep "^$1 " "$W/py.txt" | cut -d' ' -f2-; }
ok "прочитане 3 странице (ris прескочен)" '[ "$(v stranica)" = "3" ] && [ "$(v ris)" = "False" ]'
ok "мешано писмо кроз <strong>" '[ "$(v mesano)" = "1" ]'
ok "латинично C" '[ "$(v latin)" = "C" ]'
ok "_blank" '[ "$(v blank)" = "1" ]'
ok "мртав линк" '[ "$(v nedostaje)" = "1" ]'
ok "почетна ../../ препозната, назив погрешан" '[ "$(v home)" = "ПИО Рајац" ] && [ "$(v pocetna_naziv)" = "1" ]'
ok "туђа слика" '[ "$(v ext)" = "rajac.rs" ]'
ok "слика без alt" '[ "$(v alt)" = "1" ]'
ok "прескочени фолдери у извештају" '[ "$(v skip)" = "kontrola/ ris/" ]'

# 6. преузимање само дозвољених фајлова
ok "преузимање kontrola.json" 'curl -s -b "$J" "$URL?preuzmi=kontrola.json" | grep -q "\"verzija\": \"1.1\""'
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$J" "$URL?preuzmi=../../etc/passwd")
ok "покушај изласка из фолдера одбијен (404)" '[ "$CODE" = "404" ]'
CODE=$(curl -s -o /dev/null -w '%{http_code}' -b "$J" "$URL?preuzmi=stanje.json")
ok "stanje.json се не може преузети (404)" '[ "$CODE" = "404" ]'
ok "извештај се отвара кроз скрипту" 'curl -s -b "$J" "$URL?izvestaj=1" | grep -q "Извештај контроле"'

# 7. закључавање: док друга серија ради
exec 9>"$D/.lock"; flock 9
ok "друга серија чека док прва ради" 'curl -s -b "$J" "$URL?nastavi=1" | grep -q "Контрола већ ради"'
flock -u 9; exec 9>&-

# 8. брисање само POST-ом
curl -s -b "$J" -d "akcija=obrisi&csrf=$CSRF" "$URL" >/dev/null
ok "брисање уклања резултате" '[ ! -f "$D/kontrola.json" ] && [ ! -f "$D/stanje.json" ]'
ok "брисање не дира сајт" '[ -f "$PUB/index.html" ] && [ -f "$PUB/ptice/buteo-buteo/index.html" ]'

echo "Интеграционих тестова: $((PASS+FAIL)), прошло: $PASS, пало: $FAIL"
[ $FAIL -eq 0 ]
