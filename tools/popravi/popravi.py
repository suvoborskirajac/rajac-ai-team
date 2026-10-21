#!/usr/bin/env python3
"""ПИО „РАЈАЦ“ — машинске исправке текста у статичним HTML страницама (2026-10-21).
Мења САМО: облик назива ПИО „Рајац“, наводнике „…“, латинична слова-двојнике унутар ћириличних речи,
target="_blank". Не дира URL-ове, класе, научне називе, код у <script>/<style> (осим текстуалних
низова, и тамо само безбедне замене), <code>/<pre>/<textarea>.
Употреба: popravi.py ULAZ_ROOT IZLAZ_ROOT spisak.txt izvestaj.json
"""
import json, os, re, sys, collections

CYR = 'А-Яа-яЂЈЉЊЋЏђјљњћџЁё'
LAT = 'A-Za-z'
LATX = 'A-Za-zČĆŠŽĐčćšžđ'
# латинично слово -> ћирилични двојник
HOM = {'a': 'а', 'e': 'е', 'o': 'о', 'p': 'р', 'c': 'с', 'x': 'х', 'y': 'у', 'j': 'ј',
       'A': 'А', 'B': 'В', 'C': 'С', 'E': 'Е', 'H': 'Н', 'K': 'К', 'M': 'М', 'O': 'О', 'P': 'Р', 'T': 'Т', 'X': 'Х', 'Y': 'У', 'J': 'Ј'}
TOK = re.compile(f'[{CYR}{LATX}]+')
stat = collections.Counter()
rucno = collections.Counter()   # мешане речи које се не могу безбедно поправити


def fix_mixed(text, ctx_ok=True):
    """Латинична слова у ћириличној речи замени ћириличним.
    Једно латинично слово у речи = омашка при куцању (изгледа исто) → ћирилични двојник (c→с, p→р, y→у…).
    Два или више латиничних слова = остатак пресловљавања из српске латинице → српска латиница у ћирилицу (c→ц, p→п, lj→љ…).
    Речи које личе на енглеске (y, x, w, q, „цх“, „тх“, „пх“) се не дирају — иду на ручну проверу."""
    def rep(m):
        w = m.group(0)
        if not (re.search(f'[{CYR}]', w) and re.search(f'[{LATX}]', w)):
            return w
        s = m.string; i = m.start()
        if i > 0 and s[i - 1] in '°0123456789\\':
            rucno[w] += 1; return w
        lat = re.findall(f'[{LATX}]', w)
        cyr_n = len(re.findall(f'[{CYR}]', w))
        if re.search(f'[{LAT}]{{3,}}', w) and re.search(f'[{CYR}]{{2,}}', w) and re.match(f'^[{CYR}]+[{LAT}]+$|^[{LAT}]+[{CYR}]+$', w) and not re.search(r'[a-z][А-ЯЂЈЉЊЋЏ]|[A-Z][а-я]', w):
            pass
        if re.search(r'цх|тх|пх|сх|wх|sh|ch|zh|th|ph', w.lower()) or re.search(f'^[A-Z]{{2,}}[{CYR}]|[{CYR}][A-Z]{{2,}}$', w):
            rucno[w] += 1; return w
        if len(lat) == 1:
            ch = lat[0]
            if ch not in HOM or ch in 'xXyY':
                rucno[w] += 1; return w
            if cyr_n < 2 and not (ch == 'j' and cyr_n >= 1):
                rucno[w] += 1; return w
            stat['мешана слова'] += 1
            return w.replace(ch, HOM[ch])
        # више латиничних слова: остатак српске латинице
        if w.isupper() and len(w) <= 5:
            rucno[w] += 1; return w
        if any(ch in 'yYxXwWqQ' for ch in lat) or len(lat) > 3:
            rucno[w] += 1; return w
        # спојене речи из суседних ознака (нпр. „ПородицаRosaceae“) — латинички део почиње великим словом усред речи
        if re.search(f'[{CYR}][A-ZČĆŠŽĐ][a-zčćšžđ]{{2,}}', w) or re.search(f'[a-zčćšžđ]{{2,}}[А-ЯЂЈЉЊЋЏ]', w):
            rucno[w] += 1; return w
        new = sr_lat2cyr(w)
        if new == w or re.search(f'[{LATX}]', new):
            rucno[w] += 1; return w
        stat['мешана слова'] += 1
        return new
    return TOK.sub(rep, text)


