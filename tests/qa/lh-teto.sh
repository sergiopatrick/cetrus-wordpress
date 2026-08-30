#!/bin/bash
# --blocked-url-patterns precisa ser REPETIDO, uma vez por padrao.
# Passar tudo separado por virgula vira UM padrao so e nao bloqueia nada.
SP="$(cd "$(dirname "$0")" && pwd)"
CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
B=()
for p in "*googletagmanager.com*" "*connect.facebook.net*" "*facebook.com*" "*doubleclick.net*" \
         "*licdn.com*" "*outbrain.com*" "*tailtarget.com*" "*google-analytics.com*" \
         "*analytics.google.com*" "*hsforms.net*" "*hs-analytics.net*" "*hs-banner.com*" \
         "*hubspot.com*" "*hsappstatic.net*" "*hs-sites.com*" "*hubapi.com*" "*clarity.ms*" \
         "*youtube.com*" "*ytimg.com*" "*jsdelivr.net*" "*hotjar*" "*hubspotfeedback*"; do
  B+=(--blocked-url-patterns="$p")
done
for item in "home|https://cetrus.com.br/" "curso|https://cetrus.com.br/cursos/pos-graduacao-lato-sensu-ecocardiografia-fetal/"; do
  n="${item%%|*}"; u="${item##*|}"
  CHROME_PATH="$CHROME" npx -y lighthouse@13.4.1 "$u" --only-categories=performance --output=json \
    --output-path="$SP/lh/lh-$n-teto.json" --chrome-flags=--headless=new --quiet "${B[@]}" >> "$SP/lh/lh.log" 2>&1
  echo "done $n-teto" >> "$SP/lh/lh.log"
done
echo PRONTO > "$SP/lh/teto.txt"
