<?php
/**
 * Plugin Name: Cetrus - turmas com vagas esgotadas e limite de datas (Lyceum)
 * Description: Mostra no seletor "Selecione a turma" as turmas com 100% das vagas ocupadas, com " - Vagas esgotadas" e sem permitir escolha, bloqueia o cadastro nessas turmas (botao, formulario e checagem ao vivo no Lyceum) e limita as datas de cursos especificos (Pos Intervencao em Dor, SP: 3 datas).
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * CONTEXTO (27/11/2026)
 * O seletor de turma das paginas de curso vem do plugin integracao-lyceum-main:
 * products-api -> Lyceum_CourseProcessor (filtra ACTIVE e seats.amount > seats.sold)
 * -> [lyceum_course_selects] / lyceum_course_vars. Turma lotada era descartada no
 * processor e sumia da pagina. O pedido do comercial e o contrario: a data lotada
 * continua visivel, marcada como esgotada, e nao pode ser escolhida.
 *
 * COMO FUNCIONA (sem tocar no plugin nem no Lyceum)
 * 1. Processador. No plugins_loaded, o 'COURSE_' do Lyceum_ResponseProcessorRegistry
 *    passa a ser um envelope do proprio Lyceum_CourseProcessor. Antes de chamar o
 *    processor original, o envelope marca as turmas ACTIVE com amount > 0 e
 *    sold >= amount e, SO NA COPIA EM MEMORIA, poe sold = amount - 1 para que o filtro
 *    de vagas do plugin as aceite. Todas as outras regras do plugin continuam valendo
 *    (course.id, MV_, FE_, status). Depois devolve as vagas reais e marca
 *    'esgotada' => true na turma. Nada muda no cache HTTP nem no Lyceum.
 *    Fellowship (FE_) nao passa por aqui: a pagina mostra so a proxima turma com vaga
 *    e, sem nenhuma, vira fila de espera, como ja era.
 * 2. Limite de datas por curso (cetrus_vagas_limites): por unidade, mantem as N turmas
 *    COM VAGA mais proximas; turma esgotada anterior a ultima mantida continua visivel
 *    como esgotada; o resto sai. Pos Intervencao em Dor (PG_DOR2, produto 12588),
 *    UNIDADE SAO PAULO: 3 (validado com o usuario em 27/11/2026: 14/03, 23/05 e 04/07
 *    selecionaveis + 02/02/2027 esgotada visivel; 05/09 sai). A regra e automatica:
 *    quando uma turma comecar, a proxima entra.
 * 3. Seletor. A saida de [lyceum_course_selects] ganha, em cada <option> esgotada,
 *    disabled + data-cetrus-esgotada="1" + " - Vagas esgotadas" no texto.
 * 4. Pagina (JS no rodape, antes do cetrus-inscricao-sem-trava): destaca "Vagas
 *    esgotadas" (selo ao lado dos seletores), bloqueia o botao "Quero me inscrever"
 *    se a turma escolhida estiver esgotada ou se todas as turmas (da unidade ou do
 *    curso) estiverem, e segura o envio do formulario HubSpot do pop-up
 *    (contact__ct__class_site) ate confirmar a turma AO VIVO no Lyceum.
 * 5. Checagem ao vivo: POST /wp-json/cetrus/v1/turma-vagas {curso, turma} consulta a
 *    products-api SEM ler o cache de 15 min (cetrus_lyapi_leitura_fresca) e devolve se
 *    a turma esta esgotada. Se a API falhar, vale o dado da pagina (nunca trava turma
 *    com vaga por erro de rede).
 *
 * LIMITE CONHECIDO
 * O formulario de lead e do HubSpot e o navegador envia direto para o HubSpot, sem
 * passar pelo WordPress. Um envio forjado direto na API do HubSpot nao passa por aqui.
 *
 * MEDICAO (dataLayer)
 * - inscricao_bloqueada_vagas_esgotadas { curso, turma, motivo, etapa }
 *   motivo: turma | unidade | todas; etapa: selecao | botao | formulario
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio. O plugin do Lyceum volta
 * a esconder as turmas lotadas.
 */

if (!defined('ABSPATH')) {
    exit;
}

const CETRUS_VAGAS_VERSAO = '1.0.0';
const CETRUS_VAGAS_SUFIXO = ' - Vagas esgotadas';

/* ------------------------------------------------------------------ regras */

/**
 * Limite de turmas exibidas por curso e unidade.
 * Chave = codigo do curso no ACF v2_codigo_do_curso (o mesmo que o plugin usa).
 * Unidade = physicalUnit do Lyceum sem acento e em maiusculas.
 *
 * @return array<string, array<string, int>>
 */
function cetrus_vagas_limites() {
    $limites = array(
        // Pos-Graduacao Lato Sensu em Intervencao em Dor (produto 12588), definicao comercial 27/11/2026.
        'PG_DOR2' => array('UNIDADE SAO PAULO' => 3),
    );

    /**
     * Ajusta os limites sem editar este arquivo.
     *
     * @param array $limites codigo do curso => (unidade normalizada => quantidade de turmas com vaga).
     */
    return (array) apply_filters('cetrus_lyceum_limite_turmas', $limites);
}

