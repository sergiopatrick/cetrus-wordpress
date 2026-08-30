#!/bin/bash
# Camada 1 da validacao mobile: Lighthouse com o mesmo motor que o PageSpeed usa.
#   ./lh-mobile.sh <rotulo> [bloqueio]
SP="$(cd "$(dirname "$0")" && pwd)"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
ROT="$1"; BLOQ="${2:-}"
mkdir -p "$SP/lh"
URLS=(
  "home|https://cetrus.com.br/"
  "curso|https://cetrus.com.br/cursos/pos-graduacao-lato-sensu-ecocardiografia-fetal/"
  "vitrine|https://cetrus.com.br/vitrine/"
  "especialidades|https://cetrus.com.br/especialidades/"
  "curriculo|https://cetrus.com.br/curriculo/dr-anselmo-carmo/"
  "conteudos|https://cetrus.com.br/conteudos-gratuitos/"
)
for item in "${URLS[@]}"; do
  n="${item%%|*}"; u="${item##*|}"
  args=(--only-categories=performance --output=json --output-path="$SP/lh/lh-$n-$ROT.json"
        --chrome-flags=--headless=new --quiet)
  [ -n "$BLOQ" ] && args+=(--blocked-url-patterns="$BLOQ")
  CHROME_PATH="$CHROME" npx -y lighthouse@13.4.1 "$u" "${args[@]}" >> "$SP/lh/lh.log" 2>&1
  echo "done $n-$ROT" >> "$SP/lh/lh.log"
done
echo "FIM $ROT" >> "$SP/lh/lh.log"
