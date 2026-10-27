<?php
/**
 * Plugin Name: Cetrus - desliga a API legada de cursos (servidor da Penso)
 * Description: Corta antes do HTTP toda chamada a wordpress.cetrus.com.br. A infra vai pausar o servidor herdado da Penso, e sem este corte cada pagina de curso sem turma no Lyceum esperaria ate 30s pelo timeout do Lyceum_LegacyApiClient.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE ISTO EXISTE
 * O plugin integracao-lyceum-main, com lyceum_merge_mode=0, consulta a API legada
 * (https://wordpress.cetrus.com.br/api/courses/<codigo>) sempre que o Lyceum nao tem
 * turma do curso, com timeout de 30s. Conferido em 22/10/2026: dos 65 codigos que a
 * legada ainda responde, so US_TIRS tem turma, e ele ja recebe as turmas pelo Lyceum.
 * Nenhuma pagina usa dado da legada; carga horaria, duracao e profissionais vem do ACF.
 * A infra (Andressa Farias) pediu o corte em 27/10/2026 para pausar o servidor.
 *
 * O QUE ELE FAZ
 * `pre_http_request` na prioridade 1 devolve WP_Error para o host da legada, antes do
 * cache de cetrus-lyceum-api-cache.php (prioridade 10, que respeita o $preempt). O
 * Lyceum_LegacyApiClient trata WP_Error como falha e devolve false, o mesmo caminho
 * de "curso sem turma" que os 183 codigos inexistentes ja seguem hoje.
 *
 * PARA VOLTAR ATRAS: remover este arquivo do repo e implantar.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('pre_http_request', function ($preempt, $args, $url) {
    if ($preempt !== false) {
        return $preempt;
    }
    if (wp_parse_url($url, PHP_URL_HOST) !== 'wordpress.cetrus.com.br') {
        return false;
    }
    return new WP_Error('cetrus_api_legada_desligada', 'API legada do Cetrus desligada (servidor da Penso pausado).');
}, 1, 3);