/**
 * "UNIDADE São Paulo " -> "UNIDADE SAO PAULO".
 */
function cetrus_vagas_normaliza_unidade($texto) {
    $texto = remove_accents((string) $texto);
    return strtoupper(trim(preg_replace('/\s+/', ' ', $texto)));
}

/**
 * Vagas de um bloco packAcademics.lyceum.
 *
 * @return array{amount:int, sold:int}
 */
function cetrus_vagas_assentos($lyceum) {
    $lyceum = is_object($lyceum) ? (array) $lyceum : $lyceum;
    $seats  = (is_array($lyceum) && isset($lyceum['seats'])) ? $lyceum['seats'] : array();
    $seats  = is_object($seats) ? (array) $seats : $seats;

    return array(
        'amount' => isset($seats['amount']) ? (int) $seats['amount'] : 0,
        'sold'   => isset($seats['sold']) ? (int) $seats['sold'] : 0,
    );
}

/**
 * Turma ativa com capacidade cadastrada e ocupacao de 100% ou mais.
 * Capacidade zerada nao conta: sem capacidade nao ha ocupacao a calcular, e o plugin
 * continua escondendo essa turma como sempre fez.
 */
function cetrus_vagas_esta_esgotada($lyceum) {
    $lyceum = is_object($lyceum) ? (array) $lyceum : $lyceum;
    if (!is_array($lyceum)) {
        return false;
    }

    $esgotada = false;
    if (isset($lyceum['status']) && $lyceum['status'] === 'ACTIVE') {
        $s        = cetrus_vagas_assentos($lyceum);
        $esgotada = $s['amount'] > 0 && $s['sold'] >= $s['amount'];
    }

    /**
     * @param bool  $esgotada Se a turma deve aparecer como esgotada.
     * @param array $lyceum   Bloco packAcademics.lyceum.
     */
    return (bool) apply_filters('cetrus_lyceum_turma_esgotada', $esgotada, $lyceum);
}

/* ------------------------------------------------------------- processador */

/**
 * Lista de itens dentro da resposta da API (raiz, items ou data), por referencia.
 */
function &cetrus_vagas_itens(array &$dados) {
    if (isset($dados['items']) && is_array($dados['items'])) {
        return $dados['items'];
    }
    if (isset($dados['data']) && is_array($dados['data'])) {
        return $dados['data'];
    }
    return $dados;
}

/**
 * Copia da resposta com as turmas esgotadas liberadas para o filtro de vagas do plugin.
 *
 * @param mixed $bruto     Resposta decodificada da products-api.
 * @param array $esgotadas Saida: id da turma em maiusculas => vagas reais.
 * @return mixed
 */
function cetrus_vagas_prepara_bruto($bruto, &$esgotadas) {
    $esgotadas = array();
    if (!is_array($bruto)) {
        return $bruto;
    }

    $itens = &cetrus_vagas_itens($bruto);
    foreach ($itens as $i => $item) {
        if (!is_array($item) || !isset($item['packAcademics']['lyceum']) || !is_array($item['packAcademics']['lyceum'])) {
            continue;
        }
        $lyceum = $item['packAcademics']['lyceum'];
        $id     = isset($lyceum['id']) ? strtoupper(trim((string) $lyceum['id'])) : '';
        if ($id === '' || !cetrus_vagas_esta_esgotada($lyceum)) {
            continue;
        }

        $reais          = cetrus_vagas_assentos($lyceum);
        $esgotadas[$id] = $reais;

        if (!isset($itens[$i]['packAcademics']['lyceum']['seats']) || !is_array($itens[$i]['packAcademics']['lyceum']['seats'])) {
            $itens[$i]['packAcademics']['lyceum']['seats'] = array();
        }
        $itens[$i]['packAcademics']['lyceum']['seats']['amount'] = $reais['amount'];
        $itens[$i]['packAcademics']['lyceum']['seats']['sold']   = $reais['amount'] - 1;
    }
    unset($itens);

    return $bruto;
}

/**
 * Inicio da turma em timestamp UTC (mesma leitura do plugin).
 */
function cetrus_vagas_ts($data) {
    if (!is_string($data) || $data === '') {
        return PHP_INT_MAX;
    }
    try {
        $dt = new DateTime($data, new DateTimeZone('UTC'));
        return (int) $dt->format('U');
    } catch (Exception $e) {
        return PHP_INT_MAX;
    }
}

/**
 * Aplica o limite de turmas por unidade. $turmas no formato do processor (id, city, startDate...).
 * Por unidade limitada: percorre em ordem de inicio, mantem esgotadas ate a N-esima turma
 * com vaga e corta tudo o que vem depois dela.
 */
