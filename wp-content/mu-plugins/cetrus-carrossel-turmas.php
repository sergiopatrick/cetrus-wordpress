<?php
/**
 * Plugin Name: Cetrus - Carrossel dirigido por turma
 * Description: Substitui a selecao manual do carrossel da home por consulta viva as metas do Lyceum (janela de dias e ocupacao).
 * Version:     1.2.0
 * Author:      Cetrus / Sanar
 *
 * REGRA (aprovada em 28/08/2026)
 * Janela de 30 a 75 dias para o inicio, ocupacao abaixo de 60%, turma com pelo menos 3 vagas.
 * A janela pedida originalmente era 45 a 60 dias; medida contra 1.613 turmas reais com a data
 * de referencia deslizando 180 dias, ela ZERA em 05/11/2026 e fica abaixo de 8 cursos em 3 de
 * 18 semanas. A janela de 30 a 75 nunca cai abaixo de 23 e mantem os dois criterios do cliente.
 *
 * A OCUPACAO E CORTE, NAO DESEMPATE. Uma versao anterior deste plano usava ocupacao apenas para
 * ordenar; com teto de 11 cards o desempate nunca chegava a rodar e 7 dos 11 visiveis apareciam
 * com turma acima de 60% ocupada, o oposto do que foi pedido.
 *
 * COMO ENTRA NA QUERY
 * O widget e um loop-carousel com _skin=product, e nessa skin o controle Query ID NAO EXISTE
 * (products-trait.php:107 lista query_id em 'exclude'), entao elementor/query/{id} nunca dispara.
 * O caminho e o filtro elementor/query/query_args identificando pelo id do widget, em prioridade
 * ACIMA de 10: o modulo WooCommerce (module.php:1499) reconstroi os args em 10 e preserva apenas
 * posts_per_page, offset e paged.
 *
 * CURADORIA DO COMERCIAL (1.1.0, 15/09/2026)
 * A opcao 'fixos' aceita CODIGO DE CURSO (ex "PG_HIST") alem de ID de produto, e a ordem da lista
 * e a ordem no carrossel. Codigo e a forma preferida: e o vocabulario do comercial, sobrevive a
 * troca de produto e dispensa alguem ir catar ID no wp-admin. Os fixos entram na frente e ignoram
 * os cortes de janela/ocupacao/vaga minima - o proposito de um destaque e justamente furar a regra.
 * A regra viva continua valendo para as vagas restantes, entao o carrossel nunca fica curto se um
 * codigo for despublicado.
 *
 * VETO (1.2.0, 21/09/2026)
 * A opcao 'vetados' e o contrario de 'fixos': tira um curso do bloco ORGANICO sem mexer na regra.
 * Existe porque "some com esse card" nao tem resposta na 1.1.0: fixar os outros nao expulsa quem
 * entrou pela janela de dias, e baixar o 'total' derruba junto quem ninguem pediu para tirar.
 * Nao toca nos fixos: se alguem colocar o mesmo codigo nas duas listas, a curadoria vence e o
 * status avisa, porque veto silencioso em cima de destaque pedido pelo comercial e armadilha.
 * O corte e por ID e por codigo de curso, igual ao dedup dos fixos, senao um clone com o mesmo
 * codigo e outro ID reentra pela porta dos fundos.
 */

if (!defined('ABSPATH')) exit;

define('CETRUS_CARR_WIDGET',  'df02ba9');   // "Cursos em destaque no mes", home 10946
define('CETRUS_CARR_OPT',     'cetrus_carrossel');
define('CETRUS_CARR_MINIMO',  11);          // piso da cascata de fallback do bloco organico
define('CETRUS_CARR_TOTAL',   11);          // quantos cards o carrossel renderiza (padrao)
define('CETRUS_CARR_SEM_DATA', 253370764800);

function cetrus_carr_config() {
    return wp_parse_args((array) get_option(CETRUS_CARR_OPT, []), [
        'enabled'        => 0,          // 0 = mantem a selecao manual
        'dias_min'       => 30,
        'dias_max'       => 75,
        'ocupacao_max'   => 60,
        'turma_minima'   => 3,
        'cota_fellowship'=> 4,          // no maximo 4 dos 11, para nao virar vitrine de nicho
        'fixos'          => [],         // codigos de curso ou IDs, na ordem, sempre na frente
        'vetados'        => [],         // codigos de curso ou IDs barrados no bloco organico
        'total'          => CETRUS_CARR_TOTAL,
    ]);
}

