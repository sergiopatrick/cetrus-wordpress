# Projeto Cetrus — Padronização de páginas (Elementor) e Black November 2026

> **Documento central do projeto.** Outra pessoa (ou outra conta do Claude) deve conseguir retomar o trabalho só com este arquivo e o repositório `sergiopatrick/cetrus-wordpress`, branch `black-november-2026-ativacao-manual` (a antiga `projeto/black-november-2026` fica como histórico).
> Última atualização: **28/10/2026, noite** (sessão 4: ajustes da noite e virada pelo GitHub; guia em `black-november-2026/GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md`). **Branch do PR: `black-november-2026-ativacao-manual`** (https://github.com/sergiopatrick/cetrus-wordpress/tree/black-november-2026-ativacao-manual). Nada deste projeto está publicado em produção. **Relatório do processo, implantação de 01/11, rollback e riscos: [`black-november-2026/RELATORIO-IMPLANTACAO-E-ROLLBACK.md`](black-november-2026/RELATORIO-IMPLANTACAO-E-ROLLBACK.md).**

## Estado atual em uma tela

| Item | Situação em 28/10/2026 |
|---|---|
| Artifact de referência | Lido por completo (10 artboards, código-fonte). Design system na seção 3. |
| Baseline da produção | SEO: `black-november-2026/baseline/prod-pre-black-20261022.tsv` (456 URLs). **Originais (hash do Elementor de cada um dos 24 templates/páginas): `baseline/originais-prod-20261028.tsv`.** |
| Mecanismo de ativação/rollback | mu-plugin `wp-content/mu-plugins/cetrus-black-november-2026.php` **0.4.0**: **ativação e desativação MANUAIS por commit no GitHub** (chave `CETRUS_BLACK_VIRADA`; branches `black-november-2026-virada-0111` e `black-november-2026-rollback-0112`), painel com "seguir o GitHub" (padrão) e modos de emergência, trava sem modelos publicados, troca templates e páginas, **telas iguais à produção** (`cetrus_black_tela_fora()`), "Quem viu" sem upsell recebe cursos da mesma especialidade (só cursos com `v2_miniatura_titulo`). Harness: 31 casos aprovados (28/10, noite). **Liga/desliga testado no staging em 28/10 (teste 13).** **Não implantado em produção.** |
| Montagem dos modelos | **`black-november-2026/ferramentas/montar-black.js`** recria os 24 modelos `[BLACK-2026]` a partir dos originais do site em que roda. **Ensaio (simulação) na produção em 28/10: 24 de 24 sem erro**; 1 aviso (pós Sanar sem barra fixa no celular). |
| Telas SEM versão Black (iguais à produção, D7) | fellowship v2 (tag 1262 / 28779), graduação (modalidade 149 / 19379), arquivo de especialidade (25011), institucional, 404 e qualquer página sem modelo Black. |
| Staging | **Arquivos sincronizados com a produção em 28/10** (mídia, mu-plugins); **banco ainda o de 19/10** (a sincronização só de banco segue "Sincronizando…" no painel do WP.com; se concluir, apaga modelos e snippets da validação e é preciso reinstalar, seção 0). Snippets 118 (mu-plugin) e 119 (CSS) iguais ao commit `ac4ebef`; snippet 120 `[STAGING] Imagens no tamanho original` (só staging). 24 modelos remontados por script; 4 modelos das telas iguais à produção em rascunho. **Modo: desligado.** |
| Validação de 28/10 | Páginas pedidas conferidas e corrigidas: relatório, seção 5. |
| Bloqueio principal | Nenhum para o PR. Na produção: revisão/merge, implantação e montagem (relatório, seção 2). |
| Próximo passo exato | Até 31/10: PR `black-november-2026-ativacao-manual` → `main` ("Create a merge commit"), implantação e montagem na produção. 01/11: PR da `virada-0111`. 01/12: PR da `rollback-0112`. Guia `black-november-2026/GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md`. |

## 0. Como retomar o trabalho (de outra conta ou sessão)

1. **Repositório:** https://github.com/sergiopatrick/cetrus-wordpress/tree/black-november-2026-ativacao-manual (local: `C:\Users\joao.faro\repos\cetrus-wordpress`). Branch criada sobre a `main` de 28/10 (banner das calculadoras, API legada).
2. **Staging:** https://staging-821d-cetruscombr.wpcomstaging.com/wp-admin/ (logado). Versão Black: qualquer URL com `?cetrus_black=1` (logado); `?cetrus_black=0` mostra a original. Ferramentas › Black November 2026 mostra janela, modo e vínculos.
3. **Instalar a camada no staging** (o staging não tem Implantações): no console de qualquer página do wp-admin do staging:
   ```js
   var RAW = 'https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<commit>/';
   (0, eval)(await fetch(RAW + 'black-november-2026/ferramentas/staging-snippets.js').then(r => r.text()));
   await cetrusStaging.instalar(RAW);   // snippets "[BLACK-2026] Camada..." (mu-plugin) e "[BLACK-2026] CSS..." (black.css)
   ```
   **Só no staging**, para as imagens em tamanho reduzido aparecerem (o WP.com só gera `-1024x683` etc. na CDN da produção): snippet front-end `[STAGING] Imagens no tamanho original (sem CDN do WP.com)` com o conteúdo de `black-november-2026/staging/imagens-tamanho-original.php` (sem o `<?php`). Ele tem trava de host `.wpcomstaging.com` e não vai para a produção.
4. **Montar os modelos Black** (staging ou produção), no console de `/wp-admin/edit.php?post_type=elementor_library`:
   ```js
   var BASE = RAW + 'black-november-2026/';
   for (const f of ['ferramentas/transforma-black.js', 'ferramentas/montar-black.js']) (0, eval)(await fetch(BASE + f).then(r => r.text()));
   var rel = await cetrusMontar.executar({ base: BASE, simular: true });  // ensaio
   var rel = await cetrusMontar.executar({ base: BASE });                 // grava só em [BLACK-2026]
   ```
   O script sempre parte do ORIGINAL (o transformador não é idempotente), localiza os pontos de ajuste pela estrutura e recusa gravar em qualquer post que não seja `[BLACK-2026] #<id>`. Ajuste de layout entra no script ou nos `componentes/`, não à mão no Elementor (seria sobrescrito na próxima montagem).
5. **Testes:** harness do mu-plugin `tests/qa/black/mu-plugin-harness.js` (PHP 8.3 em WebAssembly numa aba de `cdn.jsdelivr.net`; dá para baixar o plugin do GitHub raw, sem colar). Varredura original × Black: `tests/qa/black/varredura-staging.js`. Mobile/tablet: iframe de mesma origem com largura fixa e `outline` (não `border`); não usar o parâmetro `m` na URL (query var do WP → 404), usar `qa`.
6. **Ler o Artifact:** ferramenta Artifact, `read` com `path: "project/Main.dc.html"` etc. Não guardar a chave de compartilhamento.
7. **Ferramentas antigas** (`ferramentas/console-staging.js`, mapa `staging/clones-staging-20261022.tsv`): referem-se aos ids do staging antes da sincronização; ficam como histórico.

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
5. **Camada CSS** (`cetrus-black-november-2026/black.css`, só na janela) para o que o Elementor não estiliza: HTML de shortcodes do Code Snippets, seletores do Lyceum, cards do WP Grid Builder, formulários. Regras de conteúdo sob `body.cetrus-black-2026.cetrus-black-conteudo` (só telas com conteúdo Black); tokens, popups e cookies sob `body.cetrus-black-2026` (site todo).
6. **Pós com layout do Artifact:** o 12073 atende pós **e** intensivos. Para não mudar a estrutura dos intensivos, os elementos novos da v2 (chips, seção "Pertencimento") entram no clone com condição de exibição por modalidade (recurso de condições de exibição do Elementor Pro, ou shortcode que só imprime em pós). Alternativa, se isso não funcionar: dois clones do 12073 e escolha por modalidade no filtro.

7. **Páginas do Artifact** (Home 10946, Vitrine 11768, Conteúdos gratuitos 27615): a página original continua sendo servida; o mu-plugin esvazia o conteúdo do Elementor dela (`elementor/frontend/builder_content_data`), enfileira o CSS do modelo `[BLACK-2026] #<id da página>` no `<head>` e imprime o conteúdo do modelo no `the_content`. URL, title, description, canonical, breadcrumb e schema do Yoast não mudam.
8. **Formulário da home:** widget HTML com o formulário HubSpot das páginas de curso (`6ed56c62…`) e a busca de curso da LP por cima do select `contact__ct__product_from_site` (D6).

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

**Resumo da baseline de produção (22/10/2026, 456 URLs):** todas HTTP 200; 5 com redirecionamento (ex.: `/shop/` → `/vitrine/`); canonical = URL final em todas; 4 com `noindex`; 8 sem meta description; 0 imagens sem `alt`; 0 erros de PHP; GTM presente em todas. 13 URLs com H1 ≠ 1 (arquivos `/modalidades/*` e `/especialidades/` com 2 H1; `/monitoria/` sem H1; 2 produtos com 2 H1). Template por URL: 19225 = 172, 12073 = 155, 32359 = 29, 19062 = 29, páginas = 24, 26931 = 20, 28008 = 14, outros = 13. H1 da home hoje: "No Cetrus, você se desenvolve ao lado dos melhores".

**Mudança de SEO a aprovar antes de implementar (home):** o Artifact troca o H1 da home por "Aproveite a melhor oportunidade para estar entre os melhores.". Risco: baixo e temporário (title, description, canonical e schema continuam os da página; a home ranqueia por marca), mas é uma alteração de heading. Mitigação proposta: manter o H1 atual como H1 da versão Black e usar a frase do Artifact com o mesmo visual, como texto de destaque (não heading); ou aceitar a troca por 30 dias. Decisão pendente (seção 14).

**Situação adotada em 23/10 (padrão seguro até decisão):** o H1 da home na versão Black é o **atual** ("No Cetrus, você se desenvolve ao lado dos melhores"), com o visual do Artifact; "BLACK NOVEMBER" e as frases de destaque são parágrafos.

**Mudanças de heading nas páginas Black (registradas antes de publicar; risco baixo, temporário, URL/title/description/canonical/schema intactos):**

| Página | H1 | H2 acrescentados pela versão Black | Motivo / mitigação |
|---|---|---|---|
| Home (10946) | igual | "As melhores condições do ano para a sua formação." e "Aprenda com quem é referência." | **28/10:** sai o H2 "Assine a newsletter do Cetrus" (newsletter removida a pedido); os depoimentos (formato do Artifact) têm título em parágrafo. seções novas do Artifact (formulário e autoridade). A frase de fechamento e o "BLACK NOVEMBER" do hero são `<p>`. Ordem das seções muda (a do Artifact); todos os H2 originais continuam. |
| Vitrine (11768) | igual ("Encontre o curso ideal para sua carreira", mesmo markup) | "Planeje hoje o médico que você será amanhã." | bloco "Falar com um consultor" do Artifact. Sobretítulo e faixa corrida são `<p>`/decorativos (`aria-hidden`). |
| Conteúdos gratuitos (27615) | igual | "Do conteúdo gratuito à prática em pacientes reais." | bloco "ponte conteúdo → curso" do Artifact. |

Os textos dos H1 não foram alterados (nem o markup); só peso/cor por CSS. Se o time preferir zero H2 novo, trocar os três `<h2>` dos componentes por `<p>` — sem outro efeito.

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
| 6a | Filtro `elementor/theme/get_location_templates/template_id` existe no Elementor Pro 4.1.3 e é chamado na resolução | Aprovado | 22/10, snippet-sonda 115 no staging (`?cetrus_probe=1`, logado): o filtro recebe todos os templates com condição, em ordem de id decrescente (32359, 28779, 28008, 26931, 21114, 19379, 19225, 19062, …), na home, na pós US-ME (12073) e na pós Sanar (28008) |
| 6b | A troca renderiza o clone de fato (header e produto) | Aprovado | 22/10, após autorização: clone `[BLACK-2026] #11545` = 35507; `/?cetrus_black=1` → `header:35507`, sem parâmetro ou `=0` → `header:11545`; na pós US-ME → `header:35507 product:35513`. Original 11545 intacto (modificado em 07/10, condição `include/general`). |
| 6d | Troca em todas as páginas de produto + home, vitrine, busca, arquivos, 404 (1ª varredura, 440 URLs, com e sem `?cetrus_black=1`) | Aprovado com ressalva | **SEO idêntico em 439 URLs** (status, H1, title, canonical; 1 URL com erro de rede). Achou 2 defeitos, corrigidos: (1) arquivos de modalidade/especialidade e busca não trocavam (Elementor escolhe o template no `pre_get_posts`) → mu-plugin decide desde o `init`; reteste OK; (2) fellowship com botão apontando direto para o popup 29363 → referências remapeadas nos clones. As comparações de template ficaram inconsistentes porque os clones mudaram durante a varredura; **2ª varredura com clones estáveis em andamento** (inclui também nº de H2, robots e JSON-LD). |
| 6e | Visual da pós US-ME com Black (desktop) | Parcial | Evidências `black-november-2026/evidencias/20261022-staging-pos-usme-*.jpg`. OK: header/logo branco/menu/Fale Conosco outline, barra de campanha, hero grafite, card de inscrição, "Quero me inscrever" claro, docentes, diferenciais, abas, acordeão. Pendente: selo "Inscrições abertas 2027" (ainda branco), seta do acordeão, degradê do hero (está chapado), estrutura v2 do Artifact (chips, pertencimento, barra fixa mobile). |
| 6c | mu-plugin instalado no staging como snippet 116 (cópia 0.1.0) | Aprovado | sem parâmetro: home 200, sem classe Black; com `?cetrus_black=1` logado: classe `cetrus-black-2026`; Ferramentas > Black November 2026 mostra janela 01/11 00:00 → 01/12 00:00, modo auto, "desligada" |
| 6f | Visual de fellowship (19225, `fellowship-em-ecocardiografia-dr-luis-guilherme-campos`) e pacote (32359, `formacao-completa-em-ultrassonografia-morfologica`) com Black | Parcial | Estrutura original preservada (D3/regra 4.2). OK: header próprio da fellowship com "Quero me inscrever" claro, hero com foto, diferenciais, acordeão, card do coordenador (após CSS), formulário embutido do pacote, conteúdo dos cursos do pacote. Pendente: card branco dos cursos do pacote (snippet 108), barra de campanha no header da fellowship (20497), selos "Lançamento". |
| 15 | Formulário da home (busca de curso + HubSpot 6ed56c62) em prévia local | Aprovado | `black-november-2026/componentes/home-condicoes-formulario.html`; prévia headless com o formulário HubSpot real (sem envio): busca "ecocardio fetal" lista 5 cursos; escolher grava `EC FETL` no select; digitar limpa; sem resultado mostra aviso; texto exato grava `EC_AEF1`; UTMs padrão `site-cetrus/organic/navegacao-padrao`; `.hbspt-form` renomeada. Evidências: `black-november-2026/evidencias/20261022-home-formulario-*.png`. **Envio real não testado** (geraria lead). |
| 7 | Lint PHP e lógica do mu-plugin com stubs do WordPress (PHP 8.3.33 em WebAssembly) | Aprovado | **23/10 (última): 18 casos, 14 OK + 4 informativos, MD5 `7be59958…cea8` (= arquivo = snippet 116, SHA-256 `ecd52906…46d8`)**, com o caso `conteudo_nao_black` (página sem modelo não recebe `cetrus-black-conteudo`). Rodada anterior do mesmo dia: 17 casos, MD5 `143d0675…5ac8`; casos novos: texto reserva zerado só com Black ativa, classe `cetrus-black-mod-pos-graduacao`, shortcodes `cetrus_black_selo` e `cetrus_black_pertencimento` registrados. Rodada anterior, 22/10: MD5 `3461d17e…f9e6`, lint OK; janela (31/10 23:59:59 fora, 01/11 00:00 dentro, 30/11 23:59:59 dentro, 01/12 00:00 fora), modos ligado/desligado, prévia só para editor logado, desligado no editor do Elementor, vínculos e 4 alertas esperados, troca 12073→clone, classe no body e CSS só quando ligado. **Não exercita o Elementor real** (isso é o teste 6). |
| 6g | Pós no layout do Artifact (23/10): selos "Inscrições abertas" (escuro) + "Condições exclusivas" (outline lavanda), degradê do hero, seção "Pertencimento" antes de "Quem viu", barra fixa mobile só com "Quero me inscrever"; pacote com cards e pílulas escuros; barra de campanha nos headers 20497, 20396 e 23382; selo no card do loop 35617 | Aprovado | desktop, 390 e 768 px sem rolagem horizontal (pós US-ME); selo do card conferido na vitrine (desktop e 2 colunas no celular, após ajuste de 23/10). |
| 6h | Home Black (modelo 35650) | Aprovado (prévia) | 23/10: ordem do Artifact (hero → destaques → condições → legado → autoridade → fellowship → depoimentos → conteúdos → newsletter → fechamento); H1 original; busca do hero com autocomplete (`.search-input-cetrus`); formulário HubSpot 6ed56c62 carrega ao rolar, rótulo "Curso de interesse", busca "ultrassom musc" lista cursos e grava o código no select (`US IMES`), **sem envio**; newsletter presente (após correção, seção 13); 390/600/768/1920 px sem rolagem horizontal (descontada a barra de admin). |
| 6i | Vitrine Black (modelo 35655) | Aprovado (prévia) | 23/10: hero grafite com sobretítulo, texto e busca lado a lado (desktop), faixa corrida, filtros/ordenação/paginação do WP Grid Builder escuros, cards Black com selo, bloco "Planeje hoje…"; 390 px OK; 768 px tem 37 px de rolagem horizontal **igual à vitrine original** (pré-existente, seção 13). |
| 6j | Conteúdos gratuitos Black (modelo 35661) | Aprovado (prévia) | 23/10: hero grafite, chips por área (ativo claro), prateleiras, bloco ponte, newsletter (slot `conteudos`), Blog; janela do material escura (formulário HubSpot carrega; **sem envio**); 390 px sem rolagem horizontal. 768 px: — |
| 6k | Fellowship (`fellowship-em-ecocardiografia-dr-luis-guilherme-campos`) e pacote (`formacao-completa-em-ultrassonografia-morfologica`) em 390 e 768 px | Aprovado | 23/10: sem rolagem horizontal; barra de campanha no header próprio ("Ver condição"); acordeões, depoimentos e "Quem viu" escuros. Pacote: aba ativa dos cursos (`.is-ativa`) e seta dos módulos corrigidas no mesmo dia. A foto do hero da fellowship não aparece no staging porque o arquivo da mídia dá 404 no staging (igual na versão original; problema do ambiente). |
| 8 | Visual (desktop/tablet/mobile) dos clones × Artifact | Parcial | pós, Home, Vitrine, Conteúdos, fellowship, pacote (6g–6k). Falta: intensivos, EAD, graduação, arquivos/busca em 390/768. |
| 9 | Funcional (seletores Lyceum, CTAs, popups HubSpot, CF7, WhatsApp, menus, filtros) | — | |
| 6l | **3ª varredura (23/10), clones estáveis:** template esperado + SEO, original × Black, 432 produtos + páginas + busca/arquivos/404 | Aprovado | `black-november-2026/validacao/staging-20261023-varredura.md`: 436 de 438 sem ressalva; SEO (title, description, canonical, robots, og, JSON-LD, H1) idêntico em todas; todos os templates trocados pelo clone certo; ressalvas explicadas (Home sem o template 15396 do hero antigo; busca com diferença transitória, igual na repetição; produto 31340 redireciona para fora nas duas versões). |
| 10 | SEO por página (diff contra a baseline) | Parcial | staging original × Black: aprovado (6l). Diff contra a baseline da **produção** fica para a prévia em produção (30/10). |
| 11 | Analytics (GA4/GTM/Pixel/Clarity/HubSpot) com disparo real bloqueado na rede | — | |
| 12 | Cadastro de produto novo por categoria (4) | — | |
| 13 | Ativação e rollback no staging, com tempo medido | Aprovado | 28/10, Ferramentas › Black November 2026 (formulário da tela): **ligado** 15:24:38 → Home, Vitrine e pós em Black sem `?cetrus_black`, graduação e fellowship v2 normais; **desligado** 15:25:02 → tudo normal. Efeito na requisição seguinte (staging sem cache de borda para logado). Registro com usuário e horário. |
| 6m | Páginas pedidas em 28/10, no staging sincronizado (Black × normal) | Aprovado | relatório, seção 5: Home (respiro 104 px, sem rolagem horizontal, depoimentos com fotos, sem newsletter, sem etiqueta), Vitrine, Conteúdos (cards, imagens, Blog), 7 produtos (imagens, depoimentos, "Quem viu"), 5 telas iguais à produção sem nada de Black |
| 6n | 4ª varredura (28/10), staging sincronizado | Aprovado | `black-november-2026/validacao/staging-20261028-varredura.md`: 442 URLs, SEO idêntico em todas; 415 OK, 14 OK com +6 H2 do "Quem viu" automático (rótulos dos cards, D8), 10 telas iguais à produção sem Black, 3 ressalvas conhecidas (hero antigo da Home, busca, produto 31340). Rodar uma varredura por vez: três em paralelo ativaram a checagem anti-robô do WP.com (403) |
| 14 | Desempenho antes/depois (Lighthouse/CWV) | — | |

## 13. Problemas encontrados e soluções aplicadas

| Data | Problema | Solução / encaminhamento |
|---|---|---|
| 22/10 | Staging privado: `fetch` sem cookie devolve "Site privado" | rastrear com a sessão logada (`credentials: 'include'`) |
| 22/10 | Python/Node ausentes; pipeline `tests/qa/*.py` não roda nesta máquina | scripts novos em PowerShell + JavaScript no navegador |
| 22/10 | `Remove-Item` falha com caminho 8.3 do TEMP (`JOAO~1.FAR`) | pasta temporária dentro da saída + `[IO.File]::Delete` |
| 22/10 | Criação de snippet PHP de teste no staging negada pelo controle de permissões | usuário autorizou rodar código no staging (D1); snippets 115 e 116 criados |
| 22/10 | Criação de modelo novo do Elementor no staging (clone do header) negada pelo controle de permissões | usuário autorizou criar modelos `[BLACK-2026]` no staging; 25 clones criados |
| 22/10 | Arquivos (modalidade, especialidade) e busca não trocavam de template | o Elementor Pro escolhe esses templates no `pre_get_posts` e guarda a escolha; `cetrus_black_ativo()` passou a decidir desde o `init` (antes esperava o `wp`) |
| 22/10 | Botões da fellowship v2 apontam direto para o popup 29363 → a página Black carregava também o popup original | transformador remapeia referências (`__dynamic__` popup, `template_id`) para os clones |
| 22/10 | Faixa do header (`f611309`) escondida por CSS personalizado fora de alguns cursos | no clone do header o CSS sai e a faixa aponta para a barra de campanha (35623) |
| 22/10 | CSS do tipo "site-css" do Code Snippets não é impresso nesta instalação (recurso Pro) | snippet 117 virou PHP que imprime o `black.css` no `<head>` só com a versão Black ativa (em produção o mu-plugin enfileira o arquivo) |
| 22/10 | Barra de campanha com 100vw causava rolagem horizontal (largura da barra de rolagem medida como 0 antes da página crescer) | medida refeita no `load` e com `ResizeObserver` |
| 22/10 | Conversão de cores não é idempotente | o transformador sempre parte do template ORIGINAL |
| 22/10 | Páginas (Home, Vitrine, Conteúdos gratuitos) não passam pelo filtro de templates | mu-plugin passou a trocar só o conteúdo do Elementor da página pelo modelo `[BLACK-2026] #<id da página>` (filtros `elementor/frontend/builder_content_data` + `the_content`); URL e todo o SEO do Yoast continuam os da página original |
| 22/10 | Formulário HubSpot: campo "Curso" (614 opções) é desenhado depois do `onFormReady`, e o vão entre campos vinha dos ~17 campos ocultos somando `gap` | observador no contêiner (não no `<form>`) reaplica a busca/placeholders; layout por margem nos campos visíveis |
| 22/10 | Bw Modelica no site: Thin, Light, Regular, Medium, Bold, ExtraBold, Black (sem SemiBold) | peso 600 do Artifact vira Bold 700 (é o que o navegador escolhe para 600 sem a face 600) |
| 23/10 | Newsletter sumia nas páginas trocadas: sem o builder do original, o Elementor devolve o `post_content` (texto reserva com `[cetrus_newsletter]`), o `do_shortcode` (prioridade 11) o executava antes da troca e o shortcode (uma vez por slot por página) voltava vazio no modelo | mu-plugin zera o conteúdo da página principal na prioridade 1 do `the_content` quando há modelo Black; a troca continua na 20 (`cetrus_black_clone_no_conteudo()`). Snippet 116 atualizado (SHA igual). |
| 23/10 | `ep_custom_footer_script` (busca antiga da home) nas configurações da página seria copiado para o modelo e poderia rodar duas vezes | removido das configurações do modelo 35650; a página original continua imprimindo o seu (ele não acha `.live-search-results` e não faz nada; quem sugere cursos é o mu-plugin `cetrus-busca-autocomplete`) |
| 23/10 | Peso do H1 por CSS não pegava: o CSS do Elementor usa 4 classes | `!important` só no `font-weight` dos H1 de Vitrine (ebc59a1) e Conteúdos (3a64c09) |
| 23/10 | Chip ativo "Todos" invisível (regra Black do chip vencia a do plugin `.is-on`) | regra explícita para `.cg-chip.is-on` (fundo claro, texto escuro) |
| 23/10 | Selo "Condições exclusivas" do card vazava 15 px na grade de 2 colunas do celular | no celular o selo fica com 9 px, `letter-spacing .06em` e `max-width: calc(100% - 20px)` |
| 23/10 | No celular o original põe H1 e subtítulo da vitrine em `order: start`, e o sobretítulo novo caía abaixo do H1 | sobretítulo com `order: -100000` (via `:has`) |
| 23/10 | Foto do hero da home cortava o rosto entre 480 e 767 px; cards de professores enormes no tablet | altura da foto `clamp(280px, 60vw, 460px)`; autoridade 4 colunas no desktop, 2×2 no tablet, carrossel de até 300 px no celular |
| 23/10 | **Pré-existente:** vitrine com 37 px de rolagem horizontal em 768 px (grade de 4 colunas não cabe ao lado dos filtros), também na versão original; e 3 px no celular só por causa da barra de rolagem do navegador desktop | não corrigido (fora do escopo); sugerir correção separada (3 colunas no tablet) |
| 23/10 | Formulário HubSpot da home só aparece depois que a seção chega perto da tela (carregamento sob demanda) | comportamento esperado; testes precisam rolar até a seção e esperar alguns segundos |
| 23/10 | **Escopo da camada CSS:** o `black.css` pintava o fundo do `body` de grafite e mudava links/abas/caixas em **todas** as páginas. Nas que não têm versão Black do conteúdo (institucionais, `/especialidades/`, currículos, 404, `/formacao-inicial-usg/`), seção de fundo transparente ficava escuro sobre escuro (visto em `/especialidades/`) | mu-plugin acrescenta `cetrus-black-conteudo` ao `body` só em produto, arquivos/busca de produto e páginas com modelo (`cetrus_black_conteudo_black()`, filtro `cetrus_black_conteudo` para estender); as regras de conteúdo do CSS passaram a exigir essa classe. Globais: tokens, popups HubSpot e barra de cookies. Header, barra e rodapé continuam Black em todo o site (são modelos do Elementor). Snippets 116 e 117 atualizados. |
| 23/10 | Graduação (19379): imagem de fundo azul-marinho no hero com véu de 20% | véu grafite a 88% no Black. O quadro "Inscrições/Vestibular" é uma imagem de conteúdo (wp-image-31680) e continua azul |
| 23/10 | Staging: algumas mídias dão 404 (ex.: foto do hero de fellowship e de intensivos) | problema do ambiente (cópia de ~16/10), igual na versão original; conferir na produção |
| 28/10 | Mesmo depois da sincronização de arquivos, imagens em tamanho reduzido (`-1024x683`, `-1024x512`…) dão 404 no staging, nas versões normal e Black (Pediatria, fellowship materno-fetal, intensivo, preparatório, EAD) | o WP.com gera esses tamanhos sob demanda na CDN da produção (lá até `-777x333` responde 200); no staging privado não. Snippet só de staging 120 troca a URL pelo original quando o tamanho não existe no disco |
| 28/10 | Home: só ~24 px entre o formulário e os números | os números (f4ad350/fa12234) têm `margin-top: -80px` no original para subir sobre o hero; o `montar-black.js` zera margens de cima negativas dentro de f4ad350 no modelo |
| 28/10 | Home Black com rolagem horizontal (2354 px numa janela de 1521) | o select de curso do HubSpot, escondido sob a busca, perdia para `.cbn-hs select.hs-input { width: 100% !important }`; regra do campo oculto com especificidade maior |
| 28/10 | "Quem viu" automático do intensivo com um card sem nome | o card (loop 11204) usa o ACF `v2_miniatura_titulo` como título; `cetrus_black_relacionados()` passou a exigir o campo (chave do transient trocada para `cetrus_black_rel2_`) |
| 28/10 | Banner das calculadoras ausente nos Conteúdos do staging (também na versão normal) | o `cetrus-conteudos-gratuitos.php` do staging é anterior a 27/10; visual Black conferido na produção só no navegador (CSS aplicado localmente, nada gravado) |
| 28/10 | Teste de imagens em aba de segundo plano dava fundos "faltando" nos Conteúdos | o lazy load de fundos do Elementor (`e-lazyloaded`) depende de IntersectionObserver, que não dispara em aba oculta; com a aba visível os fundos carregam. Testar com a aba na frente |
| 28/10 | Três varreduras em paralelo (12 requisições simultâneas por vários minutos) ativaram a checagem anti-robô do WP.com no staging: HTTP 403 "Checking your browser…" para a sessão | varreduras encerradas; ao abrir o wp-admin normalmente o navegador passou pela checagem; URLs afetadas repassadas uma por vez. Rodar **uma** varredura por vez |

## 14. Pendências e bloqueios

**Decisões do usuário registradas em 22/10/2026**

| # | Decisão |
|---|---|
| D1 | Autorizado rodar código no staging (snippets de teste e o mu-plugin como snippet). **A sincronização produção → staging não foi autorizada explicitamente**: não executar sem novo "sim". |
| D2 | Rollback no fim da janela proposta: **01/12/2026 00:00 (Brasília)**, automático pelo mu-plugin. Responsável pela validação pós-rollback ainda não nomeado. |
| D3 | Escopo: **todas as páginas do Artifact** (Home, Busca/vitrine, Produto, Conteúdos gratuitos/material rico) **e todas as páginas de produto** (pós, fellowship, cursos intensivos, pacotes e demais modalidades: EAD, graduação, preparatórios, monitoria). Header, barra de campanha e rodapé entram. |
| D4 | Fonte: "igual ao Artifact ou o mais próximo". Interpretação adotada: **Bw Modelica** (a fonte que o próprio Artifact manda usar em produção; já carregada no site) **com os pesos do Artifact** (300 nos títulos, ênfase forte, BLACK em negrito com degradê), e não a regra 300/500 de 14/10. Montserrat literal fica como alternativa documentada. |
| D5 | **Todos os cursos têm condições exclusivas**: o selo "Condições exclusivas" vale para todos os cards e páginas de produto (sem tag de participação). |
| D6 | Formulário "Receba nossas condições exclusivas" da home: **o mesmo formulário das páginas de curso**, mas o campo "curso de interesse" não vem preenchido: funciona como **busca**, igual ao da LP https://cetrus.com.br/lista-vip-black/. |
| D7 | (28/10) **Ficam iguais à produção**, sem nada de Black: fellowship v2, graduação, arquivo de especialidade, institucional, 404 e páginas sem modelo Black. Implementado em `cetrus_black_tela_fora()` (mu-plugin 0.2.0); os modelos dessas telas não são mais montados. |
| D8 | (28/10) Home: mais respiro entre formulário e números; depoimentos como no Artifact; **newsletter removida**; **sem a etiqueta "Condições exclusivas" nos cards de curso** (Home, Vitrine e demais grades; o selo continua no topo das páginas de produto). "Quem viu" vazio recebe cursos relacionados (só no Black). |
| D9 | (28/10) **Sincronização produção → staging autorizada** pelo usuário, para o staging ficar igual à produção (mídia, conteúdo, mu-plugins); a versão Black é remontada por script depois. |
| D10 | (28/10) **Sem virada automática**: a versão Black é ligada e desligada manualmente por uma pessoa (substitui D2, que previa rollback automático em 01/12 00:00). Branch nova para o PR: `black-november-2026-ativacao-manual`. |
| D11 | (28/10, noite) **Virada pelo GitHub**: a ativação em 01/11 e o rollback em 01/12 são commits manuais (merge + implantação) que mudam a chave `CETRUS_BLACK_VIRADA`; o painel fica para emergência. Substitui o modo do painel como chave principal (D10 continua: nada é automático). |
| D12 | (28/10, noite) Home: números e destaque de conteúdos **idênticos ao Artifact** (textos do Artifact nos números); formulário HubSpot **ed32406c**; H1 de Conteúdos gratuitos em **Montserrat** (como no Artifact; exceção à D4 só nesse H1). |

**Pendências de decisão abertas em 23/10**

| # | Pendência | Padrão adotado enquanto isso |
|---|---|---|
| P1 | H1 da home: atual × frase do Artifact | atual (seção 10) |
| P2 | Janela do material (Conteúdos gratuitos): o Artifact acrescenta a caixa "Quero receber as condições Black" e um painel de sucesso com curso sugerido. Exige campo novo no formulário HubSpot (fora do WordPress) e regra de curso por material | não implementado; só o visual escuro da janela |
| P3 | Hero da home: o Artifact mostra o Dr. Marco Demange, que não tem retrato da série LISTA-VIP | Dr. Feres Chaddad no lugar (retratos: Peralta, Queiroz, Prudente, Chaddad) |
| P4 | Newsletter na home: o Artifact não a mostra | mantida (antes do fechamento), com visual Black |
| P5 | Modelo Black do 404 (14718) | não clonado: a área de conteúdo do 404 fica clara com header/rodapé Black |
| P6 | Sync produção → staging | autorizado em 28/10 (D9): arquivos sincronizados; o banco segue "Sincronizando…" no painel do WP.com (o staging usa o banco de 19/10) |
| P7 | Páginas fora do Artifact (institucionais, `/especialidades/`, `/unidades/`, `/formacao-inicial-usg/`, currículos, 404): só header, barra e rodapé ficam Black; o conteúdo continua o original | assim (seção 13, escopo da camada CSS). Se quiserem essas páginas escuras também, cada uma precisa de modelo próprio |

**Bloqueios técnicos**

0. ~~Criar modelos novos no Elementor do staging~~ — autorizado pelo usuário em 22/10 ("Autorizo criar modelos novos [BLACK-2026] no Elementor do staging (sem editar os originais) e atualizar o snippet 116"). Resolvido.
0b. **H1 da home** (seção 10): decidir entre manter o H1 atual ou usar a frase do Artifact (P1).

1. **Executar código no staging.** (Resolvido por D1 para snippets; ver item 0 para modelos.) Para provar o filtro e testar ativação/rollback é preciso rodar o mu-plugin no staging. Caminhos, do mais simples ao mais "limpo": (a) autorizar explicitamente a criação de um snippet de teste no Code Snippets do staging (só reage a `?cetrus_probe=1` com usuário logado); (b) consertar a conexão Jetpack do staging e conectar as Implantações do staging à branch `projeto/black-november-2026`; (c) SFTP/SSH do staging (credenciais com o usuário).
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

> **Versão atualizada em 28/10:** passo a passo completo, com o `montar-black.js`, em `black-november-2026/RELATORIO-IMPLANTACAO-E-ROLLBACK.md` (seção 2). O texto abaixo é o plano de 22/10; a diferença principal é que os modelos são **montados na produção a partir dos originais da produção** (não importados do staging, que tinha conteúdo velho).

Pré-condições: testes 8–13 aprovados; PR aprovado; baseline final de 30/10 salva.

1. (30/10) Importar na produção os JSON dos clones `[BLACK-2026]` (Modelos > Importar modelos). Conferir títulos `#<id>` **apontando para os ids da produção** (iguais aos do staging para templates anteriores a ~16/10; conferir os demais).
2. Publicar os clones (sem condições). Eles não aparecem para ninguém ainda.
3. Mesclar o PR e acionar a implantação no WP.com. Conferir em Ferramentas > Black November 2026: janela, modo `auto`, vínculos sem alertas.
4. Prévia logada: abrir home, 1 pós, 1 fellowship, 1 intensivo, 1 pacote com `?cetrus_black=1`; rodar o pipeline em modo prévia.
5. 01/11 00:00: a janela abre sozinha. Validar em janela anônima com `?x=<aleatório>` (cache de borda ~5 min).
6. Se algo crítico falhar: modo `desligado` (efeito imediato para logados, até ~5 min para visitantes).

## 18. Plano e procedimento de rollback

> **Versão atualizada em 28/10:** `black-november-2026/RELATORIO-IMPLANTACAO-E-ROLLBACK.md`, seção 3 (níveis automático, emergência, código e limpeza; prova por hash dos originais contra `baseline/originais-prod-20261028.tsv`; `cetrusMontar.desmontar()`).

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
- **Sem automação (D10, 28/10):** ligar e desligar são manuais, em Ferramentas › Black November 2026 (registro de quem virou e quando). Nada está agendado e nada liga ou desliga sozinho; se ninguém desligar, a versão Black continua no ar. Roteiro: `black-november-2026/RELATORIO-IMPLANTACAO-E-ROLLBACK.md`, seções 2 e 3.

## 20. Monitoramento pós-publicação e pós-rollback

- **Primeira hora (01/11):** HTTP 200 nas URLs da baseline (amostra de 30 + todas as de produto à noite), template esperado por URL, H1/title/canonical iguais, erros JS no console, seletores do Lyceum, popups e CTAs, eventos GA4 `MM_click_whatsapp` e envio HubSpot (com bloqueio em teste), Clarity.
- **Diário (02–07/11), depois semanal:** 404 e 5xx (logs do WP.com), Search Console (cobertura, Core Web Vitals), MQLs do site no HubSpot e cadastros no GA4 comparados com a mesma semana de novembro/2025 e com outubro/2026 (sazonalidade da Black; não atribuir variação ao layout sem controle).
- **Pós-rollback:** mesmo checklist; diff da baseline deve voltar a zero nas colunas de template e SEO.

## 21. Histórico de alterações

| Data | Quem | O quê |
|---|---|---|
| 28/10/2026 | Claude (sessão 4, noite) | Pedidos da noite: carrossel, números (home-legado), fellowship e destaque de conteúdos (home-conteudos-destaque) na Home; formulário ed32406c; H1 e fundos de Conteúdos; correção de `css_classes` nos containers (degradê do hero da pós, barra fixa, pertencimento). mu-plugin 0.4.0 com chave pelo GitHub e trava; harness 31 casos; 24 modelos remontados no staging; painel do staging em "seguir o GitHub". Commits 2dd15ad, c1d9a7a, cbf4938 e o de documentação. |
| 28/10/2026 | Claude (sessão 4) | Staging sincronizado (arquivos) com a produção; snippets 118/119 reinstalados por script e 24 modelos remontados; páginas pedidas conferidas (relatório, seção 5). Correções: respiro formulário → números e rolagem horizontal na Home (25a70ad), snippet só de staging para imagens em tamanho reduzido (1464cda, snippet 120), "Quem viu" só com cursos com título de miniatura (ac4ebef). Teste de liga/desliga aprovado; staging deixado desligado. 4ª varredura. |
| 28/10/2026 | Claude (sessão 3) | Pedidos de 28/10: telas iguais à produção (D7), ajustes da Home/Vitrine/Conteúdos (D8), "Quem viu" com cursos relacionados, banner das calculadoras no Black; montagem por script (`montar-black.js`, ensaio na produção 24/24), instalação no staging por script (`staging-snippets.js`), linha de base dos originais (`baseline/originais-prod-20261028.tsv`), relatório de implantação/rollback/riscos; branch publicada no GitHub; `main` mesclada. Staging à espera de login para a sincronização autorizada (D9). |
| 23/10/2026 | Claude (sessão 2, tarde) | Escopo da camada CSS restrito a `body.cetrus-black-conteudo` (mu-plugin + `black.css`, snippets 116/117 atualizados); pacote (aba ativa, seta), graduação (véu do hero); harness 18 casos aprovado; 3ª varredura (438 URLs) aprovada; telas em 390/768 px; documento com seção 0 (retomada). Commits dfd4bcc, 232c5a7, 0445bc9 e seguinte. |
| 23/10/2026 | Claude (sessão 2) | **Staging:** modelos novos `[BLACK-2026] #10946 Home` (35650), `#11768 Vitrine` (35655) e `#27615 Conteúdos Gratuitos` (35661), convertidos dos originais + componentes novos; mu-plugin corrige o shortcode de uma vez por página (snippet 116 = arquivo, SHA `f2f13d56…13f8`); `black.css` ganhou newsletter, vitrine (hero, busca 16821, WP Grid Builder, `.cetrus-inicio-turma`), conteúdos gratuitos (variáveis `--cg-*`, chips, janela) e selo de card no celular, espelhados no snippet 117. Antes disso, na mesma sessão: pós no layout do Artifact (shortcodes `[cetrus_black_selo]` e `[cetrus_black_pertencimento]`), pacote, barra nos headers 20497/20396/23382, selo no loop 35617. Nenhum original editado. |
| 22/10/2026 | Claude (sessão 1) | **Staging (autorizado):** 25 clones `[BLACK-2026]` criados (mapa em `black-november-2026/staging/clones-staging-20261022.tsv`), convertidos para a paleta pelo `black-november-2026/ferramentas/transforma-black.js` a partir dos originais (nenhum original editado); barra de campanha 35623; snippet 116 atualizado (SHA-256 igual ao arquivo local, decisão desde o `init`); snippet 117 = `black.css` impresso só com Black ativa. Troca comprovada (teste 6b); 1ª varredura de 440 URLs sem diferença de SEO. |
| 22/10/2026 | Claude (sessão 1) | **Staging:** snippet 115 "[BLACK-2026][QA] Sonda do filtro" (front-end, só reage a `?cetrus_probe=1` logado; pode ser apagado) e snippet 116 "[BLACK-2026] Camada Black November 2026" (cópia do mu-plugin 0.1.0, ainda sem a troca de páginas). Filtro do Elementor confirmado (teste 6a). mu-plugin ganhou a troca de conteúdo das páginas (harness: 13 casos, MD5 `fed39b40…`). Componente do formulário da home (D6) criado e testado em prévia. Baseline de SEO da produção concluída (456 URLs, todas 200). |
| 22/10/2026 | Claude (sessão 1) | Lint e teste lógico do mu-plugin em PHP-WASM (`tests/qa/black/mu-plugin-harness.js`): aprovado. |
| 22/10/2026 | Claude (sessão 1) | Leitura do Artifact; auditoria do staging e da produção (somente leitura); rastreamento das 432 páginas de produto do staging; branch `projeto/black-november-2026`; script `tests/qa/black/seo-baseline.ps1`; baseline de SEO e de redirecionamentos da produção; rascunho do mu-plugin `cetrus-black-november-2026.php` + `black.css`; este documento. Nenhuma alteração em staging ou produção. Tentativa de criar snippet de teste no staging negada (não executada). |

## 22. Próximas ações priorizadas

Feito até 28/10: arquitetura validada no staging (varredura de 438 URLs), mu-plugin 0.2.0 com telas iguais à produção, montagem por script ensaiada na produção (24/24), relatório de implantação/rollback/riscos.

Feito em 28/10 (sessão 4): staging sincronizado (arquivos), camada reinstalada, modelos remontados, páginas pedidas conferidas e corrigidas, liga/desliga testado, 4ª varredura.

1. Revisão das páginas no staging pelo time (links no relatório; prévia `?cetrus_black=1`, logado).
2. PR `black-november-2026-ativacao-manual` → `main`; depois, o roteiro de `black-november-2026/RELATORIO-IMPLANTACAO-E-ROLLBACK.md`, seção 2.1 (tag, implantação, montagem na produção, prévia com `?cetrus_black=1`, incluindo banner das calculadoras e imagens).
3. Se a sincronização de banco do staging concluir: reinstalar snippets 118/119/120 e remontar (seção 0).
4. Pendências de decisão P1–P7 (seção 14) e responsáveis por ligar, desligar e validar (seção 19).