function cetrus_vagas_aplica_limite(array $turmas, $codigo_completo) {
    $limites = cetrus_vagas_limites();
    $codigo  = strtoupper(trim((string) $codigo_completo));
    if ($codigo === '' || empty($limites[$codigo]) || !is_array($limites[$codigo])) {
        return $turmas;
    }

    $por_unidade = array();
    foreach ($limites[$codigo] as $unidade => $n) {
        $por_unidade[cetrus_vagas_normaliza_unidade($unidade)] = max(0, (int) $n);
    }

    $ordem = array_keys($turmas);
    usort($ordem, function ($a, $b) use ($turmas) {
        $da = cetrus_vagas_ts(isset($turmas[$a]['startDate']) ? $turmas[$a]['startDate'] : '');
        $db = cetrus_vagas_ts(isset($turmas[$b]['startDate']) ? $turmas[$b]['startDate'] : '');
        return $da === $db ? $a - $b : ($da < $db ? -1 : 1);
    });

    $com_vaga = array();
    $sai      = array();
    foreach ($ordem as $k) {
        $t       = $turmas[$k];
        $unidade = cetrus_vagas_normaliza_unidade(isset($t['city']) ? $t['city'] : '');
        if (!isset($por_unidade[$unidade])) {
            continue;
        }
        $usadas = isset($com_vaga[$unidade]) ? $com_vaga[$unidade] : 0;
        if ($usadas >= $por_unidade[$unidade]) {
            $sai[$k] = true;
            continue;
        }
        if (empty($t['esgotada'])) {
            $com_vaga[$unidade] = $usadas + 1;
        }
    }

    if (!$sai) {
        return $turmas;
    }

    $mantidas = array();
    foreach ($turmas as $k => $t) {
        if (!isset($sai[$k])) {
            $mantidas[] = $t;
        }
    }
    return $mantidas;
}

/**
 * Pos-processa o resultado do Lyceum_CourseProcessor: vagas reais, flag 'esgotada',
 * limite por curso e registro para a pagina.
 */
function cetrus_vagas_ajusta_resultado($resultado, array $esgotadas, $codigo_completo) {
    if (!is_array($resultado) || empty($resultado['turmas']) || !is_array($resultado['turmas'])) {
        return $resultado;
    }

    $turmas = array();
    foreach ($resultado['turmas'] as $t) {
        if (!is_array($t)) {
            continue;
        }
        $id = isset($t['id']) ? strtoupper(trim((string) $t['id'])) : '';
        if ($id !== '' && isset($esgotadas[$id])) {
            $t['seats']    = $esgotadas[$id];
            $t['esgotada'] = true;
        } else {
            $t['esgotada'] = false;
        }
        $turmas[] = $t;
    }

    $turmas = cetrus_vagas_aplica_limite($turmas, $codigo_completo);

    $ficam = array();
    foreach ($turmas as $t) {
        $ficam[strtoupper(trim((string) (isset($t['id']) ? $t['id'] : '')))] = true;
    }

    // 'lyceum' = itens crus que o processor devolve junto (debug do plugin): mesmas turmas, vagas reais.
    if (isset($resultado['lyceum']) && is_array($resultado['lyceum'])) {
        $crus = array();
        foreach ($resultado['lyceum'] as $item) {
            $id = (is_array($item) && isset($item['packAcademics']['lyceum']['id'])) ? strtoupper(trim((string) $item['packAcademics']['lyceum']['id'])) : '';
            if ($id !== '' && !isset($ficam[$id])) {
                continue;
            }
            if ($id !== '' && isset($esgotadas[$id]) && is_array($item['packAcademics']['lyceum'])) {
                $item['packAcademics']['lyceum']['seats'] = $esgotadas[$id];
            }
            $crus[] = $item;
        }
        $resultado['lyceum'] = $crus;
    }

    $resultado['turmas']    = array_values($turmas);
    $resultado['available'] = count($turmas) > 0;
    if (!$resultado['available'] && empty($resultado['message'])) {
        $resultado['message'] = sprintf('Curso %s indisponivel', (string) $codigo_completo);
    }

    cetrus_vagas_registra($codigo_completo, $resultado['turmas']);

    return $resultado;
}

/**
 * Registro da pagina atual: curso e turmas exibidas (para o seletor e o JS do rodape).
 */
function &cetrus_vagas_registro() {
    static $registro = array('curso' => '', 'turmas' => array());
    return $registro;
}

function cetrus_vagas_registra($codigo_completo, array $turmas) {
    $registro           = &cetrus_vagas_registro();
    $registro['curso']  = strtoupper(trim((string) $codigo_completo));
    $registro['turmas'] = array();

    foreach ($turmas as $t) {
        $id = isset($t['id']) ? strtoupper(trim((string) $t['id'])) : '';
        if ($id === '') {
            continue;
        }
        $ts = cetrus_vagas_ts(isset($t['startDate']) ? $t['startDate'] : '');
        $registro['turmas'][] = array(
            'id'       => $id,
            'unidade'  => isset($t['city']) ? (string) $t['city'] : '',
            'data'     => $ts === PHP_INT_MAX ? '' : gmdate('d/m/Y', $ts),
            'esgotada' => !empty($t['esgotada']),
        );
    }
}

