# Projeto Cetrus — Padronização de páginas (Elementor) e Black November 2026

> **Documento central do projeto.** Outra pessoa (ou outra conta do Claude) deve conseguir retomar o trabalho só com este arquivo e o repositório `sergiopatrick/cetrus-wordpress`, branch `projeto/black-november-2026`.
> Última atualização: **22/10/2026** (sessão 1). Nada deste projeto está publicado em produção nem aplicado no staging até agora.

## Estado atual em uma tela

| Item | Situação em 22/10/2026 |
|---|---|
| Artifact de referência | Lido por completo (10 artboards, código-fonte). Design system na seção 3. |
| Auditoria do staging | Feita por leitura (REST autenticada + rastreamento das 432 páginas de produto). |
| Baseline de SEO da produção | `black-november-2026/baseline/prod-pre-black-20261022.tsv` (captura em andamento/concluída, ver seção 10). Redirecionamentos: `prod-pre-black-20261022-redirects.tsv`. |
| Mecanismo de ativação/rollback | Rascunho do mu-plugin `wp-content/mu-plugins/cetrus-black-november-2026.php` (+ `cetrus-black-november-2026/black.css`). **Não testado, não implantado.** |
| Templates Black no Elementor | Nenhum criado ainda. |
| Bloqueio principal | Rodar código PHP no staging (teste do filtro do Elementor e do mu-plugin) foi negado pelo controle de permissões da sessão; precisa de autorização explícita ou de outro caminho (seção 14). |
| Próximo passo exato | Seção 22, item 1. |

---

## 1. Objetivo e escopo

**Objetivo.** Aplicar no WordPress/Elementor do cetrus.com.br a identidade visual do Artifact "Cetrus · Black November 2026 · v2" durante a campanha de Black November, com templates reutilizáveis por categoria de produto, SEO preservado, validação documentada e rollback testado para voltar ao site anterior depois da campanha.

**Natureza.** Temporária. Duas operações independentes: (1) ativação da versão Black; (2) rollback pós-campanha, restaurando layout e configuração sem apagar dados criados em novembro.

**Escopo obrigatório (Etapa 3):** templates de produto das quatro categorias:

1. Pós-graduação — **tem layout no Artifact** (artboard "Produto Black · Pós em US Musculoesquelética"): reproduzir fielmente.
2. Fellowship — sem layout no Artifact: manter estrutura, aplicar identidade.
3. Cursos intensivos — sem layout no Artifact: manter estrutura, aplicar identidade.
4. Pacotes — sem layout no Artifact: manter estrutura, aplicar identidade.

**Também representado no Artifact (escopo a confirmar, seção 14):** header, barra de campanha e rodapé (globais); Home; Busca/vitrine; Conteúdos gratuitos (material rico + janela do e-book).

**Fora do escopo, salvo decisão:** EAD (template 19062), Graduação (19379), páginas institucionais, blog, LPs (`/lista-vip-black/` já é Black).

**Datas.** Desenvolvimento e validação no staging. Ativação em produção: **01/11/2026 00:00 (horário de Brasília)** — domingo. Encerramento: **pendente de confirmação** (o Artifact usa 30/11/2026 23:59:59 −03:00 no cronômetro; o rascunho do mu-plugin usa 01/12/2026 00:00 −03:00).

## 2. Links dos ambientes

| Ambiente | Endereço | Observações |
|---|---|---|
| Produção | https://cetrus.com.br | WordPress.com Atomic, plano Negócios, WP 7.1.3, PHP 8.3. Backup diário do Jetpack (último visto: 22/10/2026 00:42). |
| Staging | https://staging-821d-cetruscombr.wpcomstaging.com/wp-admin/ | Site de teste do WP.com, **privado** (visitante vê "Site privado"; `noindex, nofollow`). Cópia da produção de ~16/10/2026. **Conexão Jetpack quebrada** ("jetpack.jsonAPI does not exist"): sem backup, sem Implantações, sem varredura no painel do WP.com. |
| Painel WP.com | https://my.wordpress.com/sites/cetrus.com.br | Implantações: `.../deployments` (repo `cetrus-wordpress`, branch `main`, disparo manual). Sincronizar staging⇄produção: botão "Sincronizar" (não usar para banco, ver seção 11). |
| Repositório | https://github.com/sergiopatrick/cetrus-wordpress | Só `wp-content/mu-plugins/` vai para o servidor (workflow `.github/workflows/wpcom.yml`). Branch do projeto: `projeto/black-november-2026` (local, criada de `origin/main` d42c9d1). |
| Artifact (referência visual) | https://claude.ai/artifact/QDtcbbNjjYrk9Z8t6UB46K | Canvas do tipo Design, versão lida 1792690312-8a22. O link com chave de compartilhamento fica com o usuário (não versionar chaves). |
| LP Lista VIP Black | https://cetrus.com.br/lista-vip-black/ (página 34916) | Origem da paleta grafite/lavanda. |

## 3. Design system extraído do Artifact

Fonte: arquivos `project/*.dc.html` do Artifact (Home, Busca, Produto, Material, MaterialJanela, versões mobile e Tokens). Todos os valores abaixo foram lidos no código; nada foi presumido. O Artifact foi lido como código, não renderizado em navegador.

### 3.1 Regra-mãe

"O site inteiro vira grafite durante a campanha, com a paleta da LP Lista VIP (página 34916). Os azuis saem de cena e voltam depois da Black." (artboard Tokens). "Cada peça é removível sem mexer no conteúdo da página."

### 3.2 Cores

**Camada Black (tokens oficiais do artboard Tokens):**

| Token | Hex | Uso |
|---|---|---|
| Grafite base | `#0E0F12` | faixas, barra de campanha, fundo geral |
| Grafite 2 | `#141518` | seções alternadas (legado, fechamento) |
| Superfície | `#191A1E` | cards, formulário, chips escuros |
| Hero topo | `#2A2B30` | degradê do hero (LP) |
| Lavanda | `#8C8EB6` | "BLACK", detalhes, "+" dos números, losangos |
| Lavanda clara | `#D4D5E8` | fim do degradê, links, selo |
| Texto | `#F3F3F6` | títulos; também fundo do botão claro |
| Texto apoio | `#A9AAB8` | legendas, rótulos |

