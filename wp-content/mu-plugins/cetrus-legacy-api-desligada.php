<?php
/**
 * Plugin Name: Cetrus - API Legacy de cursos desligada
 * Description: Corta, sem abrir conexao, toda chamada HTTP do WordPress ao servidor antigo da Penso (wordpress.cetrus.com.br), que a infra desliga em 26/10/2026. O plugin integracao-lyceum recebe um erro imediato e segue so com o Lyceum, como ja acontece hoje quando a Legacy devolve 500.
 * Version: 1.0.0
 * Author: Sanar / Cetrus
 *
 * Contexto
 * --------
 * O plugin `integracao-lyceum-main` busca as turmas no Lyceum (products-api) e,
 * quando o Lyceum nao tem turma do curso, tenta a API Legacy
 * https://wordpress.cetrus.com.br/api/courses/<codigo> (lyceum_merge_mode=0).
 * Essa API roda na maquina cetrus-lnx-web01, herdada da Penso, que a infra
 * (Andressa Farias, CP-824) vai parar depois de 26/10/2026 10h.
 *
 * Em 22/10/2026 a Legacy nao contribuia com nenhuma turma exibida no site.
 * Dos 65 codigos que ela ainda conhece, so US_TIRS tinha turma, e esse curso
 * ja recebe as turmas pelo Lyceum, entao a Legacy nunca era consultada para
 * ele. Os demais devolvem turmas vazias ou HTTP 500.
 *
 * O risco do desligamento nao e de conteudo, e de tempo. Maquina parada na AWS
 * nao recusa a conexao, so deixa de responder, e o cliente da Legacy espera ate
 * 30 s (timeout do plugin) em toda pagina de curso sem turma no Lyceum. Erro
 * nao entra no cache de cetrus-lyceum-api-cache.php, entao cada render pagaria
 * essa espera. Alem disso o plugin chama a Legacy com sslverify=false, e um IP
 * liberado na AWS pode ir para outra empresa.
 *
 * O que faz
 * ---------
 * `pre_http_request` com prioridade 1 (antes do cache, que usa 10) devolve um
 * WP_Error para qualquer URL do host wordpress.cetrus.com.br. O cliente da
 * Legacy trata WP_Error como "sem dados" e devolve false, o mesmo caminho do
 * HTTP 500 de hoje. Nenhum outro host e afetado.
 *
 * Reverter: apagar este arquivo do repositorio e implantar. Ver tambem
 * cetrus-lyceum-api-cache.php, que ainda lista o host como cacheavel (inofensivo).
 */

if (!defined('ABSPATH')) {
    exit;
}

const CETRUS_LEGACY_HOST = 'wordpress.cetrus.com.br';

add_filter('pre_http_request', function ($preempt, $args, $url) {
    if ($preempt !== false) {
        return $preempt;
    }

    $host = wp_parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || strtolower($host) !== CETRUS_LEGACY_HOST) {
        return $preempt;
    }

    return new WP_Error(
        'cetrus_legacy_desligada',
        'API Legacy da Cetrus desligada (servidor da Penso desativado em 10/2026).'
    );
}, 1, 3);
