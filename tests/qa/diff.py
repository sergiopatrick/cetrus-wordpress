#!/usr/bin/env python3
"""
Compara dois snapshots e aponta regressoes.
  python3 diff.py <antes> <depois>
Saida: FALHA (bloqueia), ALERTA (olhar), INFO (mudanca esperada). Codigo 1 se houver FALHA.
"""
import sys, os, json, math

BASE = os.path.dirname(os.path.abspath(__file__))
import re as _re

def chave(u):
    """Normaliza URL de imagem: tira parametros que a propria otimizacao introduz."""
    u = _re.sub(r"[?&](quality|strip|qa)=[^&]*", "", u)
    u = u.replace("&#038;", "&").replace("&amp;", "&")
    return u.replace("?&", "?").rstrip("?&")

F, A, I = [], [], []
def falha(u,m): F.append((u,m))
def alerta(u,m): A.append((u,m))
def info(u,m): I.append((u,m))

# campos que NAO podem mudar (SEO e estrutura)
IDENTICOS = ["title","description","canonical","robots","og_title","og_url","og_image",
             "h1","jsonld","n_jsonld","final","redirects","h_xrobots"]
# Paginas cujo HTML varia sozinho entre duas leituras. Comprovado em 30/08/2026 com
# A/B do mu-plugin ligado e desligado: os numeros mudam igual nos dois casos.
# Aqui a queda de contagem vira ALERTA; title, canonical, robots e jsonld continuam rigidos.
INSTAVEIS = ("/local/", "/modalidades/", "/curriculo/", "/vitrine/", "?taxonomy=")

def instavel(u):
    return any(p in u for p in INSTAVEIS)

# campos onde queda e regressao
NAO_CAIR = {"texto_palavras":0.02, "links_internos":0.03, "n_h2":0.0, "n_h3":0.0,
            "n_img":0.0, "n_option":0.0}

def cmp_paginas(a, b):
    pa, pb = a["paginas"], b["paginas"]
    so_antes = set(pa) - set(pb); so_depois = set(pb) - set(pa)
    for u in so_antes: falha(u, "URL sumiu do corpus")
    for u in so_depois: info(u, "URL nova no corpus")
    for u in sorted(set(pa) & set(pb)):
        x, y = pa[u], pb[u]
        if y.get("http") != 200:
            # so e regressao se ANTES respondia 200. O UA de bot recebe 403 do
            # desafio de crawler da Automattic nos dois lados, e isso nao e mudanca.
            if x.get("http") == 200:
                falha(u, f"http {y.get('http')} (antes {x.get('http')})")
            elif x.get("http") != y.get("http"):
                alerta(u, f"http mudou {x.get('http')} -> {y.get('http')}")
            continue
        if x.get("http") != y.get("http"):
            falha(u, f"http mudou {x.get('http')} -> {y.get('http')}")
        if y.get("erro_php"):
            falha(u, "erro/warning de PHP no HTML")
        if x.get("tem_footer") and not y.get("tem_footer"): falha(u, "footer sumiu (render incompleto)")
        if x.get("tem_header") and not y.get("tem_header"): falha(u, "header sumiu (render incompleto)")
        for c in IDENTICOS:
            va, vb = x.get(c), y.get(c)
            if va == vb:
                continue
            if c == "og_image" and isinstance(va,str) and isinstance(vb,str) and chave(va) == chave(vb):
                info(u, "og:image ganhou parametro de otimizacao (mesmo arquivo)")
                continue
            falha(u, f"{c}: {str(va)[:70]!r} -> {str(vb)[:70]!r}")
        for c, tol in NAO_CAIR.items():
            va, vb = x.get(c), y.get(c)
            if va is None or vb is None: continue
            if vb < va * (1 - tol) or (tol == 0 and vb < va):
                (alerta if instavel(u) else falha)(u, f"{c} caiu {va} -> {vb}")
            elif vb != va:
                info(u, f"{c} {va} -> {vb}")
        if y.get("n_img_sem_alt",0) > x.get("n_img_sem_alt",0):
            alerta(u, f"imagens sem alt {x.get('n_img_sem_alt')} -> {y.get('n_img_sem_alt')}")
        if y.get("n_img_sem_dim",0) > x.get("n_img_sem_dim",0):
            alerta(u, f"imagens sem width/height {x.get('n_img_sem_dim')} -> {y.get('n_img_sem_dim')}")
        if x.get("sel_turma") != y.get("sel_turma"):
            falha(u, f"seletor de turma {x.get('sel_turma')} -> {y.get('sel_turma')}")
        for c in ("n_css","n_js","n_photon","n_gtm","n_swiper_wrapper","bytes"):
            if x.get(c) != y.get(c):
                info(u, f"{c} {x.get(c)} -> {y.get(c)}")
        if x.get("texto_sha1") != y.get("texto_sha1"):
            info(u, "texto mudou (conteudo dinamico ou edicao)")

