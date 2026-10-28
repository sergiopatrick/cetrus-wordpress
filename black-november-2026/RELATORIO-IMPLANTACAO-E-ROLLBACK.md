# Black November 2026 · Relatório do processo, implantação de 01/11 e rollback

> Documento de apoio ao `PROJETO_PADRONIZACAO_PAGINAS_CETRUS.md` (raiz do repositório). Escrito em 28/10/2026.
> Branch: `projeto/black-november-2026` (GitHub: https://github.com/sergiopatrick/cetrus-wordpress/tree/projeto/black-november-2026).

## 1. Como o trabalho foi feito (relatório)

1. **Referência visual.** O Artifact "Cetrus · Black November 2026 · v2" foi lido como código (artboards Home, Busca, Produto, Material, Tokens). Dele saíram o design system (cores, tipografia Bw Modelica, grid, componentes) e a regra de copy. Seção 3 do documento central.
2. **Auditoria.** Leitura por REST do staging e da produção (somente leitura na produção): WordPress 7.1.3, Elementor 4.3.5 / Pro 4.1.3, Hello Elementor, WooCommerce; produto = `product`, categoria de negócio = taxonomia `v2_modalidade`; templates e condições do Theme Builder mapeados (seção 5); 432 páginas de produto rastreadas para saber que template cada uma usa.
3. **Linha de base de SEO da produção** (22/10): 456 URLs com status, title, description, canonical, robots, H1/H2, JSON-LD, templates (`baseline/prod-pre-black-20261022.tsv`).
4. **Arquitetura escolhida (temporária e reversível):**
   - os templates e páginas **originais nunca são editados**;
   - cada versão Black é um **modelo novo do Elementor** com título `[BLACK-2026] #<id do original>`, sem condições de exibição;
   - o **mu-plugin** `wp-content/mu-plugins/cetrus-black-november-2026.php` troca, na hora de renderizar, o template original pelo modelo Black **só dentro da janela** 01/11/2026 00:00 → 01/12/2026 00:00 (Brasília); nas páginas Home, Vitrine e Conteúdos gratuitos troca só o conteúdo do Elementor (URL, title, description, canonical e schema do Yoast continuam os da página);
   - o **CSS** da campanha (`cetrus-black-november-2026/black.css`) só é carregado nas telas com versão Black;
   - produto novo cadastrado em novembro cai na condição do original (modalidade, tag…) e recebe a versão Black sozinho.
5. **Construção no staging** (22–23/10): modelos convertidos para a paleta grafite por um transformador (`ferramentas/transforma-black.js`) que troca cores por papel (fundo, texto, borda, botão) sem mexer em estrutura, textos, links ou shortcodes; peças novas do Artifact como componentes HTML versionados (`componentes/`).
6. **Validação no staging:** filtro do Elementor confirmado; teste do mu-plugin em PHP 8.3 (WebAssembly) com stubs (`tests/qa/black/mu-plugin-harness.js`); varredura original × Black em 438 URLs com SEO idêntico (`validacao/staging-20261023-varredura.md`); revisão em 390, 600, 768 px e desktop.
7. **Revisão de 28/10 (pedidos do usuário):**
   - ficam **iguais à produção**, sem nada de Black (nem header, barra ou rodapé): fellowship v2 (tag 1262 → template 28779), graduação (modalidade 149 → 19379), arquivo de especialidade (25011), institucional, 404 e qualquer página sem modelo Black (`cetrus_black_tela_fora()` no mu-plugin);
   - Home: mais respiro entre o formulário e os números; depoimentos no formato do Artifact (cartões lado a lado, carrossel no celular) com os depoimentos reais da página; newsletter removida; sem a etiqueta "Condições exclusivas" nos cards de curso (também na Vitrine e nas demais grades);
   - "Quem viu este curso também se interessou por:": o carrossel lista os *upsells* do WooCommerce e fica vazio quando o curso não tem nenhum (na produção também); na versão Black, só nesse caso, entram cursos da mesma especialidade e depois da mesma modalidade;
   - imagens faltando no staging: o staging era uma cópia parcial de ~16/10 (mídia ausente, conteúdo e mu-plugins antigos). **Resolução autorizada: sincronizar produção → staging** e remontar a versão Black por script;
   - **montagem por script** (`ferramentas/montar-black.js`): recria os 24 modelos a partir dos originais do site em que roda. É o mesmo procedimento no staging e na produção, e por isso a versão Black nasce do conteúdo atual da produção.

## 2. O que vai para a produção e como (implantação de 01/11/2026)

**Não há commit nem ação manual à meia-noite.** Tudo é instalado antes, inerte, e a versão Black liga sozinha às 00:00 de 01/11 pela janela do mu-plugin.

| Quando | Passo | Quem/como | Volta atrás |
|---|---|---|---|
| até 30/10 | Revisão e aprovação do PR `projeto/black-november-2026` → `main` | GitHub | fechar o PR |
| 30/10 | Tag `PRE-BLACK-2026` no último commit da `main` antes do merge | `git tag PRE-BLACK-2026 <sha> && git push origin PRE-BLACK-2026` | — |
| 30/10 | Confirmar backup do Jetpack do dia (Atividade/Backup do WP.com) | painel WP.com | — |
| 30/10 | Linha de base dos originais: `await cetrusMontar.verificarOriginais()` no wp-admin da produção; salvar em `baseline/originais-prod-AAAAMMDD.tsv` | console (somente leitura) | — |
| 30/10 | Merge do PR; tag `BLACK-NOVEMBER-2026` no commit do merge | GitHub | `git revert` do merge |
| 30/10 | WP.com › Implantações › Acionar implantação (branch `main`) | painel WP.com. **Atenção: publica todos os mu-plugins da `main`**, não só este | nova implantação do commit anterior |
| 30/10 | Conferir Ferramentas › Black November 2026: janela 01/11 00:00 → 01/12 00:00, modo `auto`, "Versão Black para visitantes: desligada" | wp-admin | — |
| 30–31/10 | `await cetrusMontar.executar({ base, simular: true })` (ensaio) e depois `executar({ base })`: cria os 24 modelos `[BLACK-2026]` a partir dos originais da produção. Inertes até a janela | console do wp-admin, `base` fixada no commit da tag | `cetrusMontar.desmontar()` (vira rascunho) |
| 31/10 | Prévia logada: Home, Vitrine, Conteúdos, 1 pós, 1 fellowship, 1 intensivo, 1 pacote, EAD com `?cetrus_black=1`; varredura `tests/qa/black/varredura-staging.js` adaptada à produção | navegador logado | — |
| 31/10 | **Congelamento**: não editar os templates originais da lista; se for preciso, rodar `executar` de novo depois (refaz a partir do original) | combinado com o time | — |
| 01/11 00:00 | A janela abre sozinha | mu-plugin | modo `desligado` |
| 01/11 00:05+ | Validar em janela anônima com `?x=<aleatório>` (cache de borda ~5 min): Home, Vitrine, Conteúdos, produtos, formulário da Home (sem enviar), WhatsApp, popups | navegador anônimo | modo `desligado` |

`base` do script na produção: `https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<sha da tag BLACK-NOVEMBER-2026>/black-november-2026/`. Fixar no commit garante que o que roda é exatamente o que foi revisado.

## 3. Estratégia de rollback (volta à versão anterior sem reconstruir páginas)

**Por que não há reconstrução:** a versão anterior **é** o próprio site original, que continua no banco o tempo todo. Templates, páginas, produtos, condições, Kit e configurações nunca são editados pela campanha. A versão Black é uma camada por cima (modelos separados + troca na renderização), e desligar a camada devolve exatamente o que estava antes.

| Nível | Como | Efeito | Tempo |
|---|---|---|---|
| 1. Automático | Fim da janela (`CETRUS_BLACK_FIM` = 01/12/2026 00:00) | o filtro devolve os originais; classe e CSS saem | imediato; visitantes em até ~5 min (cache) |
| 2. Emergência, sem deploy | Ferramentas › Black November 2026 › modo **desligado** (ou `wp option update cetrus_black_2026_modo desligado`) | igual ao nível 1, a qualquer momento | idem |
| 3. Código | `git revert` do merge + nova implantação, ou remover o mu-plugin e a pasta `cetrus-black-november-2026/` | some a camada inteira | minutos (deploy manual) |
| 4. Limpeza (após 7 dias estável) | `await cetrusMontar.desmontar()` → os 24 modelos `[BLACK-2026]` viram rascunho (não apaga; base para 2027) | nenhum efeito visível | — |
| Contingência | Original alterado por engano em novembro: revisão do Elementor ou `baseline/originais-prod-*.tsv` para identificar; restaurar backup do Jetpack só em último caso (apaga pedidos/leads/produtos posteriores) | — | — |

**Prova de que o rollback restaurou tudo:** depois do rollback, rodar `cetrusMontar.verificarOriginais()` e comparar com a linha de base: hashes iguais significam originais intocados (diferença só onde houve edição legítima do time, com data de alteração). Rodar também a varredura de SEO contra `baseline/prod-pre-black-20261022.tsv`.

**Teste do rollback:** executado no staging com tempo medido (seção 12 do documento central, teste 13), alternando os modos ligado → desligado → auto.

## 4. Problemas que podem acontecer (e o que fazer)

| # | Problema | Probabilidade / impacto | Prevenção / resposta |
|---|---|---|---|
| 1 | Alguém edita um template original depois da montagem (ex.: muda a Home em 31/10) e a versão Black fica com o conteúdo antigo | média / médio | congelamento em 31/10; rodar `executar` de novo (idempotente, sempre parte do original) |
| 2 | A Home, Vitrine ou Conteúdos mudam de estrutura e um container esperado some | baixa / médio | o script **para aquela página com erro** e não grava; a página fica com a versão original até ajustar o script |
| 3 | Template ou condição nova criada em novembro (ex.: novo template de produto) | baixa / baixo | sem modelo Black, a página sai na versão normal; acrescentar à lista do `montar-black.js` e rodar de novo |
| 4 | Cache de borda (~5 min) mostra a versão anterior logo depois de 00:00 e depois do rollback | alta / baixo | esperar ou purgar; validar com `?x=<aleatório>` |
| 5 | A implantação do WP.com publica **toda** a `main`, não só este projeto | média / médio | conferir o que está na `main` antes; implantar com o time ciente |
| 6 | Atualização do Elementor/Elementor Pro em novembro muda o filtro de templates | baixa / alto | não atualizar durante a campanha; se acontecer, conferir; modo `desligado` resolve na hora |
| 7 | Alguém renomeia um modelo `[BLACK-2026]` e o vínculo com o original se perde | baixa / baixo | a tela Ferramentas › Black November 2026 lista vínculos e alertas |
| 8 | Edição manual num modelo `[BLACK-2026]` é sobrescrita quando o script roda de novo | média / baixo | ajustes de layout entram no script (passos/componentes), não à mão |
| 9 | Formulário HubSpot da Home: lead com origem diferente da esperada | baixa / médio | mesmo formulário dos cursos (6ed56c62) com UTMs padrão; validar com o time de CRM antes |
| 10 | "Quem viu" automático (cursos da mesma especialidade) mostra curso que o comercial não queria destacar | média / baixo | só entra quando o curso não tem upsell; excluídos os ocultos do catálogo; cadastrar upsell no curso resolve |
| 11 | SEO: acréscimo de H2 (2 na Home, 1 na Vitrine, 1 em Conteúdos) | baixa / baixo | H1, title, description, canonical e schema intactos; temporário; trocar os H2 por parágrafo se preferirem |
| 12 | Desempenho: CSS extra (~25 KB) e retratos no hero da Home | baixa / baixo | só a 1ª foto com prioridade; medir Lighthouse na prévia |
| 13 | Telas excluídas dependem dos ids de termo 149 (graduação) e 1262 (fellowship v2) | baixa / baixo | se mudarem, a tela passa a receber Black; constantes no topo do mu-plugin |
| 14 | Erro de PHP no mu-plugin derruba páginas | muito baixa / alto | lint e 23 casos de teste aprovados; em produção o arquivo é o mesmo testado; resposta: nova implantação sem o arquivo |
| 15 | Staging volta a ficar desatualizado (cópia antiga) e a validação não reflete a produção | média / médio | sincronizar produção → staging antes de validar; a versão Black é remontada por script |