SR_DI = [('Lj', 'Љ'), ('LJ', 'Љ'), ('lj', 'љ'), ('Nj', 'Њ'), ('NJ', 'Њ'), ('nj', 'њ'), ('Dž', 'Џ'), ('DŽ', 'Џ'), ('dž', 'џ')]
SR_1 = dict(zip('abcčćdđefghijklmnoprsštuvzžABCČĆDĐEFGHIJKLMNOPRSŠTUVZŽ', 'абцчћдђефгхијклмнопрсштувзжАБЦЧЋДЂЕФГХИЈКЛМНОПРСШТУВЗЖ'))
def sr_lat2cyr(w):
    for a, b in SR_DI:
        w = w.replace(a, b)
    return ''.join(SR_1.get(ch, ch) for ch in w)



# Латински називи извора, програма и фајлова које је аутоматско пресловљавање погрешно претворило у ћирилицу
XC = {'481324': ('Emberiza', 'Ortolan Bunting'), '101463': ('Carduelis', 'European Goldfinch'), '588228': ('Anthus', 'Tree Pipit'),
      '471334': ('Serinus', 'European Serin'), '549215': ('Coturnix', 'Common Quail'), '83449': ('Hirundo', 'Barn Swallow'),
      '479662': ('Saxicola', 'Whinchat'), '342793': ('Muscicapa', 'Spotted Flycatcher'), '501590': ('Phylloscopus', 'Common Chiffchaff'),
      '571979': ('Mystery', 'Identity unknown'), '512590': ('Aegithalos', 'Long-tailed Tit'), '468918': ('Phoenicurus', 'Common Redstart'),
      '596811': ('Motacilla', 'Grey Wagtail'), '108362': ('Turdus', 'Mistle Thrush'), '543236': ('Sylvia', 'Eurasian Blackcap'),
      '503635': ('Sylvia', 'Common Whitethroat'), '83152': ('Phasianus', 'Common Pheasant'), '541308': ('Coccothraustes', 'Hawfinch'),
      '249313': ('Ficedula', 'Red-breasted Flycatcher'), '468919': ('Phylloscopus', 'Willow Warbler'),
      '123548': ('Turdus', 'Common Blackbird'), '86749': ('Passer', 'House Sparrow')}
def _xc(m):
    g = XC.get(m.group(5))
    if not g: return m.group(0)
    ep = m.group(3) if re.match(r'[a-z]+$', m.group(3)) else ('mystery' if g[0] == 'Mystery' else m.group(3))
    stat['пресловљено назад у латиницу'] += 1
    ext = 'ogg' if m.group(6) in ('огг', 'ogg') else 'mp3'
    return (('File:' if m.group(1) else '') + f'{g[0]} {ep} - {g[1]}{" -" if m.group(0).count(" - ") > 1 else ""} XC{m.group(5)}.{ext}')
XC_RE = re.compile(r'(Филе:|File:)?([А-Яа-яЂЈЉЊЋЏђјљњћџA-Za-z]+) ([a-zа-я]+) - ([^<>\n"]+?)(?: -)? X[ЦC](\d+)\.(мп3|mp3|огг|ogg)')
RL = {'а':'a','б':'b','в':'v','г':'g','д':'d','ђ':'dj','е':'e','ж':'zh','з':'z','и':'i','ј':'j','к':'k','л':'l','љ':'lj','м':'m','н':'n','њ':'nj','о':'o','п':'p','р':'r','с':'s','т':'t','ћ':'c','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','џ':'dz','ш':'sh'}
def lat(w):
    return ''.join((RL.get(ch.lower(), ch).capitalize() if ch.isupper() else RL.get(ch, ch)) for ch in w)
def _file(m):
    stat['пресловљено назад у латиницу'] += 1
    return 'File:' + lat(m.group(2)) + '.ogg'
