#!/usr/bin/env python3
"""
Captura um retrato completo do cetrus.com.br para comparacao antes/depois.
  python3 snapshot.py <rotulo> [--ua googlebot]
Grava snap/<rotulo>.json. SOMENTE GET, nao altera nada.
"""
import re, sys, json, os, time, hashlib, random, subprocess
import concurrent.futures as cf
from corpus import corpus

BASE = os.path.dirname(os.path.abspath(__file__))
UAS = {
 "chrome": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36",
 "googlebot": "Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)",
 "gptbot": "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot",
}

def buscar(url, ua, bust=True):
    sep = "&" if "?" in url else "?"
    alvo = f"{url}{sep}qa={random.randint(1,10**9)}" if bust else url
    p = subprocess.run(
        ["curl","-s","-L","--compressed","-A",ua,"--max-time","90","-D","-",
         "-w","\n@@FIM@@%{http_code}\t%{time_starttransfer}\t%{size_download}\t%{url_effective}\t%{num_redirects}",alvo],
        capture_output=True, text=True, errors="replace")
    saida = p.stdout
    i = saida.rfind("\n@@FIM@@")
    meta = saida[i+8:] if i > 0 else ""
    corpo = saida[:i] if i > 0 else saida
    # separa cabecalhos (podem vir varios blocos por causa de redirect) do corpo
    partes = re.split(r"\r?\n\r?\n", corpo, maxsplit=0)
    cabs, html = [], corpo
    j = corpo.find("\r\n\r\n")
    if j < 0: j = corpo.find("\n\n")
    while j >= 0 and corpo[:j].lower().startswith("http/"):
        cabs.append(corpo[:j]); corpo = corpo[j+ (4 if "\r\n\r\n" in corpo[:j+4] else 2):]
        j = corpo.find("\r\n\r\n")
        if j < 0: j = corpo.find("\n\n")
        if j >= 0 and not corpo[:j].lower().startswith("http/"): break
    html = corpo
    try:
        http, ttfb, tam, final, redirs = meta.split("\t")
        http, ttfb, tam, redirs = int(http), float(ttfb), int(tam), int(redirs)
    except Exception:
        http, ttfb, tam, final, redirs = 0, 0.0, 0, alvo, 0
    return html, "\n".join(cabs), http, ttfb, tam, final, redirs

def cab(cabs, nome):
    m = re.search(rf"(?im)^{re.escape(nome)}:\s*(.+)$", cabs)
    return m.group(1).strip() if m else None

TAG = re.compile(r"<[^>]+>")
def texto_visivel(html):
    h = re.sub(r"(?is)<(script|style|noscript|template)\b.*?</\1>", " ", html)
    h = re.sub(r"(?is)<!--.*?-->", " ", h)
    h = TAG.sub(" ", h)
    h = (h.replace("&nbsp;"," ").replace("&amp;","&").replace("&lt;","<")
          .replace("&gt;",">").replace("&#8211;","-").replace("&quot;",'"')
          .replace("&#039;","'").replace("&#8217;","'"))
    return re.sub(r"\s+", " ", h).strip()

def meta_conteudo(html, chave, attr="name"):
    m = re.search(rf'(?is)<meta[^>]+{attr}=["\']{re.escape(chave)}["\'][^>]*content=["\'](.*?)["\']', html)
    if m: return m.group(1).strip()
    m = re.search(rf'(?is)<meta[^>]+content=["\'](.*?)["\'][^>]*{attr}=["\']{re.escape(chave)}["\']', html)
    return m.group(1).strip() if m else None

def limpa_qa(v):
    return re.sub(r"[?&]qa=\d+", "", v) if isinstance(v, str) else v

