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

### `wp-content/mu-plugins/cetrus-lyceum-vagas-esgotadas.php` (27/11/2026)

**Turmas com 100% das vagas ocupadas** continuam no seletor "Selecione a turma",
com **" - Vagas esgotadas"** ao lado da data, e não podem ser escolhidas. Antes, o
`Lyceum_CourseProcessor` do `integracao-lyceum-main` descartava a turma lotada e ela
sumia da página.

- **Regra:** turma `ACTIVE` com `seats.amount > 0` e `seats.sold >= seats.amount`
  (inclui vendida acima da capacidade). Capacidade zerada continua escondida, como antes.
  Ajustável pelo filtro `cetrus_lyceum_turma_esgotada`.
- **Onde:** no `plugins_loaded`, o processador `COURSE_` do registro do plugin vira um
  envelope do próprio `Lyceum_CourseProcessor`; só a cópia em memória da resposta é
  ajustada para a turma lotada passar pelo filtro de vagas, e as vagas reais voltam no
  resultado (`lyceum_course_vars.turmas[].esgotada`). As demais regras do plugin
  (course.id, `MV_`, `FE_`, status) continuam valendo. Nada muda no Lyceum nem no cache.
- **Página:** opção `disabled` + selo **VAGAS ESGOTADAS** (tokens de alerta do Dendê)
  abaixo dos seletores. O botão "Quero me inscrever" não abre o pop-up se a turma
  escolhida estiver esgotada ou se todas as turmas (da unidade ou do curso) estiverem;
  com o curso inteiro esgotado, o botão passa a dizer "Vagas esgotadas".
- **Envio:** o formulário HubSpot do pop-up só segue depois de confirmar a turma do
  campo `contact__ct__class_site` **ao vivo** em `POST /wp-json/cetrus/v1/turma-vagas`
  (consulta a products-api sem ler o cache de 15 min do
  `cetrus-lyceum-api-cache.php`, que ganhou `cetrus_lyapi_leitura_fresca()` na 1.1.0).
  Se a API falhar, vale o dado da página (turma com vaga nunca é travada por erro de rede).
- **Limite de datas por curso** (`cetrus_vagas_limites`, filtro
  `cetrus_lyceum_limite_turmas`): Pós Intervenção em Dor (`PG_DOR2`, produto 12588),
  **UNIDADE SÃO PAULO = 3** turmas com vaga mais próximas; esgotada anterior continua
  visível; o resto sai. Validado com o comercial em 27/11/2026: 14/03, 23/05 e 04/07/2027
  selecionáveis + 02/02/2027 esgotada; 05/09/2027 sai. É automático: quando uma turma
  começa, a próxima entra.
- **Fellowship (`FE_`)** não tem seletor de datas: a página mostra só a próxima turma
  com vaga e, sem nenhuma, a fila de espera. Isso não mudou.
- **Medição:** `dataLayer` `inscricao_bloqueada_vagas_esgotadas` {curso, turma,
  motivo: turma|unidade|todas, etapa: selecao|botao|formulario}.
- **Limite conhecido:** o lead vai do navegador direto para o HubSpot; um envio forjado
  direto na API do HubSpot não passa pelo WordPress.
- **Teste:** `tests/qa/lyceum/vagas-esgotadas-harness.js` (PHP 8.3 em WebAssembly com as
  classes reais do plugin). Reverter = apagar o arquivo (o plugin volta a esconder as lotadas).

### `wp-content/mu-plugins/cetrus-carrossel-turmas.php`

Monta o carrossel **"Cursos em destaque no mês"** da home (widget `df02ba9`):
primeiro os **fixos** da curadoria do comercial, na ordem pedida, depois a regra
viva de turmas (janela de dias, ocupação, vagas) até completar o `total` de cards.
A configuração fica na option `cetrus_carrossel`, no banco, e não no código.

**Colocar cursos na frente sem SSH:** acrescente uma entrada em
`CETRUS_CARR_MIGRACOES` com uma chave nova e os códigos de curso, faça o merge e
dispare o deploy em WordPress.com › Implantações. No primeiro request que chega ao
PHP depois do deploy, os códigos vão para a frente de `fixos`, uma única vez, e o
resto da lista continua atrás como estava. A home mostra a nova ordem em até
~5 min (TTL do edge cache). Para conferir, rode `python3 tests/qa/carrossel.py`.

- Reverter o PR **não** desfaz a mudança, porque a lista fica gravada na option.
  Para desfazer, use WP-CLI com o `antes` guardado na option
  `cetrus_carrossel_mig_<chave>`, ou faça um deploy novo que regrave `fixos`.
- Com SSH, `wp cetrus-carrossel status | fixos | vetados | total` continua sendo o
  caminho direto.

**Cards do mesmo tamanho (09/11/2026):** o plugin também carrega, só na home, um
CSS que deixa todos os cards do carrossel com a altura do mais alto. Antes, o card
de curso sem turma com data no Lyceum (sem a linha "começa em X dias") ficava mais
baixo. Agora o espaço da data fica vazio e o coordenador e o local descem para o
pé do card, na mesma linha dos outros. Isso vale no desktop, no tablet e no
celular, com o card normal (modelo 11204) e com o da Black (35969), e não depende
da regra viva estar ligada.

