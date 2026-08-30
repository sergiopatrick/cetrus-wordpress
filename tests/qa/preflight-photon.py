#!/usr/bin/env python3
"""
Pre-voo do filtro de qualidade do Photon SEM tocar em producao.
Para cada imagem servida por i0.wp.com no snapshot base, busca a MESMA URL com
quality/strip e compara status, dimensoes, bytes e semelhanca visual.
  python3 preflight-photon.py <snapshot-de-assets> [qualidade]
"""
import sys, os, json, io, math, subprocess
import concurrent.futures as cf
from PIL import Image

BASE = os.path.dirname(os.path.abspath(__file__))

def sobre_branco(im):
    """Compoe sobre branco antes de comparar. Sem isso, WebP com perdas deixa
    RGB residual sob alpha=0 e a metrica acusa diferenca onde o olho nao ve nada."""
    im = im.convert("RGBA")
    fundo = Image.new("RGB", im.size, (255,255,255))
    fundo.paste(im, mask=im.split()[-1])
    return fundo

UA = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36"
Q = int(sys.argv[2]) if len(sys.argv) > 2 else 82

def baixar(url):
    p = subprocess.run(["curl","-s","-L","-A",UA,"-H","Accept: image/avif,image/webp,image/*,*/*",
                        "--max-time","60","-w","\n@@%{http_code}","-o","-",url], capture_output=True)
    s = p.stdout; i = s.rfind(b"\n@@")
    try: http = int(s[i+3:])
    except Exception: http = 0
    return (s[:i] if i >= 0 else s), http

def assinatura(b):
    im = Image.open(io.BytesIO(b))
    return im.size, im.format, sobre_branco(im).convert("L").resize((32,32), Image.BILINEAR).tobytes()

def rms(a, b):
    if len(a) != len(b): return 999.0
    return math.sqrt(sum((x-y)**2 for x,y in zip(a,b))/len(a))

def testar(url, antes):
    sep = "&" if "?" in url else "?"
    novo = f"{url}{sep}quality={Q}&strip=info"
    d = {"url": url}
    b2, http2 = baixar(novo)
    d["http_depois"] = http2
    if http2 != 200 or not b2:
        d["veredito"] = "FALHA: nao respondeu 200"; return d
    try:
        tam2, fmt2, sig2 = assinatura(b2)
    except Exception as e:
        d["veredito"] = f"FALHA: nao decodifica ({e})"; return d
    d["bytes_antes"], d["bytes_depois"] = antes.get("bytes",0), len(b2)
    d["dim_antes"] = [antes.get("w"), antes.get("h")]; d["dim_depois"] = list(tam2)
    d["formato_depois"] = fmt2
    if antes.get("w") and (antes["w"], antes["h"]) != tam2:
        d["veredito"] = f"FALHA: dimensoes {antes['w']}x{antes['h']} -> {tam2[0]}x{tam2[1]}"; return d
    if antes.get("assinatura"):
        r = rms(bytes.fromhex(antes["assinatura"]), sig2)
        d["rms"] = round(r, 2)
        if r > 8: d["veredito"] = f"FALHA: visualmente diferente (RMS {r:.1f})"; return d
        if r > 4: d["veredito"] = f"ALERTA: RMS {r:.1f}"; return d
    d["veredito"] = "ok"
    return d

def main():
    rot = sys.argv[1]
    dados = json.load(open(os.path.join(BASE,"snap",f"assets-{rot}.json")))["imagens"]
    import re as _re
    def _png(u):
        m = _re.search(r"\.([A-Za-z0-9]+)(\?|$)", u.split("?")[0] + "?")
        return bool(m) and m.group(1).lower() == "png"
    alvos = {u: d for u, d in dados.items()
             if "i0.wp.com" in u and d.get("http") == 200 and d.get("assinatura") and _png(u)}
    print(f"pre-voo com quality={Q} em {len(alvos)} imagens do Photon", flush=True)
    res = {}
    with cf.ThreadPoolExecutor(max_workers=8) as ex:
        fut = {ex.submit(testar, u, d): u for u, d in alvos.items()}
        for i, f in enumerate(cf.as_completed(fut), 1):
            u = fut[f]
            try: res[u] = f.result()
            except Exception as e: res[u] = {"url": u, "veredito": f"FALHA: {e}"}
            if i % 40 == 0: print(f"  {i}/{len(alvos)}", flush=True)
    falhas  = [d for d in res.values() if d["veredito"].startswith("FALHA")]
    alertas = [d for d in res.values() if d["veredito"].startswith("ALERTA")]
    a = sum(d.get("bytes_antes",0) for d in res.values())
    b = sum(d.get("bytes_depois",0) for d in res.values())
    json.dump(res, open(os.path.join(BASE,"snap",f"preflight-photon-q{Q}.json"),"w"), ensure_ascii=False)
    print(f"\n{'='*70}\nPRE-VOO PHOTON quality={Q}\n{'='*70}")
    print(f"imagens testadas: {len(res)}")
    print(f"peso: {a/1048576:.2f} MB -> {b/1048576:.2f} MB  ({100*(b-a)/a:+.1f}%)")
    print(f"FALHAS: {len(falhas)}")
    for d in falhas[:15]: print("   ", d["veredito"], d["url"][:95])
    print(f"ALERTAS: {len(alertas)}")
    for d in alertas[:15]: print("   ", d["veredito"], d["url"][:95])
    piores = sorted([d for d in res.values() if "rms" in d], key=lambda x: -x["rms"])[:8]
    print("\nmaiores diferencas visuais (RMS, 0 = identico):")
    for d in piores: print(f"   RMS {d['rms']:>5}  {d['bytes_antes']//1024:>5} KB -> {d['bytes_depois']//1024:>4} KB  {d['url'][:78]}")
    sys.exit(1 if falhas else 0)

if __name__ == "__main__":
    main()
