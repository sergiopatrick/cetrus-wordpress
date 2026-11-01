#!/usr/bin/env python3
"""
Cetrus · Black November 2026 · validacao da producao como visitante anonimo.

  python3 pos-virada.py foto <rotulo>            le todas as URLs e grava fotos/<rotulo>.json
  python3 pos-virada.py comparar <antes> <depois> compara duas fotos (FALHA / ALERTA / OK)

Fluxo: "foto pre-black" antes da virada, "foto pos-black" depois, e comparar. Le os cursos do
product-sitemap e as paginas fixas abaixo, 1 pagina a cada ~1,5 s, e para no primeiro 403/429
(desafio anti-robo do WP.com: nao contornar, esperar e rodar de novo).

O que vira FALHA na comparacao:
  status diferente; title, description, canonical, robots, og:title, tipos do JSON-LD ou H1 diferentes;
  H2 que sumiu (menos os de H2_SAEM_NA_BLACK); pagina que devia estar Black e nao esta (ou o contrario); link de popup apontando
  para popup original (14050, 29363, 20500, 28094) numa pagina Black; curso sem caminho para formulario.
ALERTA: H2 a mais (acrescimos aprovados da Home e do "Quem viu"), peso do HTML +30%, curso novo/sumido,
  teste A/B do time (redirecionamento para -a/ ou -b/, H1 e H2 dessas paginas nao sao comparados).
"""
import base64
import json
import os
import random
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from html import unescape

BASE = 'https://cetrus.com.br'
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Safari/537.36'
FIXAS = ['/', '/vitrine/', '/conteudos-gratuitos/', '/lista-vip-black/', '/?s=ultrassonografia', '/especialidades/',
         '/institucional/', '/modalidades/pos-graduacao/', '/modalidades/fellowship/', '/pagina-que-nao-existe-qa/']
# telas que ficam iguais a producao com a Black ligada (cetrus_black_tela_fora e paginas sem modelo)
SEM_BLACK = {'/lista-vip-black/', '/especialidades/', '/institucional/', '/pagina-que-nao-existe-qa/'}
TPL_SEM_BLACK = {'28779'}  # fellowship v2 (a graduacao entrou na Black em 31/10)
# H2 que saem de proposito na versao Black (28/10: newsletter fora da Home)
H2_SAEM_NA_BLACK = {'/': ['Assine a newsletter do Cetrus']}
POPUP_ORIGINAL = {'14050', '29363', '20500', '28094'}
AQUI = os.path.dirname(os.path.abspath(__file__))
FOTOS = os.path.join(AQUI, 'fotos')


def baixa(caminho):
    sep = '&' if '?' in caminho else '?'
    url = BASE + caminho + sep + 'qa=' + str(random.randint(1, 10 ** 9))
    req = urllib.request.Request(url, headers={'User-Agent': UA, 'Accept-Language': 'pt-BR'})
    try:
        r = urllib.request.urlopen(req, timeout=60)
        return r.status, r.geturl(), r.read().decode('utf-8', 'ignore')
    except urllib.error.HTTPError as e:
        return e.code, url, e.read().decode('utf-8', 'ignore')


def urls_cursos():
    _, _, xml = baixa('/product-sitemap.xml')
    locs = re.findall(r'<loc>([^<]+)</loc>', xml)
    return [urllib.parse.urlparse(u).path for u in locs if '/cursos/' in u]


def txt(s):
    return re.sub(r'\s+', ' ', unescape(re.sub(r'<[^>]+>', ' ', s))).strip()


def meta(html, nome):
    m = re.search(r'<meta[^>]+(?:name|property)="' + re.escape(nome) + r'"[^>]+content="([^"]*)"', html)
    return unescape(m.group(1)) if m else ''


def popups(html):
    ids = []
    for m in re.finditer(r'popup(?::|%3A)open(?:&amp;|&|%26)settings(?:=|%3D)([A-Za-z0-9+/=%]+)', html):
        try:
            ids.append(str(json.loads(base64.b64decode(urllib.parse.unquote(m.group(1)))).get('id')))
        except Exception:
            ids.append('?')
    ids += re.findall(r'data-(?:popup-inscricao-id|popup-target|elementor-popup-id)=["\'](\d+)', html)
    return sorted(set(ids))


def analisa(caminho, status, final, html):
    head = html[:html.find('</head>') + 7] if '</head>' in html else html[:20000]
    body = (re.search(r'<body[^>]*class="([^"]*)"', html) or [None, ''])[1]
    ld = []
    for bloco in re.findall(r'<script[^>]+application/ld\+json[^>]*>([\s\S]*?)</script>', html):
        ld += re.findall(r'"@type"\s*:\s*"([^"]+)"', bloco)
    return {
        'caminho': caminho, 'status': status, 'final': urllib.parse.urlparse(final).path,
        'title': txt((re.search(r'<title>([\s\S]*?)</title>', head) or [None, ''])[1]),
        'description': meta(head, 'description'), 'robots': meta(head, 'robots'), 'og_title': meta(head, 'og:title'),
        'canonical': (re.search(r'<link[^>]+rel="canonical"[^>]+href="([^"]*)"', head) or [None, ''])[1],
        'jsonld': sorted(set(ld)),
        'h1': [txt(x) for x in re.findall(r'<h1\b[^>]*>([\s\S]*?)</h1>', html)],
        'h2': [txt(x) for x in re.findall(r'<h2\b[^>]*>([\s\S]*?)</h2>', html)],
        'black': 'cetrus-black-2026' in body.split(),
        'tpl': (re.search(r'data-elementor-type="product" data-elementor-id="(\d+)"', html) or [None, ''])[1],
        'docs': re.findall(r'data-elementor-type="([\w-]+)" data-elementor-id="(\d+)"', html)[:12],
        'popups': popups(html),
        'form_inline': bool(re.search(r'hbspt\.forms\.create|hs-form|id="form"', html)),
        'faixa_vip': 'lvbn' in html, 'barra_black': 'cbn-barra' in html,
        'kb': round(len(html.encode('utf-8')) / 1024),
        'php_erro': bool(re.search(r'(Fatal error|Warning:|Parse error|Notice:)</b>', html)),
    }


