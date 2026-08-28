<?php
/**
 * Plugin Name: Cetrus - Carrossel dirigido por turma
 * Description: Substitui a selecao manual do carrossel da home por consulta viva as metas do Lyceum (janela de dias e ocupacao).
 * Version:     1.0.0
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
 */

if (!defined('ABSPATH')) exit;

define('CETRUS_CARR_WIDGET',  'df02ba9');   // "Cursos em destaque no mes", home 10946
define('CETRUS_CARR_OPT',     'cetrus_carrossel');
define('CETRUS_CARR_MINIMO',  11);          // o carrossel exibe 11
define('CETRUS_CARR_SEM_DATA', 253370764800);

function cetrus_carr_config() {
    return wp_parse_args((array) get_option(CETRUS_CARR_OPT, []), [
        'enabled'        => 0,          // 0 = mantem a selecao manual
        'dias_min'       => 30,
        'dias_max'       => 75,
        'ocupacao_max'   => 60,
        'turma_minima'   => 3,
        'cota_fellowship'=> 4,          // no maximo 4 dos 11, para nao virar vitrine de nicho
        'fixos'          => [],         // IDs sempre presentes, na frente (curadoria do comercial)
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
 * Monta a lista final, com fallback em cascata.
 * Devolve ['ids' => [...], 'origem' => 'estrita|alargada|sem_ocupacao|manual'].
 */
function cetrus_carr_montar() {
    $c   = cetrus_carr_config();
    $min = CETRUS_CARR_MINIMO;

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

    $ids = array_column($melhor, 'id');

    // curadoria do comercial vem na frente, sem duplicar
    $fixos = array_map('intval', (array) $c['fixos']);
    if ($fixos) $ids = array_values(array_unique(array_merge($fixos, $ids)));

    // registra quando o fallback disparou, em option (nunca em arquivo de log)
    update_option('cetrus_carrossel_estado', [
        'quando'    => time(),
        'origem'    => $origem,
        'total'     => count($ids),
        'suficiente'=> count($ids) >= $min,
    ], false);

    return ['ids' => $ids, 'origem' => $origem];
}

/**
 * Prioridade 20: o modulo WooCommerce reconstroi os args em 10 preservando so
 * posts_per_page/offset/paged, entao qualquer coisa abaixo disso seria descartada.
 */
add_filter('elementor/query/query_args', function ($query_args, $widget) {
    if (!$widget || !method_exists($widget, 'get_id')) return $query_args;
    if ($widget->get_id() !== CETRUS_CARR_WIDGET)       return $query_args;
    if (!cetrus_carr_ativo())                           return $query_args;

    $r = cetrus_carr_montar();
    if (empty($r['ids'])) return $query_args;   // nunca esvazia o carrossel

    $query_args['post_type']      = 'product';
    $query_args['post_status']    = 'publish';
    $query_args['post__in']       = $r['ids'];
    $query_args['orderby']        = 'post__in';
    $query_args['posts_per_page'] = CETRUS_CARR_MINIMO;
    unset($query_args['s'], $query_args['tax_query'], $query_args['meta_key'], $query_args['meta_value']);

    return $query_args;
}, 20, 2);

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cetrus-carrossel', function ($args) {
        $sub = $args[0] ?? 'status';
        $c = cetrus_carr_config();

        if ($sub === 'on' || $sub === 'off') {
            $c['enabled'] = ($sub === 'on') ? 1 : 0;
            update_option(CETRUS_CARR_OPT, $c, false);
            WP_CLI::success('enabled=' . $c['enabled']);
            return;
        }

        WP_CLI::line(sprintf('enabled=%d | janela %d-%d dias | ocupacao <%d%% | turma >=%d | cota fellowship %d',
            $c['enabled'], $c['dias_min'], $c['dias_max'], $c['ocupacao_max'], $c['turma_minima'], $c['cota_fellowship']));
        if ($c['fixos']) WP_CLI::line('fixos: ' . implode(',', $c['fixos']));
        WP_CLI::line('');

        $r = cetrus_carr_montar();
        WP_CLI::line(sprintf('origem da lista: %s | %d cursos (minimo %d)', $r['origem'], count($r['ids']), CETRUS_CARR_MINIMO));
        WP_CLI::line('');
        $agora = time();
        foreach (array_slice($r['ids'], 0, 15) as $i => $id) {
            $ini = (int) get_post_meta($id, '_lyceum_data_inicio', true);
            WP_CLI::line(sprintf('  %2d. %-6d %-10s %3dd  ocup=%2d%%  livres=%-3d %s%s',
                $i + 1, $id,
                get_post_meta($id, '_lyceum_curso_id', true),
                (int) floor(($ini - $agora) / DAY_IN_SECONDS),
                (int) get_post_meta($id, '_lyceum_ocupacao_pct', true),
                (int) get_post_meta($id, '_lyceum_vagas_livres', true),
                get_post_meta($id, 'is_fellowship', true) ? '[FE] ' : '',
                mb_substr(get_the_title($id), 0, 42)
            ));
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
