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

### `wp-content/mu-plugins/cetrus-lyceum-ocultar-turmas-tecnicas.php`

Oculta, em **todas** as páginas de curso, as **turmas técnicas de venda** que o
Lyceum mantém para receber matrículas sem turma definida: id terminado em
`.VENDAS` (ex.: `PG_COP1.SP1.VENDAS`), status `ACTIVE`, 100 vagas e data-sentinela
em 2040 (`16/01/2040 a ...`).

Como elas passam por todos os filtros do `integracao-lyceum-main`
(ACTIVE + vagas disponíveis), apareciam no seletor "Selecione a turma" como uma
opção de 2040. O mu-plugin corta esses itens na camada HTTP (filtro
`http_response`), antes de o plugin ler a resposta da `products-api`. Vale para
página de curso, fellowship e AJAX, e sobrevive a atualizações do plugin.

Regra: descarta a turma se o id termina em `.VENDAS` **ou** se `startDate` for de
2035 em diante. Ajustável pelo filtro `cetrus_lyceum_e_turma_tecnica`.

Não altera nada no Lyceum (a turma técnica continua existindo lá e continua
valendo para a operação) e não afeta o `checkout.cetrus.com.br`, que consome a
mesma API por fora do WordPress.

Cursos cuja **única** turma visível era a `.VENDAS` passam a exibir o botão de
**fila de espera** em vez do formulário de inscrição, o correto enquanto não
houver turma real cadastrada no Lyceum.

### Histórico

- `cetrus-turma-eco-fetal-2026.php`: removido em 25/08/2026. Era um override de
  exibição da data da turma de *Ecocardiografia Fetal* (`EC_AEF1`); depois que a
  turma real de out/2026 foi criada no Lyceum, o hack passou a duplicar a opção
  no seletor. A correção de data é sempre no Lyceum.
