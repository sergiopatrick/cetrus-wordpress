# Black November 2026 · Relatório do processo, implantação manual e rollback

> Documento de apoio ao `PROJETO_PADRONIZACAO_PAGINAS_CETRUS.md` (raiz do repositório). Versão de 28/10/2026, noite (virada manual por commit no GitHub; passo a passo em [`GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md`](GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md)).
> **Branch para revisão e merge: `black-november-2026-ativacao-manual`** (https://github.com/sergiopatrick/cetrus-wordpress/tree/black-november-2026-ativacao-manual), um commit com o projeto inteiro sobre a `main`. O histórico detalhado fica na branch `projeto/black-november-2026`.

## 1. Como o trabalho foi feito

1. **Referência visual.** O Artifact "Cetrus · Black November 2026 · v2" foi lido como código (artboards Home, Busca, Produto, Material, Tokens). Dele saíram o design system (cores, Bw Modelica, grid, componentes) e a regra de copy (seção 3 do documento central).
2. **Auditoria** por REST do staging e da produção (produção só leitura): WordPress 7.1.3, Elementor 4.3.5 / Pro 4.1.3, Hello Elementor, WooCommerce; categoria de negócio = taxonomia `v2_modalidade`; templates e condições do Theme Builder mapeados; 432 páginas de produto rastreadas para saber que template cada uma usa.
3. **Linhas de base da produção:** SEO de 456 URLs (`baseline/prod-pre-black-20261022.tsv`) e hash do conteúdo do Elementor dos 24 templates/páginas originais que recebem versão Black (`baseline/originais-prod-20261028.tsv`).
4. **Arquitetura (temporária e reversível):**
   - templates e páginas **originais nunca são editados**;
   - cada versão Black é um **modelo novo do Elementor**, `[BLACK-2026] #<id do original>`, sem condições de exibição;
   - o **mu-plugin** `wp-content/mu-plugins/cetrus-black-november-2026.php` troca, na hora de renderizar, o original pelo modelo Black **só quando uma pessoa liga a chave** (desde 28/10 à noite: a linha `CETRUS_BLACK_VIRADA` do próprio arquivo, por commit no GitHub; o painel tem modos de emergência). Nas páginas Home, Vitrine e Conteúdos gratuitos troca só o conteúdo do Elementor, e URL, title, description, canonical e schema do Yoast continuam os da página;
   - o **CSS** da campanha (`cetrus-black-november-2026/black.css`) só entra nas telas com versão Black;
   - produto novo cadastrado durante a campanha cai na condição do original (modalidade, tag) e recebe a versão Black sozinho.
5. **Construção e validação no staging (22–23/10):** transformador de cores por papel (fundo, texto, borda, botão) sem mexer em estrutura, textos, links ou shortcodes; componentes do Artifact versionados em `componentes/`; filtro do Elementor confirmado; varredura original × Black em 438 URLs com SEO idêntico; revisão em 390, 600, 768 px e desktop.
6. **Revisão de 28/10 (pedidos do usuário):**
   - **sem virada automática:** não há data de início nem de fim no código. Ligar e desligar é manual (seção 2);
   - **ficam iguais à produção**, sem nada de Black (nem header, barra ou rodapé): fellowship v2 (tag 1262 → template 28779), graduação (modalidade 149 → 19379), arquivo de especialidade (25011), institucional, 404 e qualquer página sem modelo Black;
   - **Home:** mais respiro entre o formulário e os números; depoimentos no formato do Artifact, com os depoimentos reais da página; newsletter removida; sem a etiqueta "Condições exclusivas" nos cards de curso (também na Vitrine e nas outras grades);
   - **"Quem viu este curso também se interessou por:"**: o carrossel lista os *upsells* do WooCommerce e fica vazio quando o curso não tem nenhum (na produção também). Na versão Black, só nesse caso, entram cursos da mesma especialidade e depois da mesma modalidade;
   - **Conteúdos gratuitos:** o banner das calculadoras (produção desde 27/10) ganhou visual Black;
   - **imagens e cards diferentes no staging:** o staging era uma cópia parcial de ~16/10 (mídia ausente, conteúdo e mu-plugins antigos). Na produção as imagens existem. Resolução: sincronizar produção → staging (autorizada) e remontar a versão Black por script;
   - **montagem por script** (`ferramentas/montar-black.js`): recria os 24 modelos a partir dos originais do site em que roda. É o mesmo procedimento no staging e na produção, e por isso a versão Black nasce do conteúdo atual da produção. Simulação na produção (só leitura) em 28/10: 24 de 24 sem erro.
7. **Validação no staging em 28/10** (depois da sincronização de arquivos): seção 5.
8. **Ajustes de 28/10 à noite e virada pelo GitHub:** seção 7.

## 2. Implantação na produção (ativação MANUAL pelo GitHub)

**Nada liga sozinho.** O código e os modelos são instalados antes, desligados. Em 01/11 uma pessoa faz o merge do commit que liga a chave e implanta; em 01/12 faz o merge do commit que a desliga e implanta. Passo a passo com links: [`GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md`](GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md).

### 2.1 Preparação (até 31/10, com o site normal no ar)

| # | Passo | Como | Volta atrás |
|---|---|---|---|
| 1 | Revisar e aprovar o PR `black-november-2026-ativacao-manual` → `main` | GitHub | fechar o PR |
| 2 | Tag `PRE-BLACK-2026` no último commit da `main` antes do merge | `git tag PRE-BLACK-2026 <sha> && git push origin PRE-BLACK-2026` | — |
| 3 | Confirmar o backup do Jetpack do dia | painel do WP.com | — |
| 4 | Merge do PR com **"Create a merge commit"** (as branches de 01/11 e 01/12 nascem desta); tag `BLACK-NOVEMBER-2026` no commit do merge | GitHub | `git revert` do merge |
| 5 | WP.com › Implantações › Acionar implantação (branch `main`). **Publica todos os mu-plugins da `main`**, não só este | painel do WP.com | implantar o commit anterior |
| 6 | Conferir Ferramentas › Black November 2026: "Versão Black para visitantes: **desligada**" | wp-admin | — |
| 7 | Linha de base dos originais: `await cetrusMontar.verificarOriginais()`; salvar em `baseline/originais-prod-AAAAMMDD.tsv` | console do wp-admin (só leitura) | — |
| 8 | Montar os modelos: `executar({ base, simular: true })` e depois `executar({ base })`. Cria os 24 modelos `[BLACK-2026]` a partir dos originais da produção; ficam inertes enquanto o modo estiver desligado | console de `/wp-admin/edit.php?post_type=elementor_library`, `base` fixada no commit da tag | `cetrusMontar.desmontar()` |
| 9 | Prévia logada com `?cetrus_black=1`: Home, Vitrine, Conteúdos, uma pós, uma fellowship, um intensivo, um pacote, EAD; varredura `tests/qa/black/varredura-staging.js` | navegador logado | — |
| 10 | **Congelamento**: não editar os templates/páginas originais da lista. Se for preciso, rodar o passo 8 de novo depois (refaz a partir do original) | combinado com o time | — |

`base` na produção: `https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<sha da tag BLACK-NOVEMBER-2026>/black-november-2026/`.

### 2.2 Virada · 01/11 (commit no GitHub)

1. Abrir o PR https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-virada-0111. O diff é uma linha: `define( 'CETRUS_BLACK_VIRADA', 'ligado' )`.
2. Merge na `main` e WP.com › Implantações › implantar a `main`.
3. Esperar ~5 min (cache de borda) e validar em janela anônima com `?x=<aleatório>`: Home, Vitrine, Conteúdos, produtos, formulário da Home (sem enviar), WhatsApp, popups; e as telas iguais à produção (fellowship v2, graduação, especialidade, institucional, 404) **sem** Black.
4. Se algo crítico falhar: Ferramentas › Black November 2026 › **forçar desligado** (imediato, sem deploy; seção 3).
5. Plano B se o PR não estiver limpo: editar a linha direto na `main` pelo GitHub (guia, seção 2).

## 3. Rollback (volta à versão anterior sem reconstruir páginas)

**Por que não há reconstrução:** a versão anterior **é** o site original, que continua no banco o tempo todo. Templates, páginas, produtos, condições, Kit e configurações nunca são editados pela campanha. A versão Black é uma camada por cima (modelos separados + troca na renderização), e desligar a camada devolve exatamente o que estava antes.

| Nível | Quando | Como | Efeito | Tempo |
|---|---|---|---|---|
| 1. Fim da campanha (manual, GitHub) | 01/12 | PR https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-rollback-0112 (uma linha: `'desligado'`), merge e implantação da `main` | o filtro devolve os originais; classe e CSS saem | minutos (implantação) + até ~5 min de cache |
| 2. Emergência | a qualquer momento | Ferramentas › Black November 2026 › **forçar desligado** (ou `wp option update cetrus_black_2026_modo desligado`) | igual, por cima do código | imediato; visitantes em até ~5 min (cache) |
| 3. Código | se o mu-plugin der problema | `git revert` do merge + nova implantação, ou remover o arquivo e a pasta `cetrus-black-november-2026/` | some a camada inteira | minutos (deploy manual) |
| 4. Limpeza | 7 dias depois do nível 1, estável | `await cetrusMontar.desmontar()`: os 24 modelos `[BLACK-2026]` viram rascunho (não apaga; base para 2027) | nenhum efeito visível | — |
| Contingência | original alterado por engano | comparar com `baseline/originais-prod-*.tsv`; restaurar pela revisão do Elementor; backup do Jetpack só em último caso (apaga pedidos/leads posteriores) | — | — |

**Prova de que o rollback restaurou tudo:** rodar `cetrusMontar.verificarOriginais()` e comparar com a linha de base (hashes iguais = originais intocados; diferença só onde o time editou, com data). Rodar também a varredura de SEO contra `baseline/prod-pre-black-20261022.tsv`.

**Responsáveis:** definir quem liga, quem desliga e quem valida (seção 19 do documento central). Como nada é automático, **se ninguém desligar, a versão Black continua no ar**. Recomenda-se lembrete na agenda do time para a data de encerramento.

## 4. Problemas que podem acontecer

Os riscos 17 a 20 entraram com a validação de 28/10 (seção 5) e os riscos 21 a 25 com os ajustes de 28/10 à noite (seção 7).

| # | Problema | Probabilidade / impacto | Prevenção / resposta |
|---|---|---|---|
| 1 | Ninguém faz o PR de 01/12 (não há desligamento automático) | média / médio | responsável nomeado + lembrete de agenda; o PR de rollback já está pronto (guia); a tela mostra o estado |
| 2 | Virada feita no horário errado ou por quem não devia | baixa / médio | merge e implantação só por quem tem acesso ao GitHub e ao WP.com; o painel registra usuário e horário das viradas manuais; desfazer = "forçar desligado" |
| 3 | Alguém edita um template original depois da montagem e a versão Black fica com o conteúdo antigo | média / médio | congelamento; rodar `executar` de novo (sempre parte do original) |
| 4 | Home, Vitrine ou Conteúdos mudam de estrutura e um container esperado some | baixa / médio | o script **para aquela página com erro** e não grava; ela fica com a versão original até ajustar o script |
| 5 | Template ou condição nova criada durante a campanha | baixa / baixo | sem modelo Black, a página sai na versão normal; acrescentar à lista do `montar-black.js` e rodar de novo |
| 6 | Cache de borda (~5 min) mostra a versão anterior logo depois da virada | alta / baixo | esperar ou purgar; validar com `?x=<aleatório>` |
| 7 | A implantação publica **toda** a `main` | média / médio | conferir o que está na `main` antes e implantar com o time ciente |
| 8 | Atualização do Elementor/Elementor Pro durante a campanha muda o filtro de templates | baixa / alto | não atualizar durante a campanha; se acontecer, conferir; modo desligado resolve na hora |
| 9 | Alguém renomeia um modelo `[BLACK-2026]` e o vínculo se perde | baixa / baixo | Ferramentas › Black November 2026 lista vínculos e alertas |
| 10 | Edição manual num modelo `[BLACK-2026]` é sobrescrita quando o script roda de novo | média / baixo | ajustes de layout entram no script ou nos componentes, não à mão |
| 11 | Formulário HubSpot da Home: origem do lead diferente da esperada | baixa / médio | mesmo formulário dos cursos (6ed56c62) com UTMs padrão; validar com CRM antes |
| 12 | "Quem viu" automático mostra curso que o comercial não queria destacar | média / baixo | só entra quando o curso não tem upsell; excluídos os ocultos do catálogo; cadastrar upsell resolve |
| 13 | SEO: +2 H2 na Home (e −1 da newsletter), +1 na Vitrine, +1 em Conteúdos | baixa / baixo | H1, title, description, canonical e schema intactos; temporário |
| 14 | Telas iguais à produção dependem dos ids de termo 149 (graduação) e 1262 (fellowship v2) | baixa / baixo | constantes no topo do mu-plugin |
| 15 | Erro de PHP no mu-plugin | muito baixa / alto | lint e 31 casos de teste aprovados (28/10 à noite); resposta: implantação sem o arquivo |
| 16 | Staging desatualizado de novo e validação sem refletir a produção | média / médio | sincronizar produção → staging antes de validar e remontar por script |
| 17 | "Quem viu" automático puxa curso sem `v2_miniatura_titulo` (o card sai sem nome) | baixa / baixo | desde 28/10 a busca exige o campo preenchido (mu-plugin, `cetrus_black_relacionados()`) |
| 18 | Margem negativa dos números da Home (sobem 80 px sobre o hero no original) volta se o original mudar de estrutura | baixa / baixo | o script zera a margem no modelo e avisa no relatório da montagem se não achar a margem |
| 19 | No staging, imagens em tamanho reduzido dão 404 (o WP.com só gera esses tamanhos na CDN da produção) | certa no staging / nenhum na produção | snippet **só de staging** `[STAGING] Imagens no tamanho original` (seção 5); não implantar na produção |
| 20 | A sincronização de banco produção → staging pedida em 28/10 continua "Sincronizando…" no painel do WP.com; se concluir, substitui o banco do staging e apaga modelos e snippets da validação | média / baixo | rodar de novo `staging-snippets.js`, o snippet de imagens e `montar-black.js` (seção 0 do documento central); a produção não é afetada |
| 21 | Commit de virada implantado antes de montar os modelos na produção | baixa / alto | trava no mu-plugin 0.4.0: sem modelo `[BLACK-2026]` publicado a versão Black não liga; a tela mostra aviso vermelho; rodar o `montar-black.js` |
| 22 | Painel deixado em "forçar desligado"/"forçar ligado" e o commit do GitHub sem efeito | baixa / médio | a tela mostra quem decide e um aviso; voltar para "seguir o GitHub" |
| 23 | PR da branch do código entra com "Squash" e o PR de 01/11 aparece com conflito | média / baixo | merge com "Create a merge commit"; plano B: editar a linha da chave na `main` pelo GitHub |
| 24 | Formulário novo da Home (ed32406c): fluxo do CRM diferente do 6ed56c62 ou estilo injetado pelo HubSpot mudar | média / médio | validar com o time de CRM antes de 01/11 (destino dos leads, automações, UTMs); o CSS do componente força altura e espaçamento dos campos |
| 25 | Montserrat no H1 de Conteúdos gratuitos (pedido de 28/10): uma fonte a mais nessa página | baixa / baixo | o Elementor a hospeda no próprio site (sem chamar o Google); some com o rollback |

## 5. Validação no staging (28/10/2026, depois da sincronização)

**Ambiente:**
- staging com os **arquivos** da produção (mídia e mu-plugins copiados pela sincronização) e o **banco de 19/10**. A sincronização só de banco continua "Sincronizando…" no painel (risco 20);
- camada instalada por `staging-snippets.js` (snippets 118 e 119, iguais ao arquivo do GitHub);
- modelos remontados por `montar-black.js`.

Conferido logado, com `?cetrus_black=1` (Black) e `?cetrus_black=0` (normal), em desktop. Ao terminar, o staging ficou **desligado** para visitantes.

| Página | Pedido | Resultado no staging | Correção |
|---|---|---|---|
| Home | mais respiro entre formulário e números | antes sobravam ~24 px: os números têm `margin-top: -80px` no original, para subir sobre o hero; agora **104 px** | `montar-black.js` zera essa margem só no modelo (25a70ad) |
| Home | (achado na validação) | rolagem horizontal: o select de cursos do HubSpot, escondido sob a busca, ficava com a largura da janela; agora sem rolagem | regra mais específica no componente do formulário (25a70ad) |
| Home | depoimentos como no Artifact; sem newsletter; sem etiqueta nos cards | 3 cartões com textos e fotos reais (as fotos carregam); newsletter ausente; 0 etiquetas nos cards do carrossel | — |
| Home | imagens; conteúdos com as imagens da produção | hero, carrossel, autoridade e conteúdos (card Outubro Rosa e 3 artigos) com as mesmas imagens da página original | — |
| Vitrine | sem etiqueta; imagens dos cards iguais à produção | 16 cards com as mesmas imagens da versão normal, sem etiqueta | — |
| Conteúdos gratuitos | imagens e comportamento dos cards iguais à produção | aulas, masterclasses e e-books com as mesmas imagens; ícone de play nos mesmos cards da produção; clique e hover vêm do plugin (o CSS Black só troca cores) | — |
| Conteúdos gratuitos | imagem ao lado de "Conteúdos mais acessados" | aparece | — |
| Conteúdos gratuitos | banner das calculadoras | não aparece no staging, nem na versão normal: o arquivo `cetrus-conteudos-gratuitos.php` do staging é anterior a 27/10. O visual Black foi conferido na página pública da produção, com o `black.css` aplicado só no navegador (nada gravado) | — (aparece na produção) |
| pós US-ME, pós Sanar | imagens | todas carregam; Black e normal iguais | — |
| pós Pediatria, fellowship "mais 4", intensivo, preparatório, EAD | imagem do curso e do hero | faltavam **nas duas versões**: o staging não tem os tamanhos reduzidos (`-1024x683` etc.), que o WP.com só gera sob demanda na CDN da produção (lá até um tamanho inventado responde 200). Agora carregam | snippet **só de staging** 120 `[STAGING] Imagens no tamanho original` (`staging/imagens-tamanho-original.php`, 1464cda) |
| intensivo | imagens dos depoimentos | 11 de 11 carregam | — |
| intensivo | "Quem viu este curso também se interessou por" | normal: vazio (sem upsell). Black: 6 cursos da mesma modalidade, todos com nome | antes saía um card sem nome; a busca passou a exigir `v2_miniatura_titulo` (ac4ebef) |
| EAD | "Quem viu" | 1 curso (o upsell cadastrado) nas duas versões; o complemento só entra quando não há upsell | — |
| fellowship v2, graduação, especialidade, institucional, 404 | iguais à produção | mesmo com `?cetrus_black=1`: sem classe Black, sem modelo `[BLACK-2026]`, sem CSS Black | — |
| Liga/desliga manual | ativação e rollback | Ferramentas › Black November 2026:<br>• **ligado** às 15:24:38: Home, Vitrine e pós em Black **sem parâmetro de prévia**; graduação e fellowship v2 normais;<br>• **desligado** às 15:25:02: tudo normal.<br>As duas viradas ficaram no registro com usuário e horário | — |
| Varredura original × Black | SEO e templates | **442 URLs, nenhuma diferença de SEO** (title, description, canonical, robots, og, JSON-LD, H1): 415 OK; 14 OK com +6 H2 que são só os rótulos dos cards do "Quem viu" automático; 10 telas iguais à produção sem nada de Black; 3 ressalvas já conhecidas (hero antigo da Home, busca, produto 31340 que redireciona). Detalhe: `validacao/staging-20261028-varredura.md` | — |

**Links no staging** (logado; `?cetrus_black=1` mostra a versão Black e `?cetrus_black=0` a normal; o modo para visitantes está desligado):

- Home: https://staging-821d-cetruscombr.wpcomstaging.com/?cetrus_black=1
- Vitrine: https://staging-821d-cetruscombr.wpcomstaging.com/vitrine/?cetrus_black=1
- Conteúdos gratuitos: https://staging-821d-cetruscombr.wpcomstaging.com/conteudos-gratuitos/?cetrus_black=1
- pós US-ME: https://staging-821d-cetruscombr.wpcomstaging.com/cursos/pos-graduacao-lato-sensu-em-ultrassonografia-musculoesqueletica/?cetrus_black=1
- pós Sanar: https://staging-821d-cetruscombr.wpcomstaging.com/cursos/pos-graduacao-em-medicina-endocanabinoide-sanar/?cetrus_black=1
- pós Pediatria: https://staging-821d-cetruscombr.wpcomstaging.com/cursos/pos-graduacao-pediatria-ambulatorial-puericultura/?cetrus_black=1
- fellowship "mais 4": https://staging-821d-cetruscombr.wpcomstaging.com/cursos/fellowship-em-ultrassonografia-obstetrica-materno-fetal-dr-luiz-miguel-miltri/?cetrus_black=1
- intensivo: https://staging-821d-cetruscombr.wpcomstaging.com/cursos/medicina-canabinoide-pratica-clinica-e-prescricao/?cetrus_black=1
- preparatório: https://staging-821d-cetruscombr.wpcomstaging.com/cursos/preparatorio-intensivo-para-a-prova-de-titulo-em-dor/?cetrus_black=1
- EAD: https://staging-821d-cetruscombr.wpcomstaging.com/cursos/ressonancia-magnetica-tumores-musculoesqueleticos/?cetrus_black=1
- Iguais à produção (devem sair sem Black mesmo com o parâmetro): fellowship v2 https://staging-821d-cetruscombr.wpcomstaging.com/cursos/fellowship-em-usg-dermatologico-dra-ana-rossol-b/?cetrus_black=1 · graduação https://staging-821d-cetruscombr.wpcomstaging.com/cursos/graduacao-em-gestao-hospitalar/?cetrus_black=1 · especialidade https://staging-821d-cetruscombr.wpcomstaging.com/especialidades/radiologia-e-diagnostico-por-imagem/?cetrus_black=1 · institucional https://staging-821d-cetruscombr.wpcomstaging.com/institucional/?cetrus_black=1
- Liga/desliga e registro: https://staging-821d-cetruscombr.wpcomstaging.com/wp-admin/tools.php?page=cetrus-black-2026

**Fica para conferir na produção** (passo 9 da seção 2.1): banner das calculadoras e imagens em tamanho reduzido. No staging, as duas diferenças vêm do ambiente, não da camada Black.

## 6. Commits da branch `black-november-2026-ativacao-manual`

| Commit | O quê |
|---|---|
| [0410454](https://github.com/sergiopatrick/cetrus-wordpress/commit/0410454) | camada temporária com ativação manual (mu-plugin 0.3.0, CSS, componentes, scripts, testes, linhas de base) |
| [0d3b1e7](https://github.com/sergiopatrick/cetrus-wordpress/commit/0d3b1e7) | relatório e documento central com ativação manual |
| [2a046a4](https://github.com/sergiopatrick/cetrus-wordpress/commit/2a046a4) | `staging-snippets.js` pagina a lista e desliga snippets antigos |
| [458d419](https://github.com/sergiopatrick/cetrus-wordpress/commit/458d419) | barra de campanha não duplica em header que já tem a faixa |
| [25a70ad](https://github.com/sergiopatrick/cetrus-wordpress/commit/25a70ad) | respiro formulário → números na Home; select oculto não estica a página |
| [1464cda](https://github.com/sergiopatrick/cetrus-wordpress/commit/1464cda) | snippet só de staging para imagens em tamanho reduzido |
| [ac4ebef](https://github.com/sergiopatrick/cetrus-wordpress/commit/ac4ebef) | "Quem viu" automático só com cursos que têm título de miniatura |
| [b33c7fb](https://github.com/sergiopatrick/cetrus-wordpress/commit/b33c7fb) | varredura reconhece os H2 do "Quem viu" automático e aceita lista de URLs |
| [2dd15ad](https://github.com/sergiopatrick/cetrus-wordpress/commit/2dd15ad) | ajustes de 28/10 à noite (Home, Conteúdos, produto) e chave de virada pelo GitHub (mu-plugin 0.4.0) |
| [c1d9a7a](https://github.com/sergiopatrick/cetrus-wordpress/commit/c1d9a7a) | trava: chave ligada sem modelos publicados não liga |
| [cbf4938](https://github.com/sergiopatrick/cetrus-wordpress/commit/cbf4938) | Home: cor do CTA do destaque de conteúdos e altura dos campos do formulário ed32406c |

## 7. Ajustes de 28/10 à noite e virada pelo GitHub

**Pedidos e resultado no staging** (prévia `?cetrus_black=1`, conferido em 28/10 por volta das 17h):

| Página | Pedido | O que foi feito |
|---|---|---|
| Home | fundo do carrossel como no Artifact | seção de destaques em `#121316` com linha superior; o mesmo em "Explore conteúdos gratuitos" |
| Home | números idênticos ao Artifact | componente `home-legado` no lugar dos números originais (f4ad350): título "Há mais de 30 anos construindo um legado na educação médica.", 4 colunas com linha em cima, "+" lavanda e os textos do Artifact. Acrescenta 1 H2 à Home Black |
| Home | contraste da seção "Pós em Formato Fellowship" | seção no grafite base `#0E0F12`; cartão em `#191A1E` com borda; coluna da imagem `#141518` (como no Artifact; a imagem é a mesma do site) |
| Home | "Explore conteúdos gratuitos" sem o modal do Outubro Rosa | componente `home-conteudos-destaque`: cartão que leva para `/conteudos-gratuitos/`, com a imagem, o rótulo, a chamada e o CTA da própria página; os 3 artigos continuam |
| Home | formulário ed32406c | `formId` trocado; mesmo embed v2 (portal 9321751, na1) com `css: ''`; mesmos campos (403 cursos na busca), UTMs padrão; o estilo que o HubSpot injeta nesse formulário foi neutralizado no componente. **Envio não testado** (geraria lead) |
| Conteúdos gratuitos | H1 igual ao Artifact | Montserrat 300, 46 px, entrelinha 1,12, em 2 linhas com "gratuitos do Cetrus" em 600; coluna de texto de ~650 px e imagem de até 520 px. O texto do H1 não mudou (SEO) |
| Conteúdos gratuitos | fundos das seções diferenciados como no Artifact | Como acessar e Blog `#0E0F12`; filtros e Aulas e E-books `#121316`; Lançamentos e Masterclasses `#000000`; Newsletter `#141518`; ponte Black com o degradê dela |
| pós US-ME | degradê cinza no hero | o degradê já existia no CSS, mas a classe não chegava ao container: o `montar-black.js` gravava a classe na chave de widget (`_css_classes`). Corrigido (`css_classes`); isso também devolveu a barra fixa do celular só com "Quero me inscrever" e o invólucro do "Pertencimento" em todas as pós |
| pós US-ME | imagem do Dr. Ronaldo Lins | a foto (`2024/12/cetrus-ronaldo-lins.jpg`) aparece no "Corpo docente" no staging, em 390 px e 1280 px, nas versões Black e normal; na produção ela também existe |

**Virada pelo GitHub (mu-plugin 0.4.0):**
- chave `CETRUS_BLACK_VIRADA` no próprio arquivo;
- branches prontas para 01/11 (`black-november-2026-virada-0111`) e 01/12 (`black-november-2026-rollback-0112`);
- o painel passa a "seguir o GitHub" por padrão, com "forçar desligado" e "forçar ligado" para emergência;
- **trava:** sem modelos publicados, a chave ligada não liga nada.

**Testes:**
- harness com 31 casos (27 OK + 4 informativos), sem falha;
- montagem dos 24 modelos no staging sem erro (1 aviso conhecido: a pós Sanar não tem barra fixa no celular).

Passo a passo do dia: [`GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md`](GUIA-VIRADA-GITHUB-0111-ROLLBACK-0112.md).
