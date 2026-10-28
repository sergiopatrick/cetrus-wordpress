<?php
/**
 * Plugin Name: Cetrus — Autocomplete da busca
 * Description: Sugestões enquanto o usuário digita no campo de busca da home. Monta um índice de cursos publicados (título, coordenador, especialidade, modalidade, cidade) servido por uma rota REST cacheada e faz o casamento no navegador, sem uma requisição por tecla, com tolerância a erro de digitação. Quando o índice não acha nada, pergunta ao Relevanssi, o mesmo motor da página de resultados. Também limpa o termo da página de resultados e rebaixa os cursos da Sanar no ranking. Não altera o _elementor_data: o painel é criado em runtime ao lado do campo, então desativar o arquivo devolve a busca ao comportamento anterior.
 * Version: 1.1.0
 * Author: Sanar / Cetrus
 */

namespace Cetrus\BuscaAutocomplete;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSAO      = '1.1.0';
const REST_NS     = 'cetrus/v1';
const REST_ROTA   = 'busca-indice';
const REST_BUSCA  = 'busca';
const TRANSIENT   = 'cetrus_ac_indice_v2';
const TTL         = 12 * HOUR_IN_SECONDS;
const OPT_VERSAO  = 'cetrus_ac_versao';
const OPT_MODO    = 'cetrus_ac_modo';

/** Quantas sugestões o painel mostra por grupo. */
const MAX_CURSOS         = 6;
const MAX_ESPECIALIDADES = 3;
const MIN_CARACTERES     = 2;

/**
 * Cursos com a tag `sanar` (os de pós on-line da Sanar vendidos no site) descem
 * no ranking. Em busca ampla como "pós graduação medicina" eles apareciam antes
 * de qualquer curso do Cetrus. Não somem: numa busca específica ("medicina do
 * sono") continuam sendo a resposta, só deixam de furar a fila.
 */
const TAG_SANAR            = 'sanar';
const PENALIDADE_SANAR     = 25;   // pontos a menos no autocomplete
const PESO_SANAR_RESULTADO = 0.4;  // multiplica o peso do Relevanssi na página /?s=

/**
 * Apelidos de busca.
 *
 * Só entram aqui os termos que o casamento por prefixo NÃO resolve sozinho.
 * "cardio" já acha "Cardiologia" por prefixo e não precisa de linha; "ultrassom"
 * não é prefixo de "ultrassonografia" (divergem no 9º caractere) e sem esta
 * entrada some com os ~140 cursos de USG do catálogo.
 *
 * O valor do apelido tem que ser UMA palavra que exista no catálogo. "tc" =>
 * "tomografia computadorizada" não achava nada, porque nenhum curso escreve o
 * termo completo no título; "tc" => "tomografia" acha os dois que existem.
 */
function apelidos() {
	return array(
		'ultrassom'  => array( 'ultrassonografia' ),
		'ultrassons' => array( 'ultrassonografia' ),
		'usg'        => array( 'ultrassonografia', 'ultrassom' ),
		'rm'         => array( 'ressonancia' ),
		'tc'         => array( 'tomografia' ),
		'rx'         => array( 'radiologia' ),
		'eco'        => array( 'ecocardiografia' ),
		'go'         => array( 'ginecologia e obstetricia' ),
		'uti'        => array( 'terapia intensiva' ),
		// Termos reais do estudo de busca de out/2026 que não achavam nada.
		'mamas'      => array( 'mama' ),
		'residencia' => array( 'monitoria', 'preparatorio' ),
		'titulo'     => array( 'preparatorio' ),
		'online'     => array( 'ead', 'on line' ),
		'sp'         => array( 'sao paulo' ),
		'rj'         => array( 'rio de janeiro' ),
		'bh'         => array( 'belo horizonte' ),
		'ortopedista'   => array( 'ortopedia' ),
		'ginecologista' => array( 'ginecologia' ),
		'cardiologista' => array( 'cardiologia' ),
		'pediatra'      => array( 'pediatria' ),
		'radiologista'  => array( 'radiologia' ),
	);
}

/** Minúsculo, sem acento e sem a pontuação que separa sigla ("BI-RADS" vira "birads"). */
function chave( $s ) {
	$s = strtolower( remove_accents( texto( $s ) ) );
	$s = preg_replace( "/[-.'\x{2019}]/u", '', $s );
	return trim( preg_replace( '/\s+/', ' ', $s ) );
}

/**
 * Os sinônimos que já estão cadastrados no Relevanssi (Configurações > Relevanssi >
 * Sinônimos). Era por isso que a página /?s= achava "dopller" e "ultrasom" e o
 * autocomplete não: os dois tinham dicionários diferentes. Agora o painel lê o
 * mesmo cadastro, e quem mantém a lista mantém num lugar só.
 */
function sinonimos_relevanssi() {
	$opt = get_option( 'relevanssi_synonyms' );
	$txt = is_array( $opt ) ? implode( ';', $opt ) : (string) $opt;
	$out = array();
	foreach ( explode( ';', $txt ) as $par ) {
		if ( false === strpos( $par, '=' ) ) {
			continue;
		}
		list( $de, $para ) = explode( '=', $par, 2 );
		$de   = chave( $de );
		$para = chave( trim( trim( $para ), '"' ) );
		if ( '' === $de || '' === $para || $de === $para ) {
			continue;
		}
		$out[ $de ][] = $para;
	}
	return $out;
}

/**
 * Junta os apelidos daqui com os sinônimos do Relevanssi. Sinônimo importado só
 * entra se o alvo existir no catálogo (a mesma armadilha do "tc"); os apelidos
 * daqui entram sempre, para o teste de QA acusar quando um deles perde o lastro.
 */
function apelidos_completos( $feno ) {
	$todos = apelidos();
	foreach ( sinonimos_relevanssi() as $de => $alvos ) {
		foreach ( $alvos as $alvo ) {
			if ( ! preg_match( '/(^|[^a-z0-9])' . preg_quote( $alvo, '/' ) . '/', $feno ) ) {
				continue;
			}
			if ( ! isset( $todos[ $de ] ) ) {
				$todos[ $de ] = array();
			}
			if ( ! in_array( $alvo, $todos[ $de ], true ) ) {
				$todos[ $de ][] = $alvo;
			}
		}
	}
	return $todos;
}