def rms(h1, h2):
    a = bytes.fromhex(h1); b = bytes.fromhex(h2)
    if len(a) != len(b): return 999.0
    return math.sqrt(sum((x-y)**2 for x,y in zip(a,b)) / len(a))

def cmp_assets(a, b):
    ia = {chave(u): d for u, d in a["imagens"].items()}
    ib = {chave(u): d for u, d in b["imagens"].items()}
    total_a = sum(d.get("bytes",0) for d in ia.values())
    total_b = sum(d.get("bytes",0) for d in ib.values())
    for u in sorted(set(ia) & set(ib)):
        x, y = ia[u], ib[u]
        if x.get("http") == 200 and y.get("http") != 200:
            falha(u, f"imagem quebrou: http {y.get('http')}"); continue
        if y.get("erro"):
            if not x.get("erro"):
                falha(u, f"imagem nao decodifica: {y['erro']}")
            continue
        if "w" in x and "w" in y and (x["w"], x["h"]) != (y["w"], y["h"]):
            falha(u, f"dimensoes mudaram {x['w']}x{x['h']} -> {y['w']}x{y['h']}")
        if x.get("assinatura") and y.get("assinatura"):
            r = rms(x["assinatura"], y["assinatura"])
            if r > 8: falha(u, f"imagem visualmente diferente (RMS {r:.1f})")
            elif r > 4: alerta(u, f"imagem levemente diferente (RMS {r:.1f})")
        if x.get("bytes") and y.get("bytes") and y["bytes"] != x["bytes"]:
            info(u, f"bytes {x['bytes']} -> {y['bytes']}")
    for u in set(ia) - set(ib): alerta(u, "imagem sumiu do corpus")
    for u in set(ib) - set(ia): info(u, "imagem nova no corpus")
    return total_a, total_b

def main():
    ra, rb = sys.argv[1], sys.argv[2]
    a = json.load(open(os.path.join(BASE,"snap",f"{ra}.json")))
    b = json.load(open(os.path.join(BASE,"snap",f"{rb}.json")))
    cmp_paginas(a,b)
    ta = tb = None
    pa_ = os.path.join(BASE,"snap",f"assets-{ra}.json")
    pb_ = os.path.join(BASE,"snap",f"assets-{rb}.json")
    if os.path.exists(pa_) and os.path.exists(pb_):
        ta, tb = cmp_assets(json.load(open(pa_)), json.load(open(pb_)))

    print(f"\n{'='*78}\nCOMPARACAO  {ra}  ->  {rb}\n{'='*78}")
    print(f"\n### FALHAS ({len(F)})")
    for u,m in F[:80]: print(f"  X  {m}\n     {u}")
    if len(F) > 80: print(f"  ... e mais {len(F)-80}")
    print(f"\n### ALERTAS ({len(A)})")
    for u,m in A[:40]: print(f"  !  {m}\n     {u}")
    if len(A) > 40: print(f"  ... e mais {len(A)-40}")
    print(f"\n### INFO ({len(I)}) - amostra")
    for u,m in I[:25]: print(f"  .  {m}  [{u[:70]}]")
    if len(I) > 25: print(f"  ... e mais {len(I)-25}")
    if ta is not None:
        print(f"\n### PESO DE IMAGENS\n  antes:  {ta/1048576:.2f} MB\n  depois: {tb/1048576:.2f} MB"
              f"\n  delta:  {(tb-ta)/1048576:+.2f} MB ({100*(tb-ta)/ta:+.1f}%)")
    print(f"\nRESULTADO: {'REPROVADO' if F else 'APROVADO'}  ({len(F)} falhas, {len(A)} alertas)")
    sys.exit(1 if F else 0)

if __name__ == "__main__":
    main()
