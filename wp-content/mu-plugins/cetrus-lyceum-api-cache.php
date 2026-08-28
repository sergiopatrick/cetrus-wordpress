<?php
/**
 * Plugin Name: Cetrus - cache HTTP da products-api (Lyceum)
 * Description: Cacheia por 15 minutos as respostas GET da products-api usadas no render das paginas de curso, cortando 3 a 9s de TTFB em cache MISS. Nao altera nenhum dado: apenas evita repetir a mesma chamada HTTP a cada pageview.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE ISTO EXISTE
 * O plugin integracao-lyceum-main monta o seletor "Selecione a turma" chamando a
 * products-api (kong.app-prod.sanar.cloud) de forma sincrona a CADA render, sem cache
 * nenhum (auditoria CWV de 28/08/2026). Custo medido: TTFB de 5,2s num curso com 3
 * turmas e 11,9s num com 28, contra ~2,3s de PHP base. Com o edge cache de so 300s
 * diluido em 402 paginas de curso, e com o cookie de carrinho do WooCommerce pondo a
 * borda em BYPASS, usuario real paga esse custo com frequencia.
 *
 * O QUE ELE FAZ
 * 1. `http_response` (prioridade 999): quando uma resposta 200 volta de um endpoint
 *    listado, grava o array de resposta num transient por 15 minutos, chaveado pela
 *    URL completa (querystring inclui o filtro de turma, entao cada curso tem a sua).
 *    Prioridade 999 = DEPOIS do corte das turmas .VENDAS feito por
 *    cetrus-lyceum-ocultar-turmas-tecnicas.php (prioridade 10): o cache ja guarda a
 *    resposta filtrada, e o pre_http_request abaixo nao repassa pelos filtros.
 * 2. `pre_http_request`: se ha transient para a URL, devolve na hora, sem HTTP.
 * 3. Memo estatico por request: a mesma URL pedida 2x no mesmo render sai do memo.
 *
 * O QUE ELE NAO CACHEIA (de proposito)
 * - Nada que nao seja GET.
 * - Paginas de lote do sincronismo diario (take >= 100): o cetrus-lyceum-sync.php
 *   varre o catalogo e deve sempre ler dado fresco.
 * - Erros e respostas nao-200: falha continua visivel, nada de mascarar com cache.
 *
 * CONSEQUENCIA OPERACIONAL: turma criada/alterada no Lyceum leva ate 15 minutos +
 * TTL do edge (300s) para aparecer no seletor do site. O checkout continua validando
 * ao vivo (a loja consome a API por fora do WordPress). Para forcar na hora:
 *   wp transient delete --all  (ou so as chaves cetrus_lyapi_*)
 * Estado vai para wp_options/objeto de cache, nunca para arquivo de log
 * (ver memoria cetrus-debug-log-publico).
 */

if (!defined('ABSPATH')) {
    exit;
}

const CETRUS_LYAPI_TTL       = 900;   // 15 minutos
const CETRUS_LYAPI_PREFIXO   = 'cetrus_lyapi_';
const CETRUS_LYAPI_TAKE_SYNC = 100;   // take >= isto = lote do sync, nao cachear

/**
 * Endpoints elegiveis: host + prefixo de caminho.
 */
function cetrus_lyapi_endpoints() {
    return [
        ['kong.app-prod.sanar.cloud', '/products-api/'],
        ['wordpress.cetrus.com.br',   '/api/courses'],
    ];
}

/**
 * Decide se a requisicao e cacheavel e devolve a chave; false caso contrario.
 *
 * @param string $url  URL completa da requisicao.
 * @param array  $args Argumentos do wp_remote_*.
 * @return string|false
 */
function cetrus_lyapi_chave($url, $args) {
    $metodo = isset($args['method']) ? strtoupper($args['method']) : 'GET';
    if ($metodo !== 'GET') {
        return false;
    }

    $partes = wp_parse_url($url);
    if (empty($partes['host'])) {
        return false;
    }

    $elegivel = false;
    foreach (cetrus_lyapi_endpoints() as $ep) {
        if ($partes['host'] === $ep[0] && strpos($partes['path'] ?? '/', $ep[1]) === 0) {
            $elegivel = true;
            break;
        }
    }
    if (!$elegivel) {
        return false;
    }

    // Lote do sincronismo diario: sempre fresco.
    if (!empty($partes['query'])) {
        parse_str($partes['query'], $q);
        if (isset($q['take']) && (int) $q['take'] >= CETRUS_LYAPI_TAKE_SYNC) {
            return false;
        }
    }

    return CETRUS_LYAPI_PREFIXO . md5($url);
}

/**
 * Memo por request (a mesma URL pode ser pedida mais de uma vez no mesmo render).
 */
function &cetrus_lyapi_memo() {
    static $memo = [];
    return $memo;
}

/**
 * Curto-circuito: serve do cache antes de abrir HTTP.
 */
add_filter('pre_http_request', function ($preempt, $args, $url) {
    if ($preempt !== false) {
        return $preempt;
    }
    $chave = cetrus_lyapi_chave($url, $args);
    if (!$chave) {
        return false;
    }

    $memo = &cetrus_lyapi_memo();
    if (isset($memo[$chave])) {
        return $memo[$chave];
    }

    $cacheado = get_transient($chave);
    if (is_array($cacheado) && isset($cacheado['body'], $cacheado['response']['code'])) {
        $memo[$chave] = $cacheado;
        return $cacheado;
    }

    return false;
}, 10, 3);

/**
 * Captura: grava respostas 200 depois de todos os outros filtros (ex.: corte .VENDAS).
 */
add_filter('http_response', function ($response, $args, $url) {
    $chave = cetrus_lyapi_chave($url, $args);
    if (!$chave || is_wp_error($response)) {
        return $response;
    }

    $codigo = wp_remote_retrieve_response_code($response);
    if ((int) $codigo !== 200) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $enxuto = [
        'headers'  => ['x-cetrus-lyapi' => 'cache'],
        'body'     => $body,
        'response' => ['code' => 200, 'message' => 'OK'],
        'cookies'  => [],
        'filename' => null,
    ];

    set_transient($chave, $enxuto, CETRUS_LYAPI_TTL);
    $memo = &cetrus_lyapi_memo();
    $memo[$chave] = $enxuto;

    return $response;
}, 999, 3);
