<?php
/**
 * Plugin Name: Cetrus - Sincronismo de turmas do Lyceum
 * Description: Materializa em post meta a proxima turma elegivel de cada produto, lida da products-api. Habilita carrossel dirigido por turma e ordenacao por data nas vitrines.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE ISTO EXISTE
 * A products-api do Lyceum leva ~1,1s por curso e o plugin integracao-lyceum-main nao tem cache
 * nenhum. Resolver data e vagas no render de uma vitrine de 16 cards custaria 6 a 17 segundos.
 * Este job varre o catalogo inteiro em ~10 chamadas em lote e grava o resultado em post meta,
 * que e o unico lugar de onde WP_Query e o WP Grid Builder conseguem ordenar.
 *
 * DECISOES DE DESENHO (nao mudar sem ler)
 * 1. So grandezas ABSOLUTAS sao gravadas. Nada de "dias para o inicio": valor relativo em cache
 *    congela se o cron parar e o site passa a anunciar curso que ja comecou. Os dias sao
 *    calculados em tempo de request por quem consome.
 * 2. TODOS os produtos publicados recebem as metas, inclusive os sem turma, com valor sentinela.
 *    orderby=meta_value_num faz INNER JOIN em postmeta: produto sem a chave SOME da vitrine.
 * 3. O casamento e por `packAcademics.lyceum.course.id` EXATO. O plugin antigo manda so o
 *    mnemonico (GO_HIST -> HIST) e a API casa por substring, o que mistura turmas de cursos
 *    diferentes em 36 produtos. Ver memoria cetrus-codigo-curso-mnemonico.
 * 4. Estado vai para wp_options, nunca para arquivo de log. Ver cetrus-debug-log-publico.
 * 5. Cliente HTTP proprio com timeout de 60s. O do plugin tem 30s cravado e uma pagina grande
 *    da products-api leva ~39s, o que devolveria WP_Error e marcaria tudo como defasado.
 */

if (!defined('ABSPATH')) exit;

define('CETRUS_LSYNC_VERSAO',    '1.0.0');
define('CETRUS_LSYNC_HOOK_LOTE', 'cetrus_lyceum_sync_lote');
define('CETRUS_LSYNC_HOOK_FIM',  'cetrus_lyceum_sync_finalizar');
define('CETRUS_LSYNC_HOOK_CRON', 'cetrus_lyceum_sync_diario');
define('CETRUS_LSYNC_OPT_ESTADO','cetrus_lyceum_sync_estado');
define('CETRUS_LSYNC_OPT_BUF',   'cetrus_lyceum_sync_buffer');
define('CETRUS_LSYNC_TAKE',      250);   // ~10s por pagina, folgado dentro do limite do Action Scheduler
define('CETRUS_LSYNC_MAX_PAG',   40);    // trava de seguranca: 40 x 250 = 10.000 registros
define('CETRUS_LSYNC_TIMEOUT',   60);

/* ------------------------------------------------------------------ estado */

function cetrus_lsync_estado($novo = null) {
    if ($novo === null) {
        return wp_parse_args((array) get_option(CETRUS_LSYNC_OPT_ESTADO, []), [
            'status'        => 'nunca_rodou',
            'inicio'        => 0,
            'fim'           => 0,
            'duracao'       => 0,
            'paginas'       => 0,
            'linhas_api'    => 0,
            'cursos_lyceum' => 0,
            'produtos'      => 0,
            'com_turma'     => 0,
            'sem_turma'     => 0,
            'erros'         => [],
        ]);
    }
    update_option(CETRUS_LSYNC_OPT_ESTADO, $novo, false);
    return $novo;
}

function cetrus_lsync_erro($msg) {
    $e = cetrus_lsync_estado();
    $e['erros'][] = gmdate('Y-m-d H:i:s') . ' ' . $msg;
    $e['erros']   = array_slice($e['erros'], -20);
    cetrus_lsync_estado($e);
}

/* -------------------------------------------------------------------- api */