def foto(rotulo):
    os.makedirs(FOTOS, exist_ok=True)
    caminhos = FIXAS + urls_cursos()
    linhas = []
    for i, c in enumerate(caminhos, 1):
        status, final, html = baixa(c)
        if status in (403, 429) or 'Checking your browser' in html[:3000]:
            print(f'PAROU em {c}: {status} (anti-robo). Espere alguns minutos e rode de novo.')
            break
        linhas.append(analisa(c, status, final, html))
        if i % 25 == 0:
            print(f'{i}/{len(caminhos)}')
        time.sleep(1.5)
    with open(os.path.join(FOTOS, rotulo + '.json'), 'w') as f:
        json.dump(linhas, f, ensure_ascii=False, indent=1)
    print(f'foto "{rotulo}": {len(linhas)} de {len(caminhos)} paginas')


def comparar(a, b):
    A = {x['caminho']: x for x in json.load(open(os.path.join(FOTOS, a + '.json')))}
    B = {x['caminho']: x for x in json.load(open(os.path.join(FOTOS, b + '.json')))}
    falhas, alertas, ok = [], [], 0
    for c in sorted(set(A) | set(B)):
        if c not in B:
            alertas.append(f'{c}: sumiu da foto "{b}"'); continue
        if c not in A:
            alertas.append(f'{c}: curso novo (sem foto "{a}")'); x, y = None, B[c]
        else:
            x, y = A[c], B[c]
        f = []
        # teste A/B do time (curso redireciona para -a/ ou -b/ ao acaso): cada foto pode cair numa variante
        ab = bool(x) and x['final'] != y['final'] and re.sub(r'-[ab]/?$', '', x['final']) == re.sub(r'-[ab]/?$', '', y['final'])
        if ab:
            alertas.append(f'{c}: teste A/B, variante {x["final"][-3:]} -> {y["final"][-3:]} (H1 e H2 nao comparados)')
        if x:
            for k in ('status', 'final', 'title', 'description', 'canonical', 'robots', 'og_title', 'jsonld', 'h1'):
                if ab and k in ('final', 'jsonld', 'h1'):
                    continue
                if x[k] != y[k]:
                    f.append(f'{k}: {str(x[k])[:60]!r} -> {str(y[k])[:60]!r}')
            sumiram = [h for h in x['h2'] if h not in y['h2'] and not ab
                       and not (y['black'] and h in H2_SAEM_NA_BLACK.get(c, []))]
            if sumiram and c != '/?s=ultrassonografia':
                f.append(f'H2 sumiu: {sumiram[:3]}')
            novos = [h for h in y['h2'] if h not in x['h2']]
            if novos:
                alertas.append(f'{c}: +{len(novos)} H2 {novos[:2]}')
            if x['kb'] and y['kb'] > x['kb'] * 1.3:
                alertas.append(f'{c}: HTML {x["kb"]} -> {y["kb"]} KB')
        # curso que redireciona para fora de /cursos/ (pagina avulsa) fica como a producao
        fora = c.startswith('/cursos/') and not y['final'].startswith('/cursos/')
        deve_black = c not in SEM_BLACK and y['tpl'] not in TPL_SEM_BLACK and not fora
        if y['status'] == 200 and deve_black and not y['black']:
            f.append('devia estar Black e nao esta')
        if y['black'] and not deve_black:
            f.append('Black numa tela que devia ficar igual a producao')
        ruins = [p for p in y['popups'] if p in POPUP_ORIGINAL]
        if y['black'] and ruins:
            f.append(f'popup original {ruins}')
        if c.startswith('/cursos/') and not fora and y['status'] == 200 and not y['popups'] and not y['form_inline']:
            f.append('curso sem popup nem formulario')
        if y['php_erro']:
            f.append('erro de PHP no HTML')
        if f:
            falhas.append(f'{c}: ' + ' | '.join(f))
        else:
            ok += 1
    print(f'OK {ok} | FALHA {len(falhas)} | ALERTA {len(alertas)}')
    for l in falhas:
        print('FALHA ', l)
    for l in alertas[:60]:
        print('ALERTA', l)
    return 1 if falhas else 0


if __name__ == '__main__':
    if len(sys.argv) >= 3 and sys.argv[1] == 'foto':
        foto(sys.argv[2])
    elif len(sys.argv) >= 4 and sys.argv[1] == 'comparar':
        sys.exit(comparar(sys.argv[2], sys.argv[3]))
    else:
        print(__doc__)
