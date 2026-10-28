# Black November 2026 · Guia da virada pelo GitHub (01/11) e do rollback (01/12)

> Para quem vai executar. Versão de 28/10/2026 (noite). Repositório: https://github.com/sergiopatrick/cetrus-wordpress
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
| `black-november-2026-ativacao-manual` | código da campanha, **desligado** (merge até 31/10) | https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-ativacao-manual |
| `black-november-2026-virada-0111` | o commit de **01/11** (chave `'ligado'`) | https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-virada-0111 |
| `black-november-2026-rollback-0112` | o commit de **01/12** (chave `'desligado'`) | https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-rollback-0112 |

As três branches estão encadeadas: a de 01/11 nasce da primeira e a de 01/12 nasce da de 01/11. Por isso **o PR da primeira deve entrar com "Create a merge commit"** (não "Squash" nem "Rebase"). Assim, no dia, o PR de 01/11 mostra só 1 commit e o de 01/12 também.

## 1. Preparação (até 31/10, com o site normal no ar)

1. **Tag de segurança** no último commit da `main`: `git tag PRE-BLACK-2026 <sha> && git push origin PRE-BLACK-2026`.
2. **Backup:** conferir o backup do Jetpack do dia no painel do WordPress.com.
3. **PR do código:** abrir o PR `black-november-2026-ativacao-manual` → `main`, revisar e fazer o merge com **"Create a merge commit"**. A chave vem `'desligado'`: o merge não muda nada no site.
4. **Implantar:** WordPress.com › Implantações › implantar a `main` (manual). **A implantação publica a `main` inteira**: confira antes se há outras mudanças nela.
5. **Conferir:** wp-admin › Ferramentas › Black November 2026 deve mostrar "Versão Black para visitantes: desligada" e "Quem decide agora: o GitHub".
6. **Linha de base dos originais** (só leitura), no console de `/wp-admin/edit.php?post_type=elementor_library`:
   ```js
   var RAW = 'https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<sha do merge>/';
   var BASE = RAW + 'black-november-2026/';
   for (const f of ['ferramentas/transforma-black.js', 'ferramentas/montar-black.js']) (0, eval)(await fetch(BASE + f).then(r => r.text()));
   await cetrusMontar.verificarOriginais();   // salvar a saída em baseline/originais-prod-AAAAMMDD.tsv
   ```
7. **Montar os modelos Black** na mesma aba: `await cetrusMontar.executar({ base: BASE, simular: true })` (ensaio: 24 itens sem erro) e depois `await cetrusMontar.executar({ base: BASE })`. Isso cria os 24 modelos `[BLACK-2026]`. Eles ficam inertes enquanto a chave estiver desligada.
8. **Prévia logada** com `?cetrus_black=1`: Home, Vitrine, Conteúdos gratuitos, uma pós, uma fellowship, um intensivo, um pacote, um EAD. Na produção, conferir também o banner das calculadoras em Conteúdos e as imagens em tamanho reduzido, que o staging não mostra. As telas iguais à produção (fellowship v2, graduação, especialidade, institucional, 404) devem sair sem nada de Black.
9. **Congelamento:** não editar os templates e páginas originais até 01/12. Se for preciso editar, rodar o passo 7 de novo depois.

## 2. Virada · 01/11/2026 (horário combinado pelo time)

1. Abrir o PR de 01/11: https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-virada-0111. O diff deve ter **uma linha**: `'desligado'` → `'ligado'`.
2. Fazer o merge na `main`.
3. WordPress.com › Implantações › implantar a `main`.
4. Esperar ~5 min (cache de borda) e validar numa janela anônima, com `?x=<número qualquer>` no fim da URL: Home (formulário, sem enviar), Vitrine, Conteúdos, produtos, WhatsApp e popups. Validar também as telas iguais à produção, sem Black.
5. Conferir em Ferramentas › Black November 2026: "Versão Black para visitantes: LIGADA".
6. **Se algo crítico falhar:** Ferramentas › Black November 2026 › "forçar desligado" › Gravar modo (efeito imediato). Depois, decidir com o time.

**Plano B (se o PR de 01/11 mostrar conflito ou mais de um commit):** na `main`, abrir o arquivo `wp-content/mu-plugins/cetrus-black-november-2026.php` no GitHub › lápis (Edit) › trocar `define( 'CETRUS_BLACK_VIRADA', 'desligado' );` por `define( 'CETRUS_BLACK_VIRADA', 'ligado' );` › commit direto na `main` com a mensagem "Black November 2026: liga a versao Black (01/11)" › implantar. É o mesmo efeito.

## 3. Rollback · 01/12/2026

1. Abrir o PR de 01/12: https://github.com/sergiopatrick/cetrus-wordpress/compare/main...black-november-2026-rollback-0112. O diff deve ter **uma linha**: `'ligado'` → `'desligado'`.
2. Fazer o merge e implantar a `main`.
3. Esperar ~5 min e validar em janela anônima: o site deve estar igual ao de antes da campanha (header, Home, produtos).
4. Ferramentas › Black November 2026 deve mostrar "desligada". Se alguém tinha usado "forçar ligado" ou "forçar desligado", voltar para "seguir o GitHub".
5. **Prova de que voltou tudo:** `await cetrusMontar.verificarOriginais()` e comparar com a linha de base do passo 1.6. Hashes iguais significam originais intocados.

**Plano B:** editar a mesma linha na `main` pelo GitHub, de `'ligado'` para `'desligado'`, e implantar. **Rollback imediato sem GitHub:** "forçar desligado" no painel.

**Limpeza (7 dias depois, com tudo estável, opcional):** `await cetrusMontar.desmontar()` põe os 24 modelos em rascunho, sem apagar. Um PR de remoção pode tirar o arquivo `cetrus-black-november-2026.php` e a pasta `cetrus-black-november-2026/` da `main`.

## 4. O que pode dar errado no dia

| Situação | O que acontece | O que fazer |
|---|---|---|
| Virada implantada antes de montar os modelos | a trava mantém o site normal; Ferramentas mostra o aviso vermelho | rodar o passo 1.7 |
| Alguém deixou o painel em "forçar desligado" | o commit não tem efeito; Ferramentas avisa | escolher "seguir o GitHub" |
| PR da primeira branch entrou com "Squash" | o PR de 01/11 aparece com conflito ou muitos commits | usar o Plano B (editar a linha) |
| Cache de borda | visitantes veem a versão anterior por até ~5 min | esperar; validar com `?x=<número>` |
| A implantação leva outras mudanças da `main` | entra junto o que estiver na `main` | conferir a `main` antes de implantar |
| Template original editado depois da montagem | a versão Black fica com o conteúdo antigo | rodar o passo 1.7 de novo |
