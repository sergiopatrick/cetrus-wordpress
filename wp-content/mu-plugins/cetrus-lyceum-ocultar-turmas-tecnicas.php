<?php
/**
 * Plugin Name: Cetrus - ocultar turmas tecnicas do Lyceum
 * Description: Remove da resposta da products-api (Lyceum) as turmas tecnicas de venda (sufixo ".VENDAS", datas-sentinela em 2040) e as turmas que ja comecaram, antes que o plugin integracao-lyceum monte o seletor "Selecione a turma".
 * Version: 1.1.0
 * Author: Sanar / Cetrus
 *
 * Contexto
 * --------
 * O seletor de turma das paginas de curso do cetrus.com.br e montado a cada
 * render pelo plugin `integracao-lyceum-main`, a partir da API
 * https://kong.app-prod.sanar.cloud/products-api/v1/product.
 *
 * O Lyceum mantem, para quase toda pos-graduacao, uma turma tecnica com id
 * terminado em ".VENDAS" (ex.: PG_COP1.SP1.VENDAS), status ACTIVE, 100 vagas e
 * data-sentinela em 2040 (16/01/2040 a ...). Ela existe para receber vendas sem
 * turma definida, nao e uma turma real, mas passa por todos os filtros do
 * processor (ACTIVE + vagas disponiveis) e aparece para o usuario como
 * "16/01/2040 a 08/08/2040".
 *
 * Este mu-plugin corta essas turmas na camada HTTP, antes do plugin ver a
 * resposta. Vale para todos os cursos e para todos os consumidores da API
 * dentro do WordPress (paginas de curso, fellowship, AJAX).
 *
 * Isto NAO altera o Lyceum: a turma tecnica continua existindo la e continua
 * valendo para a operacao. O corte e apenas de exibicao no site.
 *
 * Nao afeta o checkout.cetrus.com.br, que consome a mesma API por fora do
 * WordPress.
 *
 * Turmas que ja comecaram (1.1.0, 09/10/2026)
 * -------------------------------------------
 * O processor de curso non-fellowship (class-course-processor.php) filtra so
 * status ACTIVE e vaga sobrando, sem olhar data. Quando o Lyceum deixa uma
 * turma antiga em ACTIVE com vaga, ela aparece no seletor como se ainda
 * estivesse a venda. Em 09/10/2026 eram 100 turmas passadas em 44 cursos
 * (US_TRV2 com 4, US_MAMA com 7, MMFC com 12, desde 2024). O processor de
 * fellowship ja exigia startDate >= hoje; esta versao estende a mesma regra
 * aos demais cursos.
 *
 * "Ja comecou" = dia de inicio anterior a hoje no fuso do site. O dia e lido
 * da propria string (os 10 primeiros caracteres), o mesmo dia que o seletor
 * exibe, entao turma que comeca hoje continua visivel.
 *
 * O corte por data NAO vale para os lotes do cetrus-lyceum-sync.php
 * (take >= 100): o sync para na primeira pagina vazia, e uma pagina inteira
 * de turmas antigas filtrada para vazio encerraria a varredura no meio do
 * catalogo. O sync ja descarta turma passada por conta propria.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sufixo do id das turmas tecnicas de venda no Lyceum.
 */
const CETRUS_LYCEUM_SUFIXO_TECNICO = '.VENDAS';

/**
 * Ano a partir do qual uma data de inicio e considerada sentinela (nao real).
 * As turmas tecnicas usam 2040; nenhuma turma real do Cetrus comeca tao longe.
 */
const CETRUS_LYCEUM_ANO_SENTINELA = 2035;

/**
 * take a partir do qual a requisicao e um lote do sync (mesmo corte de
 * cetrus-lyceum-api-cache.php). Lote do sync nao recebe o corte por data.
 */
const CETRUS_LYCEUM_TAKE_LOTE_SYNC = 100;

/**
 * Diz se a URL requisitada e o endpoint de produtos da API Lyceum.
 *
 * @param string $url URL da requisicao.
 * @return bool
 */
function cetrus_lyceum_e_endpoint_produtos($url) {
    if (!is_string($url) || $url === '') {
        return false;
    }

    $configurada = get_option('lyceum_api_url');
    if (is_string($configurada) && $configurada !== '' && strpos($url, $configurada) === 0) {
        return true;
    }

    return strpos($url, '/products-api/') !== false && strpos($url, '/product') !== false;
}

/**
 * Diz se um bloco packAcademics.lyceum representa uma turma tecnica.
 *
 * @param array $lyceum Bloco packAcademics.lyceum ja normalizado em array.
 * @return bool
 */
function cetrus_lyceum_e_turma_tecnica($lyceum) {
    $id = isset($lyceum['id']) ? strtoupper(trim((string) $lyceum['id'])) : '';

    $tecnica = false;

    if ($id !== '') {
        $sufixo = CETRUS_LYCEUM_SUFIXO_TECNICO;
        $tecnica = substr($id, -strlen($sufixo)) === $sufixo;
    }

    if (!$tecnica && !empty($lyceum['startDate'])) {
        $ano = (int) substr((string) $lyceum['startDate'], 0, 4);
        if ($ano >= CETRUS_LYCEUM_ANO_SENTINELA) {
            $tecnica = true;
        }
    }

    /**
     * Permite ajustar a regra sem editar este arquivo.
     *
     * @param bool  $tecnica Se a turma deve ser ocultada.
     * @param array $lyceum  Bloco lyceum da turma.
     */
    return (bool) apply_filters('cetrus_lyceum_e_turma_tecnica', $tecnica, $lyceum);
}