def analisa(url, ua_nome="chrome"):
    ua = UAS[ua_nome]
    html, cabs, http, ttfb, tam, final, redirs = buscar(url, ua)
    d = {"url": url, "http": http, "ttfb": round(ttfb,3), "bytes": tam,
         "final": limpa_qa(final), "redirects": redirs}
    d["h_xrobots"] = cab(cabs, "x-robots-tag")
    d["h_content_type"] = cab(cabs, "content-type")
    d["h_cache_control"] = cab(cabs, "cache-control")

    if not html or http != 200:
        d["erro"] = "sem corpo ou http != 200"
        return d

    m = re.search(r"(?is)<title[^>]*>(.*?)</title>", html)
    d["title"] = re.sub(r"\s+"," ",m.group(1)).strip() if m else None
    d["description"] = meta_conteudo(html, "description")
    d["robots"] = meta_conteudo(html, "robots")
    m = re.search(r'(?is)<link[^>]+rel=["\']canonical["\'][^>]*href=["\'](.*?)["\']', html)
    d["canonical"] = limpa_qa(m.group(1).strip()) if m else None
    d["og_title"] = meta_conteudo(html, "og:title", "property")
    d["og_image"] = meta_conteudo(html, "og:image", "property")
    d["og_url"] = limpa_qa(meta_conteudo(html, "og:url", "property"))

    h1 = [re.sub(r"\s+"," ",TAG.sub("",x)).strip() for x in re.findall(r"(?is)<h1[^>]*>(.*?)</h1>", html)]
    d["h1"] = h1
    d["n_h2"] = len(re.findall(r"(?is)<h2[^>]*>", html))
    d["n_h3"] = len(re.findall(r"(?is)<h3[^>]*>", html))

    tipos = []
    for bloco in re.findall(r'(?is)<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>', html):
        try:
            dado = json.loads(bloco.strip())
        except Exception:
            tipos.append("!json-invalido"); continue
        pilha = [dado]
        while pilha:
            x = pilha.pop()
            if isinstance(x, dict):
                if "@type" in x:
                    t = x["@type"]
                    tipos += t if isinstance(t, list) else [t]
                pilha += [v for v in x.values() if isinstance(v,(dict,list))]
            elif isinstance(x, list):
                pilha += x
    d["jsonld"] = sorted(set(tipos))
    d["n_jsonld"] = len(re.findall(r'(?is)type=["\']application/ld\+json["\']', html))

    txt = texto_visivel(html)
    d["texto_len"] = len(txt)
    d["texto_palavras"] = len(txt.split())
    d["texto_sha1"] = hashlib.sha1(txt.encode("utf-8","replace")).hexdigest()[:16]
    d["texto_ini"] = txt[:220]

    hrefs = re.findall(r'(?is)<a[^>]+href=["\'](.*?)["\']', html)
    d["links_internos"] = sum(1 for h in hrefs if h.startswith("/") or "cetrus.com.br" in h)
    d["links_externos"] = sum(1 for h in hrefs if h.startswith("http") and "cetrus.com.br" not in h)

    imgs = re.findall(r"(?is)<img\b[^>]*>", html)
    d["n_img"] = len(imgs)
    d["n_img_sem_alt"] = sum(1 for t in imgs if not re.search(r'\balt=', t))
    d["n_img_sem_dim"] = sum(1 for t in imgs if not (re.search(r"\bwidth=",t) and re.search(r"\bheight=",t)))
    srcs = re.findall(r'(?is)<img\b[^>]*\bsrc=["\'](.*?)["\']', html)
    bgs  = re.findall(r'url\(["\']?(https?://[^)"\']+\.(?:png|jpe?g|webp|gif|avif)[^)"\']*)', html, re.I)
    d["imagens"] = sorted(set(srcs + bgs))

    d["n_css"] = len(re.findall(r'(?i)rel=["\']stylesheet', html))
    d["n_js"] = len(re.findall(r"(?is)<script[^>]+src=", html))
    d["n_photon"] = len(re.findall(r"i[0-2]\.wp\.com", html))
    d["n_gtm"] = len(re.findall(r"googletagmanager\.com/gtm\.js", html))
    d["n_swiper_wrapper"] = len(re.findall(r"swiper-wrapper", html))
    d["n_option"] = len(re.findall(r"<option", html))
    d["sel_turma"] = "Selecione a turma" in html
    limpo = re.sub(r"(?is)<script.*?</script>", "", html)
    d["erro_php"] = bool(re.search(r"(?i)(fatal error|parse error|there has been a critical error|"
                                   r"warning:\s|notice:\s|deprecated:\s)", limpo))
    d["tem_footer"] = "</footer>" in html.lower()
    d["tem_header"] = "<header" in html.lower()
    return d

def main():
    rotulo = sys.argv[1] if len(sys.argv) > 1 else "sem-rotulo"
    ua = "chrome"
    if "--ua" in sys.argv:
        ua = sys.argv[sys.argv.index("--ua")+1]
    urls = corpus()
    print(f"snapshot '{rotulo}' com UA={ua}: {len(urls)} URLs", flush=True)
    res = {}
    t0 = time.time()
    with cf.ThreadPoolExecutor(max_workers=4) as ex:
        fut = {ex.submit(analisa, u, ua): u for u in urls}
        for i, f in enumerate(cf.as_completed(fut), 1):
            u = fut[f]
            try:
                res[u] = f.result()
            except Exception as e:
                res[u] = {"url": u, "erro": f"excecao: {e}"}
            if i % 10 == 0:
                print(f"  {i}/{len(urls)}", flush=True)
    os.makedirs(os.path.join(BASE,"snap"), exist_ok=True)
    saida = os.path.join(BASE,"snap",f"{rotulo}.json")
    with open(saida,"w") as fh:
        json.dump({"rotulo":rotulo,"ua":ua,"ts":time.time(),"paginas":res}, fh, ensure_ascii=False, indent=1)
    ruins = [u for u,d in res.items() if d.get("http") != 200 or d.get("erro_php")]
    print(f"\nconcluido em {time.time()-t0:.0f}s -> {saida}")
    print(f"paginas com problema imediato: {len(ruins)}")
    for u in ruins[:10]: print("  ", u, res[u].get("http"), res[u].get("erro",""))

if __name__ == "__main__":
    main()