FILE_RE = re.compile(r'(Филе:|File:)([^<>\n"]{1,60}?)\.огг')
TRANS = [
    ('10000бирдс.цом', '10000birds.com'), ('10000бирдс', '10000birds'), ('авибирдс.цом', 'avibirds.com'),
    ('ДиноАнималс.цом', 'DinoAnimals.com'), ('ДиноАнималс', 'DinoAnimals'), ('А-З Анималс', 'A-Z Animals'), ('А-З Animals', 'A-Z Animals'),
    ('БирдсофтхеWорлд', 'Birds of the World'), ('BirdsoftheWorld', 'Birds of the World'), ('Бирда.орг', 'Birda.org'),
    ('БТО БирдФацтс', 'BTO BirdFacts'), ('БТО BirdFacts', 'BTO BirdFacts'), ('БирдФацтс', 'BirdFacts'), ('БирдФорум', 'BirdForum'),
    ('АллАбоутБирдс', 'All About Birds'), ('Виа Понтица', 'Via Pontica'), ('БНА Naturalists', 'BNA Naturalists'),
    ('цорвид ИД гуиде', 'corvid ID guide'), ('corvid ИД guide', 'corvid ID guide'), ('цорвид гуиде', 'corvid guide'), ('Hooded цроw', 'Hooded crow'),
    ('Belgrade бирдинг', 'Belgrade birding'), ('бирдинг лист', 'birding list'),
    ('ресидент + wинтер виситор', 'resident + winter visitor'), ('ресидент бреедер', 'resident breeder'), ('бреединг мигрант', 'breeding migrant'),
    ('ресидент/партиал', 'resident/partial'), ('ресидент лист', 'resident list'), ('(ресидент)', '(resident)'), ('birding ресидент', 'birding resident'),
    ('лате March арривал', 'late March arrival'), ('сецонд wеек April арривал', 'second week April arrival'), ('мид-Марцх арривал', 'mid-March arrival'),
    ('мид-Марцх arrival', 'mid-March arrival'), ('March арривал', 'March arrival'), ('April арривал', 'April arrival'),
    ('Владимир Yu. Аркхипов, Аркхивов, CC БY-СА 3.0', 'Vladimir Yu. Arkhipov, Arkhivov, CC BY-SA 3.0'), ('CC БY-СА', 'CC BY-SA'), ('CC БY', 'CC BY'),
    ('Бирд/Еуе АФ', 'Bird/Eye AF'), ('Bird/Eye АФ', 'Bird/Eye AF'), ('РАW + ЈПЕГ', 'RAW + JPEG'), ('RAW + ЈПЕГ', 'RAW + JPEG'), ('ЈПЕГ', 'JPEG'),
    ('еБирд.орг: Мy eBird → Доwнлоад Мy Data', 'eBird.org: My eBird → Download My Data'), ('Доwнлоад Мy Дата', 'Download My Data'),
    ('Мy eBird', 'My eBird'), ('Wи-Фи', 'Wi-Fi'), ('Поwер банк', 'Пауербанк'), ('поwер банк', 'пауербанк'), ('Соунд ИД', 'Sound ID'),
    ('слушни бирдwатцхинг', 'слушно посматрање птица'),
    ('МигратионАтлас', 'Migration Atlas'), ('оисеаух.нет', 'oiseaux.net'), ('Данубе Делта wилдлифе', 'Danube Delta Wildlife'),
    ('Ficedula хуполеуца.огг', 'Ficedula hypoleuca.ogg'), ('Wоодланд Труст', 'Woodland Trust'), ('Serbia ревиеw', 'Serbia review'),
    ('ХТМЛ5', 'HTML5'), ('ХТМЛ ', 'HTML '),
    ('Ав/А или Тв/С', 'Av/A или Tv/S'), ('Тв/С', 'Tv/S'), ('ф/5.6–ф/8', 'f/5.6–f/8'), ('Ауто ИСО', 'Auto ISO'), ('Auto ИСО', 'Auto ISO'),
    ('АФ-Ц/АИ Серво', 'AF-C/AI Servo'), ('АФ-Ц/АИ Servo', 'AF-C/AI Servo'), ('шире АФ поље', 'шире AF поље'),
]
# пола латиничне, пола ћириличне речи — исправљено ручно, по смислу реченице
RUCNO = {'рашirenим':'раширеним','Целогодишnje':'Целогодишње','Целогодишnji':'Целогодишњи','OTROVНА':'ОТРОВНА','КML':'KML','трulo':'труло',
 'пролеtnog':'пролетног','вidi':'види','crн':'црн','стare':'старе','тrogлоксен':'троглоксен','тrogлоксена':'троглоксена','смrче':'смрче','смrчом':'смрчом',
 'букove':'букове','вреeme':'време','Букva':'Буква','Воћњak':'Воћњак','дeo':'део','кратkog':'кратког','риваla':'ривала','Prisуство':'Присуство',
 'оrijентација':'оријентација','Jedаn':'Један','враћaju':'враћају','куha':'кува','jedноставније':'једноставније','jedна':'једна','jedном':'једном',
 'сjаja':'сјаја','поврshine':'површине','pojedinaчних':'појединачних','ideально':'идеално','korак':'корак','Kiша':'Киша','генitalnom':'гениталном',
 'шумskim':'шумским','тиhом':'тихом','викanjem':'викањем','боlesним':'болесним','свaki':'сваки','кровovima':'крововима','Отkriće':'Откриће',
 'hотспот':'хотспот','обjekata':'објеката','хrastove':'храстове','дажdevnjaka':'даждевњака','берbe':'бербе','краjevima':'крајевима','свaka':'свака',
 'Перnis':'Pernis','питfall':'pitfall','цrvена':'црвена','crвено':'црвено','бrezom':'брезом','заostatak':'заостатак','споriје':'спорије','споrijе':'спорије',
 'crвена':'црвена','трoglобионт':'троглобионт','хемоорijентациjа':'хемооријентација','земљиshту':'земљишту','hвата':'хвата','kabaniца':'кабаница',
 'CRВЕНЕ':'ЦРВЕНЕ','CRВЕНА':'ЦРВЕНА','crвене':'црвене','rusула':'русула','nарочито':'нарочито','Gљиве':'Гљиве','мaj':'мај','jаsниjе':'јасније',
 'рибnjаци':'рибњаци','веhа':'већа','отвореnoм':'отвореном','биноkular':'бинокулар','стаjаhе':'стајаће','текуhе':'текуће','споre':'споре',
 'браnе':'бране','ноhу':'ноћу','потиljак':'потиљак','бушu':'буше','безkичмењаци':'бескичмењаци','Малaise':'Malaise','sи-си-си':'си-си-си',
 'сеоbage':'сеобе','цvена':'црвена','жутcastу':'жућкасту','Мужjак':'Мужјак','беликy':'белику','беликe':'белике','Pajасем':'Pajасем'}
