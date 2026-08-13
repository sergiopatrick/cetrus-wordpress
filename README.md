# cetrus-wordpress

Versionamento e deploy seletivo do **código próprio da Sanar** no site
[cetrus.com.br](https://cetrus.com.br) (hospedado no WordPress.com / Atomic),
via GitHub → WordPress.com Deployments.

Espelha o mesmo modelo já usado no `sanarmed-wordpress`.

## Como o deploy funciona

- Modo de implantação: **Avançado**. Diretório de destino: **`/`** (raiz do servidor).
- O workflow [`.github/workflows/wpcom.yml`](.github/workflows/wpcom.yml) empacota
  **apenas** `wp-content/mu-plugins/` no artefato chamado `wpcom`.
- O WordPress.com consome esse artefato e faz **merge** do conteúdo em
  `/srv/htdocs/wp-content/...`. Nada fora do que o workflow copia é tocado — um
  deploy nunca sobrescreve plugins de terceiros nem o tema.
- **Implantações automáticas ficam desligadas**: cada deploy é disparado
  manualmente (WordPress.com › Implantações, ou `workflow_dispatch`).

## Conteúdo atual

### `wp-content/mu-plugins/cetrus-turma-eco-fetal-2026.php`

Correção **temporária e apenas de exibição** da data da turma do curso
*Atualização em Ecocardiografia Fetal* (produto WooCommerce `16187`):
`25/11/2027 a 27/11/2027` → `07/10/2026 a 09/10/2026`.

A data no site vem do **Lyceum** (`source: "lyceum"`, curso `EC_AEF1`). A fonte da
verdade é o Lyceum — este mu-plugin é um paliativo enquanto a turma não é ajustada lá.
É seguro porque a inscrição do site é só um formulário de lead (nome/e-mail/mensagem);
o código da turma não é enviado em nenhuma matrícula.

**Remover este arquivo** (e refazer o deploy) assim que a turma for corrigida no Lyceum.