function cetrus_carr_ativo() {
    $c = cetrus_carr_config();
    return !empty($c['enabled']) || isset($_GET['cetrus_carrossel']);
}

/**
 * Busca produtos por janela de dias, com os demais cortes.
 * Devolve array de ['id','inicio','ocup','livres','curso','fellowship'] ordenado por inicio ASC.
 */
function cetrus_carr_candidatos($dias_min, $dias_max, $ocup_max, $turma_min) {
    $agora = time();
    $de    = $agora + ($dias_min * DAY_IN_SECONDS);
    $ate   = $agora + ($dias_max * DAY_IN_SECONDS);

    $ids = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => '_lyceum_data_inicio',
                'value'   => [$de, $ate],
                'type'    => 'NUMERIC',
                'compare' => 'BETWEEN',
            ],
            [
                'key'     => '_lyceum_ocupacao_pct',
                'value'   => (int) $ocup_max,
                'type'    => 'NUMERIC',
                'compare' => '<',
            ],
            [
                'key'     => '_lyceum_turma_total',
                'value'   => (int) $turma_min,
                'type'    => 'NUMERIC',
                'compare' => '>=',
            ],
        ],
    ]);

    $out = [];
    foreach ($ids as $id) {
        // guarda contra sincronismo parado: dado com mais de 72h nao entra
        $ts = (int) get_post_meta($id, '_lyceum_sync_ts', true);
        if (!$ts || ($agora - $ts) > 72 * HOUR_IN_SECONDS) continue;

        $inicio = (int) get_post_meta($id, '_lyceum_data_inicio', true);
        if ($inicio >= CETRUS_CARR_SEM_DATA) continue;

        $out[] = [
            'id'         => $id,
            'inicio'     => $inicio,
            'ocup'       => (int) get_post_meta($id, '_lyceum_ocupacao_pct', true),
            'livres'     => (int) get_post_meta($id, '_lyceum_vagas_livres', true),
            'curso'      => (string) get_post_meta($id, '_lyceum_curso_id', true),
            'fellowship' => (bool) get_post_meta($id, 'is_fellowship', true),
        ];
    }
    usort($out, fn($a, $b) => $a['inicio'] <=> $b['inicio']);
    return $out;
}

/**
 * Desduplica por codigo de curso. Ha produtos clonados com o mesmo codigo
 * (FE_USD3 em 3 produtos, FE_DEB2 em 3, FE_USD2 e FE_OBS7 em 2); sem isto o
 * mesmo curso apareceria duas ou tres vezes seguidas no carrossel.
 */
function cetrus_carr_dedup($lista) {
    $vistos = []; $out = [];
    foreach ($lista as $x) {
        $chave = $x['curso'] !== '' ? $x['curso'] : ('id:' . $x['id']);
        if (isset($vistos[$chave])) continue;
        $vistos[$chave] = true;
        $out[] = $x;
    }
    return $out;
}

/** Aplica a cota de Fellowship, preservando a ordem. */
function cetrus_carr_cota($lista, $cota) {
    if ($cota <= 0) return $lista;
    $n = 0; $out = [];
    foreach ($lista as $x) {
        if ($x['fellowship']) {
            if ($n >= $cota) continue;
            $n++;
        }
        $out[] = $x;
    }
    return $out;
}

/**
 * Resolve a curadoria manual em IDs de produto, preservando a ordem pedida.
 *
 * Cada entrada e um ID de produto (numerico) ou um CODIGO DE CURSO (_lyceum_curso_id,
 * ex "PG_HIST"). Aceita tambem a grafia com espaco ("PG HIST"), porque o comercial copia
 * de planilha: ha 65 produtos com codigo fora do padrao e a lista chega das duas formas.
 *
 * O casamento e EXATO por _lyceum_curso_id. NUNCA por mnemonico ou substring: 17 mnemonicos
 * colidem no catalogo (ECO1 aparece em 4 produtos, alem de HIST, ERGO, DOR1...) e casar por
 * sufixo traz o curso errado para a home. Quando dois produtos publicados dividem o mesmo
 * codigo (clones tipo FE_USD3), fica o de menor ID, que e deterministico entre execucoes.
 *
 * Devolve ['ids'=>[], 'cursos'=>[], 'ausentes'=>[]]. 'ausentes' alimenta o aviso do WP-CLI:
 * um codigo digitado errado some em silencio, e silencio aqui vira reuniao perdida depois.
 */