/** IDs dos cursos da Sanar, guardados por 12h. */
function ids_sanar() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = wp_cache_get( 'ids_sanar', 'cetrus_ac' );
	if ( ! is_array( $ids ) ) {
		$ids = array_map( 'intval', get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => TAG_SANAR ) ),
		) ) );
		wp_cache_set( 'ids_sanar', $ids, 'cetrus_ac', TTL );
	}
	$ids = array_flip( $ids );
	return $ids;
}

/* -------------------------------------------------------------------------
 * Modo de operação
 * ---------------------------------------------------------------------- */

/**
 * `on` carrega para todo mundo, `preview` só com ?ac=1 na URL (a query string
 * fura o edge cache do WordPress.com, que é como se valida em produção aqui),
 * `off` não carrega nada. Trocar com:
 *   wp option update cetrus_ac_modo on
 */
function modo() {
	$m = get_option( OPT_MODO, 'preview' );
	return in_array( $m, array( 'on', 'preview', 'off' ), true ) ? $m : 'preview';
}

function ativo() {
	$m = modo();
	if ( 'off' === $m ) {
		return false;
	}
	if ( 'preview' === $m && ! isset( $_GET['ac'] ) ) {
		return false;
	}
	// A página de resultados tem o mesmo formulário da home, então quem quer
	// refinar a busca também ganha as sugestões.
	return (bool) apply_filters( 'cetrus_ac_carregar', is_front_page() || is_search() );
}

/* -------------------------------------------------------------------------
 * Índice
 * ---------------------------------------------------------------------- */

function versao_indice() {
	$v = get_option( OPT_VERSAO );
	if ( ! $v ) {
		$v = (string) time();
		update_option( OPT_VERSAO, $v, false );
	}
	return $v;
}

function invalidar() {
	delete_transient( TRANSIENT );
	wp_cache_delete( 'ids_sanar', 'cetrus_ac' );
	update_option( OPT_VERSAO, (string) time(), false );
	// Remontar o índice leva ~2,5s. Sem este agendamento a conta cai no primeiro
	// visitante que focar a busca depois de alguém salvar um curso. Os 30s também
	// agrupam a rajada de saves de uma edição em lote numa remontagem só.
	if ( ! wp_next_scheduled( 'cetrus_ac_aquecer' ) ) {
		wp_schedule_single_event( time() + 30, 'cetrus_ac_aquecer' );
	}
}

add_action( 'cetrus_ac_aquecer', function () {
	delete_transient( TRANSIENT );
	indice();
} );

add_action( 'save_post_product', __NAMESPACE__ . '\\invalidar' );
add_action( 'deleted_post', function ( $id, $post = null ) {
	if ( $post && 'product' === $post->post_type ) {
		invalidar();
	}
}, 10, 2 );
foreach ( array( 'professor', 'v2_especialidade', 'v2_modalidade', 'v2_cidade' ) as $tax ) {
	add_action( "edited_{$tax}", __NAMESPACE__ . '\\invalidar' );
	add_action( "delete_{$tax}", __NAMESPACE__ . '\\invalidar' );
}

function texto( $s ) {
	return trim( html_entity_decode( wp_strip_all_tags( (string) $s ), ENT_QUOTES, 'UTF-8' ) );
}

/** Nomes dos termos de uma taxonomia, já limpos. */
function nomes( $post_id, $tax ) {
	$t = get_the_terms( $post_id, $tax );
	if ( ! $t || is_wp_error( $t ) ) {
		return array();
	}
	return array_values( array_filter( array_map( function ( $x ) {
		return texto( $x->name );
	}, $t ) ) );
}

/**
 * Monta o índice a partir dos produtos publicados.
 *
 * Cada curso vira uma tupla (array, não objeto) para o JSON não repetir 400 vezes
 * o nome das chaves:
 *   0 título · 1 caminho da URL · 2 especialidades · 3 coordenadores
 *   4 modalidade · 5 cidade · 6 vagas encerradas (1/0) · 7 curso da Sanar (1/0)
 *   8 todas as cidades (a 5 é só a primeira, que é a que aparece na linha)
 */
function construir_indice() {
	$ids = get_posts( array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => -1,
		'fields'              => 'ids',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
		'orderby'             => 'title',
		'order'               => 'ASC',
		'tax_query'           => array(
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-search', 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
		),
	) );

	if ( ! $ids ) {
		return array( 'v' => versao_indice(), 'c' => array(), 'e' => array(), 'a' => apelidos_completos( '' ) );
	}

	// Sem aquecer os caches, os 407 produtos viram ~1.600 queries de termo e meta.
	_prime_post_caches( $ids, true, true );
	update_object_term_cache( $ids, 'product' );

	$cursos    = array();
	$feno      = '';      // catálogo inteiro normalizado, para conferir lastro de sinônimo
	$sanar     = ids_sanar();
	$contagem  = array(); // term_id => nº de cursos
	$termos    = array(); // term_id => objeto do termo
	$base      = trailingslashit( home_url() );

	foreach ( $ids as $id ) {
		$titulo = texto( get_the_title( $id ) );
		if ( '' === $titulo ) {
			continue;
		}

		$link = get_permalink( $id );
		if ( ! $link ) {
			continue;
		}
		// Guarda só o caminho; o JS remonta com o host da página.
		$caminho = '/' . ltrim( str_replace( $base, '', $link ), '/' );

		$esp_termos = get_the_terms( $id, 'v2_especialidade' );
		$esp_termos = ( $esp_termos && ! is_wp_error( $esp_termos ) ) ? $esp_termos : array();

		$especialidades = array();
		foreach ( $esp_termos as $t ) {
			$especialidades[] = texto( $t->name );
			// A contagem sai dos cursos que entram no índice. O `count` do termo não
			// serve: ele soma currículos e rascunhos junto.
			$termos[ $t->term_id ] = $t;
			$contagem[ $t->term_id ] = isset( $contagem[ $t->term_id ] ) ? $contagem[ $t->term_id ] + 1 : 1;
		}

		$coordenadores = nomes( $id, 'professor' );
		$modalidades   = nomes( $id, 'v2_modalidade' );
		$cidades       = nomes( $id, 'v2_cidade' );

		$cursos[] = array(
			$titulo,
			$caminho,
			implode( ' | ', $especialidades ),
			implode( ' | ', $coordenadores ),
			$modalidades ? $modalidades[0] : '',
			$cidades ? $cidades[0] : '',
			get_post_meta( $id, 'as_vagas_para_este_curso_estao_encerradas_', true ) ? 1 : 0,
			isset( $sanar[ $id ] ) ? 1 : 0,
			implode( ' | ', $cidades ),
		);
		$feno .= "\n" . strtolower( remove_accents( implode( ' ', array( $titulo, implode( ' ', $especialidades ), implode( ' ', $coordenadores ), implode( ' ', $modalidades ), implode( ' ', $cidades ) ) ) ) );
	}

	$especialidades = array();
	foreach ( $contagem as $term_id => $n ) {
		$link = get_term_link( $termos[ $term_id ] );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$especialidades[] = array(
			texto( $termos[ $term_id ]->name ),
			'/' . ltrim( str_replace( $base, '', $link ), '/' ),
			$n,
		);
	}
	usort( $especialidades, function ( $a, $b ) {
		return $b[2] <=> $a[2];
	} );

	return array(
		'v' => versao_indice(),
		'c' => $cursos,
		'e' => $especialidades,
		'a' => apelidos_completos( $feno ),
	);
}

