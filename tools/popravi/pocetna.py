#!/usr/bin/env python3
"""Линк ка почетној страници сајта на страницама које га немају у заглављу:
приручник „Посматрање птица“ (quick-nav + фиока) и поглавља књига (мени „Садржај“)."""
import sys, os, json, re
src, dst = sys.argv[1:3]
fam = json.load(open(sys.argv[3]))
HOME = 'https://piorajac.rs/'
n = {'prirucnik': 0, 'knjiga': 0}
def citaj(rel):
    p = os.path.join(dst, rel) if os.path.exists(os.path.join(dst, rel)) else os.path.join(src, rel)
    return open(p, encoding='utf-8').read()
def pisi(rel, s):
    q = os.path.join(dst, rel); os.makedirs(os.path.dirname(q), exist_ok=True); open(q, 'w', encoding='utf-8', newline='').write(s)
for rel in fam['quick-nav']:
    s = citaj(rel)
    if f'class="rajac-pocetna" href="{HOME}"' in s: continue
    s2 = s.replace('class="quick-nav">', f'class="quick-nav"><a class="rajac-pocetna" href="{HOME}">ПИО „РАЈАЦ“</a>', 1)
    s2 = s2.replace('<h2>Садржај</h2><div class="drawer-group-title">', f'<h2>Садржај</h2><a class="rajac-pocetna" href="{HOME}">← ПИО „РАЈАЦ“ — почетна страница</a><div class="drawer-group-title">', 1)
    if s2 != s: pisi(rel, s2); n['prirucnik'] += 1
for rel in fam['chapter-nav-top']:
    s = citaj(rel)
    if 'cnt-menu-pocetna' in s: continue
    s2 = s.replace('<ol class="cnt-menu-list">', f'<ol class="cnt-menu-list"><li class="cnt-menu-pocetna"><a href="{HOME}"><span class="cnt-menu-n">⌂</span><span class="cnt-menu-t">ПИО „РАЈАЦ“ — почетна страница</span></a></li>', 1)
    if s2 != s: pisi(rel, s2); n['knjiga'] += 1
print(n)