function cetrus_carr_resolver_fixos($fixos) {
    $entradas = [];
    foreach ((array) $fixos as $f) {
        if (is_int($f) || is_numeric($f)) { $entradas[] = (int) $f; continue; }
        // "pg dor2" e "PG_DOR2" sao a mesma coisa: normaliza a entrada para a forma
        // canonica com underscore, e a consulta abaixo procura as duas grafias no banco
        $f = preg_replace('/\s+/', '_', strtoupper(trim((string) $f)));
        if ($f !== '') $entradas[] = $f;
    }
    if (!$entradas) return ['ids' => [], 'cursos' => [], 'ausentes' => []];

    // uma consulta so para todos os codigos, nas duas grafias
    $codigos = array_values(array_filter($entradas, 'is_string'));
    $mapa = [];
    if ($codigos) {
        $busca = [];
        foreach ($codigos as $cod) {
            $busca[] = $cod;
            $busca[] = str_replace('_', ' ', $cod);
        }
        $busca = array_values(array_unique($busca));

        global $wpdb;
        $ph = implode(',', array_fill(0, count($busca), '%s'));
        $linhas = $wpdb->get_results($wpdb->prepare(
            "SELECT m.meta_value AS curso, MIN(p.ID) AS id
               FROM {$wpdb->postmeta} m
               JOIN {$wpdb->posts} p ON p.ID = m.post_id
              WHERE m.meta_key = '_lyceum_curso_id'
                AND m.meta_value IN ($ph)
                AND p.post_type = 'product'
                AND p.post_status = 'publish'
           GROUP BY m.meta_value",
            $busca
        ));
        foreach ($linhas as $l) {
            $mapa[strtoupper(str_replace(' ', '_', $l->curso))] = (int) $l->id;
        }
    }

    $ids = []; $cursos = []; $ausentes = [];
    foreach ($entradas as $e) {
        if (is_int($e)) {
            if (get_post_type($e) !== 'product' || get_post_status($e) !== 'publish') {
                $ausentes[] = (string) $e;
                continue;
            }
            $id = $e;
            $curso = (string) get_post_meta($id, '_lyceum_curso_id', true);
        } else {
            if (!isset($mapa[$e])) { $ausentes[] = $e; continue; }
            $id    = $mapa[$e];
            $curso = $e;
        }
        if (in_array($id, $ids, true)) continue;   // PG_MFE1 aparece nas duas prioridades
        $ids[] = $id;
        if ($curso !== '') $cursos[] = strtoupper(str_replace(' ', '_', $curso));
    }

    return ['ids' => $ids, 'cursos' => array_values(array_unique($cursos)), 'ausentes' => $ausentes];
}

/**
 * Mesma leitura de entrada do resolver de fixos, mas TOLERANTE: um curso vetado pode estar
 * despublicado, e nesse caso o veto ja esta cumprido e nao ha nada a avisar. Por isso o codigo
 * que nao resolve para produto vivo vira corte por codigo, e nao 'ausente'.
 */
function cetrus_carr_resolver_vetados($vetados) {
    $r = cetrus_carr_resolver_fixos($vetados);
    $cursos = $r['cursos'];
    foreach ((array) $r['ausentes'] as $a) {
        $a = preg_replace('/\s+/', '_', strtoupper(trim((string) $a)));
        if ($a !== '' && !is_numeric($a) && !in_array($a, $cursos, true)) $cursos[] = $a;
    }
    return ['ids' => $r['ids'], 'cursos' => $cursos];
}

/**
 * Monta a lista final: curadoria do comercial na frente, regra viva no que sobra.
 * Devolve ['ids','origem','fixos','organicos','ausentes'].
 */
