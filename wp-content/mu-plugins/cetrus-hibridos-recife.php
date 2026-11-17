<?php
/**
 * Plugin Name: Cetrus - turmas de Recife nas pos hibridas
 * Description: Na pagina de curso de Sao Paulo das pos hibridas, junta ao seletor as turmas de Recife, que no Lyceum estao cadastradas sob outro codigo de curso (PG_GIN2 em SP, PG_GIH2 em Recife).
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * PROBLEMA (17/11/2026)
 * Varias pos hibridas tem um codigo de curso por cidade no Lyceum. A pagina de
 * curso so busca turmas do proprio codigo (o integracao-lyceum-main manda o
 * mnemonico, GIN2, no filtro lyceumClassId), e o processor ainda descarta turma
 * cujo course.id nao seja o do curso da pagina. Resultado, a pagina de SP nunca
 * mostra a turma de Recife e o lead nao consegue escolher a unidade Recife.
 *
 * Decisao da coordenacao em 12/11/2026 (Carol Butterby): estes cursos ficam numa
 * pagina so, a de SP, com Recife como opcao do formulario.
 *
 * COMO FUNCIONA
 * `http_response` prioridade 20, depois do corte de turma tecnica e passada
 * (cetrus-lyceum-ocultar-turmas-tecnicas.php, 10) e antes do cache de 15 min
 * (cetrus-lyceum-api-cache.php, 999). Quando a resposta e a busca da pagina por
 * um mnemonico de SP da lista abaixo:
 * 1. faz a mesma busca pelo mnemonico de Recife (mesma URL, mesmos headers; essa
 *    chamada passa pelos mesmos filtros e pelo mesmo cache);
 * 2. fica so com as turmas cujo course.id e exatamente o de Recife (a API casa
 *    por substring, nunca confiar no mnemonico);
 * 3. troca o course.id dessas turmas pelo de SP, para o processor aceitar. O id
 *    da turma (PG_GIH2.RC1.2703.1) e a unidade (UNIDADE RECIFE) ficam intactos,
 *    entao o HubSpot recebe contact__ct__class_site e contact__ct__unit_site de
 *    Recife.
 * Se a busca de Recife falhar, a resposta de SP segue como veio.
 *
 * Nao vale para lote do sync (take >= 100) nem para outros mnemonicos.
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio, e apagar os
 * transients cetrus_lyapi_* para o cache sair na hora.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pares SP => Recife, pelo course.id do Lyceum.
 */
function cetrus_hibridos_pares() {
    $pares = array(
        'PG_GIN2' => 'PG_GIH2', // Ginecologia Clinica
        'PG_GEN1' => 'PG_GEH1', // USG Ginecologica e Endometriose
        'PG_USME' => 'PG_USMR', // USG Musculoesqueletica
        'PG_COP1' => 'PG_CPH1', // Colposcopia e PTGI
        'PG_HEF2' => 'PG_HEH2', // Ecocardiografia Fetal
        'PG_MFE1' => 'PG_MFH2', // Medicina Fetal
    );

    /**
     * Permite incluir ou tirar pares sem editar este arquivo.
     *
     * @param array $pares course.id de SP => course.id de Recife.
     */
    return (array) apply_filters('cetrus_hibridos_pares', $pares);
}

/**
 * Mnemonico (parte depois do ultimo "_"), como o plugin manda para a API.
 */
function cetrus_hibridos_mnemonico($codigo) {
    $partes = explode('_', (string) $codigo);
    return strtoupper((string) end($partes));
}

/**
 * Le o mnemonico do filtro lyceumClassId da URL, se for busca de pagina.
 *
 * @return string|false
 */
function cetrus_hibridos_mnemonico_da_url($url) {
    $query = wp_parse_url($url, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return false;
    }

    parse_str($query, $q);

    if (isset($q['take']) && (int) $q['take'] >= 100) {
        return false;
    }

    $filtro = isset($q['filter']) ? json_decode((string) $q['filter'], true) : null;
    if (!is_array($filtro) || empty($filtro['lyceumClassId']) || !is_array($filtro['lyceumClassId'])) {
        return false;
    }

    if (count($filtro['lyceumClassId']) !== 1) {
        return false;
    }

    return strtoupper((string) reset($filtro['lyceumClassId']));
}

/**
 * Lista de itens da resposta decodificada, por referencia ao lugar certo.
 */
