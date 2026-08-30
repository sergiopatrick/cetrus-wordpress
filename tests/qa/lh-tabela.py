#!/usr/bin/env python3
"""Tabela comparativa de Lighthouse mobile."""
import json, os, sys, glob
BASE=os.path.dirname(os.path.abspath(__file__))
rotulos=sys.argv[1:]
paginas=["home","curso","vitrine","especialidades","curriculo","conteudos"]
def le(n,r):
    p=os.path.join(BASE,"lh",f"lh-{n}-{r}.json")
    if not os.path.exists(p): return None
    d=json.load(open(p)); a=d["audits"]
    return {"score":round(d["categories"]["performance"]["score"]*100),
            "lcp":round(a["largest-contentful-paint"]["numericValue"]/1000,1),
            "tbt":round(a["total-blocking-time"]["numericValue"]),
            "cls":round(a["cumulative-layout-shift"]["numericValue"],3),
            "fcp":round(a["first-contentful-paint"]["numericValue"]/1000,1),
            "si":round(a["speed-index"]["numericValue"]/1000,1),
            "mb":round(a["total-byte-weight"]["numericValue"]/1048576,1),
            "req":len(a["network-requests"]["details"]["items"])}
cab="pagina".ljust(16)
for r in rotulos: cab += f"{r[:16]:>17}"
print(cab); print("-"*len(cab))
for n in paginas:
    linha=n.ljust(16)
    for r in rotulos:
        d=le(n,r)
        linha += f"{(str(d['score'])+' pts'):>17}" if d else f"{'-':>17}"
    print(linha)
print()
for n in paginas:
    for r in rotulos:
        d=le(n,r)
        if d: print(f"  {n:<15} {r:<14} score={d['score']:<4} LCP={d['lcp']:<6} TBT={d['tbt']:<6} CLS={d['cls']:<7} FCP={d['fcp']:<5} SI={d['si']:<5} {d['mb']}MB {d['req']} req")
