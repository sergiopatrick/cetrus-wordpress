#!/usr/bin/env python3
"""
Prints do autocomplete da home para as buscas do estudo do Paulo (out/2026).

    python3 busca_prints.py antes                       # produção como está
    python3 busca_prints.py depois --injetar <pasta>    # JS/CSS/índice novos por cima da produção

Com --injetar, a pasta precisa ter js.txt, css.txt, indice.json e fallback.json
(gerados por `busca_gerar.php` no servidor). O script marca os campos antes do JS
de produção rodar, para ele não se ligar, e serve o índice e o fallback do
Relevanssi por um fetch falso. Assim dá para ver o "depois" sem publicar nada.

Saída em shots/busca-<rotulo>/NN-termo.png e um resumo.json com o que cada painel
mostrou.
"""
import json, os, random, re, sys, time, base64, unicodedata
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from cdp import Chrome

BASE = os.path.dirname(os.path.abspath(__file__))

TERMOS = [
    # erros de digitação
    "ultrasom", "ultrasonografia", "ultrasonogafia", "dopller", "ginecolgia",
    "medicna fetal", "alexandre ferras",
    # sinônimos e pontuação
    "birads", "residencia", "titulo de especialista", "ultrassom mamas",
    # intenção de modalidade, cidade, ano e preço
    "presencial", "sao paulo", "recife", "curso intensivo", "2027", "preco",
    "ultrassom em sao paulo",
    # ranking
    "pos graduacao medicina", "medicina fetal", "ultrassom", "fellowship medicina fetal",
    # os mais buscados no GA4
    "ortopedia", "ginecologia", "cardiologia", "gineco",
]


def slug(s):
    s = unicodedata.normalize("NFD", s)
    s = "".join(c for c in s if unicodedata.category(c) != "Mn").lower()
    return re.sub(r"[^a-z0-9]+", "-", s).strip("-")


def preparo_injecao(pasta):
    ler = lambda n: open(os.path.join(pasta, n), encoding="utf-8").read()
    indice, fallback = ler("indice.json"), ler("fallback.json")
    fonte = """
(function(){
  var IDX = %s, FB = %s;
  var marcar = function(){ document.querySelectorAll('.search-input-cetrus').forEach(function(c){ c.setAttribute('data-cetrus-ac','velho'); }); };
  window.__obsAC = new MutationObserver(marcar); window.__obsAC.observe(document, {childList:true, subtree:true});
  var original = window.fetch;
  window.fetch = function(url, op){
    url = String(url);
    var resp = function(o){ return Promise.resolve(new Response(JSON.stringify(o), {status:200, headers:{'Content-Type':'application/json'}})); };
    if (url.indexOf('busca-indice') !== -1) { return resp(IDX); }
    if (url.indexOf('/cetrus/v1/busca?') !== -1) {
      var q = decodeURIComponent((url.match(/[?&]q=([^&]*)/)||[])[1]||'').replace(/\\+/g,' ');
      return resp(FB[q] || {q:q, p:[]});
    }
    return original.apply(this, arguments);
  };
})();
""" % (indice, fallback)
    return fonte, ler("js.txt"), ler("css.txt")