function cetrus_vagas_ids_esgotados() {
    $registro = &cetrus_vagas_registro();
    $ids      = array();
    foreach ($registro['turmas'] as $t) {
        if ($t['esgotada']) {
            $ids[$t['id']] = true;
        }
    }
    return $ids;
}

/**
 * Troca o processor 'COURSE_' do plugin por um envelope dele mesmo.
 */
function cetrus_vagas_instala_processador() {
    static $feito = false;
    if ($feito) {
        return;
    }
    if (!interface_exists('Lyceum_ResponseProcessorInterface') || !class_exists('Lyceum_ResponseProcessorRegistry') || !class_exists('Lyceum_CourseProcessor')) {
        return;
    }
    $feito = true;

    $envelope = new class(new Lyceum_CourseProcessor()) implements Lyceum_ResponseProcessorInterface {
        private $interno;

        public function __construct($interno) {
            $this->interno = $interno;
        }

        public function supports($codigo_completo) {
            return $this->interno->supports($codigo_completo);
        }

        public function process($raw_response, $codigo_lyceum, $codigo_completo = '') {
            $esgotadas = array();
            $bruto     = cetrus_vagas_prepara_bruto($raw_response, $esgotadas);
            $resultado = $this->interno->process($bruto, $codigo_lyceum, $codigo_completo);

            return cetrus_vagas_ajusta_resultado($resultado, $esgotadas, $codigo_completo !== '' ? $codigo_completo : $codigo_lyceum);
        }
    };

    Lyceum_ResponseProcessorRegistry::get_instance()->register('COURSE_', $envelope);
}

if (did_action('plugins_loaded')) {
    cetrus_vagas_instala_processador();
} else {
    add_action('plugins_loaded', 'cetrus_vagas_instala_processador', 20);
}

/* ----------------------------------------------------------------- seletor */

/**
 * Marca as <option> esgotadas na saida de [lyceum_course_selects].
 */
function cetrus_vagas_marca_opcoes($html) {
    $ids = cetrus_vagas_ids_esgotados();
    if (!$ids || !is_string($html) || strpos($html, 'data-lyceum-course-select') === false) {
        return $html;
    }

    return preg_replace_callback(
        '#<option\b([^>]*)>(.*?)</option>#is',
        function ($m) use ($ids) {
            if (!preg_match('/\bvalue="([^"]*)"/i', $m[1], $v)) {
                return $m[0];
            }
            $id = strtoupper(trim(html_entity_decode($v[1], ENT_QUOTES, 'UTF-8')));
            if ($id === '' || !isset($ids[$id]) || stripos($m[1], 'data-cetrus-esgotada') !== false) {
                return $m[0];
            }
            $rotulo = trim($m[2]);
            return '<option' . $m[1] . ' disabled data-cetrus-esgotada="1" class="cetrus-turma-esgotada">'
                . $rotulo . esc_html(CETRUS_VAGAS_SUFIXO) . '</option>';
        },
        $html
    );
}

add_filter('do_shortcode_tag', function ($saida, $tag) {
    if ($tag !== 'lyceum_course_selects') {
        return $saida;
    }
    return cetrus_vagas_marca_opcoes($saida);
}, 10, 2);

/* ------------------------------------------------------- checagem ao vivo */

/**
 * Consulta a products-api agora, sem ler o cache de 15 min, pela mesma URL da pagina
 * (o cache e regravado com o dado novo). Passa pelos mesmos filtros HTTP da pagina:
 * corte de turma tecnica/passada e juncao das turmas de Recife nas pos hibridas.
 *
 * @return array|WP_Error Lista de itens da API.
 */
function cetrus_vagas_busca_ao_vivo($codigo_completo) {
    $partes = explode('_', (string) $codigo_completo);
    if (count($partes) < 2) {
        return new WP_Error('codigo', 'codigo de curso invalido');
    }
    $mnemonico = (string) end($partes);

    $api = (string) get_option('lyceum_api_url', '');
    if ($api === '') {
        $api = 'https://kong.app-prod.sanar.cloud/products-api/v1/product';
    }
    $chave = (string) get_option('lyceum_api_key', '');
    if ($chave === '') {
        return new WP_Error('sem_chave', 'option lyceum_api_key vazia');
    }

    // Mesma montagem do Lyceum_ApiClient::get_products_by_lyceum_class (skip 0, take 50).
    $url = $api . '?' . http_build_query(array(
        'skip'   => 0,
        'take'   => 50,
        'filter' => wp_json_encode(array('status' => 'PUBLISHED', 'lyceumClassId' => array($mnemonico))),
    ));

    $fresca = function_exists('cetrus_lyapi_leitura_fresca');
    if ($fresca) {
        cetrus_lyapi_leitura_fresca(true);
    }
    try {
        $r = wp_remote_get($url, array(
            'headers' => array('API-KEY' => $chave, 'Content-Type' => 'application/json'),
            'timeout' => 12,
        ));
    } finally {
        if ($fresca) {
            cetrus_lyapi_leitura_fresca(false);
        }
    }

    if (is_wp_error($r)) {
        return $r;
    }
    if ((int) wp_remote_retrieve_response_code($r) !== 200) {
        return new WP_Error('http', 'HTTP ' . (int) wp_remote_retrieve_response_code($r));
    }

    $dados = json_decode((string) wp_remote_retrieve_body($r), true);
    if (!is_array($dados)) {
        return new WP_Error('json', 'resposta invalida');
    }

    return cetrus_vagas_itens($dados);
}

