#!/usr/bin/env python3
"""
Regressao do carrossel "Cursos em destaque no mes" da home (widget df02ba9).

Valida o que o comercial realmente pediu: que a ORDEM RENDERIZADA na home comece
exatamente pelos codigos de destaque, na prioridade combinada, sem repetir curso e
sem card orfao. Le o HTML de producao, nunca o banco - e o HTML que o usuario ve.

    python3 carrossel.py              # valida contra a lista esperada
    python3 carrossel.py --mostrar    # so imprime a ordem atual, sem julgar

Armadilhas ja pagas e tratadas aqui:
  * o edge cache do WordPress.com tem TTL de 300s: toda URL leva query string aleatoria;
  * cetrus.com.br sem "www" e o canonico, mas www responde 301 - seguir redirecionamento;
  * UA de navegador real, porque UA de bot recebe o desafio JS da Automattic (403).
"""
import re, sys, json, random, urllib.request, urllib.error

HOME   = "https://cetrus.com.br/"
WIDGET = "df02ba9"
UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36")

# Curadoria aprovada em 15/09/2026 (Joao Faro). Prioridade 1 = Pos-fixa 2026,
# prioridade 2 = Pos-fixa 2027. PG_MFE1 aparece nas duas listas e conta uma vez so.
P1 = ["PG_MFE1", "PG_HIST", "PG_REGE", "PG_EDA2", "PG_GERP", "PG_HEH2", "PG_ALP2", "PG_RAM1"]
P2 = ["PG_DOR2", "PG_USDE", "PG_USE2", "PG_USME"]          # PG_MFE1 ja entrou na P1
ESPERADO = P1 + P2

# id de produto de cada codigo, resolvido em 15/09/2026 por _lyceum_curso_id exato.
# Fica explicito no teste de proposito: se alguem trocar o produto por tras do codigo,
# o teste acusa em vez de acompanhar a mudanca em silencio.
IDS = {
    "PG_MFE1": 12712, "PG_HIST": 16066, "PG_REGE": 12703, "PG_EDA2": 12596,
    "PG_GERP": 16052, "PG_HEH2": 16070, "PG_ALP2": 16551, "PG_RAM1": 16601,
    "PG_DOR2": 12588, "PG_USDE": 16582, "PG_USE2": 16011, "PG_USME": 12719,
}


def baixa(url):
    sep = "&" if "?" in url else "?"
    req = urllib.request.Request(f"{url}{sep}qa={random.randint(1, 10**9)}",
                                 headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=60) as r:
        return r.read().decode("utf-8", "replace")


def ordem_dos_cards(html):
    """
    [(id do produto, url do card)] na ordem em que o Elementor imprimiu os slides.

    A classe e-loop-item-<id> aparece varias vezes por slide (uma por elemento aninhado),
    entao o link de cada card e o primeiro href de /cursos/ que vem DEPOIS da primeira
    ocorrencia do id e ANTES do proximo id diferente.
    """
    i = html.find(f'data-id="{WIDGET}"')
    if i < 0:
        return None
    trecho = html[i:]
    fim = trecho.find("elementor-swiper-button-next")
    if fim > 0:
        trecho = trecho[:fim]

    marcas = [(m.start(), int(m.group(1))) for m in re.finditer(r"e-loop-item-(\d+)", trecho)]
    links  = [(m.start(), m.group(1))
              for m in re.finditer(r'href="(https://cetrus\.com\.br/cursos/[^"]+)"', trecho)]

    saida, vistos = [], set()
    for n, (pos, pid) in enumerate(marcas):
        if pid in vistos:
            continue
        vistos.add(pid)
        limite = next((p for p, o in marcas[n + 1:] if o != pid), len(trecho))
        url = next((u for p, u in links if pos < p < limite), None)
        saida.append((pid, url))
    return saida


def main():
    mostrar = "--mostrar" in sys.argv
    html = baixa(HOME)
    cards = ordem_dos_cards(html)

    por_id = {v: k for k, v in IDS.items()}

    if cards is None:
        print("FALHA  widget df02ba9 nao encontrado na home")
        return 1

    ids = [pid for pid, _ in cards]

    if mostrar:
        for n, (pid, url) in enumerate(cards, 1):
            print(f"  {n:2d}. {pid:<6d} {por_id.get(pid, '(organico)'):<9} {url or '(sem link)'}")
        return 0

    falhas, alertas = [], []

    # 1. os destaques abrem o carrossel, na ordem exata
    esperados_ids = [IDS[c] for c in ESPERADO]
    if ids[:len(esperados_ids)] != esperados_ids:
        falhas.append("ordem dos destaques divergente")
        for n, (quero, tenho) in enumerate(zip(esperados_ids, ids), 1):
            if quero != tenho:
                falhas.append(f"    posicao {n}: esperado {quero} "
                              f"({por_id[quero]}), veio {tenho} "
                              f"({por_id.get(tenho, 'organico')})")

    # 2. nenhum curso repetido no carrossel inteiro
    if len(ids) != len(set(ids)):
        falhas.append("ha produto repetido entre os slides")

    # 3. sobrou espaco para a regra viva - o carrossel nao virou so curadoria
    organicos = [p for p in ids if p not in esperados_ids]
    if not organicos:
        alertas.append("nenhum card veio da regra viva de turmas; "
                       "confira 'total' em wp option get cetrus_carrossel")

    # 4. todo card leva a uma pagina de curso que responde 200
    for pid, url in cards:
        if not url:
            falhas.append(f"card {pid} ({por_id.get(pid, 'organico')}) saiu sem link")
            continue
        try:
            baixa(url)
        except urllib.error.HTTPError as e:
            falhas.append(f"card {pid} -> {url} respondeu HTTP {e.code}")

    print(f"carrossel: {len(ids)} cards "
          f"({len(esperados_ids)} destaques + {len(organicos)} da regra viva)")
    for n, pid in enumerate(ids, 1):
        marca = "FIX" if pid in esperados_ids else "   "
        print(f"  {n:2d}. {marca} {pid:<6d} {por_id.get(pid, '')}")

    print()
    for a in alertas:
        print("ALERTA", a)
    for f in falhas:
        print("FALHA ", f)
    if not falhas:
        print("OK  ordem, unicidade e destino de cada card conferem")
    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
