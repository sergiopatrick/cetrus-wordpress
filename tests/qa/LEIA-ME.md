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
