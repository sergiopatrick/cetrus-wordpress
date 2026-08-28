#!/bin/bash
# Smoke test do cetrus.com.br: compara estado funcional das paginas-chave.
# Uso: ./smoke.sh <rotulo>  -> salva smoke-<rotulo>.tsv e imprime
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36'
S="$(cd "$(dirname "$0")" && pwd)"
OUT="$S/smoke-$1.tsv"
URLS=(
  "home|https://cetrus.com.br/"
  "curso_eco|https://cetrus.com.br/cursos/pos-graduacao-lato-sensu-ecocardiografia-fetal/"
  "curso_usgo|https://cetrus.com.br/cursos/ultrassonografia-em-ginecologia-e-obstetricia/"
  "vitrine|https://cetrus.com.br/vitrine/"
  "especialidades|https://cetrus.com.br/especialidades/"
  "curriculo|https://cetrus.com.br/curriculo/dr-anselmo-carmo/"
)
printf "pagina\thttp\tbytes\tttfb_fresh_s\tgtm_loaders\tisScriptDebug\tjquery_min\tsel_turma\toptions\tdata2040\tclarity_tags\thsforms\tswiper_wrappers\tfatal_php\tfa_css\tphoton\n" > "$OUT"
for item in "${URLS[@]}"; do
  name="${item%%|*}"; url="${item##*|}"
  tmp=$(mktemp)
  code_ttfb=$(curl -s -A "$UA" -w '%{http_code} %{time_starttransfer}' -o "$tmp" "$url?smoke=$RANDOM$RANDOM")
  code="${code_ttfb%% *}"; ttfb="${code_ttfb##* }"
  bytes=$(wc -c < "$tmp" | tr -d ' ')
  gtm=$(grep -o "googletagmanager.com/gtm.js" "$tmp" | wc -l | tr -d ' ')
  sdbg=$(grep -o '"isScriptDebug":[a-z]*' "$tmp" | head -1 | cut -d: -f2)
  jqmin=$(grep -c 'jquery\.min\.js' "$tmp")
  selt=$(grep -c 'Selecione a turma' "$tmp")
  opts=$(grep -o '<option' "$tmp" | wc -l | tr -d ' ')
  d2040=$(grep -c '2040' "$tmp")
  clar=$(grep -o 'puburbloss\|q9qmgxpdql\|omqyipbysh' "$tmp" | sort | uniq -c | tr -s ' \n' ' ,' )
  hsf=$(grep -c 'js.hsforms.net' "$tmp")
  swip=$(grep -o 'swiper-wrapper' "$tmp" | wc -l | tr -d ' ')
  fatal=$(grep -c 'Fatal error\|Uncaught Error' "$tmp")
  facss=$(grep -o 'font-awesome[^"]*\.css' "$tmp" | wc -l | tr -d ' ')
  photon=$(grep -o 'i[0-2]\.wp\.com' "$tmp" | wc -l | tr -d ' ')
  printf "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n" \
    "$name" "$code" "$bytes" "$ttfb" "$gtm" "$sdbg" "$jqmin" "$selt" "$opts" "$d2040" "$clar" "$hsf" "$swip" "$fatal" "$facss" "$photon" >> "$OUT"
  rm -f "$tmp"
done
column -t -s $'\t' "$OUT"