function cetrus_lsync_config() {
    return [
        'url' => (string) get_option('lyceum_api_url', 'https://kong.app-prod.sanar.cloud/products-api/v1/product'),
        'key' => (string) get_option('lyceum_api_key', ''),
    ];
}

/**
 * Busca uma pagina da products-api. Devolve array de linhas ou WP_Error.
 * Nao reaproveita o Lyceum_ApiClient de proposito: o timeout dele e 30s.
 */
function cetrus_lsync_busca_pagina($skip, $take) {
    $cfg = cetrus_lsync_config();
    if ($cfg['key'] === '') return new WP_Error('sem_chave', 'option lyceum_api_key vazia');

    $url = add_query_arg([
        'skip'   => (int) $skip,
        'take'   => (int) $take,
        'filter' => wp_json_encode(['status' => 'PUBLISHED']),
    ], $cfg['url']);

    $r = wp_remote_get($url, [
        'timeout' => CETRUS_LSYNC_TIMEOUT,
        'headers' => ['API-KEY' => $cfg['key'], 'Content-Type' => 'application/json'],
    ]);
    if (is_wp_error($r)) return $r;

    $code = wp_remote_retrieve_response_code($r);
    if ($code !== 200) return new WP_Error('http_' . $code, 'HTTP ' . $code);

    $body = json_decode(wp_remote_retrieve_body($r), true);
    if (!is_array($body)) return new WP_Error('json', 'resposta nao e JSON valido');

    // a raiz e lista plana; toleramos os involucros items/data por seguranca
    if (isset($body['items']) && is_array($body['items'])) $body = $body['items'];
    elseif (isset($body['data']) && is_array($body['data'])) $body = $body['data'];

    return $body;
}

/* ---------------------------------------------------------------- filtros */

/** Turma tecnica de venda: id .VENDAS ou data-sentinela de 2035 em diante. */
function cetrus_lsync_e_turma_tecnica($id, $inicio) {
    if (is_string($id) && substr($id, -7) === '.VENDAS') return true;
    if ($inicio && (int) substr($inicio, 0, 4) >= 2035)   return true;
    return (bool) apply_filters('cetrus_lyceum_e_turma_tecnica', false, $id, $inicio);
}

/**
 * Mesma regra do class-course-processor.php, para o carrossel nao divergir da pagina de curso:
 * status ACTIVE, vagas sobrando, e fora os prefixos de monitoria voluntaria.
 */
function cetrus_lsync_turma_elegivel($t) {
    if (!is_array($t)) return false;
    if (($t['status'] ?? '') !== 'ACTIVE') return false;

    $id = (string) ($t['id'] ?? '');
    if ($id === '' || strpos($id, 'MV_') === 0) return false;

    $inicio = $t['startDate'] ?? '';
    if (!$inicio) return false;
    if (cetrus_lsync_e_turma_tecnica($id, $inicio)) return false;

    $qtd = isset($t['seats']['amount']) ? (int) $t['seats']['amount'] : 0;
    $ven = isset($t['seats']['sold'])   ? (int) $t['seats']['sold']   : 0;
    if ($qtd <= 0 || $ven >= $qtd) return false;   // sem vaga, esgotada, ou amount zerado

    return true;
}

/* ------------------------------------------------------------------ varre */

function cetrus_lsync_iniciar() {
    $e = cetrus_lsync_estado();
    if ($e['status'] === 'rodando' && (time() - (int) $e['inicio']) < 900) {
        return false;   // ja ha uma varredura em curso ha menos de 15 min
    }
    delete_option(CETRUS_LSYNC_OPT_BUF);
    cetrus_lsync_estado(array_merge($e, [
        'status' => 'rodando', 'inicio' => time(), 'fim' => 0, 'duracao' => 0,
        'paginas' => 0, 'linhas_api' => 0, 'cursos_lyceum' => 0, 'erros' => [],
    ]));
    cetrus_lsync_agenda(CETRUS_LSYNC_HOOK_LOTE, ['skip' => 0]);
    return true;
}