RUCNO_RE = re.compile('(?<![\\w])(' + '|'.join(sorted(map(re.escape, RUCNO), key=len, reverse=True)) + ')(?![\\w])')
JE_RE = re.compile(r'(?<=[\s(„])jе(?=[\s,.;:!?)])')
def fix_trans(t):
    for a, b in TRANS:
        if a in t:
            stat['пресловљено назад у латиницу'] += t.count(a); t = t.replace(a, b)
    if 'мп3' in t or 'XЦ' in t or 'огг' in t:
        t = XC_RE.sub(_xc, t)
    if '.огг' in t:
        t = FILE_RE.sub(_file, t)
    t, k = RUCNO_RE.subn(lambda m: RUCNO[m.group(1)], t); stat['мешана слова (ручни речник)'] += k
    t, k = JE_RE.subn('је', t); stat['мешана слова (ручни речник)'] += k
    return t

Q_CLOSE_BAD = re.compile(r'„([^„“”"\n<>]{1,160})[”"]')


def fix_quotes(text, straight=True):
    n0 = text
    text, k = Q_CLOSE_BAD.subn(lambda m: '„' + m.group(1) + '“', text)
    stat['наводници „…”/„…"'] += k
    if straight:
        def r(m):
            stat['наводници "…"'] += 1
            return '„' + m.group(1) + '“'
        text = re.sub(f'(?<![\\w=]{{1}})"([{CYR}][^"„“”<>\\n]{{0,80}}?)"(?![\\w])', r, text)
    return text