function cetrus_carr_montar($com_fixos = true) {
    $c   = cetrus_carr_config();
    $min = CETRUS_CARR_MINIMO;

    $fix = $com_fixos
        ? cetrus_carr_resolver_fixos($c['fixos'])
        : ['ids' => [], 'cursos' => [], 'ausentes' => []];

    $tentativas = [
        ['estrita',       $c['dias_min'], $c['dias_max'], $c['ocupacao_max'], $c['turma_minima']],
        ['alargada',      15,             120,            $c['ocupacao_max'], $c['turma_minima']],
        ['sem_ocupacao',  15,             120,            101,                $c['turma_minima']],
    ];

    $melhor = []; $origem = 'manual';
    foreach ($tentativas as [$nome, $dmin, $dmax, $omax, $tmin]) {
        $lista = cetrus_carr_cota(cetrus_carr_dedup(cetrus_carr_candidatos($dmin, $dmax, $omax, $tmin)), $c['cota_fellowship']);
        if (count($lista) > count($melhor)) { $melhor = $lista; $origem = $nome; }
        if (count($lista) >= $min) { $melhor = $lista; $origem = $nome; break; }
    }

    /*
     * Tira do bloco organico o que ja esta fixado. O corte por ID nao basta: PG_GERP e PG_ALP2
     * entram hoje pela regra viva, e um clone com o MESMO codigo de curso e outro ID passaria,
     * repetindo o curso em dois cards. O carrossel dedup por curso, e a curadoria tambem.
     */
    if ($fix['ids']) {
        $melhor = array_values(array_filter($melhor, function ($x) use ($fix) {
            if (in_array($x['id'], $fix['ids'], true)) return false;
            return !($x['curso'] !== '' && in_array($x['curso'], $fix['cursos'], true));
        }));
    }

    /*
     * Veto: tira do bloco organico o que o comercial pediu para sumir. Roda DEPOIS do corte
     * dos fixos e nunca sobre eles, entao codigo nas duas listas continua aparecendo (o status
     * avisa). Corta por ID e por codigo, pelo mesmo motivo do dedup acima.
     */
    $vet = cetrus_carr_resolver_vetados($c['vetados']);
    if ($melhor && ($vet['ids'] || $vet['cursos'])) {
        $melhor = array_values(array_filter($melhor, function ($x) use ($vet) {
            if (in_array($x['id'], $vet['ids'], true)) return false;
            return !($x['curso'] !== '' && in_array($x['curso'], $vet['cursos'], true));
        }));
    }

    $organicos = array_column($melhor, 'id');
    $ids       = array_merge($fix['ids'], $organicos);

    // registra quando o fallback disparou, em option (nunca em arquivo de log)
    update_option('cetrus_carrossel_estado', [
        'quando'     => time(),
        'origem'     => $origem,
        'total'      => count($ids),
        'fixos'      => count($fix['ids']),
        'organicos'  => count($organicos),
        'ausentes'   => $fix['ausentes'],
        'suficiente' => count($ids) >= (int) $c['total'],
    ], false);

    return [
        'ids'       => $ids,
        'origem'    => $origem,
        'fixos'     => $fix['ids'],
        'organicos' => $organicos,
        'ausentes'  => $fix['ausentes'],
    ];
}

/**
 * Prioridade 20: o modulo WooCommerce reconstroi os args em 10 preservando so
 * posts_per_page/offset/paged, entao qualquer coisa abaixo disso seria descartada.
 */
add_filter('elementor/query/query_args', function ($query_args, $widget) {
    if (!$widget || !method_exists($widget, 'get_id')) return $query_args;
    if ($widget->get_id() !== CETRUS_CARR_WIDGET)       return $query_args;
    if (!cetrus_carr_ativo())                           return $query_args;

    // escotilha de QA: ?cetrus_carrossel_sem_fixos=1 renderiza so a regra viva,
    // para comparar antes/depois na mesma URL sem mexer na option.
    $com_fixos = !isset($_GET['cetrus_carrossel_sem_fixos']);

    $r = cetrus_carr_montar($com_fixos);
    if (empty($r['ids'])) return $query_args;   // nunca esvazia o carrossel

    $c = cetrus_carr_config();

    $query_args['post_type']      = 'product';
    $query_args['post_status']    = 'publish';
    $query_args['post__in']       = $r['ids'];
    $query_args['orderby']        = 'post__in';
    $query_args['posts_per_page'] = max(1, (int) $c['total']);
    unset($query_args['s'], $query_args['tax_query'], $query_args['meta_key'], $query_args['meta_value']);

    return $query_args;
}, 20, 2);