**Valores complementares usados nas telas:** header/rodapé `#0B0C0E`; faixa legal do rodapé `#070809`; seções `#121316`; texto corrido `#C9CAD6`; links do rodapé `#D6DEEC`; texto do botão claro `#111214`; botão do formulário `#3A3B4E` com borda `rgba(212,213,232,.4)`; degradê do hero de produto `linear-gradient(180deg,#23242A 0%,#121316 100%)` + `radial-gradient(60% 70% at 0% 0%, rgba(255,255,255,.10), transparent 60%)`; hero da busca `#24252A→#16171B` com brilho a 90% −20%; fechamento `radial-gradient(60% 80% at 50% 0%, rgba(255,255,255,.10), transparent 70%), #141518`.
**Bordas:** `rgba(255,255,255,.06/.08/.10/.14/.18)`; botão outline `rgba(255,255,255,.45–.55)`.
**Degradê da palavra BLACK:** `linear-gradient(100deg, #8C8EB6 0%, #D4D5E8 100%)` com `background-clip: text`.

**Site fora da campanha (para o rollback):** `#002452` (Cetrus Dark), `#003B6C` (Medium), `#F2F3FB` (Lighter), `#F9FAFB`, `#E9EBED`, `#404444`. No Kit do Elementor (10452): 11 cores globais (c8edb45 `#002452`, a324a9f `#003B6C`, b310495 `#9BB1DF`, 243199b `#3D93D7`, d5c5f63 `#F2F3FB`, 2b2abc8 `#404444`, 5fb8232 `#595F5F`, 9c98396 `#C3C6C6`, fde34b3 `#E9EBED`, 579eab4 `#FFFFFF`, b8de8cc `#F9FAFB`).

### 3.3 Tipografia

| Papel | Artifact (mockup) | Produção |
|---|---|---|
| Títulos, rótulos, botões | Montserrat 300/400/500/600/700 | **Bw Modelica** (o próprio Artifact diz: "Montserrat entra no lugar da Bw Modelica só neste mockup") |
| Corpo | Roboto 400/500/700, 15–18 px, base 16 px / 1,55 | Roboto (já carregado; mu-plugin `cetrus-fontes.php`) |

Escala usada:

- "BLACK NOVEMBER" do hero: 64 px desktop / 32 px mobile, `letter-spacing .02em`; BLACK 700 com degradê + NOVEMBER 300.
- H1 da home: `clamp(22px, 2.2vw, 30px)` 300, ênfase 600. H1 do produto: `clamp(28px, 3.2vw, 42px)` 300 + nome do curso 600, `line-height 1.15`.
- H2 de seção: `clamp(24px, 2.4vw, 30px)` ou `clamp(22px, 2.2vw, 28px)`, 700, `#F3F3F6`. H2 editorial: `clamp(26px, 2.8vw, 38–40px)` 300 + ênfase 600.
- Eyebrow/rótulo: 12 px, `letter-spacing .2–.24em`, maiúsculas, 500–600, `#A9AAB8`.
- Card de curso: tipo 10 px 700 maiúsculas `.5px`; título 15–16 px 700; info 12 px `#A9AAB8`.
- Botões da campanha: 12–13 px 600 maiúsculas `letter-spacing .12em`.

**Conflito a decidir (seção 14):** regra anterior da Martech/Design para peças Black (14/10/2026): Bw Modelica **só Light 300 e Medium 500**, "BLACK" em Medium com degradê. O Artifact (20/10) usa 600/700 para ênfase e BLACK. Proposta: 300 → Light 300; 600/700 → Medium 500 (segue a regra da marca), salvo decisão contrária.

### 3.4 Grid, containers e espaçamento

- Container de conteúdo: `max-width 1240px`, `padding 0 20px`. Header interno: 1170 px (igual ao `container_width` do Kit). Conteúdo programático: 1000 px. Fechamento: 1080 px.
- Respiro vertical das seções: 64 px (padrão), 56 px (formulário), 72 px (autoridade), 80 px (fechamento/pertencimento). Hero do produto: `40px 20px 64px`.
- Gaps: 20 px entre cards (desktop), 12–14 px (mobile); 24–36 px entre blocos.
- Hero do produto (v2): grade `minmax(0,1fr) 440px`, `column-gap 48px`, áreas `'intro img' 'det info'` no desktop; no mobile `'intro' 'img' 'info' 'det'`.
- Grades de cards: `repeat(auto-fit, minmax(220–260px, 1fr))`; vitrine 4 colunas desktop / 2 mobile.
- Mobile: carrosséis com rolagem lateral e `scroll-snap` (itens 78–86% da largura, recuo 20 px).
- Breakpoints do site (Kit): mobile < 768, tablet 768–1024, desktop ≥ 1025. O Artifact tem só desktop (1440) e mobile (390): **tablet não está representado** (limitação; derivar do desktop com o comportamento dos breakpoints do Elementor).

### 3.5 Componentes