function cetrus_lsync_agenda($hook, $args = []) {
    if (function_exists('as_enqueue_async_action')) {
        as_enqueue_async_action($hook, [$args], 'cetrus-lyceum-sync');
    } else {
        wp_schedule_single_event(time() + 5, $hook, [$args]);
    }
}

/** Processa uma pagina e agenda a proxima. */
function cetrus_lsync_lote($args) {
    $skip = isset($args['skip']) ? (int) $args['skip'] : 0;
    $e    = cetrus_lsync_estado();

    $linhas = cetrus_lsync_busca_pagina($skip, CETRUS_LSYNC_TAKE);

    if (is_wp_error($linhas)) {
        cetrus_lsync_erro("pagina skip={$skip}: " . $linhas->get_error_message());
        // uma pagina que falha nao derruba a varredura: segue para a proxima
        if ($skip / CETRUS_LSYNC_TAKE < CETRUS_LSYNC_MAX_PAG) {
            cetrus_lsync_agenda(CETRUS_LSYNC_HOOK_LOTE, ['skip' => $skip + CETRUS_LSYNC_TAKE]);
        } else {
            cetrus_lsync_agenda(CETRUS_LSYNC_HOOK_FIM);
        }
        return;
    }

    cetrus_lsync_acumula($linhas);

    // $buf vive na option, nao neste escopo: a acumulacao foi extraida para
    // cetrus_lsync_acumula(). Ler a variavel local aqui lancava TypeError em PHP 8.3
    // e matava a cadeia do cron (o caminho do WP-CLI nao passa por aqui, por isso
    // as rodadas manuais nao acusavam).
    $e = cetrus_lsync_estado();
    $e['paginas']++;
    $e['linhas_api']    += count($linhas);
    $e['cursos_lyceum']  = count((array) get_option(CETRUS_LSYNC_OPT_BUF, []));
    cetrus_lsync_estado($e);

    // ATENCAO: a products-api devolve paginas PARCIAIS no meio da varredura
    // (medido: 250, 250, 250, 246, 227, 230, 231, 250, 250, 24, 0).
    // Parar na primeira pagina incompleta perde mais da metade do catalogo.
    // A unica condicao de fim confiavel e a pagina vazia.
    $fim = count($linhas) === 0
        || ($skip / CETRUS_LSYNC_TAKE) + 1 >= CETRUS_LSYNC_MAX_PAG;

    if ($fim) cetrus_lsync_agenda(CETRUS_LSYNC_HOOK_FIM);
    else      cetrus_lsync_agenda(CETRUS_LSYNC_HOOK_LOTE, ['skip' => $skip + CETRUS_LSYNC_TAKE]);
}

/* -------------------------------------------------------------- finaliza */

/** Sentinelas. Data no ano 9999 para o produto sem turma cair no fim da ordenacao ASC. */
const CETRUS_LSYNC_SEM_DATA  = 253370764800;  // 9999-01-01
const CETRUS_LSYNC_SEM_VAGAS = -1;            // -1 = sem turma; 0 = turma esgotada

