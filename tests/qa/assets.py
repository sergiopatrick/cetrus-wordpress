#!/usr/bin/env python3
"""
Valida TODAS as imagens referenciadas pelo corpus: status, tipo, bytes, dimensoes reais
e uma assinatura visual 32x32 para comparar antes/depois pixel a pixel.
  python3 assets.py <rotulo>        # usa snap/<rotulo>.json como fonte das URLs
Grava snap/assets-<rotulo>.json. SOMENTE GET.
"""
import sys, os, json, io, random, subprocess, time
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

def baixar(url):
    p = subprocess.run(
        ["curl","-s","-L","-A",UA,"-H","Accept: image/avif,image/webp,image/*,*/*",
         "--max-time","60","-w","\n@@%{http_code}\t%{content_type}\t%{size_download}","-o","-",url],
        capture_output=True)
    saida = p.stdout
    i = saida.rfind(b"\n@@")
    meta = saida[i+3:].decode("utf-8","replace") if i >= 0 else ""
    corpo = saida[:i] if i >= 0 else saida
    try:
        http, ctype, tam = meta.split("\t")
        http, tam = int(http), int(tam)
    except Exception:
        http, ctype, tam = 0, "", 0
    return corpo, http, ctype.strip(), tam

def analisa(url):
    d = {"url": url}
    try:
        corpo, http, ctype, tam = baixar(url)
    except Exception as e:
        d["erro"] = f"download: {e}"; return d
    d["http"], d["content_type"], d["bytes"] = http, ctype, tam
    if http != 200 or not corpo:
        d["erro"] = "sem corpo"; return d
    try:
        im = Image.open(io.BytesIO(corpo))
        d["formato"] = im.format
        d["w"], d["h"] = im.size
        g = sobre_branco(im).convert("L").resize((32,32), Image.BILINEAR)
        d["assinatura"] = g.tobytes().hex()
    except Exception as e:
        d["erro"] = f"decode: {e}"
    return d

def main():
    rotulo = sys.argv[1]
    origem = os.path.join(BASE,"snap",f"{rotulo}.json")
    dados = json.load(open(origem))
    urls = set()
    for d in dados["paginas"].values():
        for u in d.get("imagens", []):
            if u.startswith("http"):
                urls.add(u)
    urls = sorted(urls)
    print(f"validando {len(urls)} imagens unicas do snapshot '{rotulo}'", flush=True)
    res, t0 = {}, time.time()
    with cf.ThreadPoolExecutor(max_workers=8) as ex:
        fut = {ex.submit(analisa,u): u for u in urls}
        for i,f in enumerate(cf.as_completed(fut),1):
            u = fut[f]
            try: res[u] = f.result()
            except Exception as e: res[u] = {"url":u,"erro":str(e)}
            if i % 40 == 0: print(f"  {i}/{len(urls)}", flush=True)
    saida = os.path.join(BASE,"snap",f"assets-{rotulo}.json")
    json.dump({"rotulo":rotulo,"ts":time.time(),"imagens":res}, open(saida,"w"), ensure_ascii=False)
    quebradas = [u for u,d in res.items() if d.get("erro") or d.get("http") != 200]
    total_bytes = sum(d.get("bytes",0) for d in res.values())
    print(f"\nconcluido em {time.time()-t0:.0f}s -> {saida}")
    print(f"total transferido: {total_bytes/1048576:.2f} MB em {len(res)} imagens")
    print(f"quebradas ou nao decodificaveis: {len(quebradas)}")
    for u in quebradas[:12]: print("  ", res[u].get("http"), res[u].get("erro",""), u[:100])

if __name__ == "__main__":
    main()
