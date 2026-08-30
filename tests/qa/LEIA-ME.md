# Pipeline de QA do cetrus.com.br

Criado em 30/08/2026 para validar as mudancas de Core Web Vitals sem quebrar
funcionamento nem SEO. Tudo aqui e SOMENTE LEITURA sobre o site.

## Uso

    ./rodar.sh captura antes            # antes de mexer
    # ... aplica a mudanca ...
    ./rodar.sh captura depois
    ./rodar.sh comparar antes depois
    ./rodar.sh funcional                # suites de regressao de dados e front

## O que cada peca faz

| arquivo | o que valida |
|---|---|
| `corpus.py` | monta 79 URLs deterministicas cobrindo todos os templates |
| `snapshot.py` | por pagina: http, title, description, canonical, robots, og:*, H1, JSON-LD, contagem de palavras, links, imagens sem alt/sem dimensao, erro de PHP |
| `assets.py` | por imagem: http, tipo, bytes, dimensoes reais e assinatura visual 32x32 |
| `diff.py` | compara dois retratos e separa FALHA / ALERTA / INFO |
| `shots.py` | 24 screenshots (12 paginas x desktop/mobile) e diff de pixels |
| `vitals.py` | CLS e LCP em Chrome real, com o observer instalado antes do documento |
| `estrutura.py` | checagem deterministica de CLS: altura dos elementos com e sem JavaScript |
| `cdp.py` | cliente CDP minimo (WebSocket na mao) usado por vitals e estrutura |
| `preflight-photon.py` | testa o efeito do filtro de qualidade do Photon SEM tocar em producao |

## Armadilhas ja pagas, nao repita

1. **Comparar imagem sem compor sobre fundo solido.** WebP com perdas deixa RGB
   residual sob alpha=0. Uma metrica em escala de cinza direta acusou 26 logos
   "visualmente diferentes" que estavam identicos. `sobre_branco()` resolve.
2. **Paginas de facet do WP Grid Builder variam sozinhas** (`/local/`, `/modalidades/`,
   `/curriculo/`, `/vitrine/`). Contagem de `<option>` e de palavras oscila sem
   ninguem mexer, comprovado com A/B do mu-plugin ligado e desligado. Por isso
   entram em `INSTAVEIS` e queda de contagem vira ALERTA, nao FALHA.
3. **UA de bot recebe 403.** O edge da Automattic responde um desafio JavaScript
   ("Checking search engine crawler...") para Googlebot, Bingbot, GPTBot, ClaudeBot,
   facebookexternalhit e afins quando o IP nao e verificado. Isso NAO e bloqueio:
   o teste em tempo real do Search Console confirma "URL disponivel para o Google".
   O snapshot com `--ua googlebot` serve para comparar antes/depois, nunca como
   aprovacao absoluta.
4. **Hero do /institucional/ e a grade do /curriculo/ mudam sozinhos** (video de fundo
   e ordem aleatoria dos professores). Diferenca de pixel alta nessas duas paginas
   e esperada; confirme abrindo o comparativo antes de tratar como regressao.
5. **Sempre com query string aleatoria.** O edge tem TTL curto e ja enganou validacao.

## Portao de SEO

Depois de cada mudanca, rodar o teste em tempo real do Search Console
(conta corporativa, `/u/2/`) em pelo menos uma URL de cada template e conferir
"O URL esta disponivel para o Google" e a aba Captura de tela.

## Mais armadilhas, pagas em 30/08/2026

6. **Medir CLS sem furar o cache de borda.** A primeira medicao depois da correcao
   do hero deu 0,0438, igual a antes: o edge estava devolvendo o HTML anterior.
   `vitals.py` agora acrescenta uma query aleatoria. Depois de mexer em HTML,
   rode tambem `wp edge-cache purge` na URL antes de medir.
7. **Nao confie em correlacao temporal para achar culpado de CLS.** A queda de
   altura do hero coincidia com `document.fonts.ready` (4245 ms contra 4261 ms) e
   parecia reflow de fonte. Bloqueando TODAS as fontes pela rede o CLS ficou
   igual. O culpado era outro. Bloquear o recurso suspeito e desligar o
   JavaScript sao os dois testes que realmente decidem.

## Camada dupla de validacao mobile

1. **`lh-mobile.sh <rotulo>`** roda o Lighthouse 13.4.1 no perfil mobile nas 6 paginas
   principais. E o mesmo motor e a mesma pontuacao que o PageSpeed Insights mostra.
   `lh-tabela.py <rotulo> [outro]` imprime a tabela comparativa.
2. **`vitals.py`** e **`estrutura.py`** medem em Chrome real com viewport 390x844 e
   emulacao movel: CLS e LCP com o observer instalado antes do documento, e a
   checagem deterministica de altura com e sem JavaScript. Pega o que a pontuacao
   sintetica do Lighthouse esconde.
   `shots.py` fecha com 12 screenshots mobile comparados pixel a pixel.

`lh-teto.sh` mede o teto: a mesma pagina com TODO terceiro bloqueado.

**Armadilha cara:** `--blocked-url-patterns` precisa ser REPETIDO, um por padrao.
Passar `--blocked-url-patterns="a,b,c"` vira UM padrao so, nada e bloqueado, e o
teste devolve "bloquear terceiros nao muda nada". Foi exatamente o que aconteceu
aqui em 30/08/2026 e levou a uma conclusao errada que precisou ser desfeita.
