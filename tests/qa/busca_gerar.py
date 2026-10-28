#!/usr/bin/env python3
"""
Gera, a partir do mu-plugin do repo, os insumos que o busca_prints.py injeta por
cima da produção: js.txt, css.txt, indice.json e fallback.json.

    python3 busca_gerar.py <pasta>

Roda o arquivo do repo no servidor por `wp eval-file -` com o namespace trocado,
para não colidir com a versão publicada. Só lê: monta o índice sem gravar
transient e consulta o Relevanssi para os termos do busca_prints.TERMOS.
"""
import json, os, subprocess, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from busca_prints import TERMOS

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PLUGIN = os.path.join(RAIZ, "wp-content", "mu-plugins", "cetrus-busca-autocomplete.php")


def main():
    if len(sys.argv) < 2:
        print(__doc__); return 2
    pasta = sys.argv[1]
    os.makedirs(pasta, exist_ok=True)

    fonte = open(PLUGIN, encoding="utf-8").read()
    fonte = fonte.replace("namespace Cetrus\\BuscaAutocomplete;", "namespace Cetrus\\BuscaAutocompleteQA;", 1)
    fonte = fonte[: fonte.index("if ( defined( 'WP_CLI' ) && WP_CLI )")]
    fonte += """
$termos = json_decode( %s, true );
$fb = array();
foreach ( $termos as $t ) {
	$q = mb_strtolower( trim( $t ) );
	$fb[ $q ] = array( 'q' => $q, 'p' => buscar_no_catalogo( $q ) );
}
echo wp_json_encode( array( 'js' => js(), 'css' => css(), 'indice' => construir_indice(), 'fallback' => $fb ) );
""" % repr(json.dumps(TERMOS)).replace('"', '"')

    r = subprocess.run(["ssh", "cetrus", "cd ~/htdocs && wp eval-file - 2>/dev/null"],
                       input=fonte.encode(), capture_output=True, timeout=300)
    saida = r.stdout.decode("utf-8", "replace")
    try:
        d = json.loads(saida[saida.index("{"):])
    except Exception:
        print("FALHA", saida[:800], r.stderr.decode()[:800]); return 1

    open(os.path.join(pasta, "js.txt"), "w", encoding="utf-8").write(d["js"])
    open(os.path.join(pasta, "css.txt"), "w", encoding="utf-8").write(d["css"])
    json.dump(d["indice"], open(os.path.join(pasta, "indice.json"), "w", encoding="utf-8"), ensure_ascii=False)
    json.dump(d["fallback"], open(os.path.join(pasta, "fallback.json"), "w", encoding="utf-8"), ensure_ascii=False)
    i = d["indice"]
    print(f"indice: {len(i['c'])} cursos, {len(i['e'])} especialidades, {len(i['a'])} apelidos, "
          f"{sum(1 for c in i['c'] if c[7])} da Sanar")
    for q, v in d["fallback"].items():
        print(f"  relevanssi {q:<28} {len(v['p'])}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