/**
 * Codigos que estao em 'fixos' e em 'vetados' ao mesmo tempo. O veto so mexe no bloco organico,
 * entao esses continuam no ar; quem digitou provavelmente quis tirar e precisa saber disso.
 */
function cetrus_carr_conflito_veto($c) {
    $fix = cetrus_carr_resolver_fixos($c['fixos']);
    $vet = cetrus_carr_resolver_vetados($c['vetados']);
    $por_curso = array_values(array_intersect($fix['cursos'], $vet['cursos']));
    $por_id    = array_values(array_intersect($fix['ids'], $vet['ids']));
    foreach ($por_id as $id) {
        $cod = strtoupper(str_replace(' ', '_', (string) get_post_meta($id, '_lyceum_curso_id', true)));
        if ($cod === '' || !in_array($cod, $por_curso, true)) $por_curso[] = $cod !== '' ? $cod : (string) $id;
    }
    return $por_curso;
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * wp cetrus-carrossel [status|on|off|fixos|vetados|total]
     *
     *   wp cetrus-carrossel fixos PG_MFE1,PG_HIST,PG_REGE   define a curadoria, nessa ordem
     *   wp cetrus-carrossel fixos --limpar                  volta a so regra viva
     *   wp cetrus-carrossel vetados FE_ED10,FE_IDR6         barra esses no bloco organico
     *   wp cetrus-carrossel vetados --limpar                libera todo mundo de volta
     *   wp cetrus-carrossel total 15                        quantos cards o carrossel mostra
     */
    WP_CLI::add_command('cetrus-carrossel', function ($args, $assoc = []) {
        $sub = $args[0] ?? 'status';
        $c   = cetrus_carr_config();

        if ($sub === 'on' || $sub === 'off') {
            $c['enabled'] = ($sub === 'on') ? 1 : 0;
            update_option(CETRUS_CARR_OPT, $c, false);
            WP_CLI::success('enabled=' . $c['enabled']);
            return;
        }

        if ($sub === 'fixos') {
            if (!empty($assoc['limpar'])) {
                $c['fixos'] = [];
            } else {
                $lista = (string) ($args[1] ?? '');
                if ($lista === '') WP_CLI::error('passe a lista separada por virgula, ou --limpar');
                $c['fixos'] = array_values(array_filter(array_map('trim', explode(',', $lista))));
            }
            update_option(CETRUS_CARR_OPT, $c, false);
            $r = cetrus_carr_resolver_fixos($c['fixos']);
            WP_CLI::success(sprintf('%d fixos, %d resolvidos%s',
                count($c['fixos']), count($r['ids']),
                $r['ausentes'] ? ', NAO RESOLVIDOS: ' . implode(', ', $r['ausentes']) : ''));
            return;
        }

        if ($sub === 'vetados') {
            if (!empty($assoc['limpar'])) {
                $c['vetados'] = [];
            } else {
                $lista = (string) ($args[1] ?? '');
                if ($lista === '') WP_CLI::error('passe a lista separada por virgula, ou --limpar');
                $c['vetados'] = array_values(array_filter(array_map('trim', explode(',', $lista))));
            }
            update_option(CETRUS_CARR_OPT, $c, false);
            $conflito = cetrus_carr_conflito_veto($c);
            WP_CLI::success(sprintf('%d vetados%s', count($c['vetados']),
                $conflito ? '; TAMBEM ESTA EM fixos (a curadoria vence): ' . implode(', ', $conflito) : ''));
            return;
        }

        if ($sub === 'total') {
            $n = (int) ($args[1] ?? 0);
            if ($n < 1 || $n > 40) WP_CLI::error('total precisa ficar entre 1 e 40');
            $c['total'] = $n;
            update_option(CETRUS_CARR_OPT, $c, false);
            WP_CLI::success('total=' . $n);
            return;
        }

        WP_CLI::line(sprintf('enabled=%d | janela %d-%d dias | ocupacao <%d%% | turma >=%d | cota fellowship %d | total %d',
            $c['enabled'], $c['dias_min'], $c['dias_max'], $c['ocupacao_max'], $c['turma_minima'],
            $c['cota_fellowship'], $c['total']));
        if ($c['fixos'])   WP_CLI::line('curadoria: ' . implode(', ', $c['fixos']));
        if ($c['vetados']) WP_CLI::line('vetados:   ' . implode(', ', $c['vetados']));
        WP_CLI::line('');

        $conflito = cetrus_carr_conflito_veto($c);
        if ($conflito) {
            WP_CLI::warning('vetado e fixo ao mesmo tempo, continua no carrossel pela curadoria: '
                . implode(', ', $conflito));
        }

        $r     = cetrus_carr_montar();
        $total = max(1, (int) $c['total']);
        WP_CLI::line(sprintf('%d fixos + %d organicos (%s) = %d; o carrossel mostra %d',
            count($r['fixos']), count($r['organicos']), $r['origem'], count($r['ids']), $total));

        if ($r['ausentes']) {
            WP_CLI::warning('sem produto publicado: ' . implode(', ', $r['ausentes']));
        }
        WP_CLI::line('');

        $agora = time(); $sem_data = [];
        foreach (array_slice($r['ids'], 0, $total + 4) as $i => $id) {
            $ini  = (int) get_post_meta($id, '_lyceum_data_inicio', true);
            $tem  = $ini > 0 && $ini < CETRUS_CARR_SEM_DATA;
            if (!$tem) $sem_data[] = get_post_meta($id, '_lyceum_curso_id', true) ?: $id;
            WP_CLI::line(sprintf('  %2d. %-3s %-6d %-10s %-9s ocup=%2d%%  livres=%-3d %s%s',
                $i + 1,
                in_array($id, $r['fixos'], true) ? 'FIX' : '',
                $id,
                get_post_meta($id, '_lyceum_curso_id', true),
                $tem ? sprintf('%dd', (int) floor(($ini - $agora) / DAY_IN_SECONDS)) : 'sem data',
                (int) get_post_meta($id, '_lyceum_ocupacao_pct', true),
                (int) get_post_meta($id, '_lyceum_vagas_livres', true),
                get_post_meta($id, 'is_fellowship', true) ? '[FE] ' : '',
                mb_substr(get_the_title($id), 0, 42)
            ));
            if ($i + 1 === $total && count($r['ids']) > $total) {
                WP_CLI::line('  ' . str_repeat('-', 20) . ' corte do carrossel ' . str_repeat('-', 20));
            }
        }

        /*
         * O card so mostra "comeca em X dias" quando o sync tem data. Curso sem data nao
         * quebra o card (o shortcode devolve string vazia de proposito), mas um destaque
         * mudo na home merece aviso: quase sempre e cadastro de turma no Lyceum, nao bug.
         */
        if ($sem_data) {
            WP_CLI::warning('destaque sem data de turma (card sai sem a linha de inicio): '
                . implode(', ', array_unique($sem_data)));
        }
    });
}

