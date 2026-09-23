#!/usr/bin/env python3
"""
Regressao do autocomplete da busca da home (mu-plugin cetrus-busca-autocomplete).

Valida o contrato que o usuario sente: o indice existe, todo destino que ele
oferece responde 200, e todo apelido de busca continua tendo lastro no catalogo.

    python3 autocomplete.py               # valida
    python3 autocomplete.py --mostrar     # so imprime o resumo do indice

Armadilhas ja pagas e tratadas aqui:
  * apelido com valor de DUAS palavras nao acha nada. "tc" => "tomografia
    computadorizada" devolvia zero porque nenhum curso escreve o termo inteiro no
    titulo. O teste 3 existe por causa disso: apelido sem lastro vira FALHA, nao
    some em silencio;
  * o edge cache do WordPress.com tem TTL de 300s: toda URL leva query string
    aleatoria;
  * UA de bot recebe o desafio JS da Automattic (403), entao UA de navegador real;
  * o painel so e injetado na home. Em modo preview ele exige ?ac=1 na URL, que e
    tambem o que fura o edge cache.
"""
import json, random, re, sys, unicodedata, urllib.error, urllib.request

HOME  = "https://cetrus.com.br/"
REST  = "https://cetrus.com.br/wp-json/cetrus/v1/busca-indice"
UA = ("Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 "
      "(KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36")

MIN_CURSOS = 350          # 404 em 23/09/2026; abaixo disso algo quebrou na query
MIN_ESPEC  = 50           # 72 em 23/09/2026
AMOSTRA    = 12           # destinos sorteados para conferir HTTP 200

# Termos que precisam continuar achando curso. Sao as buscas reais do campo:
# nome de especialidade, nome de coordenador e a abreviacao que o medico digita.
GOLDEN = {
    "ultrassom":   1,     # so casa via apelido; sem ele somem ~140 cursos de USG
    "usg":         1,
    "ecocardio":   3,
    "ginecologia": 3,
    "pediatria":   3,
    "dor":         3,
    "doppler":     3,
}


def baixa(url, bruto=False):
    sep = "&" if "?" in url else "?"
    req = urllib.request.Request(f"{url}{sep}qa={random.randint(1, 10**9)}",
                                 headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=60) as r:
        dado = r.read()
    return dado if bruto else dado.decode("utf-8", "replace")


def normaliza(s):
    s = unicodedata.normalize("NFD", s)
    return "".join(c for c in s if unicodedata.category(c) != "Mn").lower()


def prefixo(feno, token):
    return re.search(r"(^|[^a-z0-9])" + re.escape(token), feno) is not None


def main():
    mostrar = "--mostrar" in sys.argv
    falhas, alertas = [], []

    # 1. o indice responde e tem volume plausivel
    try:
        idx = json.loads(baixa(REST))
    except Exception as e:
        print(f"FALHA  indice nao respondeu: {e}")
        return 1

    cursos, espec, apelidos = idx.get("c", []), idx.get("e", []), idx.get("a", {})
    print(f"indice: {len(cursos)} cursos, {len(espec)} especialidades, "
          f"{len(apelidos)} apelidos, versao {idx.get('v')}")

    if len(cursos) < MIN_CURSOS:
        falhas.append(f"so {len(cursos)} cursos no indice (esperado >= {MIN_CURSOS})")
    if len(espec) < MIN_ESPEC:
        falhas.append(f"so {len(espec)} especialidades (esperado >= {MIN_ESPEC})")

    # 2. nenhuma linha quebrada
    for c in cursos:
        if not c[0].strip():
            falhas.append(f"curso sem titulo em {c[1]}")
        if not c[1].startswith("/"):
            falhas.append(f"caminho invalido: {c[1]!r} ({c[0][:40]})")

    # 3. todo apelido precisa ter lastro. Sem isto um apelido morto fica anos no
    #    codigo fingindo que ajuda.
    feno_tudo = [normaliza(f"{c[0]} {c[2]} {c[3]}") for c in cursos]
    for chave, alvos in apelidos.items():
        for alvo in alvos:
            n = sum(1 for f in feno_tudo if prefixo(f, normaliza(alvo)))
            if n == 0:
                falhas.append(f"apelido {chave!r} -> {alvo!r} nao casa com nenhum curso")
            elif mostrar:
                print(f"  apelido {chave:<10} -> {alvo:<18} {n:3d} cursos")

    # 4. as buscas que importam continuam devolvendo curso
    for termo, minimo in GOLDEN.items():
        alvos = [normaliza(termo)] + [normaliza(a) for a in apelidos.get(termo, [])]
        n = sum(1 for f in feno_tudo if any(prefixo(f, a) for a in alvos))
        if n < minimo:
            falhas.append(f"busca {termo!r} devolveria {n} cursos (esperado >= {minimo})")
        elif mostrar:
            print(f"  busca   {termo:<12} {n:3d} cursos")

    # 5. destino sorteado responde 200. E o que o usuario clica.
    amostra = random.sample(cursos, min(AMOSTRA, len(cursos)))
    amostra += random.sample(espec, min(4, len(espec)))
    for item in amostra:
        url = "https://cetrus.com.br" + item[1]
        try:
            baixa(url)
        except urllib.error.HTTPError as e:
            falhas.append(f"{item[0][:45]} -> {item[1]} respondeu HTTP {e.code}")

    # 6. titulo repetido nao e culpa da busca, mas custa vaga na lista
    vistos = {}
    for c in cursos:
        chave = (normaliza(c[0]), normaliza(c[3]))
        vistos.setdefault(chave, []).append(c[1])
    repetidos = {k: v for k, v in vistos.items() if len(v) > 1}
    if repetidos:
        alertas.append(f"{len(repetidos)} curso(s) cadastrados em 2 slugs "
                       f"(o painel deduplica, mas sao paginas duplicadas de verdade)")
        for (t, _), slugs in list(repetidos.items())[:5]:
            alertas.append(f"    {t[:52]} -> {', '.join(slugs)}")

    # 7. a home carrega o painel quando o modo permite
    html = baixa(HOME + "?ac=1")
    if "cetrus-ac-painel" not in html:
        falhas.append("home nao trouxe o CSS do painel (mu-plugin desativado ou modo off)")
    if "search-input-cetrus" not in html:
        falhas.append("home nao tem mais o campo .search-input-cetrus (hero refeita?)")

    print()
    for a in alertas:
        print("ALERTA", a)
    for f in falhas:
        print("FALHA ", f)
    if not falhas:
        print("OK  indice, apelidos, buscas-chave e destinos conferem")
    return 1 if falhas else 0


if __name__ == "__main__":
    sys.exit(main())