def main():
    if len(sys.argv) < 2:
        print(__doc__); return 2
    rotulo = sys.argv[1]
    pasta = sys.argv[sys.argv.index("--injetar") + 1] if "--injetar" in sys.argv else None
    destino = os.path.join(BASE, "shots", f"busca-{rotulo}")
    os.makedirs(destino, exist_ok=True)

    b = Chrome(1440, 1000, porta=9340)
    resumo = []
    try:
        b.cmd("Page.setLifecycleEventsEnabled", {"enabled": True})
        js_novo = css_novo = None
        if pasta:
            fonte, js_novo, css_novo = preparo_injecao(pasta)
            b.cmd("Page.addScriptToEvaluateOnNewDocument", {"source": fonte})
        b.ir(f"https://cetrus.com.br/?v={random.randint(1, 10**9)}", espera=12)
        if pasta:
            b.js("window.__obsAC.disconnect();document.querySelectorAll('.search-input-cetrus').forEach(function(c){c.removeAttribute('data-cetrus-ac');});"
                 "document.querySelectorAll('.cetrus-ac-painel,.cetrus-ac-sr').forEach(function(e){e.remove();});")
            b.js("(function(){var s=document.createElement('style');s.textContent=%s;document.head.appendChild(s);})()" % json.dumps(css_novo))
            b.js(js_novo)

        # Some com banner de cookie e barra fixa que tampariam o painel no print.
        b.js("document.querySelectorAll('#hs-eu-cookie-confirmation,.cky-consent-container').forEach(function(e){e.remove();})")

        achar = """(function(){
  var cs = Array.prototype.filter.call(document.querySelectorAll('.search-input-cetrus'), function(c){
    var r = c.getBoundingClientRect(); return r.width > 0 && r.height > 0 && getComputedStyle(c).visibility !== 'hidden';
  });
  window.__campo = cs[0]; return cs.length;
})()"""
        if not b.js(achar):
            print("FALHA campo de busca não encontrado"); return 1
        b.js("window.__campo.scrollIntoView({block:'center'}); window.scrollBy(0, 160);")
        time.sleep(1)

        for n, termo in enumerate(TERMOS, 1):
            b.js("window.__campo.focus(); window.__campo.value=''; window.__campo.dispatchEvent(new Event('input',{bubbles:true}));")
            time.sleep(0.3)
            b.cmd("Input.insertText", {"text": termo})
            time.sleep(2.2)
            if os.environ.get("DEBUG"):
                print(b.js("(function(){var c=window.__campo;var p=c.closest('.position-relative');return [c.value, document.activeElement===c, p?p.querySelectorAll('.cetrus-ac-painel').length:-1, p&&p.querySelector('.cetrus-ac-painel')?p.querySelector('.cetrus-ac-painel').outerHTML.slice(0,200):''];})()"))
            info = b.js("""(function(){
  var c = window.__campo, f = c.closest('form') || c, r = f.getBoundingClientRect(), sy = window.scrollY;
  var p = c.closest('.position-relative') ? c.closest('.position-relative').querySelector('.cetrus-ac-painel') : null;
  var pr = p && p.getAttribute('data-aberto') ? p.getBoundingClientRect() : null;
  var titulos = p ? Array.prototype.map.call(p.querySelectorAll('.cetrus-ac-item .cetrus-ac-titulo'), function(e){return e.textContent.trim();}) : [];
  var grupos = p ? Array.prototype.map.call(p.querySelectorAll('.cetrus-ac-grupo'), function(e){return e.textContent.trim();}) : [];
  return {x:r.left, y:r.top + sy, w:r.width, h:r.height, pb: (pr ? Math.max(pr.bottom, r.bottom) : r.bottom) + sy,
          vazio: !!(p && p.querySelector('.cetrus-ac-vazio')), itens: titulos, grupos: grupos,
          aviso: p && p.querySelector('.cetrus-ac-vazio') ? p.querySelector('.cetrus-ac-vazio').textContent.trim() : ''};
})()""")
            x = max(0, info["x"] - 24); y = max(0, info["y"] - 24)
            clip = {"x": x, "y": y, "width": info["w"] + 48, "height": max(120, info["pb"] - y + 24), "scale": 1}
            shot = b.cmd("Page.captureScreenshot", {"format": "png", "clip": clip})
            arq = os.path.join(destino, f"{n:02d}-{slug(termo)}.png")
            with open(arq, "wb") as f:
                f.write(base64.b64decode(shot["data"]))
            resumo.append({"termo": termo, "arquivo": os.path.basename(arq), "vazio": info["vazio"],
                           "sugestoes": len(info["itens"]), "grupos": info["grupos"], "primeiros": info["itens"][:4]})
            marca = "VAZIO" if info["vazio"] else f"{len(info['itens']):2d}"
            print(f"{n:02d} {termo:<28} {marca}  {' / '.join(t[:38] for t in info['itens'][:2])}")
    finally:
        b.fechar()

    with open(os.path.join(destino, "resumo.json"), "w", encoding="utf-8") as f:
        json.dump(resumo, f, ensure_ascii=False, indent=1)
    vazios = sum(1 for r in resumo if r["vazio"])
    print(f"\n{vazios} de {len(resumo)} buscas sem sugestão. Prints em {destino}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
