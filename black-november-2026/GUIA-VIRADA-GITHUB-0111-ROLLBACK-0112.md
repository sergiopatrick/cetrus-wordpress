# Black November 2026 · Guia da virada pelo GitHub (01/11) e do rollback (01/12)

> Para quem vai executar. Versão de 29/10/2026 (inclui os pedidos de 29/10: WhatsApp Black e inscrição pelo popup nas páginas de curso). Repositório: https://github.com/sergiopatrick/cetrus-wordpress
> Nada liga ou desliga sozinho: cada passo abaixo é feito por uma pessoa, no GitHub e no painel do WordPress.com.

## Como a chave funciona

O arquivo `wp-content/mu-plugins/cetrus-black-november-2026.php` tem uma linha que liga ou desliga a versão Black:

```php
define( 'CETRUS_BLACK_VIRADA', 'desligado' );   // 01/11: 'ligado'   01/12: 'desligado'
```

- **01/11:** um commit troca `'desligado'` por `'ligado'`. Depois do merge na `main` e da implantação, a versão Black entra no ar.
- **01/12:** um commit volta para `'desligado'`. Depois do merge e da implantação, o site volta a ser exatamente o de antes.
- **Emergência (a qualquer hora, sem GitHub):** wp-admin › Ferramentas › Black November 2026 › "forçar desligado" › Gravar modo. O efeito é imediato e vale por cima do código.
- **Trava:** se a chave estiver `'ligado'` mas os modelos `[BLACK-2026]` ainda não tiverem sido montados no site, a versão Black **não liga** e a tela de Ferramentas mostra o aviso.

| Branch | Para quê | Abrir o PR |
|---|---|---|
| Ordem | PR | O que faz | Abrir o PR |
|---|---|---|---|
| **1º** | `black-november-2026-ativacao-manual` → `main` | todo o código da campanha, **desligado**, com todos os pedidos (inclusive WhatsApp Black e inscrição pelo popup nos cursos, de 29/10) | https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-ativacao-manual?expand=1 |
| **2º** | `black-november-2026-virada-0111` → `main` | liga a Black: só a chave (commit `08424dd`) | https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-virada-0111?expand=1 |
| 01/12 | `black-november-2026-rollback-0112` → `main` | desliga a Black (commit `fdd7412`) | https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-rollback-0112?expand=1 |

As três branches estão encadeadas: a de 01/11 nasce da primeira e a de 01/12 nasce da de 01/11. Por isso **o PR da primeira deve entrar com "Create a merge commit"** (não "Squash" nem "Rebase"). Assim, no dia, o PR de 01/11 mostra só 1 commit e o de 01/12 também.

> **Atenção: o 2º PR sozinho não basta.** A branch `virada-0111` foi criada em 28/10 e não tem os pedidos de 29/10 (WhatsApp Black, formulário de inscrição nas páginas de curso, formulário da Home). Eles só entram pelo 1º PR, que precisa ter o merge antes do 2º.

## 1. Antes da virada (31/10 ou no próprio 01/11, com o site normal no ar)

Pode ser feito na véspera ou no dia, antes da seção 2. Feito no dia, ele também serve de ensaio: a Black fica visível só para quem está logado.

