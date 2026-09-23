<?php
/**
 * Plugin Name: Cetrus — Autocomplete da busca
 * Description: Sugestões enquanto o usuário digita no campo de busca da home. Monta um índice de cursos publicados (título, coordenador, especialidade) servido por uma rota REST cacheada e faz o casamento no navegador, sem uma requisição por tecla. Não altera o _elementor_data: o painel é criado em runtime ao lado do campo, então desativar o arquivo devolve a busca ao comportamento anterior.
 * Version: 1.0.0
 * Author: Sanar / Cetrus
 */

namespace Cetrus\BuscaAutocomplete;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSAO      = '1.0.0';
const REST_NS     = 'cetrus/v1';
const REST_ROTA   = 'busca-indice';
const TRANSIENT   = 'cetrus_ac_indice_v1';
const TTL         = 12 * HOUR_IN_SECONDS;
const OPT_VERSAO  = 'cetrus_ac_versao';
const OPT_MODO    = 'cetrus_ac_modo';

/** Quantas sugestões o painel mostra por grupo. */
const MAX_CURSOS         = 6;
const MAX_ESPECIALIDADES = 3;
const MIN_CARACTERES     = 2;

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
	);
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
	return (bool) apply_filters( 'cetrus_ac_carregar', is_front_page() );
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
 *   4 modalidade · 5 cidade · 6 vagas encerradas (1/0)
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
		return array( 'v' => versao_indice(), 'c' => array(), 'e' => array(), 'a' => apelidos() );
	}

	// Sem aquecer os caches, os 407 produtos viram ~1.600 queries de termo e meta.
	_prime_post_caches( $ids, true, true );
	update_object_term_cache( $ids, 'product' );

	$cursos    = array();
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
		);
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
		'a' => apelidos(),
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
		'min'    => MIN_CARACTERES,
		'cursos' => MAX_CURSOS,
		'esp'    => MAX_ESPECIALIDADES,
		'busca'  => home_url( '/' ),
	) );

	return <<<JS
