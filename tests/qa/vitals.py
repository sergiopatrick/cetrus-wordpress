#!/usr/bin/env python3
"""
Mede CLS e LCP reais em Chrome headless, com o observer instalado ANTES do documento.
  python3 vitals.py <rotulo> [repeticoes]
Grava snap/vitals-<rotulo>.json.
"""
import sys, os, json, time, statistics
import cdp

BASE = os.path.dirname(os.path.abspath(__file__))
PAGINAS = {
 "home":           "https://cetrus.com.br/",
 "vitrine":        "https://cetrus.com.br/vitrine/",
 "especialidades": "https://cetrus.com.br/especialidades/",
 "curso-eco":      "https://cetrus.com.br/cursos/pos-graduacao-lato-sensu-ecocardiografia-fetal/",
 "curriculo":      "https://cetrus.com.br/curriculo/dr-anselmo-carmo/",
 "conteudos":      "https://cetrus.com.br/conteudos-gratuitos/",
}
VIEWPORTS = {"desk": (1440, 900, False), "mob": (390, 844, True)}

OBSERVER = r"""
window.__qa = {cls:0, shifts:[], lcp:null};
try{
  new PerformanceObserver((l)=>{ for(const e of l.getEntries()){ if(e.hadRecentInput) continue;
    window.__qa.cls += e.value;
    window.__qa.shifts.push({t:Math.round(e.startTime), v:+e.value.toFixed(4),
      src:(e.sources||[]).map(s=>{const n=s.node; if(!n||!n.tagName) return 'null';
        return n.tagName+(n.id?'#'+n.id:'')+(n.className&&typeof n.className==='string'?'.'+n.className.trim().split(/\s+/).slice(0,3).join('.'):'')
          +' [h '+Math.round(s.previousRect.height)+'->'+Math.round(s.currentRect.height)+']';})});
  }}).observe({type:'layout-shift', buffered:true});
}catch(e){}
try{
  new PerformanceObserver((l)=>{ const es=l.getEntries(); const e=es[es.length-1];
    window.__qa.lcp={t:Math.round(e.startTime), el:e.element?e.element.tagName:null, url:e.url||null};
  }).observe({type:'largest-contentful-paint', buffered:true});
}catch(e){}
"""

COLETA = r"""(()=>{ const q=window.__qa||{cls:0,shifts:[]};
  const w=document.querySelector('.elementor-element-df02ba9');
  const s=w&&w.querySelector('.swiper');
  return {cls:+q.cls.toFixed(4), lcp:q.lcp,
    shifts:(q.shifts||[]).sort((a,b)=>b.v-a.v).slice(0,6),
    carrossel: s? Math.round(s.getBoundingClientRect().height): null,
    vw: innerWidth, vh: innerHeight, altura_doc: document.documentElement.scrollHeight};})()"""

def medir(url, largura, altura, mobile, porta):
    c = cdp.Chrome(largura, altura, mobile, porta)
    try:
        c.cmd("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVER})
        c.ir(url, 6)
        # rola a pagina como um usuario faria, para acordar lazy load e o carrossel
        for _ in range(6):
            c.js("window.scrollBy(0, innerHeight*0.9)")
            time.sleep(0.7)
        c.js("window.scrollTo(0,0)")
        time.sleep(3)
        return c.js(COLETA)
    finally:
        c.fechar()

def main():
    rotulo = sys.argv[1]
    reps = int(sys.argv[2]) if len(sys.argv) > 2 else 2
    saida = {}
    porta = 9400
    for nome, url in PAGINAS.items():
        for vp, (w,h,mob) in VIEWPORTS.items():
            amostras = []
            for r in range(reps):
                porta += 1
                try:
                    amostras.append(medir(url, w, h, mob, porta))
                except Exception as e:
                    amostras.append({"erro": str(e)[:160]})
            cls = [a["cls"] for a in amostras if isinstance(a, dict) and "cls" in a]
            chave = f"{nome}-{vp}"
            saida[chave] = {"url": url, "amostras": amostras,
                            "cls_mediana": round(statistics.median(cls),4) if cls else None,
                            "cls_max": round(max(cls),4) if cls else None}
            print(f"  {chave:<24} CLS mediana={saida[chave]['cls_mediana']}  max={saida[chave]['cls_max']}", flush=True)
    os.makedirs(os.path.join(BASE,"snap"), exist_ok=True)
    p = os.path.join(BASE,"snap",f"vitals-{rotulo}.json")
    json.dump({"rotulo":rotulo,"ts":time.time(),"paginas":saida}, open(p,"w"), ensure_ascii=False, indent=1)
    print(f"\n-> {p}")

if __name__ == "__main__":
    main()