1. **Conferir a `main`:** a implantação publica a `main` inteira. Ver se entrou algo além da Black: https://github.com/sergiopatrick/cetrus-wordpress/commits/main
2. **Tag de segurança** no último commit da `main`: `git tag PRE-BLACK-2026 <sha> && git push origin PRE-BLACK-2026`. Sem terminal: GitHub › Releases › Draft a new release › tag `PRE-BLACK-2026` na `main`.
3. **Backup:** conferir o backup do Jetpack do dia no painel do WordPress.com.
4. **1º PR, o do código:** abrir https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-ativacao-manual?expand=1 › Create pull request › merge com **"Create a merge commit"**. A chave vem `'desligado'`: o merge não muda nada no site.
5. **Implantar:** WordPress.com › Implantações (https://my.wordpress.com/sites/cetrus.com.br/deployments) › implantar a `main`.
6. **Conferir:** wp-admin › Ferramentas › Black November 2026 deve mostrar "Versão Black para visitantes: desligada" e "Quem decide agora: o GitHub". O site para visitantes continua igual, com o WhatsApp verde.
7. **Linha de base dos originais** (só leitura), no console de https://cetrus.com.br/wp-admin/edit.php?post_type=elementor_library:
   ```js
   var RAW = 'https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/main/';
   var BASE = RAW + 'black-november-2026/';
   for (const f of ['ferramentas/transforma-black.js', 'ferramentas/montar-black.js']) (0, eval)(await fetch(BASE + f).then(r => r.text()));
   await cetrusMontar.verificarOriginais();   // salvar a saída em baseline/originais-prod-AAAAMMDD.tsv
   ```
   Se der erro logo depois do merge (404 ou `SyntaxError`), é o cache do GitHub: esperar 5 minutos e rodar de novo.
8. **Montar os modelos Black** na mesma aba: `await cetrusMontar.executar({ base: BASE, simular: true })` (ensaio: 24 itens sem erro) e depois `await cetrusMontar.executar({ base: BASE })`. Isso cria os 24 modelos `[BLACK-2026]`, que ficam inertes enquanto a chave estiver desligada. É esta montagem que acerta o formulário dentro do popup de inscrição dos cursos.
9. **Prévia logada** com `?cetrus_black=1` (só quem está logado vê):
   - Home: botão flutuante de WhatsApp em grafite com aro lavanda; formulário (sem enviar).
   - Vitrine, Conteúdos gratuitos, uma pós, uma fellowship, um intensivo, um pacote, um EAD.
   - **Inscrição:** numa pós, numa fellowship e num intensivo, escolher unidade e turma, clicar em "Quero me inscrever" e ver o formulário abrir (sem enviar).
   - Banner das calculadoras em Conteúdos e imagens em tamanho reduzido (o staging não mostra).
   - Telas iguais à produção (fellowship v2, graduação, especialidade, institucional, 404) sem nada de Black.
10. **Congelamento:** não editar os templates e páginas originais até 01/12. Se for preciso editar, rodar o passo 8 de novo depois.

## 2. Virada · 01/11/2026 (horário combinado pelo time)

1. **2º PR, o da virada:** abrir https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-virada-0111?expand=1 › Create pull request. Conferir: **1 commit** (`08424dd`) e **uma linha** no diff (`'desligado'` → `'ligado'`). Se mostrar mais de um commit: com o 1º PR ainda aberto, voltar à seção 1; se o 1º PR já entrou (por "Squash" ou "Rebase"), usar o Plano B abaixo.
2. Fazer o merge na `main`.
3. WordPress.com › Implantações › implantar a `main`.
4. Esperar ~5 min (cache de borda) e validar numa janela anônima, com `?x=<número qualquer>` no fim da URL:
   - Home: versão Black, botão flutuante de WhatsApp em grafite com aro lavanda (abre o WhatsApp do comercial), formulário (sem enviar).
   - Vitrine, Conteúdos, produtos e popups.
   - Numa pós, escolher unidade e turma e clicar em "Quero me inscrever": o formulário tem que abrir (sem enviar).
   - Telas iguais à produção, sem Black.
5. Conferir em Ferramentas › Black November 2026: "Versão Black para visitantes: LIGADA".
6. **Se algo crítico falhar:** Ferramentas › Black November 2026 › "forçar desligado" › Gravar modo (efeito imediato). Depois, decidir com o time.

**Plano B (com o 1º PR já na `main`, se o PR de 01/11 mostrar conflito ou mais de um commit):** na `main`, abrir o arquivo `wp-content/mu-plugins/cetrus-black-november-2026.php` no GitHub › lápis (Edit) › trocar `define( 'CETRUS_BLACK_VIRADA', 'desligado' );` por `define( 'CETRUS_BLACK_VIRADA', 'ligado' );` › commit direto na `main` com a mensagem "Black November 2026: liga a versao Black (01/11)" › implantar. É o mesmo efeito.

## 3. Rollback · 01/12/2026

1. Abrir o PR de 01/12: https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-rollback-0112. O diff deve ter **uma linha**: `'ligado'` → `'desligado'`.
2. Fazer o merge e implantar a `main`.
3. Esperar ~5 min e validar em janela anônima: o site deve estar igual ao de antes da campanha (header, Home, produtos; o WhatsApp flutuante da Home volta ao verde).
4. Ferramentas › Black November 2026 deve mostrar "desligada". Se alguém tinha usado "forçar ligado" ou "forçar desligado", voltar para "seguir o GitHub".
5. **Prova de que voltou tudo:** `await cetrusMontar.verificarOriginais()` e comparar com a linha de base do passo 1.7. Hashes iguais significam originais intocados.

**Plano B:** editar a mesma linha na `main` pelo GitHub, de `'ligado'` para `'desligado'`, e implantar. **Rollback imediato sem GitHub:** "forçar desligado" no painel.

**Limpeza (7 dias depois, com tudo estável, opcional):** `await cetrusMontar.desmontar()` põe os 24 modelos em rascunho, sem apagar. Um PR de remoção pode tirar o arquivo `cetrus-black-november-2026.php` e a pasta `cetrus-black-november-2026/` da `main`.

## 4. O que pode dar errado no dia

| Situação | O que acontece | O que fazer |
|---|---|---|
| Virada implantada antes de montar os modelos | a trava mantém o site normal; Ferramentas mostra o aviso vermelho | rodar o passo 1.8 |
| Só o PR da virada entrou, sem o do código | a Black liga com o código de 28/10: WhatsApp verde e "Quero me inscrever" sem abrir o formulário nos cursos | fazer o merge do 1º PR e implantar de novo |
| Alguém deixou o painel em "forçar desligado" | o commit não tem efeito; Ferramentas avisa | escolher "seguir o GitHub" |
| PR da primeira branch entrou com "Squash" | o PR de 01/11 aparece com conflito ou muitos commits | usar o Plano B (editar a linha) |
| Cache de borda | visitantes veem a versão anterior por até ~5 min | esperar; validar com `?x=<número>` |
| A implantação leva outras mudanças da `main` | entra junto o que estiver na `main` | conferir a `main` antes de implantar |
| Template original editado depois da montagem | a versão Black fica com o conteúdo antigo | rodar o passo 1.8 de novo |
