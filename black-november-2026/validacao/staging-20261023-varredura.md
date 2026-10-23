# Varredura do staging — 23/10/2026 (3ª, com clones estáveis)

Executada no wp-admin do staging (sessão logada), com `tests/qa/black/varredura-staging.js`
(colado no console depois das ferramentas `window.cb`): cada URL pedida duas vezes,
`?cetrus_black=0` (original) e `?cetrus_black=1` (Black), 4 em paralelo, ~20 min.

**Escopo:** 432 produtos publicados (REST `wp/v2/product`), Home, Vitrine, Conteúdos gratuitos,
busca, `/especialidades/`, 404 e um produto com caminho `/modalidade/…`; depois, repetidos à parte:
busca e os arquivos `/modalidades/pos-graduacao/` e `/modalidades/fellowship/`.

**Comparado por URL:** status HTTP; title; meta description; canonical; robots; og:title; tipos do
JSON-LD do Yoast; H1 (textos, em ordem); H2 (textos, sem ordem, descontados os acréscimos aprovados
da seção 10); classe `cetrus-black-2026` só na versão Black; template esperado = clone
`[BLACK-2026]` do template que o original renderiza; páginas com modelo Black renderizando o modelo.

## Resultado

| Item | Resultado |
|---|---|
| URLs comparadas | 438 (+ 1 não comparável, abaixo) |
| Aprovadas sem ressalva | 436 |
| Status | 437 × 200/200 e 1 × 404/404 (a URL de teste do 404) |
| SEO (title, description, canonical, robots, og:title, JSON-LD, H1) | idêntico em todas |
| Classe Black | presente em todas as versões Black e ausente em todas as originais |
| Troca de template de produto | 12073→35513 (158), 19225→35528 (174), 19062→35525 (29), 32359→35567 (29), 26931→35552 (20), 28008→35555 (14), 28779→35561 (5), 19379→35531 (1), 21114→35543 (1), 34795→35570 (1) |
| Páginas | Home 10946→35650, Vitrine 11768→35655, Conteúdos 27615→35661; `/especialidades/` (24973) sem modelo, continua a original (esperado) |
| Busca e arquivos | 12747→35516; 12753→35519; card 11204→35617; SEO e H1/H2 iguais (repetição) |
| 404 | 14718 nos dois (sem modelo Black; decisão P5) |

**Ressalvas (explicadas, não são defeito):**

1. Home: o template de seção 15396 não aparece na versão Black. Ele estava dentro dos containers
   do hero e da busca antigos (5c98cd0, 317b4e0, d3db8d1), substituídos pelo hero do Artifact.
   H2 da Home: 27 → 29, os 2 acréscimos aprovados.
2. Busca `/?s=ultrassonografia`: na varredura a versão original veio sem resultados (4 H2) e a Black
   com 12 cards (16 H2). Repetida em seguida: 16/16 H2, mesmos templates, SEO igual. Diferença
   transitória do índice de busca, não da camada Black.
3. Produto 31340 (`/cursos/produtividade-de-alta-performance-para-radiologistas/`): redireciona
   para outro domínio nas duas versões (o navegador não segue por CORS). Já tinha falhado em 22/10.
   Redirecionamento pré-existente; conferir na produção se é intencional.