function &cetrus_hibridos_itens(array &$dados) {
    if (isset($dados['items']) && is_array($dados['items'])) {
        return $dados['items'];
    }
    if (isset($dados['data']) && is_array($dados['data'])) {
        return $dados['data'];
    }
    return $dados;
}

/**
 * course.id de um item da API, em maiusculas.
 */
function cetrus_hibridos_course_id($item) {
    $id = isset($item['packAcademics']['lyceum']['course']['id']) ? $item['packAcademics']['lyceum']['course']['id'] : '';
    return strtoupper(trim((string) $id));
}

/**
 * Junta as turmas de Recife na resposta da pagina de SP.
 */
function cetrus_hibridos_junta_recife($response, $args, $url) {
    static $em_andamento = false;

    if ($em_andamento || is_wp_error($response) || !is_array($response)) {
        return $response;
    }

    if (!function_exists('cetrus_lyceum_e_endpoint_produtos') || !cetrus_lyceum_e_endpoint_produtos($url)) {
        return $response;
    }

    if ((int) wp_remote_retrieve_response_code($response) !== 200) {
        return $response;
    }

    $mnemonico = cetrus_hibridos_mnemonico_da_url($url);
    if ($mnemonico === false) {
        return $response;
    }

    $sp = $rec = '';
    foreach (cetrus_hibridos_pares() as $cod_sp => $cod_rec) {
        if (cetrus_hibridos_mnemonico($cod_sp) === $mnemonico) {
            $sp  = strtoupper($cod_sp);
            $rec = strtoupper($cod_rec);
            break;
        }
    }
    if ($rec === '') {
        return $response;
    }

    $dados = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($dados)) {
        return $response;
    }

    $mn_rec  = cetrus_hibridos_mnemonico($rec);
    $url_rec = add_query_arg(
        'filter',
        rawurlencode(wp_json_encode(array('status' => 'PUBLISHED', 'lyceumClassId' => array($mn_rec)))),
        $url
    );

    $em_andamento = true;
    $resp_rec     = wp_remote_get($url_rec, array(
        'headers' => isset($args['headers']) ? $args['headers'] : array(),
        'timeout' => isset($args['timeout']) ? $args['timeout'] : 30,
    ));
    $em_andamento = false;

    if (is_wp_error($resp_rec) || (int) wp_remote_retrieve_response_code($resp_rec) !== 200) {
        return $response;
    }

    $dados_rec = json_decode((string) wp_remote_retrieve_body($resp_rec), true);
    if (!is_array($dados_rec)) {
        return $response;
    }

    // course.id que o processor vai aceitar: o das turmas de SP quando houver
    // (PG_MFE1 vem como "MFE1"), senao o codigo completo de SP.
    $itens        = &cetrus_hibridos_itens($dados);
    $id_aceito_sp = $sp;
    foreach ($itens as $item) {
        $cid = cetrus_hibridos_course_id($item);
        if ($cid === $sp || $cid === cetrus_hibridos_mnemonico($sp)) {
            $id_aceito_sp = $cid;
            break;
        }
    }

    $ja_tem = array();
    foreach ($itens as $item) {
        if (isset($item['packAcademics']['lyceum']['id'])) {
            $ja_tem[strtoupper((string) $item['packAcademics']['lyceum']['id'])] = true;
        }
    }

    $somadas = 0;
    foreach (cetrus_hibridos_itens($dados_rec) as $item) {
        $cid = cetrus_hibridos_course_id($item);
        if ($cid !== $rec && $cid !== $mn_rec) {
            continue;
        }
        $id_turma = isset($item['packAcademics']['lyceum']['id']) ? strtoupper((string) $item['packAcademics']['lyceum']['id']) : '';
        if ($id_turma === '' || isset($ja_tem[$id_turma])) {
            continue;
        }

        $item['packAcademics']['lyceum']['course']['id']  = $id_aceito_sp;
        $item['packAcademics']['lyceum']['cetrus_origem'] = $rec;
        $itens[] = $item;
        $somadas++;
    }
    unset($itens);

    if ($somadas === 0) {
        return $response;
    }

    $novo_body = wp_json_encode($dados);
    if (!is_string($novo_body)) {
        return $response;
    }

    $response['body'] = $novo_body;

    if (get_option('lyceum_debug_mode')) {
        do_action('lyceum_log', '[cetrus-hibridos] ' . $sp . ' + ' . $somadas . ' turma(s) de ' . $rec);
    }

    return $response;
}
add_filter('http_response', 'cetrus_hibridos_junta_recife', 20, 3);