function cetrus_vagas_rest_resposta($dados, $status = 200) {
    $resposta = new WP_REST_Response($dados, $status);
    $resposta->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    return $resposta;
}

/**
 * POST /wp-json/cetrus/v1/turma-vagas  {curso: "PG_DOR2", turma: "PG_DOR2.SP1.2702.1"}
 */
function cetrus_vagas_rest_verificar($req) {
    $curso = strtoupper(trim((string) $req->get_param('curso')));
    $turma = strtoupper(trim((string) $req->get_param('turma')));

    if (!preg_match('/^[A-Z0-9]{1,10}_[A-Z0-9]{1,20}$/', $curso) || !preg_match('/^[A-Z0-9_.\-]{3,80}$/', $turma) || strpos($curso, 'FE_') === 0) {
        return cetrus_vagas_rest_resposta(array('erro' => 'parametros'), 400);
    }

    // Freio contra abuso: a checagem bate na API do Lyceum.
    $ip     = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $chave  = 'cetrus_vagas_rl_' . md5($ip);
    $vezes  = (int) get_transient($chave);
    if ($vezes >= 40) {
        return cetrus_vagas_rest_resposta(array('erro' => 'limite'), 429);
    }
    set_transient($chave, $vezes + 1, 10 * MINUTE_IN_SECONDS);

    $itens = cetrus_vagas_busca_ao_vivo($curso);
    if (is_wp_error($itens)) {
        return cetrus_vagas_rest_resposta(array('erro' => 'lyceum'), 503);
    }

    $achou     = null;
    $esgotadas = array();
    foreach ($itens as $item) {
        $lyceum = (is_array($item) && isset($item['packAcademics']['lyceum']) && is_array($item['packAcademics']['lyceum'])) ? $item['packAcademics']['lyceum'] : null;
        if (!$lyceum || empty($lyceum['id'])) {
            continue;
        }
        $id  = strtoupper(trim((string) $lyceum['id']));
        $esg = cetrus_vagas_esta_esgotada($lyceum);
        if ($esg) {
            $esgotadas[] = $id;
        }
        if ($id === $turma) {
            $achou = array('esgotada' => $esg, 'ativa' => isset($lyceum['status']) && $lyceum['status'] === 'ACTIVE');
        }
    }

    return cetrus_vagas_rest_resposta(array(
        'turma'      => $turma,
        'encontrada' => $achou !== null,
        'esgotada'   => $achou !== null && $achou['esgotada'],
        'esgotadas'  => $esgotadas,
        'verificado' => gmdate('c'),
    ));
}

add_action('rest_api_init', function () {
    register_rest_route('cetrus/v1', '/turma-vagas', array(
        'methods'             => 'POST',
        'callback'            => 'cetrus_vagas_rest_verificar',
        'permission_callback' => '__return_true',
        'args'                => array(
            'curso' => array('required' => true, 'type' => 'string'),
            'turma' => array('required' => true, 'type' => 'string'),
        ),
    ));
});

/* ------------------------------------------------------------------ pagina */

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('product')) {
        return;
    }
    // Tokens Dende: #FBF3E6 ColorFeedbackSurfaceAlert, #7F5205 ColorFeedbackOnAlert,
    // 10em BorderRadiusPill (mesmo selo de "ultimas vagas" do card de curso).
    $css = '
[data-lyceum-course-select] option.cetrus-turma-esgotada,
[data-lyceum-course-select] option[data-cetrus-esgotada]{color:#7F5205;background:#FBF3E6;font-weight:600}
.cetrus-vagas-tag{display:inline-block;padding:3px 8px;border-radius:10em;background:#FBF3E6;color:#7F5205;
  font-size:.75rem;font-weight:600;line-height:1.3;letter-spacing:.02em;text-transform:uppercase;white-space:nowrap}
.cetrus-vagas-nota,.cetrus-vagas-alerta{display:block;margin:8px 0 0;font-size:.8125rem;line-height:1.45}
.cetrus-vagas-nota .cetrus-vagas-tag,.cetrus-vagas-alerta .cetrus-vagas-tag{margin-right:6px}
.cetrus-vagas-alerta{font-weight:500}
.cetrus-vagas-alerta[hidden],.cetrus-vagas-nota[hidden]{display:none}
.hs-form .cetrus-vagas-alerta{margin:0 0 12px}
[data-lyceum-enrollment-btn].cetrus-btn-esgotado{cursor:not-allowed;opacity:.85}';
    wp_register_style('cetrus-lyceum-vagas', false, array(), CETRUS_VAGAS_VERSAO);
    wp_enqueue_style('cetrus-lyceum-vagas');
    wp_add_inline_style('cetrus-lyceum-vagas', $css);
});

