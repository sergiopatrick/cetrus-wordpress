#!/bin/bash
# Pipeline de QA do cetrus.com.br.
#   ./rodar.sh captura <rotulo>        captura o estado atual (paginas, imagens, visual, vitals, estrutura)
#   ./rodar.sh comparar <antes> <depois>
#   ./rodar.sh funcional               suites de regressao (dados no servidor + front local)
set -u
QA="$(cd "$(dirname "$0")" && pwd)"
cd "$QA"

captura() {
  R="$1"
  echo "### 1/6 paginas (79 URLs, HTML + SEO)"      ; python3 snapshot.py "$R"
  echo "### 2/6 paginas vistas pelo Googlebot"      ; python3 snapshot.py "$R-googlebot" --ua googlebot
  echo "### 3/6 imagens (status, dimensoes, visual)"; python3 assets.py  "$R"
  echo "### 4/6 regressao visual (24 screenshots)"  ; python3 shots.py   "$R"
  echo "### 5/6 CLS e LCP em navegador real"        ; python3 vitals.py  "$R" 3
  echo "### 6/6 checagem estrutural de CLS"         ; python3 estrutura.py "$R"
  echo "captura '$R' concluida"
}

comparar() {
  A="$1"; B="$2"; falhou=0
  echo "############ PAGINAS E SEO ############"
  python3 diff.py "$A" "$B" || falhou=1
  echo; echo "############ VISAO DO GOOGLEBOT ############"
  python3 diff.py "$A-googlebot" "$B-googlebot" || falhou=1
  echo; echo "############ REGRESSAO VISUAL ############"
  python3 shots.py "$A" "$B" --diff || falhou=1
  echo; echo "############ CLS E LCP ############"
  python3 - "$A" "$B" <<'PY'
import json,sys,os
qa=os.path.dirname(os.path.abspath("rodar.sh"))
a=json.load(open(f"snap/vitals-{sys.argv[1]}.json"))["paginas"]
b=json.load(open(f"snap/vitals-{sys.argv[2]}.json"))["paginas"]
print(f"{'pagina':<22} {'CLS antes':>10} {'CLS depois':>11}  veredito")
print("-"*58)
pior=0
for k in sorted(set(a)&set(b)):
    x=a[k]["cls_mediana"]; y=b[k]["cls_mediana"]
    if x is None or y is None: v="?"
    elif y > x + 0.02: v="PIOROU"; pior+=1
    elif y < x - 0.02: v="melhorou"
    else: v="igual"
    print(f"{k:<22} {str(x):>10} {str(y):>11}  {v}")
print(f"\npaginas que pioraram: {pior}")
PY
  echo; echo "############ ESTRUTURA DE CLS ############"
  python3 - "$A" "$B" <<'PY'
import json,sys
a=json.load(open(f"snap/estrutura-{sys.argv[1]}.json"))["medidas"]
b=json.load(open(f"snap/estrutura-{sys.argv[2]}.json"))["medidas"]
for vp in a:
    print(f"--- {vp} ---")
    for k in a[vp]:
        print(f"  {k:<16} delta antes={a[vp][k]['delta']}  delta depois={b[vp].get(k,{}).get('delta')}")
PY
  echo; [ $falhou -eq 0 ] && echo "PIPELINE: APROVADO" || echo "PIPELINE: TEM FALHA, ver acima"
  return $falhou
}

funcional() {
  echo "### dados (servidor, somente leitura)"
  scp -q ~/Documents/cetrus-testes-2026-08-28/testes-dados.php cetrus:/tmp/
  ssh -o BatchMode=yes cetrus "cd ~/htdocs && wp eval-file /tmp/testes-dados.php" | tail -6
  echo; echo "### front (local)"
  python3 ~/Documents/cetrus-testes-2026-08-28/testes-http.py | tail -8
}

case "${1:-}" in
  captura)  captura "$2" ;;
  comparar) comparar "$2" "$3" ;;
  funcional) funcional ;;
  *) echo "uso: ./rodar.sh {captura <rotulo>|comparar <a> <b>|funcional}"; exit 2 ;;
esac
