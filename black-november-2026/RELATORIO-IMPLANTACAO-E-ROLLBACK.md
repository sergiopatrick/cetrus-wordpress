# Black November 2026 · Relatório do processo, implantação manual e rollback

> Documento de apoio ao `PROJETO_PADRONIZACAO_PAGINAS_CETRUS.md` (raiz do repositório). Versão de 28/10/2026 (ativação manual).
> **Branch para revisão e merge: `black-november-2026-ativacao-manual`** (https://github.com/sergiopatrick/cetrus-wordpress/tree/black-november-2026-ativacao-manual), um commit com o projeto inteiro sobre a `main`. O histórico detalhado fica na branch `projeto/black-november-2026`.

## 1. Como o trabalho foi feito

1. **Referência visual.** O Artifact "Cetrus · Black November 2026 · v2" foi lido como código (artboards Home, Busca, Produto, Material, Tokens). Dele saíram o design system (cores, Bw Modelica, grid, componentes) e a regra de copy (seção 3 do documento central).
2. **Auditoria** por REST do staging e da produção (produção só leitura): WordPress 7.1.3, Elementor 4.3.5 / Pro 4.1.3, Hello Elementor, WooCommerce; categoria de negócio = taxonomia `v2_modalidade`; templates e condições do Theme Builder mapeados; 432 páginas de produto rastreadas para saber que template cada uma usa.
3. **Linhas de base da produção:** SEO de 456 URLs (`baseline/prod-pre-black-20261022.tsv`) e hash do conteúdo do Elementor dos 24 templates/páginas originais que recebem versão Black (`baseline/originais-prod-20261028.tsv`).
4. **Arquitetura (temporária e reversível):**
   - templates e páginas **originais nunca são editados**;
   - cada versão Black é um **modelo novo do Elementor**, `[BLACK-2026] #<id do original>`, sem condições de exibição;
   - o **mu-plugin** `wp-content/mu-plugins/cetrus-black-november-2026.php` troca, na hora de renderizar, o original pelo modelo Black **só quando uma pessoa liga o modo "ligado"**. Nas páginas Home, Vitrine e Conteúdos gratuitos troca só o conteúdo do Elementor, e URL, title, description, canonical e schema do Yoast continuam os da página;
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

## 2. Implantação na produção (ativação MANUAL)

**Nada liga sozinho.** O código e os modelos são instalados antes, desligados, e uma pessoa liga a versão Black quando a campanha começar. Ela também a desliga quando a campanha acabar.

### 2.1 Preparação (até 31/10, com o site normal no ar)

| # | Passo | Como | Volta atrás |
|---|---|---|---|
| 1 | Revisar e aprovar o PR `black-november-2026-ativacao-manual` → `main` | GitHub | fechar o PR |
| 2 | Tag `PRE-BLACK-2026` no último commit da `main` antes do merge | `git tag PRE-BLACK-2026 <sha> && git push origin PRE-BLACK-2026` | — |
| 3 | Confirmar o backup do Jetpack do dia | painel do WP.com | — |
| 4 | Merge do PR; tag `BLACK-NOVEMBER-2026` no commit do merge | GitHub | `git revert` do merge |
| 5 | WP.com › Implantações › Acionar implantação (branch `main`). **Publica todos os mu-plugins da `main`**, não só este | painel do WP.com | implantar o commit anterior |
| 6 | Conferir Ferramentas › Black November 2026: "Versão Black para visitantes: **desligada**" | wp-admin | — |
| 7 | Linha de base dos originais: `await cetrusMontar.verificarOriginais()`; salvar em `baseline/originais-prod-AAAAMMDD.tsv` | console do wp-admin (só leitura) | — |
| 8 | Montar os modelos: `executar({ base, simular: true })` e depois `executar({ base })`. Cria os 24 modelos `[BLACK-2026]` a partir dos originais da produção; ficam inertes enquanto o modo estiver desligado | console de `/wp-admin/edit.php?post_type=elementor_library`, `base` fixada no commit da tag | `cetrusMontar.desmontar()` |
| 9 | Prévia logada com `?cetrus_black=1`: Home, Vitrine, Conteúdos, uma pós, uma fellowship, um intensivo, um pacote, EAD; varredura `tests/qa/black/varredura-staging.js` | navegador logado | — |
| 10 | **Congelamento**: não editar os templates/páginas originais da lista. Se for preciso, rodar o passo 8 de novo depois (refaz a partir do original) | combinado com o time | — |

`base` na produção: `https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<sha da tag BLACK-NOVEMBER-2026>/black-november-2026/`.

### 2.2 Virada (dia e hora decididos pelo time, ex.: 01/11)

1. A pessoa responsável entra no wp-admin da produção (administrador).
2. Ferramentas › Black November 2026 › modo **ligado** › Gravar modo. Fica registrado quem virou e quando.
   - Alternativa por WP-CLI: `wp option update cetrus_black_2026_modo ligado`.
3. Esperar ~5 min (cache de borda) e validar em janela anônima com `?x=<aleatório>`: Home, Vitrine, Conteúdos, produtos, formulário da Home (sem enviar), WhatsApp, popups; e as telas iguais à produção (fellowship v2, graduação, especialidade, institucional, 404) **sem** Black.
4. Se algo crítico falhar: modo **desligado** (seção 3).

## 3. Rollback (volta à versão anterior sem reconstruir páginas)

**Por que não há reconstrução:** a versão anterior **é** o site original, que continua no banco o tempo todo. Templates, páginas, produtos, condições, Kit e configurações nunca são editados pela campanha. A versão Black é uma camada por cima (modelos separados + troca na renderização), e desligar a camada devolve exatamente o que estava antes.

| Nível | Quando | Como | Efeito | Tempo |
|---|---|---|---|---|
| 1. Fim da campanha (manual) | data combinada (ex.: 01/12) | Ferramentas › Black November 2026 › modo **desligado** (ou `wp option update cetrus_black_2026_modo desligado`) | o filtro devolve os originais; classe e CSS saem | imediato; visitantes em até ~5 min (cache) |
| 2. Emergência | a qualquer momento | igual ao nível 1 | igual | igual |
| 3. Código | se o mu-plugin der problema | `git revert` do merge + nova implantação, ou remover o arquivo e a pasta `cetrus-black-november-2026/` | some a camada inteira | minutos (deploy manual) |
| 4. Limpeza | 7 dias depois do nível 1, estável | `await cetrusMontar.desmontar()`: os 24 modelos `[BLACK-2026]` viram rascunho (não apaga; base para 2027) | nenhum efeito visível | — |
| Contingência | original alterado por engano | comparar com `baseline/originais-prod-*.tsv`; restaurar pela revisão do Elementor; backup do Jetpack só em último caso (apaga pedidos/leads posteriores) | — | — |

**Prova de que o rollback restaurou tudo:** rodar `cetrusMontar.verificarOriginais()` e comparar com a linha de base (hashes iguais = originais intocados; diferença só onde o time editou, com data). Rodar também a varredura de SEO contra `baseline/prod-pre-black-20261022.tsv`.

**Responsáveis:** definir quem liga, quem desliga e quem valida (seção 19 do documento central). Como nada é automático, **se ninguém desligar, a versão Black continua no ar**. Recomenda-se lembrete na agenda do time para a data de encerramento.

## 4. Problemas que podem acontecer

Os riscos 17 a 20 entraram com a validação de 28/10 (seção 5).

| # | Problema | Probabilidade / impacto | Prevenção / resposta |
|---|---|---|---|
| 1 | Ninguém desliga na data combinada (não há desligamento automático) | média / médio | responsável nomeado + lembrete de agenda; a tela mostra o estado e o registro das viradas |
| 2 | Virada feita no horário errado ou por quem não devia | baixa / médio | só administrador; registro com usuário e horário; desfazer é o mesmo botão |
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
| 15 | Erro de PHP no mu-plugin | muito baixa / alto | lint e 24 casos de teste aprovados; resposta: implantação sem o arquivo |
| 16 | Staging desatualizado de novo e validação sem refletir a produção | média / médio | sincronizar produção → staging antes de validar e remontar por script |
| 17 | "Quem viu" automático puxa curso sem `v2_miniatura_titulo` (o card sai sem nome) | baixa / baixo | desde 28/10 a busca exige o campo preenchido (mu-plugin, `cetrus_black_relacionados()`) |
| 18 | Margem negativa dos números da Home (sobem 80 px sobre o hero no original) volta se o original mudar de estrutura | baixa / baixo | o script zera a margem no modelo e avisa no relatório da montagem se não achar a margem |
| 19 | No staging, imagens em tamanho reduzido dão 404 (o WP.com só gera esses tamanhos na CDN da produção) | certa no staging / nenhum na produção | snippet **só de staging** `[STAGING] Imagens no tamanho original` (seção 5); não implantar na produção |
| 20 | A sincronização de banco produção → staging pedida em 28/10 continua "Sincronizando…" no painel do WP.com; se concluir, substitui o banco do staging e apaga modelos e snippets da validação | média / baixo | rodar de novo `staging-snippets.js`, o snippet de imagens e `montar-black.js` (seção 0 do documento central); a produção não é afetada |

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
