# Varredura original × Black no staging — 28/10/2026 (4ª rodada)

**Ambiente:**
- staging com os arquivos da produção sincronizados em 28/10 e o banco de 19/10;
- snippets 118 (mu-plugin) e 119 (CSS) iguais ao commit `ac4ebef`, mais o snippet 120 `[STAGING] Imagens no tamanho original`;
- 24 modelos `[BLACK-2026]` publicados e 4 em rascunho (telas iguais à produção);
- modo **desligado**, comparação feita com `?cetrus_black=0` × `?cetrus_black=1`, logado.

**Script:** `tests/qa/black/varredura-staging.js`.

## Como rodou

1. Uma frente com a versão `ac4ebef` do script, percorrendo a lista em ordem (10 páginas fixas + 432 produtos).
2. Uma segunda frente com a versão `b33c7fb`, que reconhece os H2 do "Quem viu" automático e aceita `window.__valUrls`. Ela percorreu os 432 produtos de trás para frente. As duas se encontraram e cobriram todas as URLs. Os resultados guardados das duas frentes não têm nenhuma resposta diferente de 200, exceto o 404 proposital.
3. Com três varreduras em paralelo, o WP.com ativou a checagem anti-robô ("Checking your browser…", HTTP 403) para a sessão. As frentes foram encerradas. Depois de abrir o wp-admin normalmente, as 15 URLs pendentes foram repassadas **uma por vez**, com pausa entre as requisições.
   - **Lição:** rodar **uma** varredura por vez.

## Resultado (442 URLs)

| Resultado | URLs | Detalhe |
|---|---|---|
| OK | 415 | status, title, description, canonical, robots, og:title, tipos do JSON-LD, H1 e H2 iguais; classe Black presente; todos os templates trocados pelo modelo certo |
| OK, "Quem viu" automático | 14 | tudo igual e **+6 H2**: são os rótulos de modalidade ("Cursos Intensivos", "Pós-Graduação"…) dos cards que o "Quem viu" vazio ganha só no Black (D8). Nenhum H2 do original some. Ex.: intensivo de canabinoide, terapias peptídicas, medicina do esporte, fellowships de endoscopia, ecocardiografia e cirurgia de pé |
| OK, igual à produção | 10 | fellowship v2, graduação, especialidades, institucional, 404 etc.: sem classe Black e sem nenhum modelo `[BLACK-2026]`, mesmo com `?cetrus_black=1` |
| Home: "falta" o template 15396 | 1 | esperado: é o hero antigo, substituído pelo hero do Artifact (igual à rodada de 23/10) |
| Busca `/?s=ultrassonografia`: H2 4/4 com conteúdo diferente | 1 | mesmo número de H2; diferença transitória dos resultados de busca, já vista em 23/10 |
| Produto 31340 (`produtividade-de-alta-performance-para-radiologistas`) | 1 | redireciona para fora do site nas duas versões (igual a 23/10) |

**Conclusão:** nenhuma diferença de SEO entre o original e a versão Black. As únicas diferenças de H2 são acréscimos aprovados: Home e "Quem viu" automático.