function cetrus_lsync_finalizar() {
    $buf = (array) get_option(CETRUS_LSYNC_OPT_BUF, []);
    $e   = cetrus_lsync_estado();

    // uma varredura que nao trouxe nada nao pode zerar o site
    if (empty($buf)) {
        cetrus_lsync_erro('buffer vazio, metas preservadas');
        $e['status'] = 'falhou'; $e['fim'] = time();
        $e['duracao'] = $e['fim'] - (int) $e['inicio'];
        cetrus_lsync_estado($e);
        return;
    }

    $ids = get_posts([
        'post_type' => 'product', 'post_status' => 'publish',
        'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
    ]);

    $agora = time(); $com = 0; $sem = 0;

    foreach ($ids as $id) {
        $codigo = (string) get_post_meta($id, 'v2_codigo_do_curso', true);
        if ($codigo === '' || strpos($codigo, '_') === false) {
            $codigo = (string) get_post_meta($id, '_sku', true);
        }
        $t = ($codigo !== '' && isset($buf[$codigo])) ? $buf[$codigo] : null;

        if ($t && $t['inicio'] >= $agora) {
            cetrus_lsync_grava($id, [
                '_lyceum_data_inicio'  => $t['inicio'],
                '_lyceum_vagas_livres' => $t['livres'],
                '_lyceum_ocupacao_pct' => $t['ocup'],
                '_lyceum_turma_total'  => $t['qtd'],
                '_lyceum_turma_id'     => $t['turma'],
                '_lyceum_unidade'      => $t['unidade'],
                '_lyceum_curso_id'     => $codigo,
                '_lyceum_sync_ts'      => $agora,
            ]);
            $com++;
        } else {
            cetrus_lsync_grava($id, [
                '_lyceum_data_inicio'  => CETRUS_LSYNC_SEM_DATA,
                '_lyceum_vagas_livres' => CETRUS_LSYNC_SEM_VAGAS,
                '_lyceum_ocupacao_pct' => 0,
                '_lyceum_turma_total'  => 0,
                '_lyceum_turma_id'     => '',
                '_lyceum_unidade'      => '',
                '_lyceum_curso_id'     => $codigo,
                '_lyceum_sync_ts'      => $agora,
            ]);
            $sem++;
        }
    }

    delete_option(CETRUS_LSYNC_OPT_BUF);

    $e['status']    = 'ok';
    $e['fim']       = time();
    $e['duracao']   = $e['fim'] - (int) $e['inicio'];
    $e['produtos']  = count($ids);
    $e['com_turma'] = $com;
    $e['sem_turma'] = $sem;
    cetrus_lsync_estado($e);
}

/**
 * Escreve so o que mudou, para nao sujar postmeta a toa.
 *
 * Cuidado: get_post_meta() devolve '' tanto para "chave ausente" quanto para "chave com valor
 * vazio". Comparar so o valor faria a chave nunca ser criada quando o valor e vazio, e ai o
 * produto ficaria sem a linha em postmeta. Por isso o metadata_exists().
 */
function cetrus_lsync_grava($id, $metas) {
    foreach ($metas as $k => $v) {
        $existe = metadata_exists('post', $id, $k);
        if (!$existe || (string) get_post_meta($id, $k, true) !== (string) $v) {
            update_post_meta($id, $k, $v);
        }
    }
}

/* -------------------------------------------------------------- consumo */

/**
 * Dias ate o inicio da proxima turma, calculado AGORA (nunca lido de cache).
 * Devolve null se o produto nao tem turma ou se o sincronismo esta velho demais.
 */
function cetrus_lyceum_dias_para_inicio($post_id, $validade_horas = 72) {
    $ts = (int) get_post_meta($post_id, '_lyceum_sync_ts', true);
    if (!$ts || (time() - $ts) > $validade_horas * HOUR_IN_SECONDS) return null;

    $ini = (int) get_post_meta($post_id, '_lyceum_data_inicio', true);
    if (!$ini || $ini >= CETRUS_LSYNC_SEM_DATA) return null;

    return (int) floor(($ini - time()) / DAY_IN_SECONDS);
}

/**
 * [cetrus_inicio_turma] - texto curto de proxima turma, para o card de curso.
 *
 * Existe porque ordenar a vitrine por "inicio mais proximo" sem mostrar a data faz a
 * ordenacao parecer embaralhamento aleatorio para quem olha. Devolve string vazia quando
 * nao ha turma ou quando o sincronismo esta velho, para nao inventar informacao.
 *
 * Atributos: formato="dias|data|ambos" (padrao ambos), classe="".
 */
