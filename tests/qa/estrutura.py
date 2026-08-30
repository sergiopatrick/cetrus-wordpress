#!/usr/bin/env python3
"""
Checagem ESTRUTURAL de CLS, deterministica: mede a altura dos elementos suspeitos
com JavaScript DESLIGADO (estado inicial que o usuario ve) e com JavaScript LIGADO
(estado final). Se as duas alturas batem, aquele elemento nao pode causar shift.
  python3 estrutura.py <rotulo>
"""
import sys, os, json, time
import cdp

BASE = os.path.dirname(os.path.abspath(__file__))
ALVOS = {
  "carrossel_home": ".elementor-element-df02ba9 .swiper",
  "widget_home":    ".elementor-element-df02ba9",
  "hero_home":      ".elementor-element-1a97b3a img",
  "hero_wrap":      ".elementor-element-1a97b3a",
}
URL = "https://cetrus.com.br/"
VIEWPORTS = {"desk": (1440, 900, False), "mob": (390, 844, True)}

MEDIR = """(()=>{const alvos=%s; const r={};
 for(const k in alvos){ const e=document.querySelector(alvos[k]);
   r[k]= e? {h:Math.round(e.getBoundingClientRect().height), w:Math.round(e.getBoundingClientRect().width)} : null; }
 r._doc = document.documentElement.scrollHeight; return r;})()""" % json.dumps(ALVOS)

def medir(largura, altura, mobile, porta, sem_js):
    c = cdp.Chrome(largura, altura, mobile, porta)
    try:
        if sem_js:
            c.cmd("Emulation.setScriptExecutionDisabled", {"value": True})
        c.ir(URL, 10 if sem_js else 16)
        return c.js(MEDIR)
    finally:
        c.fechar()

def main():
    rotulo = sys.argv[1]
    saida, porta = {}, 9500
    for vp, (w,h,mob) in VIEWPORTS.items():
        porta += 1; sem = medir(w,h,mob,porta, True)
        porta += 1; com = medir(w,h,mob,porta, False)
        linha = {}
        for k in ALVOS:
            a = (sem or {}).get(k); b = (com or {}).get(k)
            ha = a["h"] if a else None; hb = b["h"] if b else None
            delta = (hb - ha) if (ha is not None and hb is not None) else None
            linha[k] = {"sem_js": ha, "com_js": hb, "delta": delta}
        saida[vp] = linha
        print(f"\n--- {vp} ({w}x{h}) ---")
        for k, v in linha.items():
            marca = "ok " if (v["delta"] == 0) else ("?? " if v["delta"] is None else "SHIFT")
            print(f"  {marca:5} {k:<16} sem JS={v['sem_js']}  com JS={v['com_js']}  delta={v['delta']}")
    os.makedirs(os.path.join(BASE,"snap"), exist_ok=True)
    p = os.path.join(BASE,"snap",f"estrutura-{rotulo}.json")
    json.dump({"rotulo":rotulo,"ts":time.time(),"medidas":saida}, open(p,"w"), indent=1)
    print(f"\n-> {p}")

if __name__ == "__main__":
    main()