/**
 * Diz se a turma ja comecou (dia de inicio anterior a hoje no fuso do site).
 *
 * @param array $lyceum Bloco packAcademics.lyceum ja normalizado em array.
 * @return bool
 */
function cetrus_lyceum_turma_ja_comecou($lyceum) {
    $inicio = isset($lyceum['startDate']) ? substr((string) $lyceum['startDate'], 0, 10) : '';

    $comecou = false;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio)) {
        $comecou = $inicio < wp_date('Y-m-d');
    }

    /**
     * Permite ajustar a regra sem editar este arquivo.
     *
     * @param bool  $comecou Se a turma deve ser ocultada por ja ter comecado.
     * @param array $lyceum  Bloco lyceum da turma.
     */
    return (bool) apply_filters('cetrus_lyceum_turma_ja_comecou', $comecou, $lyceum);
}

/**
 * Diz se a URL e um lote de varredura do sync (take >= 100).
 *
 * @param string $url URL da requisicao.
 * @return bool
 */
function cetrus_lyceum_e_lote_sync($url) {
    $query = wp_parse_url($url, PHP_URL_QUERY);
    if (!is_string($query) || $query === '') {
        return false;
    }

    parse_str($query, $q);

    return isset($q['take']) && (int) $q['take'] >= CETRUS_LYCEUM_TAKE_LOTE_SYNC;
}

/**
 * Remove os itens de turma tecnica (e, se pedido, de turma ja iniciada) de
 * uma lista de produtos da API.
 *
 * @param array $itens          Lista de itens da API.
 * @param int   $removidos      Contador por referencia.
 * @param bool  $corta_passadas Se tambem remove as turmas que ja comecaram.
 * @return array Lista sem as turmas removidas.
 */
function cetrus_lyceum_filtra_itens($itens, &$removidos, $corta_passadas = false) {
    $mantidos = array();

    foreach ($itens as $item) {
        $normalizado = is_object($item) ? (array) $item : $item;
        $lyceum      = null;

        if (is_array($normalizado) && isset($normalizado['packAcademics'])) {
            $pack   = is_object($normalizado['packAcademics']) ? (array) $normalizado['packAcademics'] : $normalizado['packAcademics'];
            $lyceum = isset($pack['lyceum']) ? $pack['lyceum'] : null;
            $lyceum = is_object($lyceum) ? (array) $lyceum : $lyceum;
        }

        if (is_array($lyceum) && cetrus_lyceum_e_turma_tecnica($lyceum)) {
            $removidos++;
            continue;
        }

        if ($corta_passadas && is_array($lyceum) && cetrus_lyceum_turma_ja_comecou($lyceum)) {
            $removidos++;
            continue;
        }

        $mantidos[] = $item;
    }

    return $mantidos;
}

/**
 * Intercepta a resposta da products-api e remove as turmas tecnicas.
 *
 * @param array  $response Resposta do wp_remote_*.
 * @param array  $args     Argumentos da requisicao.
 * @param string $url      URL requisitada.
 * @return array
 */
function cetrus_lyceum_filtra_resposta_api($response, $args, $url) {
    if (is_wp_error($response) || !is_array($response)) {
        return $response;
    }

    if (!cetrus_lyceum_e_endpoint_produtos($url)) {
        return $response;
    }

    $codigo = isset($response['response']['code']) ? (int) $response['response']['code'] : 0;
    if ($codigo !== 200) {
        return $response;
    }

    $body = isset($response['body']) ? $response['body'] : '';
    if (!is_string($body) || $body === '') {
        return $response;
    }

    $dados = json_decode($body, true);
    if (!is_array($dados)) {
        return $response;
    }

    $removidos      = 0;
    $corta_passadas = !cetrus_lyceum_e_lote_sync($url);

    if (isset($dados['items']) && is_array($dados['items'])) {
        $dados['items'] = cetrus_lyceum_filtra_itens($dados['items'], $removidos, $corta_passadas);
    } elseif (isset($dados['data']) && is_array($dados['data'])) {
        $dados['data'] = cetrus_lyceum_filtra_itens($dados['data'], $removidos, $corta_passadas);
    } else {
        $dados = cetrus_lyceum_filtra_itens($dados, $removidos, $corta_passadas);
    }

    if ($removidos === 0) {
        return $response;
    }

    $novo_body = wp_json_encode($dados);
    if (!is_string($novo_body)) {
        return $response;
    }

    $response['body'] = $novo_body;

    if (get_option('lyceum_debug_mode')) {
        do_action('lyceum_log', '[cetrus-turmas-tecnicas] removidas=' . $removidos . ' | passadas=' . ($corta_passadas ? 'sim' : 'nao') . ' | url=' . $url);
    }

    return $response;
}
add_filter('http_response', 'cetrus_lyceum_filtra_resposta_api', 10, 3);
