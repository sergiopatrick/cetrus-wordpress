#!/usr/bin/env python3
"""
Regressao visual: captura screenshots com Chrome headless e compara com um rotulo anterior.
  python3 shots.py <rotulo>                 # captura
  python3 shots.py <antes> <depois> --diff  # compara
"""
import sys, os, subprocess, json, math
from PIL import Image, ImageChops

BASE = os.path.dirname(os.path.abspath(__file__))
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
PAGINAS = {
 "home":            "https://cetrus.com.br/",
 "vitrine":         "https://cetrus.com.br/vitrine/",
 "especialidades":  "https://cetrus.com.br/especialidades/",
 "espec-usg":       "https://cetrus.com.br/especialidades/ultrassonografia-geral/",
 "curso-eco":       "https://cetrus.com.br/cursos/pos-graduacao-lato-sensu-ecocardiografia-fetal/",
 "curso-usgo":      "https://cetrus.com.br/cursos/ultrassonografia-em-ginecologia-e-obstetricia/",
 "curriculo":       "https://cetrus.com.br/curriculo/dr-anselmo-carmo/",
 "curriculo-arq":   "https://cetrus.com.br/curriculo/",
 "conteudos":       "https://cetrus.com.br/conteudos-gratuitos/",
 "institucional":   "https://cetrus.com.br/institucional/",
 "unidades":        "https://cetrus.com.br/unidades/",
 "formacao-usg":    "https://cetrus.com.br/formacao-inicial-usg/",
}
VIEWPORTS = {"desk": (1440, 3400), "mob": (390, 2600)}

def capturar(rotulo):
    destino = os.path.join(BASE, "shots", rotulo)
    os.makedirs(destino, exist_ok=True)
    for nome, url in PAGINAS.items():
        for vp, (w,h) in VIEWPORTS.items():
            arq = os.path.join(destino, f"{nome}-{vp}.png")
            cmd = [CHROME, "--headless=new", "--disable-gpu", "--hide-scrollbars",
                   "--force-device-scale-factor=1", f"--window-size={w},{h}",
                   "--virtual-time-budget=12000", f"--screenshot={arq}", url]
            subprocess.run(cmd, capture_output=True, timeout=120)
            ok = os.path.exists(arq) and os.path.getsize(arq) > 5000
            print(f"  {'ok  ' if ok else 'FALHA'} {nome}-{vp}  {os.path.getsize(arq) if os.path.exists(arq) else 0} bytes")
    print(f"screenshots em {destino}")

def comparar(a, b):
    da = os.path.join(BASE,"shots",a); db = os.path.join(BASE,"shots",b)
    falhas = 0
    print(f"{'pagina':<22} {'RMS':>7}  {'%pixels dif':>12}  veredito")
    print("-"*62)
    for nome in PAGINAS:
        for vp in VIEWPORTS:
            fa = os.path.join(da,f"{nome}-{vp}.png"); fb = os.path.join(db,f"{nome}-{vp}.png")
            if not (os.path.exists(fa) and os.path.exists(fb)):
                print(f"{nome+'-'+vp:<22} {'-':>7}  {'-':>12}  SEM PAR"); falhas += 1; continue
            ia = Image.open(fa).convert("RGB"); ib = Image.open(fb).convert("RGB")
            if ia.size != ib.size:
                ib = ib.resize(ia.size)
            dif = ImageChops.difference(ia, ib)
            hist = dif.convert("L").histogram()
            n = sum(hist)
            r = math.sqrt(sum(i*i*c for i,c in enumerate(hist)) / n)
            pct = 100.0 * sum(hist[12:]) / n     # pixels com diferenca perceptivel
            if pct > 3.0 or r > 12:  ver, falhas = "REVISAR", falhas+1
            elif pct > 0.8:          ver = "olhar"
            else:                    ver = "ok"
            print(f"{nome+'-'+vp:<22} {r:>7.2f}  {pct:>11.2f}%  {ver}")
    print(f"\npaginas a revisar: {falhas}")
    return falhas

if __name__ == "__main__":
    if "--diff" in sys.argv:
        sys.exit(1 if comparar(sys.argv[1], sys.argv[2]) else 0)
    capturar(sys.argv[1])