def fix_name(text, latin_page=False):
    def cnt(k):
        stat['назив'] += k
    # „ПИО „Рајац“ без отварања/затварања, без наводника
    text, k = re.subn(r'(?<![\w„])ПИО\s+Рајац(?![\w“”"])', 'ПИО „Рајац“', text); cnt(k)
    text, k = re.subn(r'(?<![\w„])ПИО\s+РАЈАЦ(?![\w“”"])', 'ПИО „РАЈАЦ“', text); cnt(k)
    text, k = re.subn(r'ПИО\s+Рајац[”"“]', 'ПИО „Рајац“', text); cnt(k)
    text, k = re.subn(r'ПИО\s+РАЈАЦ[”"“]', 'ПИО „РАЈАЦ“', text); cnt(k)
    text, k = re.subn(r'ПИО\s*„Рајац(?![\w“”"])', 'ПИО „Рајац“', text); cnt(k)
    text, k = re.subn(r'ПИО\s*"Рајац["”“]', 'ПИО „Рајац“', text); cnt(k)
    text, k = re.subn(r'ПИО\s*"РАЈАЦ["”“]', 'ПИО „РАЈАЦ“', text); cnt(k)
    text, k = re.subn(r'(Предео изузетних одлика)\s*"Рајац["”“]', r'\1 „Рајац“', text); cnt(k)
    text, k = re.subn(r'ПИО\s*„РАЈАЦ(?![\w“”"])', 'ПИО „РАЈАЦ“', text); cnt(k)
    text, k = re.subn(r'(Предео изузетних одлика)\s+Рајац(?![\w“”"])', r'\1 „Рајац“', text); cnt(k)
    text, k = re.subn(r'PIO\s*„Рајац“', 'ПИО „Рајац“', text); cnt(k)
    if not latin_page:
        text, k = re.subn(r'(?<![\w/.-])PIO\s*[„"]?Rajac[“”"]?(?![\w/.-])', 'ПИО „Рајац“', text); cnt(k)
        text, k = re.subn(r'(?<![\w/.-])PIO\s*[„"]?RAJAC[“”"]?(?![\w/.-])', 'ПИО „РАЈАЦ“', text); cnt(k)
    return text


def fix_text(t, latin_page):
    t = fix_trans(t)
    t = fix_name(t, latin_page)
    t = fix_quotes(t, straight=True)
    if not latin_page:
        t = fix_mixed(t)
    return t


def fix_jsstring(t, latin_page):
    """Безбедне замене унутар <script>: само не-ASCII знаци (наводници „“, ћирилица)."""
    t = fix_trans(t)
    t = re.sub(r'(?<![\w„])ПИО\s+Рајац(?![\w“”"\'`])', lambda m: (stat.update(['назив']), 'ПИО „Рајац“')[1], t)
    t, k = re.subn(r'„([^„“”"\'`\n<>\\]{1,160})”', r'„\1“', t); stat['наводници „…”/„…"'] += k
    t, k = re.subn(r'ПИО „Рајац”', 'ПИО „Рајац“', t); stat['назив'] += k
    if not latin_page:
        # мешана слова само у ћириличним речима унутар низова (идентификатори нису ћирилични)
        t = fix_mixed(t)
    return t


ATTR_TXT = re.compile(r'(\s(?:alt|title|aria-label|placeholder|data-title|data-caption)\s*=\s*)("([^"]*)"|\'([^\']*)\')', re.I)
META_CONTENT = re.compile(r'<meta\b[^>]*>', re.I)
BLANK = re.compile(r'\s+target\s*=\s*(["\']?)_blank\1', re.I)
SEG = re.compile(r'(<!--.*?-->|<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<(?:code|pre|textarea|svg)\b[^>]*>.*?</(?:code|pre|textarea|svg)\s*>|<[^>]+>)', re.S | re.I)


def fix_attr_val(v, latin_page):
    enc = '&quot;' in v
    if enc: v = v.replace('&quot;', '"')
    v = fix_trans(v)
    v = fix_name(v, latin_page)
    v, k = Q_CLOSE_BAD.subn(lambda m: '„' + m.group(1) + '“', v)
    stat['наводници „…”/„…"'] += k
    if not latin_page:
        v = fix_mixed(v)
    return v.replace('"', '&quot;') if '"' in v else v