(function () {
	'use strict';
	var CFG = {$cfg};
	var SEL = '.search-input-cetrus';

	/* ---- texto ------------------------------------------------------- */

	/* Normaliza mantendo o mapa de posições para o original, senão o destaque
	   escorrega uma casa a cada acento (o NFD transforma "á" em dois caracteres). */
	function normalizar(s) {
		var fora = '', mapa = [], i, k, d;
		s = String(s || '');
		for (i = 0; i < s.length; i++) {
			d = s[i].normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase();
			for (k = 0; k < d.length; k++) { fora += d[k]; mapa.push(i); }
		}
		return { t: fora, m: mapa };
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
				d.c.forEach(function (c) {
					c.nt = normalizar(c[0]);
					c.ne = normalizar(c[2]).t;
					c.nc = normalizar(c[3]).t;
				});
				d.e.forEach(function (e) { e.nt = normalizar(e[0]); });
				return d;
			})
			.catch(function () { promessa = null; return null; });
		return promessa;
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

	/* 10 prefixo no título, 7 no coordenador, 6 na especialidade; achado só no
	   meio da palavra vale pouco. Token que não aparece em lugar nenhum
	   desqualifica o curso, então "ultrassom fetal" não devolve tudo de USG. */
	function pontos(hay, token, apelidos, altoPrefixo, altoDentro) {
		var vs = variantes(token, apelidos), i;
		for (i = 0; i < vs.length; i++) {
			if (prefixo(hay, vs[i])) { return altoPrefixo; }
		}
		// Achado no meio da palavra só conta para token longo. Com 2 ou 3 letras
		// "rm" casa com "fáRMaco" e a lista vira ruído.
		if (token.length < 4) { return 0; }
		for (i = 0; i < vs.length; i++) {
			if (hay.indexOf(vs[i]) !== -1) { return altoDentro; }
		}
		return 0;
	}

	function pontuar(curso, tokens, consulta, apelidos) {
		var total = 0, i, melhor;
		for (i = 0; i < tokens.length; i++) {
			melhor = Math.max(
				pontos(curso.nt.t, tokens[i], apelidos, 10, 5),
				pontos(curso.nc, tokens[i], apelidos, 7, 3),
				pontos(curso.ne, tokens[i], apelidos, 6, 2)
			);
			if (melhor === 0) { return -1; }
			total += melhor;
		}
		if (curso.nt.t.indexOf(consulta) === 0) { total += 20; }
		else if (curso.nt.t.indexOf(consulta) !== -1) { total += 10; }
		if (curso[6]) { total -= 100; }
		return total;
	}

	function filtrar(dados, bruto) {
		var consulta = normalizar(bruto).t.trim();
		var tokens = consulta.split(/\\s+/).filter(Boolean);
		if (!tokens.length) { return { cursos: [], esp: [] }; }

		var cursos = [], i, p;
		for (i = 0; i < dados.c.length; i++) {
			p = pontuar(dados.c[i], tokens, consulta, dados.a);
			if (p > -1) { cursos.push({ item: dados.c[i], p: p }); }
		}
		cursos.sort(function (a, b) { return b.p - a.p || a.item[0].localeCompare(b.item[0], 'pt'); });

		// O catálogo tem alguns cursos cadastrados duas vezes (mesmo título e
		// mesmo coordenador em dois slugs). Sem isto eles comem duas vagas da
		// lista mostrando exatamente a mesma linha.
		var vistos = {}, unicos = [], chave;
		for (i = 0; i < cursos.length; i++) {
			chave = cursos[i].item.nt.t + '::' + cursos[i].item.nc;
			if (vistos[chave]) { continue; }
			vistos[chave] = 1;
			unicos.push(cursos[i]);
		}
		cursos = unicos;

		var esp = [];
		for (i = 0; i < dados.e.length; i++) {
			var ok = tokens.every(function (t) {
				return pontos(dados.e[i].nt.t, t, dados.a, 1, 1) > 0;
			});
			if (ok) { esp.push(dados.e[i]); }
		}

		return { cursos: cursos.slice(0, CFG.cursos), esp: esp.slice(0, CFG.esp), total: cursos.length };
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
			if (a === undefined) { return; }
			html += escaparHtml(original.slice(cursor, a));
			html += '<mark>' + escaparHtml(original.slice(a, b)) + '</mark>';
			cursor = b;
		});
		return html + escaparHtml(original.slice(cursor));
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

		var dados = null, itens = [], ativo = -1, ultimo = '';

		/* max-height fixo no CSS deixava o painel passar da dobra na hero (ele começa
		   a ~400px do topo). Aqui ele recebe o espaço que realmente sobra abaixo do
		   campo. visualViewport porque no celular o teclado come altura da tela. */
		function ajustarAltura() {
			var r = input.getBoundingClientRect();
			var visivel = (window.visualViewport && window.visualViewport.height) || window.innerHeight;
			var espaco = visivel - r.bottom - 24;
			painel.style.maxHeight = Math.max(180, Math.min(520, espaco)) + 'px';
		}

		function fechar() {
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

		function ir(url, rotulo, tipo) {
			try {
				window.dataLayer = window.dataLayer || [];
				window.dataLayer.push({
					event: 'cetrus_busca_sugestao',
					busca_termo: input.value,
					busca_tipo: tipo,
					busca_escolha: rotulo
				});
			} catch (e) {}
			window.location.href = url;
		}

		function desenhar(bruto) {
			if (!dados) { return; }
			var r = filtrar(dados, bruto);
			var tokens = normalizar(bruto).t.trim().split(/\\s+/).filter(Boolean);
			var html = '', n = 0;
			itens = [];

			if (r.cursos.length) {
				html += '<div class="cetrus-ac-grupo">Cursos</div>';
				r.cursos.forEach(function (c) {
					var it = c.item;
					var sub = [it[3].split(' | ')[0], it[4], it[5]].filter(Boolean).join(' · ');
					var selo = it[6] ? '<span class="cetrus-ac-selo">Vagas encerradas</span>' : '';
					html += '<a class="cetrus-ac-item" role="option" aria-selected="false" id="' + id + '-o' + n +
						'" href="' + escaparHtml(it[1]) + '" data-rotulo="' + escaparHtml(it[0]) + '" data-tipo="curso">' +
						'<span class="cetrus-ac-titulo">' + destacar(it[0], it.nt, tokens, dados.a) + selo + '</span>' +
						(sub ? '<span class="cetrus-ac-sub">' + destacar(sub, normalizar(sub), tokens, dados.a) + '</span>' : '') +
						'</a>';
					n++;
				});
			}

			if (r.esp.length) {
				html += '<div class="cetrus-ac-grupo">Especialidades</div>';
				r.esp.forEach(function (e) {
					html += '<a class="cetrus-ac-item" role="option" aria-selected="false" id="' + id + '-o' + n +
						'" href="' + escaparHtml(e[1]) + '" data-rotulo="' + escaparHtml(e[0]) + '" data-tipo="especialidade">' +
						'<span class="cetrus-ac-titulo">' + destacar(e[0], e.nt, tokens, dados.a) + '</span>' +
						'<span class="cetrus-ac-sub">' + e[2] + (e[2] === 1 ? ' curso' : ' cursos') + '</span>' +
						'</a>';
					n++;
				});
			}

			if (!n) {
				html += '<div class="cetrus-ac-vazio">Nada encontrado para <strong>' + escaparHtml(bruto.trim()) +
					'</strong>. Tente o nome da especialidade.</div>';
			}

			html += '<button type="button" class="cetrus-ac-rodape" id="' + id + '-o' + n + '" role="option" aria-selected="false" data-todos="1">' +
				'Ver todos os resultados para "' + escaparHtml(bruto.trim()) + '"</button>';

			painel.innerHTML = html;
			itens = Array.prototype.slice.call(painel.querySelectorAll('[role="option"]'));
			ajustarAltura();
			painel.scrollTop = 0;
			painel.setAttribute('data-aberto', '1');
			input.setAttribute('aria-expanded', 'true');
			ativo = -1;
			aviso.textContent = r.cursos.length
				? r.cursos.length + ' curso(s) sugerido(s)'
				: 'Nenhuma sugestão';
		}

		function atualizar() {
			var bruto = input.value || '';
			if (bruto.trim().length < CFG.min) { fechar(); ultimo = ''; return; }
			if (bruto === ultimo && painel.hasAttribute('data-aberto')) { return; }
			ultimo = bruto;
			if (dados) { desenhar(bruto); return; }
			// Primeira busca da sessão: o índice ainda está vindo. Mostrar o estado
			// evita o campo parecer quebrado enquanto a resposta não chega.
			painel.innerHTML = '<div class="cetrus-ac-vazio">Buscando cursos\u2026</div>';
			ajustarAltura();
			painel.setAttribute('data-aberto', '1');
			input.setAttribute('aria-expanded', 'true');
			itens = []; ativo = -1;
			carregar().then(function (d) {
				if (!d) { fechar(); return; }
				dados = d;
				if ((input.value || '').trim().length >= CFG.min && document.activeElement === input) {
					desenhar(input.value);
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
			ir(alvo.getAttribute('href'), alvo.getAttribute('data-rotulo'), alvo.getAttribute('data-tipo'));
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

		\WP_CLI::log( 'modo .............. ' . modo() );
		\WP_CLI::log( 'versao ............ ' . versao_indice() );
		\WP_CLI::log( 'url ............... ' . url_indice() );
		\WP_CLI::log( 'transient ......... ' . ( get_transient( TRANSIENT ) ? 'quente' : 'frio' ) );
	} );
}