function indice() {
	$cache = get_transient( TRANSIENT );
	if ( is_array( $cache ) && isset( $cache['c'] ) ) {
		return $cache;
	}
	$i = construir_indice();
	set_transient( TRANSIENT, $i, TTL );
	return $i;
}

/* -------------------------------------------------------------------------
 * Rota REST
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route( REST_NS, '/' . REST_ROTA, array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			$i        = indice();
			$resposta = new \WP_REST_Response( $i, 200 );

			// A URL sempre carrega ?v=<versao>, que muda quando um curso é salvo.
			// Com isso dá para deixar o navegador guardar por um dia inteiro.
			$pediu_versao = (string) $req->get_param( 'v' );
			$max_age      = ( $pediu_versao && $pediu_versao === (string) $i['v'] ) ? DAY_IN_SECONDS : 300;

			$resposta->header( 'Cache-Control', 'public, max-age=' . $max_age );
			$resposta->header( 'X-Cetrus-AC-Cursos', (string) count( $i['c'] ) );
			return $resposta;
		},
	) );
} );

function url_indice() {
	return add_query_arg( 'v', versao_indice(), rest_url( REST_NS . '/' . REST_ROTA ) );
}

/**
 * Caminhos dos cursos que a página de resultados devolveria para o termo.
 *
 * É o plano B do painel: o casamento no navegador não achou nada, então pergunta
 * ao Relevanssi, que é o motor da página /?s= (busca aproximada ligada, sinônimos,
 * conteúdo da página do curso). Assim o painel nunca diz "nada encontrado" quando
 * o botão Pesquisar acharia algo.
 */
function buscar_no_catalogo( $termo, $max = 8 ) {
	$args = array(
		's'              => $termo,
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $max,
		'no_found_rows'  => true,
	);
	$q = new \WP_Query();
	$q->parse_query( $args );
	$posts = function_exists( 'relevanssi_do_query' ) ? relevanssi_do_query( $q ) : $q->query( $args );

	$base = trailingslashit( home_url() );
	$out  = array();
	foreach ( (array) $posts as $p ) {
		$link = get_permalink( is_object( $p ) ? $p->ID : (int) $p );
		if ( $link ) {
			$out[] = '/' . ltrim( str_replace( $base, '', $link ), '/' );
		}
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( REST_NS, '/' . REST_BUSCA, array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'q' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( $req ) {
			$termo = mb_substr( trim( preg_replace( '/\s+/u', ' ', (string) $req->get_param( 'q' ) ) ), 0, 60 );
			if ( mb_strlen( $termo ) < 3 ) {
				return new \WP_REST_Response( array( 'q' => $termo, 'p' => array() ), 200 );
			}
			$cache = 'b_' . md5( versao_indice() . '|' . mb_strtolower( $termo ) );
			$p     = wp_cache_get( $cache, 'cetrus_ac' );
			if ( ! is_array( $p ) ) {
				$p = buscar_no_catalogo( $termo );
				wp_cache_set( $cache, $p, 'cetrus_ac', HOUR_IN_SECONDS );
			}
			$resposta = new \WP_REST_Response( array( 'q' => $termo, 'p' => $p ), 200 );
			$resposta->header( 'Cache-Control', 'public, max-age=3600' );
			return $resposta;
		},
	) );
} );

/* -------------------------------------------------------------------------
 * Página de resultados (/?s=)
 * ---------------------------------------------------------------------- */

// Mesmo critério do autocomplete: curso da Sanar não fura a fila do Cetrus.
add_filter( 'relevanssi_match', function ( $match ) {
	if ( isset( $match->doc ) && isset( ids_sanar()[ (int) $match->doc ] ) ) {
		$match->weight *= PESO_SANAR_RESULTADO;
	}
	return $match;
} );

/**
 * Limpa o termo antes de buscar e redireciona para a URL limpa.
 *
 * Dois problemas que apareciam no GA4 (search_term) e na própria busca:
 *  - link de campanha montado com a UTM dentro do termo, "?s=ultrassom/?utm_source=google"
 *    (1.506 eventos no ano). O Relevanssi buscava "ultrassom/?utm_source=google";
 *  - o mesmo termo partido em variantes de caixa e espaço ("Ginecologia ",
 *    "GINECOLOGIA"), que o relatório conta como buscas diferentes.
 * A UTM vira parâmetro de verdade, então a atribuição da campanha não se perde.
 */
add_action( 'template_redirect', function () {
	if ( is_admin() || ! is_search() || ! isset( $_GET['s'] ) || ! is_string( $_GET['s'] ) ) {
		return;
	}
	$bruto = wp_unslash( $_GET['s'] );
	$termo = $bruto;
	$extra = array();
	if ( preg_match( '#^(.*?)/?\?(.*)$#s', $termo, $m ) ) {
		$termo = $m[1];
		parse_str( $m[2], $extra );
	}
	$termo = rtrim( mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $termo ) ), 'UTF-8' ), '/' );
	if ( $termo === $bruto ) {
		return;
	}
	$args = wp_unslash( $_GET );
	foreach ( $extra as $k => $v ) {
		if ( ! isset( $args[ $k ] ) && is_string( $v ) ) {
			$args[ $k ] = $v;
		}
	}
	$args['s'] = $termo;
	wp_safe_redirect( add_query_arg( urlencode_deep( $args ), home_url( '/' ) ), 301 );
	exit;
} );