/**
 * Configuracao + script da pagina. Prioridade 98: o listener de clique precisa ser
 * registrado antes do cetrus-inscricao-sem-trava (99), que abre o pop-up sem turma.
 */
add_action('wp_footer', function () {
    if (is_admin() || wp_doing_ajax() || is_feed() || is_embed() || isset($_GET['elementor-preview'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }
    if (!is_singular('product')) {
        return;
    }
    $registro = &cetrus_vagas_registro();
    if ($registro['curso'] === '' || !$registro['turmas']) {
        return;
    }

    $cfg = array(
        'curso'    => $registro['curso'],
        'turmas'   => $registro['turmas'],
        'endpoint' => esc_url_raw(rest_url('cetrus/v1/turma-vagas')),
        'sufixo'   => CETRUS_VAGAS_SUFIXO,
    );
    echo '<script id="cetrus-vagas-esgotadas-cfg">window.cetrusVagas=' . wp_json_encode($cfg) . ';</script>' . "\n";
    echo '<script id="cetrus-vagas-esgotadas">' . cetrus_vagas_js() . '</script>' . "\n";
}, 98);

function cetrus_vagas_js() {
    return <<<'JS'
(function () {
	var C = window.cetrusVagas;
	if (!C || !C.turmas || !C.turmas.length) return;
	var SUF = C.sufixo || ' - Vagas esgotadas';
	var TAG = '<span class="cetrus-vagas-tag">Vagas esgotadas</span>';
	var ACENTOS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');
	var esg = {};
	var vivo = {};
	var liberado = [];

	function up(v) { return String(v || '').trim().toUpperCase(); }
	function norm(v) { return String(v || '').normalize('NFD').replace(ACENTOS, '').toUpperCase().replace(/\s+/g, ' ').trim(); }
	C.turmas.forEach(function (t) { if (t.esgotada) esg[up(t.id)] = true; });
	function eEsgotada(id) { return !!esg[up(id)]; }

	function dl(dados) {
		window.dataLayer = window.dataLayer || [];
		var o = { event: 'inscricao_bloqueada_vagas_esgotadas', curso: C.curso };
		for (var k in dados) if (Object.prototype.hasOwnProperty.call(dados, k)) o[k] = dados[k];
		window.dataLayer.push(o);
	}
	function forms() { return [].slice.call(document.querySelectorAll('[data-lyceum-enrollment-form]')); }
	function visivel(el) { var r = el.getBoundingClientRect(); return r.width > 0 && r.height > 0; }
	function formAtivo() { var fs = forms(); for (var i = 0; i < fs.length; i++) if (visivel(fs[i])) return fs[i]; return fs[0] || null; }
	function sel(f) { return f ? { u: f.querySelector('[data-lyceum-unit-select]'), c: f.querySelector('[data-lyceum-course-select]') } : {}; }
	function turmasDa(unidade) {
		var u = norm(unidade);
		return C.turmas.filter(function (t) { return !u || norm(t.unidade) === u; });
	}
	function todasEsgotadas(lista) { return lista.length > 0 && lista.every(function (t) { return eEsgotada(t.id); }); }
	function datas(lista) {
		var d = lista.filter(function (t) { return eEsgotada(t.id) && t.data; }).map(function (t) { return t.data; });
		return d.length > 1 ? d.slice(0, -1).join(', ') + ' e ' + d[d.length - 1] : (d[0] || '');
	}

	/* Motivo para nao seguir: turma escolhida esgotada, ou nenhuma turma com vaga no escopo. */
	function motivo(turma, unidade) {
		if (turma && eEsgotada(turma)) return 'turma';
		if (turma) return '';
		if (unidade && todasEsgotadas(turmasDa(unidade))) return 'unidade';
		if (todasEsgotadas(C.turmas)) return 'todas';
		return '';
	}
	function textoMotivo(m) {
		if (m === 'turma') return TAG + 'nesta turma. Escolha outra data para continuar sua inscrição.';
		if (m === 'unidade') return TAG + 'em todas as turmas desta unidade. Escolha outra unidade.';
		return TAG + 'em todas as turmas deste curso.';
	}

	/* Opcoes: disabled + rotulo. Idempotente; refeito depois que o plugin recria as opcoes. */
	function marcarOpcoes() {
		[].forEach.call(document.querySelectorAll('[data-lyceum-course-select] option'), function (o) {
			if (!o.value || !eEsgotada(o.value)) return;
			o.disabled = true;
			o.setAttribute('data-cetrus-esgotada', '1');
			o.classList.add('cetrus-turma-esgotada');
			if (o.textContent.indexOf(SUF.trim()) === -1) o.textContent = o.textContent.trim() + SUF;
		});
		forms().forEach(function (f) {
			var s = sel(f);
			if (s.c && s.c.value && eEsgotada(s.c.value)) s.c.value = '';
		});
	}

	function caixa(f, classe) {
		var el = f.querySelector('.' + classe);
		if (!el) {
			el = document.createElement('small');
			el.className = classe;
			el.setAttribute('role', classe === 'cetrus-vagas-alerta' ? 'alert' : 'status');
			el.hidden = true;
			var antes = f.querySelector('.alert-submit-btn');
			f.insertBefore(el, antes || null);
		}
		return el;
	}

	/* Nota informativa (destaque) e estado dos botoes. */
	function atualizar() {
		forms().forEach(function (f) {
			var s = sel(f);
			var unidade = s.u ? s.u.value : '';
			var unidades = s.u ? [].filter.call(s.u.options, function (o) { return o.value; }) : [];
			if (!unidade && unidades.length === 1) unidade = unidades[0].value;
			var lista = unidade ? turmasDa(unidade) : (todasEsgotadas(C.turmas) ? C.turmas : []);
			var nota = caixa(f, 'cetrus-vagas-nota');
			var quais = datas(lista);
			if (!quais) { nota.hidden = true; return; }
			nota.innerHTML = TAG + (todasEsgotadas(lista)
				? (unidade ? 'em todas as turmas desta unidade.' : 'em todas as turmas deste curso.')
				: (quais.indexOf(' e ') > -1 ? 'nas turmas de ' : 'na turma de ') + quais + '. Garanta sua vaga nas próximas datas.');
			nota.hidden = false;
		});

		var tudo = todasEsgotadas(C.turmas);
		[].forEach.call(document.querySelectorAll('[data-lyceum-enrollment-btn]'), function (b) {
			var alvo = b.querySelector('.elementor-button-text') || b;
			if (tudo) {
				if (!b.hasAttribute('data-cetrus-texto')) b.setAttribute('data-cetrus-texto', alvo.textContent);
				alvo.textContent = 'Vagas esgotadas';
				b.classList.add('cetrus-btn-esgotado');
				b.setAttribute('aria-disabled', 'true');
			} else if (b.hasAttribute('data-cetrus-texto')) {
				alvo.textContent = b.getAttribute('data-cetrus-texto');
				b.removeAttribute('data-cetrus-texto');
				b.classList.remove('cetrus-btn-esgotado');
				b.removeAttribute('aria-disabled');
			}
		});
	}

	function alertar(f, m) {
		forms().forEach(function (x) {
			var a = caixa(x, 'cetrus-vagas-alerta');
			a.innerHTML = textoMotivo(m);
			a.hidden = false;
		});
		var al = f.querySelectorAll('.alert-submit-btn');
		[].forEach.call(al, function (a) { a.classList.add('hidden'); });
	}
	function limparAlerta() {
		[].forEach.call(document.querySelectorAll('[data-lyceum-enrollment-form] .cetrus-vagas-alerta'), function (a) { a.hidden = true; });
	}
	function rolar(f) {
		var top = f.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop) - (window.innerWidth < 768 ? 200 : 100);
		window.scrollTo({ top: top, behavior: 'smooth' });
	}

	function aplicarVivo(r) {
		if (!r || !r.esgotadas) return false;
		var novo = false;
		r.esgotadas.forEach(function (id) { if (!esg[up(id)]) { esg[up(id)] = true; novo = true; } });
		if (novo) { marcarOpcoes(); atualizar(); }
		return novo;
	}

	/* Checagem ao vivo no Lyceum (sem cache). null = nao deu para confirmar. */
	function checar(turma) {
		var id = up(turma);
		if (!id || !C.endpoint || !window.fetch) return Promise.resolve(null);
		var v = vivo[id];
		if (v && v.p && Date.now() - v.em < 60000) return v.p;
		var ctrl = window.AbortController ? new AbortController() : null;
		var t = ctrl ? setTimeout(function () { ctrl.abort(); }, 8000) : 0;
		var p = fetch(C.endpoint, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			cache: 'no-store',
			body: JSON.stringify({ curso: C.curso, turma: id }),
			signal: ctrl ? ctrl.signal : undefined
		}).then(function (r) { return r.ok ? r.json() : null; })
			.then(function (r) { clearTimeout(t); aplicarVivo(r); return r; })
			.catch(function () { clearTimeout(t); return null; });
		vivo[id] = { em: Date.now(), p: p };
		p.then(function (r) {
			if (!r) delete vivo[id];
			else if (vivo[id] && vivo[id].p === p) vivo[id].r = r;
		});
		return p;
	}

	/* 1. Troca de unidade/turma: o plugin recria as opcoes; remarca depois dele. */
	document.addEventListener('change', function (e) {
		var a = e.target;
		if (!a || !a.matches || !a.closest || !a.closest('[data-lyceum-enrollment-form]')) return;
		if (a.matches('[data-lyceum-course-select]') && a.value && eEsgotada(a.value)) {
			var escolhida = up(a.value);
			a.value = '';
			alertar(a.closest('[data-lyceum-enrollment-form]'), 'turma');
			dl({ turma: escolhida, motivo: 'turma', etapa: 'selecao' });
			return;
		}
		if (a.matches('[data-lyceum-unit-select], [data-lyceum-course-select]')) {
			limparAlerta();
			setTimeout(function () { marcarOpcoes(); atualizar(); }, 0);
		}
		if (a.matches('[data-lyceum-course-select]') && a.value) {
			checar(a.value).then(function (r) {
				if (r && r.esgotada) {
					var f = a.closest('[data-lyceum-enrollment-form]');
					marcarOpcoes(); atualizar();
					if (f) alertar(f, 'turma');
				}
			});
		}
	}, false);

	/* 2. Botao "Quero me inscrever": captura no window, antes do cetrus-inscricao-sem-trava. */
	window.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('[data-lyceum-enrollment-btn]') : null;
		if (!btn) return;
		var f = formAtivo();
		if (!f) return;
		var s = sel(f);
		var turma = s.c ? s.c.value : '';
		var m = motivo(turma, s.u ? s.u.value : '');
		if (!m) {
			limparAlerta();
			if (turma) checar(turma);
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		alertar(f, m);
		rolar(f);
		dl({ turma: up(turma), motivo: m, etapa: 'botao' });
	}, true);

	/* 3. Envio do formulario de lead (HubSpot no pop-up): turma do campo oculto. */
	function aviso(form, m) {
		var a = form.querySelector('.cetrus-vagas-alerta');
		if (!a) {
			a = document.createElement('div');
			a.className = 'cetrus-vagas-alerta';
			a.setAttribute('role', 'alert');
			form.insertBefore(a, form.firstChild);
		}
		a.innerHTML = textoMotivo(m);
		a.hidden = false;
	}
	function reenviar(form, botao) {
		liberado.push(form);
		if (form.requestSubmit) {
			try { form.requestSubmit(botao && botao.form === form ? botao : undefined); return; } catch (err) { /* segue */ }
		}
		if (botao) botao.click();
	}
	function aoEnviar(e) {
		var form = e.target;
		if (!form || form.tagName !== 'FORM') return;
		var campo = form.querySelector('input[name="contact__ct__class_site"]');
		if (!campo) return;
		var i = liberado.indexOf(form);
		if (i > -1) { liberado.splice(i, 1); return; }

		var turma = up(campo.value);
		var u = form.querySelector('input[name="contact__ct__unit_site"]');
		var m = motivo(turma, u ? u.value : '');
		if (m) {
			e.preventDefault();
			e.stopImmediatePropagation();
			aviso(form, m);
			dl({ turma: turma, motivo: m, etapa: 'formulario' });
			return;
		}
		if (!turma) return;

		var v = vivo[turma];
		if (v && v.r !== undefined && Date.now() - v.em < 60000) {
			if (v.r && v.r.esgotada) {
				e.preventDefault();
				e.stopImmediatePropagation();
				aviso(form, 'turma');
				dl({ turma: turma, motivo: 'turma', etapa: 'formulario' });
			}
			return;
		}

		e.preventDefault();
		e.stopImmediatePropagation();
		var botao = e.submitter || form.querySelector('[type="submit"]');
		if (botao) botao.setAttribute('aria-busy', 'true');
		checar(turma).then(function (r) {
			if (botao) botao.removeAttribute('aria-busy');
			if ((r && r.esgotada) || eEsgotada(turma)) {
				aviso(form, 'turma');
				dl({ turma: turma, motivo: 'turma', etapa: 'formulario' });
				return;
			}
			reenviar(form, botao);
		});
	}
	function proteger(win) {
		try {
			if (!win || win.__cetrusVagas) return;
			win.__cetrusVagas = true;
			win.addEventListener('submit', aoEnviar, true);
		} catch (err) { /* iframe de outra origem */ }
	}
	proteger(window);
	/* Formulario HubSpot dentro de iframe da mesma origem (embed legado). */
	function iframes() {
		[].forEach.call(document.querySelectorAll('.hbspt-form iframe, .hubspot-forms iframe, iframe.hs-form-iframe'), function (fr) {
			try { proteger(fr.contentWindow); } catch (err) { /* ignora */ }
			if (!fr.__cetrusVagas) { fr.__cetrusVagas = true; fr.addEventListener('load', function () { proteger(fr.contentWindow); }); }
		});
	}
	if (window.MutationObserver) new MutationObserver(iframes).observe(document.documentElement, { childList: true, subtree: true });

	function iniciar() { marcarOpcoes(); atualizar(); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
	window.addEventListener('load', function () { setTimeout(iniciar, 0); });
})();
JS;
}