| Componente | Especificação |
|---|---|
| Header | fundo `#0B0C0E`, borda inferior `rgba(255,255,255,.10)`, altura mín. 65 px, logo branco 30 px, menu 15 px 500 `#F3F3F6` (gap 26 px), "Fale Conosco" outline (raio 8 px, borda .55, altura 44 px); mobile: hambúrguer 44 px em `#191A1E`. Mesmos itens do site. |
| Barra de campanha | link de largura total logo abaixo do header, fundo `#0E0F12`, padding `11px 20px`, "BLACK November" (12 px, `.24em`, BLACK com degradê) + texto 14 px + CTA outline (raio 4 px, 12 px 600 maiúsculas). Substitui a faixa sazonal. |
| Botão claro (primário) | fundo `#F3F3F6`, texto `#111214`, raio 4 px (campanha) ou 8 px (CTA do produto), altura 52–54 px. |
| Botão outline | borda `rgba(255,255,255,.45–.55)`, texto `#FFF`, raio 4 px, 12–13 px 600 maiúsculas `.12em`. |
| Botão de formulário | fundo `#3A3B4E`, borda `rgba(212,213,232,.4)`, raio 6 px, altura 52 px. |
| Selo "Condições exclusivas" | fundo `#0E0F12`, borda `rgba(212,213,232,.35–.4)`, texto `#D4D5E8`, 10–11 px 600 maiúsculas `.14em`; raio 3 px no card (canto superior, **só em turma participante**, sem cobrir a faixa de selo existente) e pílula no hero. |
| Chip "Inscrições abertas" | pílula `#191A1E`, 11 px 700 maiúsculas. |
| Card de curso | fundo `#191A1E`, borda `.08`, raio 4 px, imagem 420:250, faixa de selo inferior `rgba(14,15,18,.88)`, padding `14px 16px 18px`. |
| Card de diferencial | `#191A1E`, borda `.10`, raio 12 px, padding 20 px, ícone losango `#D4D5E8` 22 px. |
| Docente | retrato 444:528, raio 12 px, véu inferior até `rgba(0,0,0,.85)`, pílula "Coordenador". |
| Depoimento | `blockquote` `#191A1E`, raio 12–16 px, padding 24–26 px. |
| Acordeão | linhas com borda inferior `.10`, botão de 54 px, sinais + / −. |
| Formulário | campos `#0E0F12`, borda `.14`, raio 6 px, 46 px; rótulos 11 px maiúsculas `.16em`. |
| Busca | caixa `rgba(255,255,255,.06)` borda `.14` raio 10 px; botão claro "Pesquisar". |
| Rodapé | `#0B0C0E`; faixa legal `#070809`. |
| Faixa corrida (busca) | 12 px maiúsculas `.22em`, separador losango `#8C8EB6`. |
| Pertencimento | frase "Todo **[especialidade]** que hoje é referência, um dia precisou tomar essa decisão." com a palavra alternando (respeita `prefers-reduced-motion`; texto completo para leitor de tela). |
| Barra fixa mobile do produto | só "Quero me inscrever" (botão claro). |
| Cronômetro | desligado por padrão; só nos últimos dias, só no hero. |

**Estados de interação definidos no Artifact:** links `#D4D5E8` → hover `#FFFFFF`; acordeão `aria-expanded`; seletor de turma desabilitado até escolher a unidade; troca de foto do hero por botões com `aria-pressed`. **Não definidos:** hover de botões e cards, foco visível, menus suspensos. Proposta: herdar os estados atuais do site, só trocando as cores.

### 3.6 Regras de copy

Falamos: planejamento, trajetória, calendário de turmas, vaga garantida, oportunidade; a condição exclusiva é vantagem de quem se antecipa. Mote: "Referência em Excelência", "A maior oportunidade para estar entre os melhores". **Não falamos:** "imperdível", "só hoje", porcentagem como headline; nada de vermelho, amarelo ou contagem agressiva fora do hero.

### 3.7 Mapa das telas do Artifact

| Artboard | O que muda em relação ao site (comentários do próprio Artifact) |
|---|---|
| Home Black (desktop/mobile) | Hero novo "BLACK NOVEMBER" com retratos de professores; "Cursos em destaque" logo após o hero; seção "Condições exclusivas" com formulário; legado (números); autoridade; fellowship; depoimentos; conteúdos gratuitos; fechamento com frase alternada. |
| Busca e vitrine Black | Barra de campanha, hero baixo grafite com busca, faixa corrida, mesmos filtros e mesma vitrine (sem filtro Black), bloco "Falar com um consultor". |
| Produto Black · Pós | Estrutura real do template de produto + barra de campanha, chips no hero, grade v2 do hero, seção "Pertencimento", barra fixa mobile só com "Quero me inscrever", selo nos cards relacionados. |
| Material rico Black | Mesma página de conteúdos gratuitos + bloco "ponte conteúdo → curso" e, na janela do e-book, opt-in "Quero receber as condições Black". |
| Tokens | Paleta, tipografia, peças, copy e calendário. |

### 3.8 Limitações conhecidas da reprodução

- Fonte: Montserrat → Bw Modelica (pesos sob decisão).
- Tablet sem referência.
- Contraste: o texto legal do rodapé no Artifact (`rgba(255,255,255,.18)` sobre `#070809`) não passa WCAG AA. Proposta: manter o contraste atual do site nessa linha.
- Imagens do Artifact são uploads do próprio canvas (`/_blob/...`); em produção usar as mídias do WordPress (retratos via REST `curriculo`).

## 4. Auditoria técnica do WordPress

Levantada em 22/10/2026 por leitura (REST autenticada no staging e na produção, sem gravar nada).