function cetrus_lyceum_sc_inicio($atts = []) {
    $a = shortcode_atts(['formato' => 'ambos', 'classe' => '', 'id' => 0], $atts, 'cetrus_inicio_turma');
    $id = (int) $a['id'] ?: get_the_ID();
    if (!$id) return '';

    $dias = cetrus_lyceum_dias_para_inicio($id);
    if ($dias === null || $dias < 0) return '';

    $ts   = (int) get_post_meta($id, '_lyceum_data_inicio', true);
    $data = $ts ? date_i18n('d/m/Y', $ts) : '';

    if ($dias === 0)      $quando = 'começa hoje';
    elseif ($dias === 1)  $quando = 'começa amanhã';
    else                  $quando = 'começa em ' . $dias . ' dias';

    switch ($a['formato']) {
        case 'dias':  $txt = $quando; break;
        case 'data':  $txt = $data ? 'Início em ' . $data : $quando; break;
        default:      $txt = $data ? $quando . ' (' . $data . ')' : $quando;
    }

    $classe = 'cetrus-inicio-turma' . ($a['classe'] ? ' ' . sanitize_html_class($a['classe']) : '');
    return '<span class="' . esc_attr($classe) . '">' . esc_html($txt) . '</span>';
}
add_shortcode('cetrus_inicio_turma', 'cetrus_lyceum_sc_inicio');

/** [cetrus_vagas_turma] - so avisa quando esta acabando; nunca anuncia sala vazia. */
function cetrus_lyceum_sc_vagas($atts = []) {
    $a = shortcode_atts(['limite' => 3, 'id' => 0], $atts, 'cetrus_vagas_turma');
    $id = (int) $a['id'] ?: get_the_ID();
    if (!$id || cetrus_lyceum_dias_para_inicio($id) === null) return '';

    $livres = (int) get_post_meta($id, '_lyceum_vagas_livres', true);
    if ($livres <= 0 || $livres > (int) $a['limite']) return '';

    $txt = $livres === 1 ? 'última vaga' : 'últimas ' . $livres . ' vagas';
    return '<span class="cetrus-vagas-turma">' . esc_html($txt) . '</span>';
}
add_shortcode('cetrus_vagas_turma', 'cetrus_lyceum_sc_vagas');