/**
 * Setas de navegacao do carrossel da home.
 * O widget passou a ter arrows=yes, mas o CSS do site deixava a seta de voltar
 * no canto inferior esquerdo e a de avancar no meio da direita. Aqui elas ficam
 * simetricas, centradas na vertical e nas bordas da faixa.
 * Cores do Dende: #003B6C ColorBrandCetrusMain, #FFFFFF ColorNeutralWhite.
 */
add_action('wp_enqueue_scripts', function () {
    if (!cetrus_carr_ativo()) return;
    wp_register_style('cetrus-carrossel-setas', false, [], '1.0.0');
    wp_enqueue_style('cetrus-carrossel-setas');
    wp_add_inline_style('cetrus-carrossel-setas', '
/* o !important e necessario: o CSS do tema posiciona uma das setas por bottom,
   deixando a de voltar no rodape do widget e a de avancar no meio */
.elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button{position:absolute !important;
  top:50% !important;bottom:auto !important;transform:translateY(-50%) !important;
  z-index:5;display:flex;align-items:center;justify-content:center;
  width:40px;height:40px;border-radius:10em;background:#fff;color:#003B6C;
  box-shadow:0 1px 8px rgba(17,18,18,.15);cursor:pointer;margin:0 !important}
.elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button svg{width:18px;height:18px;fill:currentColor}
.elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button-prev{left:-8px !important;right:auto !important}
.elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button-next{right:-8px !important;left:auto !important}
.elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button:hover{background:#003B6C;color:#fff}
.elementor-element-' . CETRUS_CARR_WIDGET . '{position:relative}
@media (max-width:860px){
  .elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button-prev{left:0}
  .elementor-element-' . CETRUS_CARR_WIDGET . ' .elementor-swiper-button-next{right:0}
}');
});