/* -------------------------------------------------------------------------
 * Front-end
 * ---------------------------------------------------------------------- */

function css() {
	/* Tudo com escopo em .cetrus-ac-painel de propósito. O kit global do Elementor
	   traz `.elementor-kit-10452 button { background: <azul Cetrus> }`, que tem
	   mais peso que uma classe sozinha e pintava a linha "Ver todos os resultados"
	   de azul-marinho, como se fosse um botão. Dois níveis de classe ganham dele
	   sem precisar de !important. */
	return <<<CSS
/* O contêiner que a hero já trazia ficou anos vazio e tem CSS antigo por id.
   O painel novo é outro elemento, então o antigo só precisa sair da frente. */
#live-search-results { display: none !important; }

.cetrus-ac-painel {
	position: absolute;
	top: calc(100% + 6px);
	left: 0;
	right: 0;
	z-index: 999;
	background: #fff;
	border: 1px solid #E3E6E9;
	border-radius: 8px;
	box-shadow: 0 12px 32px rgba(0, 59, 108, .16);
	max-height: 60vh;
	overflow-y: auto;
	overscroll-behavior: contain;
	text-align: left;
	padding: 6px 0;
	font-family: Roboto, sans-serif;
	display: none;
}
.cetrus-ac-painel[data-aberto="1"] { display: block; }

.cetrus-ac-painel .cetrus-ac-grupo {
	font-family: BwModelica, Roboto, sans-serif;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: .08em;
	text-transform: uppercase;
	color: #6B7A8C;
	padding: 10px 16px 6px;
	margin: 0;
}
.cetrus-ac-painel .cetrus-ac-grupo:first-child { padding-top: 6px; }

.cetrus-ac-painel .cetrus-ac-item,
.cetrus-ac-painel .cetrus-ac-rodape {
	display: block;
	width: 100%;
	box-sizing: border-box;
	text-align: left;
	background: #fff;
	border: 0;
	border-radius: 0;
	box-shadow: none;
	text-transform: none;
	letter-spacing: normal;
	font-family: Roboto, sans-serif;
	text-decoration: none;
	cursor: pointer;
	margin: 0;
	min-height: 0;
}

.cetrus-ac-painel .cetrus-ac-item {
	padding: 9px 16px;
	color: #0F1B2B;
}
.cetrus-ac-painel .cetrus-ac-item:hover,
.cetrus-ac-painel .cetrus-ac-item[data-ativo="1"] { background: #F1F5FA; color: #0F1B2B; }
.cetrus-ac-painel .cetrus-ac-item:focus-visible { outline: 2px solid #003B6C; outline-offset: -2px; }

.cetrus-ac-painel .cetrus-ac-titulo {
	font-size: 14px;
	font-weight: 400;
	line-height: 1.35;
	color: #0F1B2B;
	/* Título de curso aqui chega a 120 caracteres e no celular quebrava em 3
	   linhas, deixando ~4 sugestões visíveis. Duas linhas dão altura previsível. */
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}
.cetrus-ac-painel .cetrus-ac-sub {
	font-size: 12px;
	line-height: 1.35;
	color: #6B7A8C;
	display: block;
	margin-top: 2px;
	/* Uma linha só. "Coordenador · Modalidade · Cidade" quebrava em duas no
	   celular e a altura da linha ficava imprevisível. O corte é no fim, então
	   o nome do coordenador, que é o que importa, sempre sobra. */
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.cetrus-ac-painel .cetrus-ac-item mark {
	background: none;
	color: #003B6C;
	font-weight: 700;
	padding: 0;
}
.cetrus-ac-painel .cetrus-ac-selo {
	display: inline-block;
	font-size: 10px;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
	color: #8A5A00;
	background: #FFF4DE;
	border-radius: 4px;
	padding: 1px 6px;
	margin-left: 6px;
	vertical-align: 1px;
}

.cetrus-ac-painel .cetrus-ac-vazio {
	padding: 14px 16px;
	font-size: 13px;
	line-height: 1.4;
	color: #6B7A8C;
}
.cetrus-ac-painel .cetrus-ac-rodape {
	border-top: 1px solid #EDF0F3;
	margin-top: 6px;
	padding: 11px 16px;
	font-size: 13px;
	font-weight: 700;
	color: #003B6C;
}
.cetrus-ac-painel .cetrus-ac-rodape:hover,
.cetrus-ac-painel .cetrus-ac-rodape[data-ativo="1"] { background: #F1F5FA; color: #003B6C; }

.cetrus-ac-sr {
	position: absolute;
	width: 1px; height: 1px;
	padding: 0; margin: -1px;
	overflow: hidden;
	clip: rect(0 0 0 0);
	white-space: nowrap;
	border: 0;
}

@media (max-width: 767px) {
	.cetrus-ac-painel { max-height: 50vh; }
	.cetrus-ac-painel .cetrus-ac-item { padding: 11px 14px; }
}
CSS;
}

function js() {
	$cfg = wp_json_encode( array(
		'url'    => url_indice(),
		'api'    => rest_url( REST_NS . '/' . REST_BUSCA ),
		'min'    => MIN_CARACTERES,
		'cursos' => MAX_CURSOS,
		'esp'    => MAX_ESPECIALIDADES,
		'sanar'  => PENALIDADE_SANAR,
		'busca'  => home_url( '/' ),
	) );

	return <<<JS
(function () {
	'use strict';
	var CFG = {$cfg};
	// Na home o campo tem classe própria; na página de resultados é o mesmo
	// formulário sem a classe.
	var SEL = '.search-input-cetrus, form.search-form input[name="s"]';

	/* ---- texto ------------------------------------------------------- */

	function semAcento(s) {
		return String(s || '').normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase();
	}

	/* Normaliza mantendo o mapa de posições para o original, senão o destaque
	   escorrega uma casa a cada acento (o NFD transforma "á" em dois caracteres).
	   Pontuação vira espaço, que é troca de 1 por 1 e não mexe no mapa. */
	function normalizar(s) {
		var fora = '', mapa = [], i, k, d;
		s = String(s || '');
		for (i = 0; i < s.length; i++) {
			d = semAcento(s[i]);
			for (k = 0; k < d.length; k++) { fora += d[k]; mapa.push(i); }
		}
		return { t: fora.replace(/[^a-z0-9]/g, ' '), m: mapa };
	}

	/* Pontuação que gruda sigla e palavra composta. "BI-RADS" precisa casar com
	   "birads" e com "bi rads", então o texto de busca de cada campo guarda as
	   duas formas: separada por espaço e colada. */
	var GRUDA = /[-.'\\u2019]/g;
	function feno(s) {
		var a = semAcento(s);
		var sep = a.replace(/[^a-z0-9]/g, ' ');
		var col = a.replace(GRUDA, '').replace(/[^a-z0-9]/g, ' ');
		return col === sep ? sep : sep + ' | ' + col;
	}

	function consultaDe(bruto) {
		return semAcento(bruto).replace(GRUDA, '').replace(/[^a-z0-9]+/g, ' ').trim();
	}

	/* Palavras que não ajudam a achar curso e, por serem exigidas, zeravam a lista:
	   "titulo DE especialista", "CURSO intensivo", "ultrassom EM sao paulo". Só saem
	   quando sobra outra palavra na busca. */
	var VAZIAS = {};
	'a o e de da do das dos em na no nas nos para com curso cursos preco precos valor valores quanto custa'
		.split(' ').forEach(function (p) { VAZIAS[p] = 1; });

	function tokensDe(bruto) {
		var todos = consultaDe(bruto).split(' ').filter(Boolean);
		var uteis = todos.filter(function (t) { return !VAZIAS[t]; });
		return uteis.length ? uteis : todos;
	}

	function escaparRegex(s) { return s.replace(/[.*+?^\${}()|[\\]\\\\]/g, '\\\\\$&'); }
	function escaparHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	/* ---- índice ------------------------------------------------------ */

	var promessa = null;
	function carregar() {
		if (promessa) { return promessa; }
		promessa = fetch(CFG.url, { credentials: 'omit' })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
			.then(function (d) {
				var vocab = {};
				function guardar(txt) {
					txt.split(/[^a-z0-9]+/).forEach(function (w) { if (w.length >= 4) { vocab[w] = 1; } });
				}
				d.porCaminho = {};
				d.c.forEach(function (c) {
					c.nt = normalizar(c[0]);
					c.ht = feno(c[0]);
					c.ne = feno(c[2]);
					c.nc = feno(c[3]);
					c.nm = feno(c[4]);
					c.nl = feno(c[8] || c[5]);
					[c.ht, c.ne, c.nc, c.nm, c.nl].forEach(guardar);
					d.porCaminho[c[1]] = c;
				});
				d.e.forEach(function (e) { e.nt = normalizar(e[0]); guardar(e.nt.t); });
				d.vocab = Object.keys(vocab);
				return d;
			})
			.catch(function () { promessa = null; return null; });
		return promessa;
	}

	/* ---- erro de digitação ------------------------------------------- */

	/* Distância de edição com troca de vizinhas ("ginecolgia", "dopller"), que
	   para de calcular assim que passa do limite. */
	function distancia(a, b, lim) {
		if (Math.abs(a.length - b.length) > lim) { return lim + 1; }
		var ant2 = [], ant = [], at, i, j, custo, menor;
		for (j = 0; j <= b.length; j++) { ant[j] = j; }
		for (i = 1; i <= a.length; i++) {
			at = [i]; menor = i;
			for (j = 1; j <= b.length; j++) {
				custo = a[i - 1] === b[j - 1] ? 0 : 1;
				at[j] = Math.min(ant[j] + 1, at[j - 1] + 1, ant[j - 1] + custo);
				if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
					at[j] = Math.min(at[j], ant2[j - 2] + 1);
				}
				if (at[j] < menor) { menor = at[j]; }
			}
			if (menor > lim) { return lim + 1; }
			ant2 = ant; ant = at;
		}
		return ant[b.length];
	}

	/* Palavras do catálogo a 1 letra de distância (2 em palavra de 8 ou mais).
	   Também compara com o começo da palavra, porque quem digita "ultrasonog"
	   ainda não terminou. Só roda para palavra que não existe no catálogo, então
	   não muda nenhuma busca que já funcionava. */
	var aprox = {};
	function aproximar(token, dados) {
		if (aprox[token]) { return aprox[token]; }
		var n = token.length, i, w, d, lim, melhor, achadas = [];
		if (n < 5 || /[0-9]/.test(token) || dados.a[token]) { return (aprox[token] = []); }
		for (i = 0; i < dados.vocab.length; i++) {
			if (dados.vocab[i].indexOf(token) === 0) { return (aprox[token] = []); }
		}
		lim = n >= 8 ? 2 : 1;
		melhor = lim + 1;
		for (i = 0; i < dados.vocab.length; i++) {
			w = dados.vocab[i];
			if (w.length < n - lim) { continue; }
			d = distancia(token, w, lim);
			if (w.length > n) { d = Math.min(d, distancia(token, w.slice(0, n), lim), distancia(token, w.slice(0, n + 1), lim)); }
			if (d < melhor) { melhor = d; achadas = [w]; }
			else if (d === melhor && achadas.length < 6) { achadas.push(w); }
		}
		return (aprox[token] = melhor <= lim ? achadas : []);
	}

	/* ---- casamento --------------------------------------------------- */

	function variantes(token, apelidos) {
		var v = [token];
		if (apelidos && apelidos[token]) { v = v.concat(apelidos[token]); }
		return v;
	}

	function prefixo(hay, token) {
		return new RegExp('(^|[^a-z0-9])' + escaparRegex(token)).test(hay);
	}

	/* 10 prefixo no título, 7 no coordenador, 6 na especialidade, 4 na modalidade
	   e na cidade; achado só no meio da palavra vale pouco. Token que não aparece
	   em lugar nenhum desqualifica o curso, então "ultrassom fetal" não devolve
	   tudo de USG. */
	function pontos(hay, token, apelidos, altoPrefixo, altoDentro) {
		var vs = variantes(token, apelidos), i;
		for (i = 0; i < vs.length; i++) {
			if (prefixo(hay, vs[i])) { return altoPrefixo; }
		}
		// Achado no meio da palavra só conta para token longo. Com 2 ou 3 letras
		// "rm" casa com "fáRMaco" e a lista vira ruído.
		if (token.length < 4 || !altoDentro) { return 0; }
		for (i = 0; i < vs.length; i++) {
			if (hay.indexOf(vs[i]) !== -1) { return altoDentro; }
		}
		return 0;
	}

	function bonusTitulo(ht, frases) {
		var b = 0;
		frases.forEach(function (f) {
			if (ht.indexOf(f) === 0) { b = Math.max(b, 20); }
			else if (ht.indexOf(f) !== -1) { b = Math.max(b, 10); }
		});
		return b;
	}

	function pontuar(curso, tokens, consulta, apelidos) {
		var total = 0, i, melhor;
		for (i = 0; i < tokens.length; i++) {
			melhor = Math.max(
				pontos(curso.ht, tokens[i], apelidos, 10, 5),
				pontos(curso.nc, tokens[i], apelidos, 7, 3),
				pontos(curso.ne, tokens[i], apelidos, 6, 2),
				pontos(curso.nm, tokens[i], apelidos, 4, 0),
				pontos(curso.nl, tokens[i], apelidos, 4, 0)
			);
			if (melhor === 0) { return -1; }
			total += melhor;
		}
		total += bonusTitulo(curso.ht, [consulta]);
		// Palavra digitada errada ("ultrasom") não está em título nenhum, então o
		// bônus de "título começa com a busca" vai para a palavra corrigida.
		if (tokens.length === 1 && apelidos[tokens[0]] && !bonusTitulo(curso.ht, [consulta])) {
			total += bonusTitulo(curso.ht, apelidos[tokens[0]]);
		}
		if (curso[7]) { total -= CFG.sanar; }
		if (curso[6]) { total -= 100; }
		return total;
	}

	/* Apelidos da busca atual: os do índice mais as correções de digitação. */
	function apelidosDa(tokens, dados) {
		var ap = Object.create(dados.a);
		tokens.forEach(function (t) {
			var corr = aproximar(t, dados);
			if (corr.length) { ap[t] = (dados.a[t] || []).concat(corr); }
		});
		return ap;
	}

	function unicos(cursos) {
		// O catálogo tem alguns cursos cadastrados duas vezes (mesmo título e
		// mesmo coordenador em dois slugs). Sem isto eles comem duas vagas da
		// lista mostrando exatamente a mesma linha.
		var vistos = {}, fora = [], chave, i;
		for (i = 0; i < cursos.length; i++) {
			chave = cursos[i].item.nt.t + '::' + cursos[i].item.nc;
			if (vistos[chave]) { continue; }
			vistos[chave] = 1;
			fora.push(cursos[i]);
		}
		return fora;
	}

	function filtrar(dados, bruto) {
		var tokens = tokensDe(bruto);
		if (!tokens.length) { return { cursos: [], esp: [], tokens: [], ap: dados.a }; }
		var consulta = tokens.join(' ');
		var ap = apelidosDa(tokens, dados);

		var cursos = [], i, p;
		for (i = 0; i < dados.c.length; i++) {
			p = pontuar(dados.c[i], tokens, consulta, ap);
			if (p > -1) { cursos.push({ item: dados.c[i], p: p }); }
		}
		cursos.sort(function (a, b) { return b.p - a.p || a.item[0].localeCompare(b.item[0], 'pt'); });
		cursos = unicos(cursos);

		var esp = [];
		for (i = 0; i < dados.e.length; i++) {
			var ok = tokens.every(function (t) {
				return pontos(dados.e[i].nt.t, t, ap, 1, 1) > 0;
			});
			if (ok) { esp.push(dados.e[i]); }
		}

		return { cursos: cursos.slice(0, CFG.cursos), esp: esp.slice(0, CFG.esp), total: cursos.length, tokens: tokens, ap: ap, origem: 'indice' };
	}

	/* ---- plano B: o motor da página de resultados -------------------- */

	var remotos = {};
	function catalogo(bruto) {
		var q = bruto.trim().toLowerCase();
		if (remotos[q]) { return remotos[q]; }
		var url = CFG.api + (CFG.api.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q);
		remotos[q] = fetch(url, { credentials: 'omit' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.catch(function () { return null; })
			.then(function (d) { if (!d) { delete remotos[q]; } return d; });
		return remotos[q];
	}

	/* ---- destaque ---------------------------------------------------- */

	function destacar(original, norm, tokens, apelidos) {
		var faixas = [];
		tokens.forEach(function (tk) {
			variantes(tk, apelidos).forEach(function (t) {
				var re = new RegExp(escaparRegex(t), 'g'), m;
				while ((m = re.exec(norm.t)) !== null) {
					faixas.push([m.index, m.index + t.length]);
					if (re.lastIndex === m.index) { re.lastIndex++; }
				}
			});
		});
		if (!faixas.length) { return escaparHtml(original); }

		faixas.sort(function (a, b) { return a[0] - b[0]; });
		var juntas = [faixas[0]], i;
		for (i = 1; i < faixas.length; i++) {
			if (faixas[i][0] <= juntas[juntas.length - 1][1]) {
				juntas[juntas.length - 1][1] = Math.max(juntas[juntas.length - 1][1], faixas[i][1]);
			} else { juntas.push(faixas[i]); }
		}

		var html = '', cursor = 0;
		juntas.forEach(function (f) {
			var a = norm.m[f[0]];
			var b = f[1] - 1 < norm.m.length ? norm.m[f[1] - 1] + 1 : original.length;
			if (a === undefined || a < cursor) { return; }
			html += escaparHtml(original.slice(cursor, a));
			html += '<mark>' + escaparHtml(original.slice(a, b)) + '</mark>';
			cursor = b;
		});
		return html + escaparHtml(original.slice(cursor));
	}

	/* A linha de baixo mostra a primeira cidade do curso. Se a busca foi por uma
	   cidade, mostra a cidade buscada, senão "recife" achava o curso e a linha
	   dizia "São Paulo". */
	function cidadeDa(it, tokens, ap) {
		var todas = (it[8] || it[5] || '').split(' | ');
		for (var i = 0; i < todas.length; i++) {
			var f = feno(todas[i]);
			for (var k = 0; k < tokens.length; k++) {
				if (pontos(f, tokens[k], ap, 1, 0)) { return todas[i]; }
			}
		}
		return it[5];
	}

	/* ---- painel ------------------------------------------------------ */

	var seq = 0;

	function montar(input) {
		var ancora = input.closest('.position-relative') || input.parentElement;
		var form = input.closest('form');
		if (!ancora || !form) { return; }

		var id = 'cetrus-ac-' + (++seq);
		var painel = document.createElement('div');
		painel.className = 'cetrus-ac-painel';
		painel.id = id;
		painel.setAttribute('role', 'listbox');
		ancora.appendChild(painel);

		var aviso = document.createElement('div');
		aviso.className = 'cetrus-ac-sr';
		aviso.setAttribute('aria-live', 'polite');
		ancora.appendChild(aviso);

		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('aria-controls', id);

		var dados = null, itens = [], ativo = -1, ultimo = '', espera = null, semSugestao = {};

		/* max-height fixo no CSS deixava o painel passar da dobra na hero (ele começa
		   a ~400px do topo). Aqui ele recebe o espaço que realmente sobra abaixo do
		   campo. visualViewport porque no celular o teclado come altura da tela. */
		function ajustarAltura() {
			var r = input.getBoundingClientRect();
			var visivel = (window.visualViewport && window.visualViewport.height) || window.innerHeight;
			var espaco = visivel - r.bottom - 24;
			painel.style.maxHeight = Math.max(180, Math.min(520, espaco)) + 'px';
		}

		function abrir(html) {
			painel.innerHTML = html;
			itens = Array.prototype.slice.call(painel.querySelectorAll('[role="option"]'));
			ajustarAltura();
			painel.scrollTop = 0;
			painel.setAttribute('data-aberto', '1');
			input.setAttribute('aria-expanded', 'true');
			ativo = -1;
		}

		function fechar() {
			clearTimeout(espera);
			painel.removeAttribute('data-aberto');
			input.setAttribute('aria-expanded', 'false');
			input.removeAttribute('aria-activedescendant');
			itens = []; ativo = -1;
		}

		function marcar(i) {
			itens.forEach(function (el) { el.removeAttribute('data-ativo'); });
			ativo = i;
			if (i < 0 || !itens[i]) { input.removeAttribute('aria-activedescendant'); return; }
			itens[i].setAttribute('data-ativo', '1');
			input.setAttribute('aria-activedescendant', itens[i].id);
			var r = itens[i].getBoundingClientRect(), p = painel.getBoundingClientRect();
			if (r.bottom > p.bottom) { painel.scrollTop += r.bottom - p.bottom; }
			else if (r.top < p.top) { painel.scrollTop -= p.top - r.top; }
		}

		function empurrar(ev) {
			try {
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push(ev);
			} catch (e) {}
		}

		function ir(url, rotulo, tipo, origem) {
			empurrar({
				event: 'cetrus_busca_sugestao',
				busca_termo: input.value,
				busca_tipo: tipo,
				busca_escolha: rotulo,
				busca_origem: origem
			});
			window.location.href = url;
		}

		function linhaCurso(it, n, r, origem) {
			var sub = [it[3].split(' | ')[0], it[4], cidadeDa(it, r.tokens, r.ap)].filter(Boolean).join(' · ');
			var selo = it[6] ? '<span class="cetrus-ac-selo">Vagas encerradas</span>' : '';
			return '<a class="cetrus-ac-item" role="option" aria-selected="false" id="' + id + '-o' + n +
				'" href="' + escaparHtml(it[1]) + '" data-rotulo="' + escaparHtml(it[0]) + '" data-tipo="curso" data-origem="' + origem + '">' +
				'<span class="cetrus-ac-titulo">' + destacar(it[0], it.nt, r.tokens, r.ap) + selo + '</span>' +
				(sub ? '<span class="cetrus-ac-sub">' + destacar(sub, normalizar(sub), r.tokens, r.ap) + '</span>' : '') +
				'</a>';
		}

		function linhaEsp(e, n, r, origem) {
			return '<a class="cetrus-ac-item" role="option" aria-selected="false" id="' + id + '-o' + n +
				'" href="' + escaparHtml(e[1]) + '" data-rotulo="' + escaparHtml(e[0]) + '" data-tipo="especialidade" data-origem="' + origem + '">' +
				'<span class="cetrus-ac-titulo">' + destacar(e[0], e.nt, r.tokens, r.ap) + '</span>' +
				'<span class="cetrus-ac-sub">' + e[2] + (e[2] === 1 ? ' curso' : ' cursos') + '</span>' +
				'</a>';
		}

		function rodape(bruto, n) {
			return '<button type="button" class="cetrus-ac-rodape" id="' + id + '-o' + n + '" role="option" aria-selected="false" data-todos="1">' +
				'Ver todos os resultados para "' + escaparHtml(bruto.trim()) + '"</button>';
		}

		function desenhar(bruto, r) {
			var html = '', n = 0;

			if (r.cursos.length) {
				html += '<div class="cetrus-ac-grupo">Cursos</div>';
				r.cursos.forEach(function (c) { html += linhaCurso(c.item, n++, r, r.origem); });
			}
			if (r.esp.length) {
				html += '<div class="cetrus-ac-grupo">Especialidades</div>';
				r.esp.forEach(function (e) { html += linhaEsp(e, n++, r, r.origem); });
			}

			if (!n) {
				// Nada nem no índice nem no motor da página de resultados. Em vez de
				// um beco sem saída, as especialidades mais procuradas do catálogo.
				html += '<div class="cetrus-ac-vazio">Nada encontrado para <strong>' + escaparHtml(bruto.trim()) +
					'</strong>. Confira a grafia ou veja as especialidades mais procuradas.</div>';
				html += '<div class="cetrus-ac-grupo">Mais procuradas</div>';
				dados.e.slice(0, 4).forEach(function (e) { html += linhaEsp(e, n++, r, 'sugestao'); });
				var chave = bruto.trim().toLowerCase();
				if (!semSugestao[chave]) {
					semSugestao[chave] = 1;
					empurrar({ event: 'cetrus_busca_sem_sugestao', busca_termo: bruto.trim() });
				}
			}

			abrir(html + rodape(bruto, n));
			aviso.textContent = r.cursos.length
				? r.cursos.length + ' curso(s) sugerido(s)'
				: 'Nenhuma sugestão';
		}

		function resolver(bruto) {
			var r = filtrar(dados, bruto);
			clearTimeout(espera);
			if (r.cursos.length || r.esp.length || consultaDe(bruto).length < 3) {
				desenhar(bruto, r);
				return;
			}
			// O índice não achou. Antes de dizer "nada encontrado", pergunta ao
			// Relevanssi, que é quem responde quando a pessoa aperta Pesquisar.
			abrir('<div class="cetrus-ac-vazio">Buscando em todo o catálogo\u2026</div>' + rodape(bruto, 0));
			espera = setTimeout(function () {
				catalogo(bruto).then(function (d) {
					if ((input.value || '') !== bruto) { return; }
					var cursos = [];
					((d && d.p) || []).forEach(function (caminho) {
						var it = dados.porCaminho[caminho];
						if (it && !it[6]) { cursos.push({ item: it, p: 0 }); }
					});
					r.cursos = unicos(cursos).slice(0, CFG.cursos);
					r.origem = 'catalogo';
					desenhar(bruto, r);
				});
			}, 250);
		}

		function atualizar() {
			var bruto = input.value || '';
			if (bruto.trim().length < CFG.min) { fechar(); ultimo = ''; return; }
			if (bruto === ultimo && painel.hasAttribute('data-aberto')) { return; }
			ultimo = bruto;
			if (dados) { resolver(bruto); return; }
			// Primeira busca da sessão: o índice ainda está vindo. Mostrar o estado
			// evita o campo parecer quebrado enquanto a resposta não chega.
			abrir('<div class="cetrus-ac-vazio">Buscando cursos\u2026</div>');
			itens = [];
			carregar().then(function (d) {
				if (!d) { fechar(); return; }
				dados = d;
				if ((input.value || '').trim().length >= CFG.min && document.activeElement === input) {
					resolver(input.value);
				}
			});
		}

		input.addEventListener('focus', function () { carregar(); if (input.value.trim().length >= CFG.min) { atualizar(); } });
		input.addEventListener('input', atualizar);

		input.addEventListener('keydown', function (ev) {
			if (ev.key === 'Escape') { fechar(); return; }
			if (!painel.hasAttribute('data-aberto') || !itens.length) { return; }
			if (ev.key === 'ArrowDown') { ev.preventDefault(); marcar(ativo + 1 >= itens.length ? 0 : ativo + 1); }
			else if (ev.key === 'ArrowUp') { ev.preventDefault(); marcar(ativo - 1 < 0 ? itens.length - 1 : ativo - 1); }
			else if (ev.key === 'Enter') {
				// Sem item destacado o Enter continua enviando o formulário, como antes.
				if (ativo < 0) { return; }
				ev.preventDefault();
				itens[ativo].click();
			} else if (ev.key === 'Tab') { fechar(); }
		});

		painel.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
		painel.addEventListener('click', function (ev) {
			var alvo = ev.target.closest('[role="option"]');
			if (!alvo) { return; }
			ev.preventDefault();
			if (alvo.hasAttribute('data-todos')) { fechar(); form.submit(); return; }
			ir(alvo.getAttribute('href'), alvo.getAttribute('data-rotulo'), alvo.getAttribute('data-tipo'), alvo.getAttribute('data-origem'));
		});

		window.addEventListener('resize', function () {
			if (painel.hasAttribute('data-aberto')) { ajustarAltura(); }
		});
		document.addEventListener('click', function (ev) {
			if (!ancora.contains(ev.target)) { fechar(); }
		});
		input.addEventListener('blur', function () {
			setTimeout(function () { if (!ancora.contains(document.activeElement)) { fechar(); } }, 120);
		});
	}

	function iniciar() {
		var campos = document.querySelectorAll(SEL);
		if (!campos.length) { return; }
		Array.prototype.forEach.call(campos, function (c) {
			if (c.getAttribute('data-cetrus-ac')) { return; }
			c.setAttribute('data-cetrus-ac', '1');
			montar(c);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', iniciar);
	} else { iniciar(); }
})();
JS;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! ativo() ) {
		return;
	}

	wp_register_style( 'cetrus-ac', false, array(), VERSAO );
	wp_enqueue_style( 'cetrus-ac' );
	wp_add_inline_style( 'cetrus-ac', css() );

	wp_register_script( 'cetrus-ac', false, array(), VERSAO, true );
	wp_enqueue_script( 'cetrus-ac' );
	wp_add_inline_script( 'cetrus-ac', js() );
}, 20 );

/* -------------------------------------------------------------------------
 * WP-CLI
 * ---------------------------------------------------------------------- */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'cetrus-ac', function ( $args ) {
		$sub = isset( $args[0] ) ? $args[0] : 'status';

		if ( 'limpar' === $sub ) {
			invalidar();
			\WP_CLI::success( 'Índice invalidado. Nova versão: ' . versao_indice() );
			return;
		}

		if ( 'indice' === $sub ) {
			$i    = construir_indice();
			$json = wp_json_encode( $i );
			\WP_CLI::log( 'cursos ............ ' . count( $i['c'] ) );
			\WP_CLI::log( 'especialidades .... ' . count( $i['e'] ) );
			\WP_CLI::log( 'json .............. ' . number_format( strlen( $json ) / 1024, 1 ) . ' KiB' );
			\WP_CLI::log( 'json gzip ......... ' . number_format( strlen( gzencode( $json, 6 ) ) / 1024, 1 ) . ' KiB' );
			\WP_CLI::log( 'versao ............ ' . $i['v'] );
			return;
		}

		// wp cetrus-ac busca "dopller": o que o plano B do painel devolveria.
		if ( 'busca' === $sub ) {
			$termo = isset( $args[1] ) ? $args[1] : '';
			foreach ( buscar_no_catalogo( $termo ) as $caminho ) {
				\WP_CLI::log( $caminho );
			}
			return;
		}

		\WP_CLI::log( 'modo .............. ' . modo() );
		\WP_CLI::log( 'versao ............ ' . versao_indice() );
		\WP_CLI::log( 'url ............... ' . url_indice() );
		\WP_CLI::log( 'transient ......... ' . ( get_transient( TRANSIENT ) ? 'quente' : 'frio' ) );
	} );
}