def fix_tag(tag, latin_page):
    if tag.startswith('<!--'):
        return tag
    t, k = BLANK.subn('', tag); stat['target=_blank'] += k
    t, k = re.subn(r'type="/ptice/assets/audio/(mpeg|ogg)"', r'type="audio/\1"', t); stat['поправљен type аудио снимка'] += k
    def ra(m):
        q = m.group(2)[0]; val = m.group(3) if q == '"' else m.group(4)
        nv = fix_attr_val(val, latin_page)
        if q == "'": nv = nv.replace("'", '&#39;') if "'" in nv and "'" not in val else nv
        return m.group(1) + q + nv + q
    t = ATTR_TXT.sub(ra, t)
    if t[:5].lower() == '<meta' and re.search(r'(name|property)\s*=\s*["\'](description|og:title|og:description|og:site_name|twitter:title|twitter:description|application-name|apple-mobile-web-app-title)["\']', t, re.I):
        t = re.sub(r'(\scontent\s*=\s*")([^"]*)(")', lambda m: m.group(1) + fix_attr_val(m.group(2), latin_page) + m.group(3), t)
    return t


def fix_script(block, latin_page):
    m = re.match(r'(<script\b[^>]*>)(.*?)(</script\s*>)', block, re.S | re.I)
    if not m: return block
    open_t, body, close_t = m.groups()
    open_t, k = BLANK.subn('', open_t); stat['target=_blank'] += k
    if re.search(r'\bsrc\s*=', open_t, re.I) and not body.strip():
        return open_t + body + close_t
    # target="_blank" у HTML шаблонима унутар JS-а
    body, k = re.subn(r'\s+target=\\?(["\'])_blank\\?\1', '', body); stat['target=_blank (у скрипти)'] += k
    body = fix_jsstring(body, latin_page)
    return open_t + body + close_t


def is_latin_page(html):
    body = re.sub(r'<script.*?</script>|<style.*?</style>|<[^>]+>', ' ', html, flags=re.S | re.I)
    c = len(re.findall(f'[{CYR}]', body)); l = len(re.findall(f'[{LAT}]', body))
    return l > c


def fix_html(html):
    latin_page = is_latin_page(html)
    out = []; pos = 0
    for m in SEG.finditer(html):
        if m.start() > pos:
            out.append(fix_text(html[pos:m.start()], latin_page))
        seg = m.group(0); low = seg[:7].lower()
        if low.startswith('<script'):
            out.append(fix_script(seg, latin_page))
        elif low.startswith('<style') or seg.startswith('<!--'):
            out.append(seg)
        elif re.match(r'<(code|pre|textarea|svg)\b', seg, re.I):
            # само отварајућа ознака може имати target/alt — садржај се не дира
            mm = re.match(r'(<[^>]+>)(.*)', seg, re.S)
            out.append(fix_tag(mm.group(1), latin_page) + mm.group(2))
        else:
            out.append(fix_tag(seg, latin_page))
        pos = m.end()
    out.append(fix_text(html[pos:], latin_page))
    return ''.join(out)


def main():
    src, dst, lst, rep = sys.argv[1:5]
    changed = []
    per = {}
    for rel in open(lst, encoding='utf-8').read().split('\n'):
        if not rel: continue
        p = os.path.join(src, rel)
        try:
            raw = open(p, 'rb').read(); html = raw.decode('utf-8')
        except Exception:
            stat['прескочено (није UTF-8)'] += 1; continue
        before = collections.Counter(stat)
        new = fix_html(html)
        for _ in range(3):
            nn = fix_html(new)
            if nn == new: break
            new = nn
        if new != html:
            q = os.path.join(dst, rel); os.makedirs(os.path.dirname(q), exist_ok=True)
            open(q, 'w', encoding='utf-8', newline='').write(new)
            changed.append(rel)
            per[rel] = {k: stat[k] - before[k] for k in stat if stat[k] - before[k]}
    json.dump({'izmenjeno': len(changed), 'zbir': stat, 'po_stranici': per, 'rucno': rucno.most_common()}, open(rep, 'w', encoding='utf-8'), ensure_ascii=False, indent=1)
    print(len(changed), dict(stat))


if __name__ == '__main__':
    main()