| Item | Staging | Produção |
|---|---|---|
| WordPress / PHP | 7.1.3 | 7.1.3 / 8.3 |
| Tema | Hello Elementor (sem tema filho; "Child Theme Configurator" inativo) | igual |
| Elementor / Pro | 4.3.5 / 4.1.3 | 4.3.5 / 4.1.3 |
| Kit ativo | 10452 "Elementor Cetrus Teste 1" (container 1170 px, padding 8, espaço entre widgets 24, botões BwModelica 700 `#003B6C` → hover `#002452`, raio 8) | igual |
| Plugins ativos | 29, mesmas versões da produção | ACF Pro 6.8.10, Code Snippets 4.0.0, CF7 6.2.3, Elementor/Pro, Jeg Kit, Make Column Clickable, WP Grid Builder (+Elementor), Relevanssi Premium (+Live Ajax), WooCommerce 11.2.1, Yoast SEO 28.7, Yoast Duplicate Post, Redirection 5.10.1, **Redirect Redirection 1.4.0 (segundo plugin de redirect ativo)**, Head & Footer Code, Microsoft Clarity, Imagify, Jetpack, Integração Cetrus-Lyceum 1.0.8, WordPress Agent (big-sky) etc. |
| Tipos de post | `product` (WooCommerce), `curriculo`, `elementor_library`, `e-floating-buttons`, `elementor_snippet` | igual |
| Taxonomias de produto | `v2_modalidade` (categoria de negócio), `v2_especialidade`, `v2_cidade`, `professor`, `docente`, `fellow-mais-4`, `product_tag`, `product_cat` (todos em "Sem categoria") | igual |
| Campos | ACF (ex.: `v2_codigo_do_curso`, `v2_duracao`), exibidos por shortcodes do Code Snippets | igual |
| Código PHP próprio | 80 snippets no Code Snippets (71 ativos, id máx. 114) + 13 mu-plugins do repositório | igual |
| Código no Elementor | 7 "Código personalizado" (Swiper, preload, sessionStorage, WP Grid Builder, acordeão) | — |
| Rastreamento | GTM-MKCGH2J via snippets 42 e 90 (este com persistência de UTMs e rastreio de formulários HubSpot), Clarity (plugin), HubSpot (via GTM), Pixel Meta (via GTM) | igual. **O staging também carrega o GTM e os formulários HubSpot** (ver riscos). |
| SEO | Yoast SEO 28.7 (sitemaps `product`, `page`, `curriculo`, `v2_*`, `polo-de-treinamento` etc.), Redirection com 79 regras 301 | staging `noindex, nofollow` por ser privado |
| Backup | sem (Jetpack quebrado) | Jetpack diário |
| Implantação por GitHub | indisponível (Jetpack quebrado) | ativa, manual, branch `main`, só mu-plugins |
| Cache | Batcache + cache de borda (~5 min) | igual |

**Diferenças conhecidas staging × produção (22/10):** o staging é uma cópia de ~16/10. Depois disso a produção ganhou, entre outros: header 11545 alterado em 19/10; templates de container 35597 ("Faixa – Lista Vip 2027") e 34626 alterado; home (10946) e `/lista-vip-black/` alteradas em 19/10; produtos novos (ex.: 35650 em 22/10). A produção tem 73 itens na biblioteca do Elementor; o staging, 66. **Novos posts criados no staging terão ids que podem colidir com ids já usados na produção** → nada do projeto pode depender de id fixo entre ambientes.

## 5. Inventário das páginas e templates

### 5.1 Templates do Theme Builder (iguais em staging e produção, salvo os da seção 4)