add_action('wp_enqueue_scripts', function () {
    wp_register_style('cetrus-lyceum-turma', false, [], '1.0.0');
    wp_enqueue_style('cetrus-lyceum-turma');
    /**
     * Card de curso (template Elementor 11204, compartilhado por home e as 4 vitrines).
     * No mobile os cards ficam com ~250px e apareciam tres defeitos:
     *   1. titulo quebrando NO MEIO DA PALAVRA ("Ultrassonog / rafia")
     *   2. coordenador e data vazando para fora do card
     *   3. linhas coladas, sem respiro
     * A causa de (1) e (2) e a mesma familia: item de lista do Elementor e flex, e
     * filho flex sem min-width:0 nao encolhe nem quebra - ele estoura o container.
     */
    $card = '
.elementor-11204 .elementor-heading-title{overflow-wrap:break-word;word-break:normal;
  -webkit-hyphens:none;hyphens:none}
.elementor-11204 .elementor-icon-list-item{align-items:flex-start;min-width:0}
.elementor-11204 .elementor-icon-list-item > .elementor-icon-list-icon{flex:0 0 auto;margin-top:2px}
.elementor-11204 .elementor-icon-list-text{min-width:0;overflow-wrap:break-word;word-break:normal;
  -webkit-hyphens:none;hyphens:none;line-height:1.35}
@media (max-width:860px){
  /* o titulo pode ocupar ate 3 linhas antes de truncar, em vez de cortar cedo */
  .elementor-11204 .elementor-heading-title{font-size:.9375rem;line-height:1.25;
    display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  .elementor-11204 .elementor-icon-list-items{gap:4px}
  .elementor-11204 .elementor-icon-list-text{font-size:.75rem}
  /* coordenador longo trunca em uma linha em vez de empurrar o card */
  .elementor-11204 .elementor-icon-list-item:not(:first-child) .elementor-icon-list-text{
    display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
}';
    wp_add_inline_style('cetrus-lyceum-turma', $card);

    // Facet 16 "Ordenar por" das vitrines. Sem isto ele ocupa a largura inteira
    // da coluna do grid (~810px), que e desproporcional para um controle de ordem.
    // Tokens Dende: #C3C6C6 ColorNeutralLight, #111212 ColorNeutralDarkest,
    // rgba(17,18,18,.65) ColorTextNeutralLigther, .875rem FontSizeSmall, 8px BorderRadius2.
    $ordenar = '
.wpgb-facet-16{display:flex;align-items:center;justify-content:flex-end;gap:12px;margin:0 0 16px}
.wpgb-facet-16 .wpgb-facet-title{margin:0;font-size:.875rem;font-weight:500;
  color:rgba(17,18,18,.65);white-space:nowrap;line-height:1}
.wpgb-facet-16 fieldset{margin:0;padding:0;border:0}
.wpgb-facet-16 .wpgb-sort-facet{flex:0 0 auto;width:240px;max-width:100%;position:relative}
.wpgb-facet-16 select.wpgb-sort{width:100%;height:40px;padding:0 36px 0 12px;
  border:1px solid #C3C6C6;border-radius:8px;background:#fff;color:#111212;
  font-size:.875rem;line-height:1.2;cursor:pointer}
.wpgb-facet-16 select.wpgb-sort:focus{outline:2px solid #003B6C;outline-offset:-1px;border-color:#003B6C}
@media (max-width:640px){
  .wpgb-facet-16{justify-content:space-between;gap:8px;margin-bottom:12px}
  .wpgb-facet-16 .wpgb-sort-facet{flex:1 1 auto;width:auto;min-width:0}
  .wpgb-facet-16 .wpgb-facet-title{font-size:.8125rem}
}';
    wp_add_inline_style('cetrus-lyceum-turma', $ordenar);

    wp_add_inline_style('cetrus-lyceum-turma',
        // Tokens Dende: #003B6C ColorBrandCetrusMain, #FBF3E6 ColorFeedbackSurfaceAlert,
        // #7F5205 ColorFeedbackOnAlert, .75rem FontSizeTiny, 10em BorderRadiusPill.
        // O selo segue a especificacao de Tag estatica do DS: tinyBody com peso 500,
        // nao 600 (peso 600 e das tags interativas).
        '.cetrus-inicio-turma{color:#003B6C;font-weight:500;font-size:.75rem}' .
        '.cetrus-vagas-turma{display:inline-block;margin-left:8px;padding:4px 8px;border-radius:10em;' .
        'background:#FBF3E6;color:#7F5205;font-size:.75rem;font-weight:500;letter-spacing:.02em}');
});

/* ----------------------------------------------------------------- hooks */

add_action(CETRUS_LSYNC_HOOK_LOTE, 'cetrus_lsync_lote', 10, 1);
add_action(CETRUS_LSYNC_HOOK_FIM,  'cetrus_lsync_finalizar', 10, 0);
add_action(CETRUS_LSYNC_HOOK_CRON, 'cetrus_lsync_iniciar', 10, 0);

add_action('init', function () {
    if (!wp_next_scheduled(CETRUS_LSYNC_HOOK_CRON)) {
        // 04:10 em Sao Paulo = 07:10 UTC
        wp_schedule_event(strtotime('tomorrow 07:10 UTC'), 'daily', CETRUS_LSYNC_HOOK_CRON);
    }
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cetrus-lyceum-sync', function ($args, $assoc) {
        $sub = $args[0] ?? 'status';

        if ($sub === 'status') {
            $e = cetrus_lsync_estado();
            foreach ($e as $k => $v) {
                WP_CLI::line(sprintf('%-14s %s', $k, is_array($v) ? implode(' | ', $v) : $v));
            }
            return;
        }

        if ($sub === 'run') {
            // execucao sincrona, sem Action Scheduler, para uso manual
            cetrus_lsync_estado(array_merge(cetrus_lsync_estado(), [
                'status' => 'rodando', 'inicio' => time(), 'paginas' => 0,
                'linhas_api' => 0, 'cursos_lyceum' => 0, 'erros' => [],
            ]));
            delete_option(CETRUS_LSYNC_OPT_BUF);

            $skip = 0; $pag = 0;
            do {
                $t0 = microtime(true);
                $linhas = cetrus_lsync_busca_pagina($skip, CETRUS_LSYNC_TAKE);
                if (is_wp_error($linhas)) {
                    WP_CLI::warning("skip={$skip}: " . $linhas->get_error_message());
                    cetrus_lsync_erro("skip={$skip}: " . $linhas->get_error_message());
                    break;
                }
                $n = count($linhas);
                WP_CLI::line(sprintf('  pagina %-2d skip=%-5d linhas=%-4d %.1fs', ++$pag, $skip, $n, microtime(true) - $t0));

                // reusa a mesma logica de acumulacao
                cetrus_lsync_acumula($linhas);

                $e = cetrus_lsync_estado();
                $e['linhas_api'] += $n;
                cetrus_lsync_estado($e);

                $skip += CETRUS_LSYNC_TAKE;
                // ver comentario em cetrus_lsync_lote(): so a pagina vazia encerra
            } while ($n > 0 && $pag < CETRUS_LSYNC_MAX_PAG);

            $e = cetrus_lsync_estado();
            $e['paginas'] = $pag;
            $e['cursos_lyceum'] = count((array) get_option(CETRUS_LSYNC_OPT_BUF, []));
            cetrus_lsync_estado($e);

            WP_CLI::line('  gravando metas...');
            cetrus_lsync_finalizar();

            $e = cetrus_lsync_estado();
            WP_CLI::success(sprintf(
                'status=%s | %ds | %d paginas | %d cursos no Lyceum | %d produtos (%d com turma, %d sem)',
                $e['status'], $e['duracao'], $e['paginas'], $e['cursos_lyceum'],
                $e['produtos'], $e['com_turma'], $e['sem_turma']
            ));
            if (!empty($e['erros'])) foreach ($e['erros'] as $x) WP_CLI::warning($x);
            return;
        }

        WP_CLI::error("subcomando desconhecido: $sub (use status ou run)");
    });
}

/** Acumula um conjunto de linhas no buffer. Extraido para o WP-CLI reusar. */
function cetrus_lsync_acumula($linhas) {
    $buf   = (array) get_option(CETRUS_LSYNC_OPT_BUF, []);
    $agora = time();

    foreach ($linhas as $linha) {
        $t = $linha['packAcademics']['lyceum'] ?? null;
        if (!$t || !cetrus_lsync_turma_elegivel($t)) continue;

        $curso = (string) ($t['course']['id'] ?? '');
        if ($curso === '') continue;

        $ini = strtotime($t['startDate']);
        if (!$ini) continue;

        $qtd = (int) $t['seats']['amount'];
        $ven = (int) $t['seats']['sold'];

        $cand = [
            'turma'    => (string) $t['id'],
            'inicio'   => $ini,
            'qtd'      => $qtd,
            'vendidas' => $ven,
            'livres'   => max(0, $qtd - $ven),
            'ocup'     => (int) round(min(100, max(0, $ven / $qtd * 100))),
            'unidade'  => (string) ($t['physicalUnit']['name'] ?? ''),
        ];

        if (!isset($buf[$curso])) { $buf[$curso] = $cand; continue; }
        $atual = $buf[$curso];
        $candFuturo  = $cand['inicio']  >= $agora;
        $atualFuturo = $atual['inicio'] >= $agora;
        if ($candFuturo && !$atualFuturo)                                          $buf[$curso] = $cand;
        elseif ($candFuturo === $atualFuturo && $cand['inicio'] < $atual['inicio']) $buf[$curso] = $cand;
    }

    update_option(CETRUS_LSYNC_OPT_BUF, $buf, false);
}