### `wp-content/mu-plugins/cetrus-lyceum-sync.php` › cards das vitrines

Além de sincronizar as turmas, o plugin carrega o CSS do card de curso. Desde
09/11/2026 esse CSS também deixa **todos os cards de uma vitrine com a mesma
altura**: a do card mais alto da página que está na tela. Vale para a Vitrine
(`/vitrine/`), a busca e os arquivos de modalidade (`/modalidades/…`) e de
especialidade (`/especialidades/…`). A regra se prende ao card
(`.cetrus-card-product`), não ao id de cada grade. Por isso continua valendo ao
filtrar, ordenar ou paginar pelo WP Grid Builder, e em qualquer grade nova que
use esse card. No card mais baixo, o espaço que sobra fica entre o título e o
coordenador.

### `wp-content/mu-plugins/cetrus-cursos-fm-cidade.php`

Corrige a localização exibida nas páginas de curso cujo **SKU** (`_sku`) ou
**Código do Curso** (ACF `v2_codigo_do_curso`) começa com `FM`. Hoje são 9
produtos: 5 em Fortaleza e 4 em Manaus. A turma deles no Lyceum vem com
`physicalUnit` "UNIDADE SÃO PAULO", e o seletor "Selecione a unidade" anunciava
São Paulo.

Nesses cursos, e só neles:

- o seletor de unidade some (sem ocupar espaço). A unidade única já vem
  escolhida e o seletor de turma vem liberado, então o fluxo "Quero me inscrever"
  e o popup HubSpot continuam iguais, inclusive os campos
  `contact__ct__unit_site` e `contact__ct__class_site`;
- logo abaixo de **Duração** aparece **Localização: Cidade - UF** (ex.:
  `Fortaleza - CE`), no card do desktop e na aba "Informações Básicas" do mobile,
  com o mesmo estilo da linha de duração.

A localização vem do metabox **Cidades** (taxonomia `v2_cidade`) do produto. A
cidade é o termo marcado e a UF é o estado acima dela na hierarquia (Fortaleza
fica dentro de Ceará). Se a cidade estiver solta no topo da taxonomia, a UF vem
do estado marcado no próprio produto. Um estado marcado que não é a UF de
nenhuma cidade marcada aparece como outro local, pelo nome (São Paulo e Rio de
Janeiro saem como `São Paulo - SP` e `Rio de Janeiro - RJ`, porque o termo do
estado é usado para a capital); sem estado em lugar nenhum, aparece só a cidade.
Uma cidade pendurada no estado errado herda a UF errada (hoje, Imperatriz está
dentro de Manaus): a correção é mudar o pai do termo em Produtos › Cidades.

Para mudar a localização de um curso, basta editar esse metabox, sem precisar
de deploy. Um produto FM novo entra sozinho, desde que tenha a cidade marcada e
use um template de produto com o shortcode `[show-duracao]`.

Se o JavaScript não rodar, o seletor de unidade volta a aparecer e o formulário
fica como era antes, nunca travado.

### `wp-content/mu-plugins/cetrus-whatsapp-flutuante.php`

Botão flutuante de WhatsApp da equipe comercial, **só na home**, no canto
inferior direito: círculo verde `#25D366` de 56 px no desktop e 52 px até
1024 px, com o mesmo ícone do botão "Fale Conosco" do cabeçalho. Abre
`api.whatsapp.com/send/` em nova aba, com a mensagem pré-preenchida. Nas outras
páginas o arquivo não imprime nada (nem CSS, nem JS).

- **Número e mensagem:** constantes `CETRUS_WA_TELEFONE` e `CETRUS_WA_MENSAGEM`
  no topo do arquivo (ou o filtro `cetrus_wa_config`). A mensagem vai em texto
  puro; a codificação da URL é feita pelo código. Mudou, faça merge e deploy.
- **Outras páginas:** filtro `cetrus_wa_exibir` (ex.: `__return_true` para o
  site todo).
- **Rastreamento:** nenhum código novo. O acionador de clique do GTM para links
  `https://api.whatsapp.com/` já dispara `MM_click_whatsapp` (GA4
  `G-640TX0LEC1` e Pixel da Meta), uma vez por clique. O link tem
  `id="cetrus-whatsapp-flutuante"` para separar o botão dos outros links de
  WhatsApp (Click ID no GTM; `link_id` no evento `click` das medições otimizadas).
- **Vizinhos:** fica no mesmo eixo do "Subir ao início" da home e um JS curto o
  empilha acima dele, do banner de cookies do HubSpot e dos pop-ups do HubSpot
  que estiverem na mesma coluna (por `transform`, sem CLS). Se algo cobrir mais
  da metade de baixo da tela, o botão some até aquilo fechar.

### Histórico

- `cetrus-turma-eco-fetal-2026.php`: removido em 25/08/2026. Era um override de
  exibição da data da turma de *Ecocardiografia Fetal* (`EC_AEF1`); depois que a
  turma real de out/2026 foi criada no Lyceum, o hack passou a duplicar a opção
  no seletor. A correção de data é sempre no Lyceum.