| Tipo | Id | Nome | Condição |
|---|---|---|---|
| product | 12073 | [Post Individual] - Produto | `include/product` (padrão de todo produto) |
| product | 19225 | Produto Fellowship | `in_v2_modalidade/238` |
| product | 32359 | Pacotes - Template padrão - Julho de 2026 | `in_v2_modalidade/1591` |
| product | 28008 | [POST] - Produto individual [Sanar Pós] | `in_product_tag/1180` (tag "Sanar") |
| product | 26931 | [POST] - Produto individual - Pediatria | `in_v2_especialidade/190` |
| product | 28779 | [FELLOWSHIP] Página de Produto v2 | `in_product_tag/1262` ("teste-fellow") |
| product | 21114 | Produto Fellow - Especial - Mais 4 Profs | `in_fellow-mais-4/875` |
| product | 34795 | Produto Fellowship - polo adicional (cópia de teste do #19225) | sem condição; 1 produto usa (via snippet 112) |
| product | 19062 | [Post Individual] - Produto EAD | `in_v2_modalidade/420` |
| product | 19379 | [Produto Individual] - Graduação | `in_v2_modalidade/149` |
| header | 11545 | Header 1 - Default - Fale Conosco | `include/general` |
| header | 20497 | Header 4 - Fellowship - CTA Inscreva-se | `include/woocommerce/in_v2_modalidade/238` |
| header | 20396 | Header 3 - Pacote - CTA Inscreva-se | `include/singular/page/19257` |
| header | 23382 | Header - Graduação | `in_v2_modalidade/149` |
| header | 21326 | Header 5 - Lista_Vip | página 21335 |
| header | 19499 / 29605 | Header 2 - Produto / Header Teste v3 | sem condição / rascunho |
| footer | 10919 | Footer 1 - Default | `include/general` |
| footer | 21331 | Footer 2 - Lista_Vip | página 21335 |
| popup | 14050, 29363, 20500 | Formulário / Formulário Fellow / Formulário - Produto Fellowship | singular / singular / geral |
| popup | 28094 | Formulário - Produto Sanar Pós | `in_product_tag/1180` |
| popup | 13513, 15446, 25119, 25700, 17059 | filtros (vitrine, busca, especialidade, modalidade, currículo) | arquivos |
| product-archive | 12753, 25011 | [Arquivo] Modalidades / Especialidades | arquivos `v2_modalidade`/`v2_cidade` e `v2_especialidade` |
| search-results | 12747 | [Arquivo] Resultado Pesquisa | busca |
| error-404 | 14718 | Erro 404 | 404 |
| loop-item | 11204 | Card Produto - v1 | usado nos carrosséis "Quem viu este curso" |
| loop-item | 12017, 29791, 23191 | Card professores / Coordenador fellow / Card B2B | por widget |

### 5.2 Uso real por página de produto (rastreamento do staging, 432 publicadas, 22/10)

Template efetivamente renderizado (atributo `data-elementor-type="product"`):

| Template | Páginas | Quem cai nele |
|---|---|---|
| 19225 Fellowship | 173 | fellowship (140) + pós em formato fellowship (33) |
| 12073 Produto padrão | 158 | pós (26), intensivos (58), preparatórios (5), monitoria (2), outros |
| 32359 Pacotes | 29 | pacotes |
| 19062 EAD | 29 | EAD |
| 26931 Pediatria | 20 | pós (12) e **intensivos (6)** com especialidade Pediatria |
| 28008 Sanar Pós | 14 | pós com tag Sanar (inclusive 2 com Pediatria) |
| 28779 Fellowship v2 | 5 | fellowship com tag 1262 + 1 pós-fellowship |
| 21114 Fellow mais 4 | 1 | — |
| 19379 Graduação | 1 | — |
| 34795 cópia de teste | 1 | `fellowship-em-ecocardiografia-dr-thalles-barbosa` |
| erro de rede | 1 | produto 31340 (repetir) |

Desempate observado entre condições do mesmo nível (taxonomia): vence o template de id maior (28008 > 26931; 28779 > 19225; 21114 > 19225). Registrar e revalidar depois da troca.

### 5.3 Páginas afetadas

- **Globais** (header 11545, footer 10919): todas as páginas do site, inclusive as que não são de produto.
- **Produto:** as 424 URLs do `product-sitemap.xml` da produção (432 publicadas no staging).
- **Se o escopo incluir as outras telas do Artifact:** Home (página 10946), Vitrine (`/vitrine/`, página 11768), resultados de busca (12747), arquivos de modalidade/especialidade (12753, 25011), Conteúdos gratuitos (página 27615).

## 6. Mapeamento das quatro categorias de produtos

Classificação de negócio = taxonomia **`v2_modalidade`** (não é `product_cat`, que está toda em "Sem categoria").

| Categoria | Termo (id, slug) | Publicados (staging) | Template atual | Layout no Artifact? | Particularidades |
|---|---|---|---|---|---|
| Pós-graduação | 147 `pos-graduacao` (+1179 `pos-graduacao-on-line`) | 78 só pós + 35 pós-fellowship | 12073; 26931 (Pediatria); 28008 (Sanar); pós-fellowship → 19225/28779/21114 | **Sim** | shortcodes `[lyceum_course_selects]`, `[lyceum_course_button]`, `[mostrar-cards-professores]`, `[mostrar_testimonials_slider]`, `[mostrar-breadcrumb-produto]`; popup HubSpot 14050/29363; Sanar usa popup 28094 |
| Fellowship | 238 `fellowship` | 145 (+35 com pós) | 19225; 28779 (tag 1262); 21114 (mais 4 profs); 34795 (teste) | Não | `[lyceum_fellowship_button]`, snippets V3 [FELLOWSHIP] 91–102 (hero, módulos, local, depoimentos, FAQ, coordenadores, mapa do polo), header próprio 20497, popup 20500 |
| Cursos intensivos | 148 `cursos-intensivos` (filho: 408 preparatórios) | 106 (+6 preparatórios) | 12073; 26931 quando Pediatria | Não (usa o mesmo template da pós) | mesmos shortcodes do 12073; mu-plugin `cetrus-cursos-fm-cidade` (cursos FM_) |
| Pacotes | 1591 `pacotes` | 29 | 32359 | Não | snippets 106–110 (cursos do pacote, coordenadores, formulário de seleção), **formulário Contact Form 7** (widget `jkit_contact_form_7`), header 20396 (página 19257) |

**Não presumir campos iguais:** pós/intensivos usam o 12073 e os seletores do Lyceum; fellowship tem componentes V3 próprios e header próprio; pacotes têm composição de cursos e CF7.

## 7. Arquitetura dos templates e campos dinâmicos

**Decisão proposta (aguardando validação no staging): clones "[BLACK-2026]" + troca em tempo de renderização por mu-plugin versionado.**

1. **Originais intocados.** Nenhum template atual é editado. Eles continuam donos das condições de exibição.
2. **Um clone Black por template afetado**, criado no Elementor (duplicar), sem condições, com título `[BLACK-2026] #<id do original> <nome>`. O clone mantém a mesma estrutura, widgets, shortcodes e tags dinâmicas; muda só estilo (e, na pós, os ajustes v2 do Artifact).
3. **Troca em tempo de renderização:** o mu-plugin `cetrus-black-november-2026.php` usa o filtro `elementor/theme/get_location_templates/template_id` do Elementor Pro para devolver o id do clone no lugar do original enquanto a campanha estiver ativa. As condições continuam sendo avaliadas no original; por isso o desempate entre condições não muda e produto novo herda a versão Black sozinho.
4. **Componentes referenciados por id dentro de widgets** (loop item 11204 nos carrosséis, templates de container no header) não passam pelo filtro: o clone aponta direto para o clone do componente (ex.: carrossel do clone da pós → clone `[BLACK-2026] #11204`).
5. **Camada CSS** (`cetrus-black-november-2026/black.css`, só na janela, sob `body.cetrus-black-2026`) para o que o Elementor não estiliza: HTML de shortcodes do Code Snippets, seletores do Lyceum, cards do WP Grid Builder, formulários.
6. **Pós com layout do Artifact:** o 12073 atende pós **e** intensivos. Para não mudar a estrutura dos intensivos, os elementos novos da v2 (chips, seção "Pertencimento") entram no clone com condição de exibição por modalidade (recurso de condições de exibição do Elementor Pro, ou shortcode que só imprime em pós). Alternativa, se isso não funcionar: dois clones do 12073 e escolha por modalidade no filtro.

**Templates a clonar (lista mínima):** product 12073, 26931, 28008, 19225, 28779, 21114, 32359 (+34795 se a cópia de teste continuar em uso); header 11545, 20497, 20396; footer 10919; loop-item 11204; popups 14050, 29363, 20500, 28094 (se os formulários precisarem de tema escuro). Decidir EAD 19062 e Graduação 19379.

**Por que não editar os originais e restaurar depois:** o rollback ficaria dependente de restaurar `_elementor_data` (revisões ou backup), e qualquer edição legítima feita em novembro seria perdida ou misturada. **Por que não só CSS global:** cores globais do Kit são usadas ao mesmo tempo como fundo e como texto (ex.: `#002452`), então remapear as variáveis inverteria contraste em vários pontos; nos templates de produto ~115 de 140 cores são globais e ~25 fixas.

## 8. Regras de aplicação automática dos templates

- A escolha do template continua 100% nas condições atuais do Theme Builder (seção 5.1). Nenhuma condição nova é criada nos clones.
- Durante a campanha: `original resolvido pelas condições` → `clone [BLACK-2026] #original` (se existir e estiver publicado).
- Fora da campanha, com o mu-plugin desligado, ou sem clone: o original.
- Validação automática obrigatória (seção 12): para cada URL de produto, `template(com Black) == clone(template(sem Black))`.

## 9. Instruções para cadastrar novos produtos em produção

Nada muda no cadastro. Para o template certo ser aplicado (durante e depois da campanha):

1. Cadastre o produto normalmente (WooCommerce > Produtos > Adicionar novo) com os campos ACF de sempre.
2. Marque a **Modalidade** (`v2_modalidade`): Pós-Graduação, Fellowship, Cursos Intensivos ou Pacotes. É ela que escolhe o template (Fellowship → 19225; Pacotes → 32359; Pós e Intensivos → 12073).
3. Exceções que mudam o template: tag **Sanar** (→ 28008), especialidade **Pediatria** (→ 26931, também para intensivos), tag **teste-fellow** (→ 28779), "Fellow mais 4" = Sim (→ 21114).
4. Publicar. Durante a campanha a página já sai com a versão Black do template correspondente; depois dela, com o template normal. Não é preciso escolher template no produto.
5. (Pendente de decisão) Para o selo "Condições exclusivas", marcar a tag de participação na Black (seção 14).

Este procedimento será validado com um produto de teste por categoria no staging (seção 12) antes de ser considerado aprovado.

## 10. Linha de base e validações de SEO

**Arquivos (produção, 22/10/2026, somente leitura):**

- `black-november-2026/baseline/prod-pre-black-20261022.tsv` — uma linha por URL dos sitemaps `product`, `page` e `v2_modalidade`: HTTP, redirecionamentos, URL final, title, description, canonical, robots, og:title, og:url, H1 (texto), H2 (textos), contagem de H3, tipos JSON-LD, breadcrumb, templates do Elementor renderizados, links (total/internos), imagens (total/sem alt), palavras, GTM, HubSpot, "Selecione a turma", links de WhatsApp, erro PHP.
- `...-sitemaps.txt` (contagem por sitemap) e `...-robots.txt`.
- `prod-pre-black-20261022-redirects.tsv` — 79 regras 301 do Redirection.

**Script:** `tests/qa/black/seo-baseline.ps1` (PowerShell 5.1 + curl.exe; não depende de Python/Node, que não existem nesta máquina). Query aleatória em cada pedido para furar o cache de borda; UA de navegador (UA de bot recebe desafio do edge).

**Achados pré-existentes (não são deste projeto; não corrigir sem decisão):**

- ~50 redirecionamentos antigos apontam para `https://cetruscombr.wpcomstaging.com/...` (domínio interno), gerando cadeia de 2 saltos; `/mamografia` termina em **404** depois de 2 saltos.
- 3 regras-modelo "/URL Origem → URL Destino" ativas; cadeia `/fale-pelo-whatsapp-2/ → /pagina-de-contato-new-cetrus-v1/ → /fale-pelo-whatsapp/`.
- Dois plugins de redirecionamento ativos (Redirection e Redirect Redirection).
- No staging, 3 páginas de produto com quantidade de H1 ≠ 1 (conferir na produção).

**Regras de preservação para os clones:** mesmos textos e níveis de heading (H1 único, mesmos H2/H3 e ordem), mesmos links e âncoras, mesmos `alt`, breadcrumb e shortcodes; title/description/canonical/robots/JSON-LD vêm do Yoast e não dependem do template. Elementos novos da campanha (barra, selo, pertencimento) não usam H1, e só usam H2 se aprovado; texto acessível sem duplicar heading.

## 11. Estratégia de versionamento e deploy

| O quê | Onde vive | Como vai para produção |
|---|---|---|
| mu-plugin da camada Black + CSS | Git (`wp-content/mu-plugins/`) | PR → merge em `main` → WP.com > Implantações > Acionar implantação (manual). Inerte fora da janela. |
| Clones `[BLACK-2026]` do Elementor | banco (posts `elementor_library`) | Exportar JSON no staging (Modelos > Exportar) e versionar em `black-november-2026/elementor/`; importar na produção (Modelos > Importar). Importação cria posts novos sem condições: é o que queremos. O vínculo é pelo título. |
| Baselines, scripts, documentação | Git (`black-november-2026/`, `tests/qa/black/`) | não vão para o servidor (o workflow só copia mu-plugins). |
| Configurações do Elementor (Kit, condições) | banco | **não são alteradas** por este projeto. |

**Não usar** "Sincronizar → enviar staging para produção" do WP.com com banco: substituiria pedidos, leads, produtos e usuários criados na produção depois da cópia.

**Identificadores de versão:**

- Tag Git `PRE-BLACK-2026` no commit de `main` imediatamente anterior ao merge do projeto (hoje seria `d42c9d1`); criar no dia do merge.
- Tag Git `BLACK-NOVEMBER-2026` no commit aprovado para a campanha.
- No banco, a versão anterior **é** o conjunto de templates originais (intocados) + backup do Jetpack do dia; exportar os JSON dos originais para `black-november-2026/elementor/pre-black/` como referência.

## 12. Testes executados e evidências

Status: **Aprovado / Reprovado / Bloqueado / Não aplicável**; "—" = ainda não executado.

| # | Verificação | Status | Evidência |
|---|---|---|---|
| 1 | Leitura completa do Artifact | Aprovado | arquivos `project/*.dc.html` lidos (seção 3) |
| 2 | Acesso ao wp-admin do staging | Aprovado | painel "Joao Faro", REST autenticada |
| 3 | Inventário de templates e condições (staging e produção) | Aprovado | seção 5.1 |
| 4 | Mapeamento produto → template (staging, 432 páginas) | Aprovado | seção 5.2 (1 página com erro de rede, repetir) |
| 5 | Baseline SEO da produção | — | `prod-pre-black-20261022.tsv` (ver histórico) |
| 6 | Filtro `elementor/theme/get_location_templates/template_id` existe e troca o template | **Bloqueado** | execução de PHP no staging negada pelo controle de permissões da sessão (seção 14) |
| 7 | Lint PHP e lógica do mu-plugin com stubs do WordPress (PHP 8.3.33 em WebAssembly) | Aprovado | `tests/qa/black/mu-plugin-harness.js`, 22/10: MD5 `3461d17e…f9e6`, lint OK; janela (31/10 23:59:59 fora, 01/11 00:00 dentro, 30/11 23:59:59 dentro, 01/12 00:00 fora), modos ligado/desligado, prévia só para editor logado, desligado no editor do Elementor, vínculos e 4 alertas esperados, troca 12073→clone, classe no body e CSS só quando ligado. **Não exercita o Elementor real** (isso é o teste 6). |
| 8 | Visual (desktop/tablet/mobile) dos clones × Artifact | — | |
| 9 | Funcional (seletores Lyceum, CTAs, popups HubSpot, CF7, WhatsApp, menus, filtros) | — | |
| 10 | SEO por página (diff contra a baseline) | — | |
| 11 | Analytics (GA4/GTM/Pixel/Clarity/HubSpot) com disparo real bloqueado na rede | — | |
| 12 | Cadastro de produto novo por categoria (4) | — | |
| 13 | Ativação e rollback no staging, com tempo medido | — | |
| 14 | Desempenho antes/depois (Lighthouse/CWV) | — | |

## 13. Problemas encontrados e soluções aplicadas

| Data | Problema | Solução / encaminhamento |
|---|---|---|
| 22/10 | Staging privado: `fetch` sem cookie devolve "Site privado" | rastrear com a sessão logada (`credentials: 'include'`) |
| 22/10 | Python/Node ausentes; pipeline `tests/qa/*.py` não roda nesta máquina | scripts novos em PowerShell + JavaScript no navegador |
| 22/10 | `Remove-Item` falha com caminho 8.3 do TEMP (`JOAO~1.FAR`) | pasta temporária dentro da saída + `[IO.File]::Delete` |
| 22/10 | Criação de snippet PHP de teste no staging negada pelo controle de permissões | registrado como bloqueio; pedir autorização (seção 14) |

## 14. Pendências e bloqueios

**Bloqueios técnicos**

1. **Executar código no staging.** Para provar o filtro e testar ativação/rollback é preciso rodar o mu-plugin no staging. Caminhos, do mais simples ao mais "limpo": (a) autorizar explicitamente a criação de um snippet de teste no Code Snippets do staging (só reage a `?cetrus_probe=1` com usuário logado); (b) consertar a conexão Jetpack do staging e conectar as Implantações do staging à branch `projeto/black-november-2026`; (c) SFTP/SSH do staging (credenciais com o usuário).
2. **Staging desatualizado** (~16/10). Recomendado puxar a produção para o staging (WP.com > Sincronizar > puxar da produção) **antes** de criar os clones, para os clones nascerem dos templates atuais (header 11545 mudou em 19/10). Isso sobrescreve o staging; precisa de aprovação.
3. **Teste de formulários no staging envia dados reais**: o staging carrega GTM-MKCGH2J, HubSpot e CF7. Testes devem bloquear `/g/collect`, `facebook.com/tr`, envio do HubSpot e e-mail, ou usar contatos de teste combinados com o time de CRM.

**Decisões do usuário**

4. Data/hora de encerramento e responsável pelo rollback (seção 19).
5. Escopo além dos produtos: header/rodapé/barra (recomendado sim), Home, Vitrine/Busca, Conteúdos gratuitos, EAD e Graduação.
6. Pesos da Bw Modelica (regra da marca 300/500 × Artifact 300/600/700).
7. Quais turmas/cursos participam da Black (para o selo "Condições exclusivas" e a barra "Ver condição"); fonte do dado (tag de produto proposta: `black-2026`).
8. Formulário "Receba nossas condições exclusivas" da home: qual formulário HubSpot e qual fluxo comercial.
9. Responsável pela execução do deploy em 30–31/10 e pelo monitoramento em 01/11 (domingo).

## 15. Cronograma até a publicação

| Data | Etapa | Status |
|---|---|---|
| 22/10 (qui) | 1–3. Auditoria do Artifact e do staging, mapeamento de páginas/produtos, design system, baseline de SEO | em andamento (este documento) |
| 23/10 (sex) | Decisões da seção 14; sincronizar staging com produção; liberar execução de código no staging; provar o filtro; mu-plugin 0.2 no staging | — |
| 24–27/10 | 4. Clones Black: header/rodapé/barra → pós (layout Artifact) → fellowship → intensivos → pacotes → cards/popups | — |
| 27–28/10 | 5–6. Testes funcionais e responsivos; SEO e analytics por página | — |
| 28/10 | 7–8. Correções e nova rodada; teste de cadastro de produto novo nas 4 categorias | — |
| 28/10 | 9. Teste de ativação e rollback no staging (com tempo) | — |
| 29/10 (qui) | 10. Revisão final e aprovação; PR revisado | — |
| 30/10 (sex) | 11. Baseline PRE-BLACK final (SEO + export JSON dos originais + backup Jetpack do dia); importar clones na produção (inertes); merge + implantação do mu-plugin (inerte até 01/11); prévia logada `?cetrus_black=1` em produção | — |
| 31/10 (sáb) | Congelamento; go/no-go | — |
| 01/11 00:00 (dom) | 12. Ativação automática pela janela do mu-plugin; validação imediata | — |
| 01/11–30/11 | 13. Monitoramento pós-publicação | — |
| fim da janela | Rollback automático + validação pós-rollback | — |

A data não se sobrepõe à qualidade: sem os testes 8–13 aprovados, o PR não é mesclado e a versão Black não liga.

## 16. Baseline pré-Black November (`BASELINE PRE-BLACK NOVEMBER 2026`)

| Componente | Como fica preservado | Status |
|---|---|---|
| Templates originais do Elementor | Não são editados; ids na seção 5.1; export JSON em `black-november-2026/elementor/pre-black/` | export: — |
| Configurações globais (Kit 10452) | Não são alteradas; valores na seção 3.2/4 | registrado |
| Condições de exibição | Não são alteradas; lista na seção 5.1 | registrado |
| Header/footer/componentes | Originais intocados | registrado |
| CSS/JS personalizados | Snippets e "Código personalizado" não são alterados; mu-plugins no Git (`main` d42c9d1) | registrado |
| URLs e SEO | `prod-pre-black-20261022.tsv` + redirects | em captura |
| Formulários/CTAs/integrações | colunas de formulários/WhatsApp/GTM na baseline; popups listados | parcial |
| Backup restaurável | Jetpack diário na produção (22/10 00:42). Confirmar o backup de 30–31/10 e o tipo de restauração disponível antes do merge | — |

## 17. Procedimento de ativação da versão Black

Pré-condições: testes 8–13 aprovados; PR aprovado; baseline final de 30/10 salva.

1. (30/10) Importar na produção os JSON dos clones `[BLACK-2026]` (Modelos > Importar modelos). Conferir títulos `#<id>` **apontando para os ids da produção** (iguais aos do staging para templates anteriores a ~16/10; conferir os demais).
2. Publicar os clones (sem condições). Eles não aparecem para ninguém ainda.
3. Mesclar o PR e acionar a implantação no WP.com. Conferir em Ferramentas > Black November 2026: janela, modo `auto`, vínculos sem alertas.
4. Prévia logada: abrir home, 1 pós, 1 fellowship, 1 intensivo, 1 pacote com `?cetrus_black=1`; rodar o pipeline em modo prévia.
5. 01/11 00:00: a janela abre sozinha. Validar em janela anônima com `?x=<aleatório>` (cache de borda ~5 min).
6. Se algo crítico falhar: modo `desligado` (efeito imediato para logados, até ~5 min para visitantes).

## 18. Plano e procedimento de rollback

**Mecanismo principal (sem tocar no banco):** fim da janela (`CETRUS_BLACK_FIM`) ou modo `desligado` → o filtro devolve os originais, a classe e o CSS deixam de ser impressos. Produtos, leads, pedidos e edições de novembro ficam intactos.

**Passo a passo pós-campanha:**

1. Na data combinada, confirmar em Ferramentas > Black November 2026 que o estado é "desligada" (ou gravar `desligado`).
2. Purga: aguardar ~5 min ou purgar o cache de borda; conferir em janela anônima.
3. Rodar o checklist da seção 20 (pós-rollback) e o diff de SEO contra a baseline.
4. Depois de 7 dias estável: PR removendo o mu-plugin e a pasta de CSS; clones `[BLACK-2026]` passam a rascunho (não apagar: servem de referência para 2027).

**Contingência:** se um original tiver sido alterado indevidamente em novembro, restaurar pela revisão do Elementor ou pelo JSON de `elementor/pre-black/`. Restaurar backup completo do Jetpack só em último caso e com plano de reconciliação de dados (apaga pedidos/leads/produtos posteriores ao backup).

**Teste do rollback:** obrigatório no staging antes de 31/10 (teste 13), medindo o tempo. Ainda não executado.

## 19. Data prevista para reversão e responsável

- Data/hora do rollback: **PENDENTE** (proposta: fim da janela em 01/12/2026 00:00, ou 30/11 23:59:59 como no cronômetro do Artifact).
- Responsável pela execução e pela validação: **PENDENTE**.
- Automação: a reversão acontece pela própria janela do mu-plugin (não depende de cron nem de pessoa), **mas só depois que o mu-plugin for testado no staging e implantado**. Até lá, nada está agendado.

## 20. Monitoramento pós-publicação e pós-rollback

- **Primeira hora (01/11):** HTTP 200 nas URLs da baseline (amostra de 30 + todas as de produto à noite), template esperado por URL, H1/title/canonical iguais, erros JS no console, seletores do Lyceum, popups e CTAs, eventos GA4 `MM_click_whatsapp` e envio HubSpot (com bloqueio em teste), Clarity.
- **Diário (02–07/11), depois semanal:** 404 e 5xx (logs do WP.com), Search Console (cobertura, Core Web Vitals), MQLs do site no HubSpot e cadastros no GA4 comparados com a mesma semana de novembro/2025 e com outubro/2026 (sazonalidade da Black; não atribuir variação ao layout sem controle).
- **Pós-rollback:** mesmo checklist; diff da baseline deve voltar a zero nas colunas de template e SEO.

## 21. Histórico de alterações

| Data | Quem | O quê |
|---|---|---|
| 22/10/2026 | Claude (sessão 1) | Lint e teste lógico do mu-plugin em PHP-WASM (`tests/qa/black/mu-plugin-harness.js`): aprovado. |
| 22/10/2026 | Claude (sessão 1) | Leitura do Artifact; auditoria do staging e da produção (somente leitura); rastreamento das 432 páginas de produto do staging; branch `projeto/black-november-2026`; script `tests/qa/black/seo-baseline.ps1`; baseline de SEO e de redirecionamentos da produção; rascunho do mu-plugin `cetrus-black-november-2026.php` + `black.css`; este documento. Nenhuma alteração em staging ou produção. Tentativa de criar snippet de teste no staging negada (não executada). |

## 22. Próximas ações priorizadas

1. **Obter as decisões da seção 14** (itens 1, 2, 4–9), começando por: autorização para rodar código no staging e sincronizar o staging com a produção.
2. Provar no staging o filtro de troca de template (teste 6) com a sonda; se o filtro não existir nesta versão, trocar a estratégia para condições no próprio clone (troca em lote por script) e registrar aqui.
3. Instalação do mu-plugin no staging (snippet ou Implantações). Lint e lógica já aprovados em PHP-WASM (teste 7); repetir o harness a cada mudança no arquivo.
4. Exportar JSON dos originais (pre-black) para `black-november-2026/elementor/pre-black/`.
5. Criar os clones na ordem: header 11545 + barra → footer 10919 → produto 12073 (pós v2 + intensivos) → 19225 → 32359 → 26931/28008/28779/21114 → loop 11204 → popups.
6. Montar o pipeline de comparação (template esperado, SEO, visual por screenshot, rede/analytics com bloqueio) e rodar a cada clone.
7. Teste de produto novo por categoria; teste de ativação/rollback com tempo.
8. PR para revisão.
