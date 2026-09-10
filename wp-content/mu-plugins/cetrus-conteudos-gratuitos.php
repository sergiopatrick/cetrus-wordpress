<?php
/**
 * Plugin Name: Cetrus — Conteúdos Gratuitos
 * Description: Busca, filtro por especialidade, janela de inscrição com o formulário HubSpot embedado e link direto por material na /conteudos-gratuitos/. Não altera o _elementor_data da página: tudo é injetado no HTML renderizado, então desativar o arquivo devolve a página ao estado anterior.
 * Version: 1.0.0
 * Author: Sanar / Cetrus
 */

namespace Cetrus\ConteudosGratuitos;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PAGE_ID   = 27615;
const PAGE_SLUG = 'conteudos-gratuitos';
const PORTAL    = '9321751';
const REGION    = 'na1';
const QUERY_VAR = 'material';
const OPT_RULES = 'cetrus_cg_rules_v';
const RULES_VER = '1';
const VERSAO    = '1.0.0';

/**
 * Catálogo dos materiais.
 *
 * `card` é o id do container do Elementor na página (classe .card_isca), usado para
 * casar o item com o cartão já renderizado. `lp` é o caminho da landing page no
 * HubSpot, que serve de chave alternativa caso o id do cartão mude no Elementor.
 */
function catalogo() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$itens = array(

		'carreira-em-ultrassonografia' => array(
			'card'    => '88e7f40',
			'capa'    => 26816,
			'titulo'  => 'Ultrassonografia: o próximo passo na carreira médica',
			'desc'    => 'Descubra como a ultrassonografia está transformando a rotina e os ganhos de médicos que decidiram evoluir profissionalmente.',
			'formato' => 'Vídeo',
			'esp'     => array( 'Ultrassonografia', 'Carreira' ),
			'form'    => 'b8da8638-40e6-4ceb-b1a1-5d67a9ac8ce4',
			'lp'      => '/carreira-usg-geral',
		),

		'podcast-dor-cronica-e-medicina-regenerativa' => array(
			'card'    => '30590af',
			'capa'    => 26818,
			'titulo'  => 'Dor crônica e medicina regenerativa: para onde vamos?',
			'desc'    => 'Com os Drs. Marco Demange (USP) e Marcos Cardoso (Cetrus), um episódio sobre como a medicina regenerativa está mudando a forma de tratar a dor.',
			'formato' => 'Podcast',
			'esp'     => array( 'Medicina da Dor' ),
			'form'    => '89236908-d5f2-4821-8404-789261e0dfa0',
			'lp'      => '/podcast-dor-crônica-e-medicina-regenerativa',
		),

		'carreira-em-pediatria' => array(
			'card'    => 'd1632fd',
			'capa'    => 26819,
			'titulo'  => 'Carreiras na área da Pediatria',
			'desc'    => 'Como TDAH, TEA, alergias e obesidade infantil estão redefinindo o papel do pediatra generalista e abrindo espaço para a sub-especialização.',
			'formato' => 'Vídeo',
			'esp'     => array( 'Pediatria', 'Carreira' ),
			'form'    => '2a75e2d1-46f8-4245-b365-0f2c622abcb4',
			'lp'      => '/carreia-em-pediatria',
		),

		'gina-2025-lactente-sibilante' => array(
			'card'    => '8cde34b',
			'capa'    => 26821,
			'titulo'  => 'Atualização GINA 2025: desafios no lactente sibilante',
			'desc'    => 'O que mudou no diagnóstico e no manejo do lactente sibilante com as atualizações do GINA 2025, direto e prático para aplicar no dia a dia.',
			'formato' => 'Minicurso',
			'esp'     => array( 'Pediatria' ),
			'form'    => 'f17fe418-1b1e-4da3-a2a6-e13f2e2e6b83',
			'lp'      => '/minicurso-lactente-sibilante-2025',
		),

		'carreira-em-medicina-da-dor' => array(
			'card'    => '2b47ab3',
			'capa'    => 26837,
			'titulo'  => 'Domine a dor: construa uma carreira médica de destaque',
			'desc'    => 'Como se destacar na medicina da dor com técnicas modernas, um mercado promissor e abordagens que transformam a prática médica.',
			'formato' => 'Vídeo',
			'esp'     => array( 'Medicina da Dor', 'Carreira' ),
			'form'    => '1245d39c-1264-4b24-b4f7-c62c7880ba9a',
			'lp'      => '/dominado-a-dor',
		),

		'curso-botonologia' => array(
			'card'    => '0fe4911',
			'capa'    => 19084,
			'titulo'  => 'Botonologia: descomplicando os aparelhos',
			'desc'    => 'Entenda o funcionamento dos botões do aparelho de ultrassonografia e aprenda a usá-los de forma mais eficiente, com o Dr. Alexandre Ferraz.',
			'formato' => 'Curso',
			'esp'     => array( 'Ultrassonografia' ),
			'form'    => '06bbf1c0-b414-4423-bc8b-95be3255d9a9',
			'lp'      => '/curso-gratuito-botonologia',
		),

		'curso-hernias-femorais-na-usg' => array(
			'card'    => '9e86ea6',
			'capa'    => 19083,
			'titulo'  => 'Domine as hérnias femorais na USG',
			'desc'    => 'Identifique com precisão as hérnias femorais durante o exame de imagem e avalie corretamente seu paciente, com o Dr. Adriano Czapkowski.',
			'formato' => 'Curso',
			'esp'     => array( 'Ultrassonografia' ),
			'form'    => 'efbb1805-1497-4800-a063-1ee55f8ee075',
			'lp'      => '/curso-gratuito-us-das-hernias-femorais',
		),

		'curso-introducao-a-bi-rads' => array(
			'card'    => '5d957e0',
			'capa'    => 19082,
			'titulo'  => 'Introdução a BI-RADS',
			'desc'    => 'Aprenda a avaliar e classificar lesões da ultrassonografia de mama com a Dra. Patrícia Cravo, coordenadora da pós-graduação em USG mamária do Cetrus.',
			'formato' => 'Curso',
			'esp'     => array( 'Ultrassonografia' ),
			'form'    => 'fdc543d8-2949-4e22-b495-6b450121a291',
			'lp'      => '/curso-gratuito-bi-rads',
		),

		'masterclass-oportunidades-na-ultrassonografia' => array(
			'card'    => '861444a',
			'capa'    => 19070,
			'titulo'  => 'Oportunidades na ultrassonografia',
			'desc'    => 'Masterclass com o Dr. Caio Nunes sobre como melhorar os ganhos e ganhar qualidade de vida atuando com ultrassonografia.',
			'formato' => 'Masterclass',
			'esp'     => array( 'Ultrassonografia', 'Carreira' ),
			'form'    => '206bac12-2dc1-47c2-8f7f-1f128a0d99a3',
			'lp'      => '/masterclass-oportunidades-na-ultrassonografia',
		),

		'masterclass-tendinopatias-do-supraespinhal' => array(
			'card'    => '29c0938',
			'capa'    => 19078,
			'titulo'  => 'Como avaliar as tendinopatias do supraespinhal',
			'desc'    => 'Aula online gratuita com o Dr. Everaldo Gregio sobre a avaliação das tendinopatias do supraespinhal na ultrassonografia.',
			'formato' => 'Masterclass',
			'esp'     => array( 'Ultrassonografia' ),
			'form'    => '99d85378-fe25-43a5-beb7-60e7de012e09',
			'lp'      => '/masterclass-como-avaliar-as-tendinopatias-do-supraespinhal',
		),

		'ebook-parametros-em-medicina-fetal' => array(
			'card'    => '7c8e312',
			'capa'    => 26007,
			'titulo'  => 'As tabelas e medidas que guiam a avaliação fetal confiável',
			'desc'    => 'Tabelas e medidas por faixa gestacional e parâmetros biométricos: saber interpretar cada medida fetal é um diferencial técnico indispensável.',
			'formato' => 'E-book',
			'esp'     => array( 'Ultrassonografia em GO' ),
			'form'    => '859af58b-6c70-4dde-992f-bbe8211f8681',
			'lp'      => '/ebook-gratuito-parametros-de-referencia-em-medicina-fetal',
		),

		'ebook-mapas-mentais-gineco-endocrinos' => array(
			'card'    => 'bd82144',
			'capa'    => 26008,
			'titulo'  => '4 mapas mentais para dominar os eixos hormonais da mulher',
			'desc'    => 'Quatro mapas mentais para estudar de forma visual e direta, com foco no raciocínio clínico dos principais eixos hormonais da mulher.',
			'formato' => 'E-book',
			'esp'     => array( 'Ultrassonografia em GO' ),
			'form'    => '978e37e9-d126-49b5-9311-b162576b749a',
			'lp'      => '/ebook-gratuito-mapas-mentais-gineco-endocrinos',
		),

		'ebook-parametros-em-usg-ginecologica' => array(
			'card'    => 'ddf8766',
			'capa'    => 26011,
			'titulo'  => 'Parâmetros de referência essenciais para sua prática diária',
			'desc'    => 'Domine as medidas, os cortes e as interpretações que fazem diferença em laudos ginecológicos bem conduzidos.',
			'formato' => 'E-book',
			'esp'     => array( 'Ultrassonografia em GO' ),
			'form'    => 'afa0b505-27c2-4407-a17b-264f4718f9dd',
			'lp'      => '/ebook-gratuito-parametros-de-referencia-para-ultrassonografia-em-go',
		),

		'ebook-saude-financeira-para-medicos' => array(
			'card'    => '4d16e7e',
			'capa'    => 26041,
			'titulo'  => 'Saúde financeira para médicos',
			'desc'    => 'Uma série de dicas para entender como anda a saúde financeira da sua carreira médica e o que fazer para melhorá-la.',
			'formato' => 'E-book',
			'esp'     => array( 'Carreira' ),
			'form'    => 'a5ed57d5-297c-48f2-9a7b-26eec3017674',
			'lp'      => '/e-book-saude-financeira-para-medicos',
		),

		'ebook-calcificacao-caseosa-do-anel-mitral' => array(
			'card'    => 'ab8b2d9',
			'capa'    => 26045,
			'titulo'  => 'Calcificação caseosa do anel mitral',
			'desc'    => 'Os aspectos essenciais da calcificação caseosa do anel mitral: epidemiologia, apresentação clínica, tratamento e o papel do ecocardiograma.',
			'formato' => 'E-book',
			'esp'     => array( 'Cardiologia' ),
			'form'    => 'cccf5507-addc-4921-bbbb-871df751cbb6',
			'lp'      => '/calcificação-caseosa-do-anel-mitral',
		),

		'ebook-monitorizacao-hemodinamica' => array(
			'card'    => '2399b90',
			'capa'    => 26049,
			'titulo'  => 'Monitorização hemodinâmica',
			'desc'    => 'Os parâmetros da monitorização hemodinâmica e como interpretá-los, para quem quer atuar com cardiologia à beira do leito.',
			'formato' => 'E-book',
			'esp'     => array( 'Cardiologia' ),
			'form'    => '40e473b1-e11d-43e0-92b1-abcfb91d12d4',
			'lp'      => '/e-book-carreira-cardiologia-monitorizacao-hemodinamica',
		),

		'ebook-tabelas-de-us-pediatrica' => array(
			'card'    => '992fe18',
			'capa'    => 26050,
			'titulo'  => 'US pediátrica: 13 tabelas essenciais para a sua prática',
			'desc'    => 'Os principais parâmetros dos exames de ultrassonografia pediátrica reunidos em 13 tabelas de consulta rápida.',
			'formato' => 'E-book',
			'esp'     => array( 'Pediatria', 'Ultrassonografia' ),
			'form'    => '18a0f795-1b63-411a-a879-7d7c673ee518',
			'lp'      => '/e-book-gratuito-tabelas-de-ultrassonografia-pediatrica',
		),
	);

	$cache = $itens;
	return $cache;
}

/** Ordem dos filtros de especialidade. Só entra no chip quem tem material. */
function especialidades() {
	$ordem = array( 'Ultrassonografia', 'Ultrassonografia em GO', 'Cardiologia', 'Pediatria', 'Medicina da Dor', 'Carreira' );
	$conta = array();
	foreach ( catalogo() as $item ) {
		foreach ( $item['esp'] as $e ) {
			$conta[ $e ] = isset( $conta[ $e ] ) ? $conta[ $e ] + 1 : 1;
		}
	}
	$saida = array();
	foreach ( $ordem as $e ) {
		if ( ! empty( $conta[ $e ] ) ) {
			$saida[ $e ] = $conta[ $e ];
		}
	}
	// Especialidade que exista no catálogo mas não na ordem acima entra no fim, para nunca sumir.
	foreach ( $conta as $e => $n ) {
		if ( ! isset( $saida[ $e ] ) ) {
			$saida[ $e ] = $n;
		}
	}
	return $saida;
}

/** Mapa id do cartão no Elementor => slug. */
function por_card() {
	static $m = null;
	if ( null === $m ) {
		$m = array();
		foreach ( catalogo() as $slug => $item ) {
			$m[ $item['card'] ] = $slug;
		}
	}
	return $m;
}

function link_do( $slug ) {
	return home_url( '/' . PAGE_SLUG . '/' . $slug . '/' );
}

function url_limpa() {
	return home_url( '/' . PAGE_SLUG . '/' );
}

function e_a_pagina() {
	return is_page( PAGE_ID ) || get_queried_object_id() === PAGE_ID;
}


/* -------------------------------------------------------------------------
 * Link direto: /conteudos-gratuitos/<slug>/ abre a página com a janela aberta
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		add_rewrite_rule(
			'^' . PAGE_SLUG . '/([^/]+)/?$',
			'index.php?page_id=' . PAGE_ID . '&' . QUERY_VAR . '=$matches[1]',
			'top'
		);
		if ( get_option( OPT_RULES ) !== RULES_VER ) {
			flush_rewrite_rules( false );
			update_option( OPT_RULES, RULES_VER, false );
		}
	}
);

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = QUERY_VAR;
		return $vars;
	}
);

function slug_pedido() {
	$s = get_query_var( QUERY_VAR );
	if ( ! $s ) {
		return '';
	}
	$s = sanitize_title( rawurldecode( $s ) );
	return isset( catalogo()[ $s ] ) ? $s : '';
}

/** Slug que não existe volta 301 para a página limpa, em vez de servir conteúdo duplicado. */
add_action(
	'template_redirect',
	function () {
		if ( get_queried_object_id() !== PAGE_ID ) {
			return;
		}
		$pedido = get_query_var( QUERY_VAR );
		if ( $pedido && ! slug_pedido() ) {
			wp_safe_redirect( url_limpa(), 301 );
			exit;
		}
	},
	5
);

/** O redirect canônico do WP enxergaria /<slug>/ como permalink errado da página. */
add_filter(
	'redirect_canonical',
	function ( $url ) {
		if ( get_queried_object_id() === PAGE_ID && get_query_var( QUERY_VAR ) ) {
			return false;
		}
		return $url;
	},
	10,
	1
);

/** Toda URL de material aponta o canônico para a página limpa. */
function canonico( $url ) {
	return ( e_a_pagina() && get_query_var( QUERY_VAR ) ) ? url_limpa() : $url;
}
add_filter( 'wpseo_canonical', __NAMESPACE__ . '\\canonico', 20 );
add_filter( 'wpseo_opengraph_url', __NAMESPACE__ . '\\canonico', 20 );


/* -------------------------------------------------------------------------
 * Injeção no HTML renderizado da página
 * ---------------------------------------------------------------------- */

/**
 * Marca cada cartão com os dados do material e insere a barra de filtro.
 *
 * Roda sobre o HTML já montado pelo Elementor, então o _elementor_data continua
 * intocado: quem editar a página no Elementor não vê nada disso e não quebra nada.
 */
add_filter(
	'elementor/frontend/the_content',
	function ( $html ) {
		if ( ! e_a_pagina() || is_admin() ) {
			return $html;
		}

		$mapa  = por_card();
		$itens = catalogo();

		// 1. Enriquece cada cartão: atributos de dado, rótulo de formato e link real.
		$html = preg_replace_callback(
			'/<div class="([^"]*\bcard_isca\b[^"]*)"([^>]*?)data-id="([0-9a-f]+)"([^>]*)>/',
			function ( $m ) use ( $mapa, $itens ) {
				$id = $m[3];
				if ( ! isset( $mapa[ $id ] ) ) {
					return $m[0];
				}
				$slug = $mapa[ $id ];
				$it   = $itens[ $slug ];

				$attrs = sprintf(
					' data-cg-slug="%s" data-cg-form="%s" data-cg-titulo="%s" data-cg-desc="%s" data-cg-formato="%s" data-cg-esp="%s" data-cg-busca="%s"',
					esc_attr( $slug ),
					esc_attr( $it['form'] ),
					esc_attr( $it['titulo'] ),
					esc_attr( $it['desc'] ),
					esc_attr( $it['formato'] ),
					esc_attr( implode( '|', $it['esp'] ) ),
					esc_attr( chave_de_busca( $it ) )
				);

				return '<div class="' . $m[1] . ' cg-card"' . $m[2] . 'data-id="' . $id . '"' . $m[4] . $attrs . '>'
					. '<a class="cg-card__link" href="' . esc_url( link_do( $slug ) ) . '">'
					. '<span class="cg-sr">Acessar ' . esc_html( $it['titulo'] ) . '</span></a>'
					. '<span class="cg-card__formato" aria-hidden="true">' . esc_html( $it['formato'] ) . '</span>';
			},
			$html
		);

		// 2. Barra de busca e chips, logo acima da primeira prateleira.
		$ancora = '<div class="elementor-element elementor-element-531de49';
		$pos    = strpos( $html, $ancora );
		if ( false !== $pos ) {
			$html = substr( $html, 0, $pos ) . barra() . substr( $html, $pos );
		}

		// 3. O passo 2 falava em "abrir a página": agora a inscrição acontece na própria página.
		$html = str_replace(
			'Após abrir a página, inscreva-se e receba seu acesso.',
			'Preencha o formulário na janela que abre e receba o acesso na hora.',
			$html
		);

		// 4. Janela de inscrição.
		$html .= modal();

		return $html;
	},
	20
);

/** Texto que o campo de busca compara: título, descrição, formato e especialidades. */
function chave_de_busca( $it ) {
	$bruto = $it['titulo'] . ' ' . $it['desc'] . ' ' . $it['formato'] . ' ' . implode( ' ', $it['esp'] );
	return normaliza( $bruto );
}

/** Minúsculas sem acento, para a busca não depender de o médico digitar o acento. */
function normaliza( $s ) {
	$s = mb_strtolower( $s, 'UTF-8' );
	$de = array( 'á', 'à', 'â', 'ã', 'ä', 'é', 'ê', 'ë', 'í', 'ï', 'ó', 'ô', 'õ', 'ö', 'ú', 'ü', 'ç', 'ñ' );
	$pa = array( 'a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'i', 'i', 'o', 'o', 'o', 'o', 'u', 'u', 'c', 'n' );
	$s = str_replace( $de, $pa, $s );
	return preg_replace( '/\s+/', ' ', trim( $s ) );
}

function barra() {
	$esp   = especialidades();
	$total = count( catalogo() );

	$chips = '<button type="button" class="cg-chip is-on" data-cg-chip="" aria-pressed="true">Todos</button>';
	foreach ( $esp as $nome => $n ) {
		$chips .= sprintf(
			'<button type="button" class="cg-chip" data-cg-chip="%s" aria-pressed="false">%s<span class="cg-chip__n">%d</span></button>',
			esc_attr( $nome ),
			esc_html( $nome ),
			$n
		);
	}

	ob_start();
	?>
<div class="cg-barra" id="cg-barra">
	<div class="cg-barra__wrap">
		<div class="cg-busca">
			<svg class="cg-busca__lupa" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
			<input type="search" id="cg-q" class="cg-busca__campo" autocomplete="off" spellcheck="false"
				placeholder="Buscar por tema, especialidade ou formato"
				aria-label="Buscar conteúdo gratuito"
				role="combobox" aria-expanded="false" aria-controls="cg-sugestoes" aria-autocomplete="list">
			<button type="button" class="cg-busca__limpar" hidden aria-label="Limpar busca">
				<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
			</button>
			<ul class="cg-sug" id="cg-sugestoes" role="listbox" aria-label="Sugestões" hidden></ul>
		</div>
		<div class="cg-chips" role="group" aria-label="Filtrar por especialidade"><?php echo $chips; // phpcs:ignore ?></div>
		<p class="cg-contagem" role="status" aria-live="polite" data-cg-total="<?php echo (int) $total; ?>"></p>
	</div>
</div>
	<?php
	return ob_get_clean();
}

function modal() {
	ob_start();
	?>
<div class="cg-modal" id="cgModal" hidden>
	<div class="cg-modal__fundo" data-cg-fechar></div>
	<div class="cg-modal__caixa" role="dialog" aria-modal="true" aria-labelledby="cg-modal-titulo">
		<button type="button" class="cg-modal__x" data-cg-fechar aria-label="Fechar">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
		</button>

		<div class="cg-modal__capa">
			<div class="cg-modal__img" id="cg-modal-img"></div>
			<p class="cg-modal__eyebrow"><span id="cg-modal-formato"></span><span id="cg-modal-esp"></span></p>
			<h2 class="cg-modal__titulo" id="cg-modal-titulo"></h2>
			<p class="cg-modal__desc" id="cg-modal-desc"></p>
			<dl class="cg-modal__meta">
				<div><dt>Acesso</dt><dd>Imediato</dd></div>
				<div><dt>Custo</dt><dd>Gratuito</dd></div>
			</dl>
		</div>

		<div class="cg-modal__form">
			<h3 class="cg-modal__formtit">Tenha acesso gratuito</h3>
			<p class="cg-modal__formsub">Preencha os dados abaixo para liberar seu acesso agora mesmo.</p>
			<div id="cg-modal-alvo" class="cg-modal__alvo"></div>
			<p class="cg-modal__falha" hidden>Não conseguimos carregar o formulário. Desative o bloqueador de anúncios e recarregue a página.</p>
		</div>
	</div>
</div>
	<?php
	return ob_get_clean();
}


/* -------------------------------------------------------------------------
 * CSS e JS
 * ---------------------------------------------------------------------- */

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! e_a_pagina() ) {
			return;
		}
		wp_register_style( 'cetrus-cg', false, array(), VERSAO );
		wp_enqueue_style( 'cetrus-cg' );
		wp_add_inline_style( 'cetrus-cg', css() );
	},
	20
);

add_action(
	'wp_footer',
	function () {
		if ( ! e_a_pagina() ) {
			return;
		}
		$dados = array(
			'portal'  => PORTAL,
			'region'  => REGION,
			'base'    => url_limpa(),
			'abrir'   => slug_pedido(),
			'materiais' => array(),
		);
		foreach ( catalogo() as $slug => $it ) {
			$dados['materiais'][] = array(
				'slug'    => $slug,
				'titulo'  => $it['titulo'],
				'desc'    => $it['desc'],
				'formato' => $it['formato'],
				'esp'     => $it['esp'],
				'busca'   => chave_de_busca( $it ),
				'capa'    => $it['capa'] ? wp_get_attachment_image_url( $it['capa'], 'large' ) : '',
			);
		}
		echo '<script id="cg-dados" type="application/json">' . wp_json_encode( $dados ) . '</script>' . "\n";
		echo '<script id="cg-js">' . js() . '</script>' . "\n";
	},
	30
);

function css() {
	return <<<'CSS'
/* Tokens do design system Dende (@sanardigital/dende-tokens).
   Exceção consciente: o Dende define Inter como fonte de marca, mas esta página
   é inteira BwModelica/Roboto pelo kit do Elementor. Usar Inter só neste bloco
   deixaria dois títulos diferentes na mesma tela, então a tipografia segue a página. */
:root{
	--cg-dark:#002452;                 /* ColorBrandCetrusDark */
	--cg-medium:#003b6c;               /* ColorBrandCetrusMain / Medium */
	--cg-light:#9bb1df;                /* ColorBrandCetrusLight */
	--cg-cetrus-lighter:#e1e8f5;       /* ColorBrandCetrusLighter */
	--cg-lighter:#f2f3fb;              /* ColorBrandCetrusSurface */
	--cg-neutral:#f9fafb;              /* ColorNeutralLigthest */
	--cg-linha:#e9ebed;                /* ColorNeutralLighter */
	--cg-cinza-md:#899090;             /* ColorNeutralMedium */
	--cg-cinza:rgba(17,18,18,.65);     /* ColorTextNeutralLigther */
	--cg-cinza-esc:rgba(17,18,18,.85); /* ColorTextNeutralPrimary */
	--cg-erro:#c61d1d;                 /* ColorFeedbackMainError */
	--cg-r2:8px;                       /* BorderRadius2 */
	--cg-r4:16px;                      /* BorderRadius4 */
	--cg-pill:10em;                    /* BorderRadiusPill */
	--cg-raio:12px;                    /* raio do cartão que já existe na página */
	--cg-titulo:"BwModelica","Roboto",-apple-system,BlinkMacSystemFont,sans-serif;
	--cg-corpo:"Roboto",-apple-system,BlinkMacSystemFont,sans-serif;
}
.cg-sr{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}

/* ---------- barra de busca e filtros ----------
   O kit do Elementor estiliza `button` e `input` com especificidade de classe
   (.elementor-kit-10452 button), então tudo aqui é ancorado no id da barra. */
#cg-barra{background:var(--cg-neutral);padding:0 0 28px}
/* Mesma caixa das seções do Elementor nesta página: o kit usa
   --content-width: min(100%, 1170px) no .e-con-inner, com 24px de folga lateral
   quando 1170 não cabe. Sem isto a barra fica 35px à direita dos títulos. */
#cg-barra .cg-barra__wrap{width:min(100%,1170px);margin-inline:auto;padding-inline:0;box-sizing:border-box}
@media(max-width:1218px){
	#cg-barra .cg-barra__wrap{width:100%;padding-inline:24px}
}
#cg-barra .cg-busca{position:relative;max-width:560px}
#cg-barra .cg-busca__campo{
	width:100%;box-sizing:border-box;height:52px;padding:0 46px;margin:0;
	font-family:var(--cg-corpo);font-size:16px;line-height:normal;color:var(--cg-cinza-esc);
	background:#fff;border:1px solid var(--cg-linha);border-radius:var(--cg-r2);box-shadow:none;
	transition:border-color .15s ease,box-shadow .15s ease;-webkit-appearance:none;appearance:none
}
#cg-barra .cg-busca__campo::placeholder{color:var(--cg-cinza-md);opacity:1}
#cg-barra .cg-busca__campo:focus{outline:none;border-color:var(--cg-medium);box-shadow:0 0 0 3px rgba(0,59,108,.16)}
#cg-barra .cg-busca__campo::-webkit-search-cancel-button{display:none;-webkit-appearance:none}
#cg-barra .cg-busca__lupa{position:absolute;left:15px;top:50%;transform:translateY(-50%);width:19px;height:19px;
	fill:none;stroke:var(--cg-cinza);stroke-width:2;stroke-linecap:round;pointer-events:none;z-index:1}
#cg-barra .cg-busca__limpar{position:absolute;right:8px;top:50%;transform:translateY(-50%);
	width:32px;height:32px;min-width:0;padding:0;display:grid;place-items:center;
	background:none;border:0;box-shadow:none;cursor:pointer;border-radius:var(--cg-r2)}
#cg-barra .cg-busca__limpar[hidden]{display:none}
#cg-barra .cg-busca__limpar:hover{background:var(--cg-lighter)}
#cg-barra .cg-busca__limpar svg{width:15px;height:15px;fill:none;stroke:var(--cg-cinza);stroke-width:2;stroke-linecap:round}

#cg-barra .cg-sug{position:absolute;z-index:60;top:calc(100% + 6px);left:0;right:0;margin:0;padding:6px;
	list-style:none;background:#fff;border:1px solid var(--cg-linha);border-radius:var(--cg-r2);
	box-shadow:0 12px 32px rgba(0,36,82,.14);max-height:320px;overflow:auto}
#cg-barra .cg-sug[hidden]{display:none}
#cg-barra .cg-sug li{margin:0;padding:0;list-style:none}
#cg-barra .cg-sug li:before{content:none}
#cg-barra .cg-sug button{display:block;width:100%;text-align:left;padding:9px 12px;margin:0;
	background:none;border:0;box-shadow:none;border-radius:var(--cg-r2);cursor:pointer;
	font-family:var(--cg-corpo);font-size:14px;font-weight:400;line-height:1.35;color:var(--cg-cinza-esc)}
#cg-barra .cg-sug button:hover,#cg-barra .cg-sug button:focus,#cg-barra .cg-sug .is-ativa button{background:var(--cg-lighter);outline:none}
#cg-barra .cg-sug small{display:block;margin-top:2px;font-size:12px;color:var(--cg-cinza)}
#cg-barra .cg-sug mark{background:transparent;color:var(--cg-medium);font-weight:600}

#cg-barra .cg-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
#cg-barra .cg-chip{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;margin:0;cursor:pointer;
	font-family:var(--cg-corpo);font-size:14px;font-weight:400;line-height:1;color:var(--cg-cinza-esc);
	background:#fff;border:1px solid var(--cg-linha);border-radius:var(--cg-pill);box-shadow:none;
	transition:background .15s ease,border-color .15s ease,color .15s ease}
#cg-barra .cg-chip:hover{background:#fff;border-color:var(--cg-light);color:var(--cg-dark)}
#cg-barra .cg-chip.is-on,#cg-barra .cg-chip.is-on:hover{background:var(--cg-dark);border-color:var(--cg-dark);color:#fff}
#cg-barra .cg-chip__n{font-size:11px;opacity:.65}
#cg-barra .cg-chip:focus-visible{outline:2px solid var(--cg-medium);outline-offset:2px}
#cg-barra .cg-contagem{margin:14px 0 0;font-family:var(--cg-corpo);font-size:12px;color:var(--cg-cinza);min-height:19px}
#cg-barra .cg-contagem b{color:var(--cg-dark);font-weight:600}

#cg-barra .cg-semnada{display:none;padding:36px 20px 8px;text-align:center;font-family:var(--cg-corpo);font-size:14px;color:var(--cg-cinza)}
#cg-barra .cg-semnada.is-on{display:block}
#cg-barra .cg-semnada b{display:block;font-family:var(--cg-titulo);font-size:18px;color:var(--cg-dark);margin-bottom:6px}
#cg-barra .cg-semnada button{margin-top:14px;padding:11px 22px;font-family:var(--cg-corpo);font-size:14px;font-weight:500;
	color:#fff;background:var(--cg-dark);border:0;border-radius:var(--cg-r2);box-shadow:none;cursor:pointer}
#cg-barra .cg-semnada button:hover{background:var(--cg-medium)}

/* ---------- cartão ---------- */
.cg-card{position:relative}
.cg-card .cg-card__link{position:absolute;inset:0;z-index:4;border-radius:var(--cg-raio);display:block;text-decoration:none}
.cg-card .cg-card__link:focus-visible{outline:3px solid var(--cg-medium);outline-offset:3px}
.cg-card .cg-card__formato{position:absolute;z-index:3;top:16px;right:16px;
	font-family:var(--cg-corpo);font-size:10px;font-weight:600;letter-spacing:.09em;text-transform:uppercase;
	color:var(--cg-light);background:rgba(0,20,44,.62);padding:5px 9px;border-radius:var(--cg-pill);line-height:1;
	backdrop-filter:blur(3px);-webkit-backdrop-filter:blur(3px);pointer-events:none}
@media(max-width:767px){.cg-card .cg-card__formato{top:8px;right:8px;font-size:9px;padding:4px 7px}}

/* filtro: some o que não casa, e a prateleira que ficou sem nada */
.cg-oculto{display:none!important}
.cg-vazia{display:none!important}

/* Carrossel vira grade quando não há o que rolar: com filtro ativo, ou quando
   sobram menos cartões do que cabem na vista. Nos dois casos o Swiper roda em
   loop e preencheria o espaço com clones, repetindo o mesmo material na tela. */
.e-n-carousel.cg-grade .swiper-wrapper{
	display:flex!important;flex-wrap:wrap!important;gap:16px;
	transform:none!important;height:auto!important}
.e-n-carousel.cg-grade .swiper-slide{
	width:240px!important;max-width:240px!important;margin:0!important;flex:0 0 auto}
.e-n-carousel.cg-grade .swiper-slide-duplicate{display:none!important}
.e-n-carousel.cg-grade .swiper-pagination,
.e-n-carousel.cg-grade .elementor-swiper-button{display:none!important}
@media(max-width:767px){
	.e-n-carousel.cg-grade .swiper-slide{width:calc(50% - 8px)!important;max-width:none!important}
}

/* ---------- janela de inscrição ---------- */
#cgModal{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px}
#cgModal[hidden]{display:none}
#cgModal .cg-modal__fundo{position:absolute;inset:0;background:rgba(0,20,44,.72);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px)}
#cgModal .cg-modal__caixa{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,.85fr) minmax(0,1fr);
	width:100%;max-width:880px;max-height:calc(100vh - 40px);overflow:auto;
	background:#fff;border-radius:var(--cg-r4);box-shadow:0 24px 64px rgba(0,20,44,.4)}
#cgModal .cg-modal__x{position:absolute;top:12px;right:12px;z-index:3;width:34px;height:34px;min-width:0;padding:0;
	display:grid;place-items:center;background:#fff;border:1px solid var(--cg-linha);border-radius:50%;
	box-shadow:none;cursor:pointer}
#cgModal .cg-modal__x:hover{background:var(--cg-lighter)}
#cgModal .cg-modal__x svg{width:14px;height:14px;fill:none;stroke:var(--cg-cinza-esc);stroke-width:2.2;stroke-linecap:round}

#cgModal .cg-modal__capa{padding:26px;background:var(--cg-lighter);border-radius:var(--cg-r4) 0 0 var(--cg-r4);display:flex;flex-direction:column}
#cgModal .cg-modal__img{width:100%;aspect-ratio:4/3;border-radius:var(--cg-r2);background:var(--cg-dark) center/cover no-repeat;margin-bottom:18px}
#cgModal .cg-modal__eyebrow{margin:0 0 8px;font-family:var(--cg-corpo);font-size:11px;font-weight:600;
	letter-spacing:.08em;text-transform:uppercase;color:var(--cg-medium)}
#cgModal .cg-modal__eyebrow span+span:before{content:" · ";color:var(--cg-cinza)}
#cgModal .cg-modal__titulo{margin:0 0 10px;font-family:var(--cg-titulo);font-size:22px;line-height:1.22;font-weight:700;color:var(--cg-dark)}
#cgModal .cg-modal__desc{margin:0 0 18px;font-family:var(--cg-corpo);font-size:14px;line-height:1.55;color:var(--cg-cinza)}
#cgModal .cg-modal__meta{display:flex;gap:26px;margin:auto 0 0;padding-top:16px;border-top:1px solid var(--cg-cetrus-lighter)}
#cgModal .cg-modal__meta div{margin:0}
#cgModal .cg-modal__meta dt{font-family:var(--cg-corpo);font-size:10px;font-weight:600;letter-spacing:.08em;
	text-transform:uppercase;color:var(--cg-cinza);margin:0 0 3px}
#cgModal .cg-modal__meta dd{margin:0;font-family:var(--cg-corpo);font-size:14px;font-weight:500;color:var(--cg-dark)}

#cgModal .cg-modal__form{padding:26px 26px 30px}
#cgModal .cg-modal__formtit{margin:0 0 6px;font-family:var(--cg-titulo);font-size:20px;font-weight:700;color:var(--cg-dark)}
#cgModal .cg-modal__formsub{margin:0 0 18px;font-family:var(--cg-corpo);font-size:14px;color:var(--cg-cinza)}
#cgModal .cg-modal__alvo{min-height:180px}
#cgModal .cg-modal__falha{margin:12px 0 0;font-family:var(--cg-corpo);font-size:13px;color:var(--cg-erro)}
#cgModal .cg-modal__falha[hidden]{display:none}

/* formulário HubSpot na identidade do Cetrus */
#cgModal .cg-modal__alvo .hs-form-field{margin-bottom:14px}
#cgModal .cg-modal__alvo label{display:block;margin-bottom:5px;font-family:var(--cg-corpo);font-size:14px;font-weight:500;color:var(--cg-cinza-esc)}
#cgModal .cg-modal__alvo .hs-form-required{color:var(--cg-erro);margin-left:2px}
#cgModal .cg-modal__alvo .hs-field-desc{display:block;margin:-2px 0 6px;font-family:var(--cg-corpo);
	font-size:12px;line-height:1.35;color:var(--cg-cinza)}
#cgModal .cg-modal__alvo legend.hs-field-desc{padding:0;border:0;width:auto}
#cgModal .cg-modal__alvo input[type=text],#cgModal .cg-modal__alvo input[type=email],
#cgModal .cg-modal__alvo input[type=tel],#cgModal .cg-modal__alvo input[type=number],
#cgModal .cg-modal__alvo select,#cgModal .cg-modal__alvo textarea{
	width:100%;box-sizing:border-box;height:44px;padding:0 13px;margin:0;
	font-family:var(--cg-corpo);font-size:15px;line-height:normal;color:var(--cg-cinza-esc);
	background:#fff;border:1px solid var(--cg-linha);border-radius:var(--cg-r2);box-shadow:none}
#cgModal .cg-modal__alvo textarea{height:auto;padding:11px 13px;min-height:80px}
#cgModal .cg-modal__alvo input:focus,#cgModal .cg-modal__alvo select:focus,#cgModal .cg-modal__alvo textarea:focus{
	outline:none;border-color:var(--cg-medium);box-shadow:0 0 0 3px rgba(0,59,108,.16)}
#cgModal .cg-modal__alvo .hs-error-msg,#cgModal .cg-modal__alvo .hs-error-msgs label{color:var(--cg-erro);font-size:12px;font-weight:400}
#cgModal .cg-modal__alvo ul.hs-error-msgs{list-style:none;margin:5px 0 0;padding:0}
#cgModal .cg-modal__alvo ul.hs-error-msgs li:before{content:none}
#cgModal .cg-modal__alvo .hs-form-booleancheckbox label,#cgModal .cg-modal__alvo .hs-fieldtype-checkbox label{
	display:flex;align-items:flex-start;gap:8px;font-size:13px;line-height:1.4;font-weight:400}
#cgModal .cg-modal__alvo .hs-form-booleancheckbox input,#cgModal .cg-modal__alvo .hs-fieldtype-checkbox input{
	width:auto;height:auto;margin-top:2px}
#cgModal .cg-modal__alvo ul.inputs-list{list-style:none;margin:0;padding:0}
#cgModal .cg-modal__alvo ul.inputs-list li:before{content:none}
#cgModal .cg-modal__alvo .legal-consent-container{font-size:12px;line-height:1.5;color:var(--cg-cinza)}
#cgModal .cg-modal__alvo .hs-button{
	width:100%;height:50px;margin-top:6px;padding:0;cursor:pointer;
	font-family:var(--cg-corpo);font-size:15px;font-weight:600;line-height:1;color:#fff;
	background:var(--cg-dark);border:0;border-radius:var(--cg-r2);box-shadow:none;transition:background .15s ease}
#cgModal .cg-modal__alvo .hs-button:hover{background:var(--cg-medium)}
#cgModal .cg-modal__alvo .submitted-message{font-family:var(--cg-corpo);font-size:15px;line-height:1.6;color:var(--cg-cinza-esc)}

@media(max-width:860px){
	#cgModal{padding:16px}
	#cgModal .cg-modal__caixa{grid-template-columns:1fr;max-height:calc(100vh - 32px)}
	#cgModal .cg-modal__capa{border-radius:var(--cg-r4) var(--cg-r4) 0 0;padding:22px}
	#cgModal .cg-modal__img{aspect-ratio:16/9;margin-bottom:14px}
	#cgModal .cg-modal__meta{margin-top:16px}
	#cgModal .cg-modal__form{padding:22px}
}
@media(max-width:767px){
	#cg-barra{padding-bottom:22px}
	/* 7 chips empilhavam em 4 linhas no telefone. Viram uma faixa que rola,
	   sangrando até a borda como os carrosséis da página. */
	#cg-barra .cg-chips{
		gap:7px;margin-top:13px;flex-wrap:nowrap;overflow-x:auto;
		margin-inline:-24px;padding-inline:24px;scroll-padding-inline:24px;
		scrollbar-width:none;-ms-overflow-style:none;-webkit-overflow-scrolling:touch}
	#cg-barra .cg-chips::-webkit-scrollbar{display:none}
	#cg-barra .cg-chip{padding:8px 13px;font-size:13px;flex:0 0 auto}
	#cgModal .cg-modal__titulo{font-size:19px}
}
body.cg-travado{overflow:hidden}
CSS;
}

function js() {
	return <<<'JS'
(function(){
"use strict";
var no = document.getElementById('cg-dados');
if(!no) return;
var CFG; try{ CFG = JSON.parse(no.textContent); }catch(e){ return; }

var barra   = document.getElementById('cg-barra');
var campo   = document.getElementById('cg-q');
var limpar  = barra && barra.querySelector('.cg-busca__limpar');
var sug     = document.getElementById('cg-sugestoes');
var chips   = barra ? [].slice.call(barra.querySelectorAll('.cg-chip')) : [];
var contagem= barra && barra.querySelector('.cg-contagem');
var modal   = document.getElementById('cgModal');
var alvo    = document.getElementById('cg-modal-alvo');
var falha   = modal && modal.querySelector('.cg-modal__falha');

var termo = '', chipAtivo = '', slugAberto = '', devolverFoco = null, iSug = -1, sugAtual = [];

function norm(s){
	return (s||'').toLowerCase()
		.normalize('NFD').replace(/[\u0300-\u036f]/g,'')
		.replace(/\s+/g,' ').trim();
}
function cards(){ return [].slice.call(document.querySelectorAll('.cg-card[data-cg-slug]')); }
function porSlug(s){ return document.querySelector('.cg-card[data-cg-slug="'+s+'"]'); }
function dadoDo(s){
	for(var i=0;i<CFG.materiais.length;i++){ if(CFG.materiais[i].slug===s) return CFG.materiais[i]; }
	return null;
}

/* ---------------- filtro ---------------- */

function casa(m){
	if(chipAtivo && m.esp.indexOf(chipAtivo)===-1) return false;
	if(!termo) return true;
	var t = norm(termo);
	if(!t) return true;
	// toda palavra digitada precisa aparecer em algum campo do material
	return t.split(' ').every(function(p){ return m.busca.indexOf(p)!==-1; });
}

function aplica(){
	var filtrando = !!(termo || chipAtivo);
	document.body.classList.toggle('cg-filtrando', filtrando);
	var visiveis = {}, n = 0;
	CFG.materiais.forEach(function(m){
		var ok = casa(m);
		visiveis[m.slug] = ok;
		if(ok) n++;
	});

	cards().forEach(function(c){
		var slide = c.closest('.swiper-slide') || c.parentElement;
		var mostra = visiveis[c.dataset.cgSlug];
		(slide || c).classList.toggle('cg-oculto', !mostra);
	});

	// prateleira que ficou sem nenhum cartão sai de cena, junto com seu título
	[].slice.call(document.querySelectorAll('.e-n-carousel, .swiper')).forEach(function(car){
		var sec = car.closest('.e-con-boxed, .e-parent') || car.parentElement;
		if(!sec) return;
		if(!car.querySelector('.cg-card[data-cg-slug]')) return;  // carrossel que não é de material

		var reais = [].slice.call(car.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)'));
		var naTela = reais.filter(function(s){ return !s.classList.contains('cg-oculto'); }).length;
		sec.classList.toggle('cg-vazia', naTela === 0);

		// quantos cabem na vista, no tamanho de tela de agora
		var cabem = 4;
		try{
			var p = car.swiper && car.swiper.params;
			if(p && typeof p.slidesPerView === 'number'){ cabem = p.slidesPerView; }
			if(car.swiper && car.swiper.currentBreakpoint && p.breakpoints){
				var bp = p.breakpoints[car.swiper.currentBreakpoint];
				if(bp && typeof bp.slidesPerView === 'number'){ cabem = bp.slidesPerView; }
			}
		}catch(e){}

		var grade = filtrando || naTela <= cabem;
		car.classList.toggle('cg-grade', grade);

		if(car.swiper){
			try{
				if(grade){ car.swiper.setTranslate(0); }
				else { car.swiper.update(); car.swiper.slideTo(0,0); }
			}catch(e){}
		}
	});

	if(contagem){
		var total = CFG.materiais.length;
		if(!termo && !chipAtivo){ contagem.textContent = total + ' conteúdos gratuitos disponíveis'; }
		else if(n === 0){ contagem.textContent = 'Nenhum conteúdo encontrado.'; }
		else { contagem.innerHTML = 'Mostrando <b>'+n+'</b> de '+total+' conteúdos'; }
	}
	semNada(n === 0);
}

var vazioEl = null;
function semNada(mostrar){
	if(mostrar && !vazioEl){
		vazioEl = document.createElement('div');
		vazioEl.className = 'cg-semnada is-on';
		vazioEl.innerHTML = '<b>Nenhum conteúdo com esse filtro</b>'+
			'<span>Tente outra palavra ou veja todos os materiais.</span>'+
			'<br><button type="button">Ver todos os conteúdos</button>';
		vazioEl.querySelector('button').addEventListener('click', function(){
			termo=''; chipAtivo='';
			if(campo) campo.value='';
			if(limpar) limpar.hidden = true;
			marcaChips(); aplica(); fechaSug();
			if(campo) campo.focus();
		});
		barra.appendChild(vazioEl);
	} else if(mostrar && vazioEl){
		vazioEl.classList.add('is-on');
	} else if(vazioEl){
		vazioEl.classList.remove('is-on');
	}
}

function marcaChips(){
	chips.forEach(function(b){
		var on = (b.dataset.cgChip||'') === chipAtivo;
		b.classList.toggle('is-on', on);
		b.setAttribute('aria-pressed', on ? 'true' : 'false');
	});
}

chips.forEach(function(b){
	b.addEventListener('click', function(){
		var v = b.dataset.cgChip || '';
		chipAtivo = (chipAtivo === v) ? '' : v;
		marcaChips(); aplica(); fechaSug();
	});
});

/* ---------------- busca com sugestão ---------------- */

function realca(txt, t){
	if(!t) return txt;
	var i = norm(txt).indexOf(norm(t));
	if(i === -1) return txt;
	return txt.slice(0,i)+'<mark>'+txt.slice(i,i+t.length)+'</mark>'+txt.slice(i+t.length);
}

function abreSug(){
	if(!sug || !campo) return;
	var t = norm(campo.value);
	if(t.length < 2){ fechaSug(); return; }
	sugAtual = CFG.materiais.filter(function(m){
		if(chipAtivo && m.esp.indexOf(chipAtivo)===-1) return false;
		return t.split(' ').every(function(p){ return m.busca.indexOf(p)!==-1; });
	}).slice(0,6);

	if(!sugAtual.length){ fechaSug(); return; }
	sug.innerHTML = sugAtual.map(function(m,i){
		return '<li role="option" id="cg-sug-'+i+'" aria-selected="false">'+
			'<button type="button" data-cg-ir="'+m.slug+'">'+realca(m.titulo, campo.value.trim())+
			'<small>'+m.formato+' · '+m.esp.join(' · ')+'</small></button></li>';
	}).join('');
	sug.hidden = false;
	campo.setAttribute('aria-expanded','true');
	iSug = -1;
}

function fechaSug(){
	if(!sug) return;
	sug.hidden = true; sug.innerHTML = ''; iSug = -1; sugAtual = [];
	if(campo){ campo.setAttribute('aria-expanded','false'); campo.removeAttribute('aria-activedescendant'); }
}

function moveSug(d){
	if(sug.hidden || !sugAtual.length) return;
	var itens = [].slice.call(sug.children);
	if(iSug > -1) itens[iSug].classList.remove('is-ativa');
	iSug += d;
	if(iSug < 0) iSug = itens.length-1;
	if(iSug >= itens.length) iSug = 0;
	itens[iSug].classList.add('is-ativa');
	itens.forEach(function(li,i){ li.setAttribute('aria-selected', i===iSug ? 'true':'false'); });
	campo.setAttribute('aria-activedescendant','cg-sug-'+iSug);
}

if(campo){
	var temporizador;
	campo.addEventListener('input', function(){
		termo = campo.value;
		if(limpar) limpar.hidden = !termo;
		clearTimeout(temporizador);
		temporizador = setTimeout(function(){ aplica(); abreSug(); }, 140);
	});
	campo.addEventListener('keydown', function(ev){
		if(ev.key === 'ArrowDown'){ ev.preventDefault(); moveSug(1); }
		else if(ev.key === 'ArrowUp'){ ev.preventDefault(); moveSug(-1); }
		else if(ev.key === 'Enter'){
			if(iSug > -1 && sugAtual[iSug]){ ev.preventDefault(); abre(sugAtual[iSug].slug); fechaSug(); }
		}
		else if(ev.key === 'Escape'){ fechaSug(); }
	});
	campo.addEventListener('focus', abreSug);
}
if(limpar){
	limpar.addEventListener('click', function(){
		campo.value=''; termo=''; limpar.hidden = true;
		aplica(); fechaSug(); campo.focus();
	});
}
if(sug){
	sug.addEventListener('click', function(ev){
		var b = ev.target.closest('[data-cg-ir]');
		if(!b) return;
		abre(b.dataset.cgIr); fechaSug();
	});
}
document.addEventListener('click', function(ev){
	if(barra && !barra.contains(ev.target)) fechaSug();
});

/* ---------------- janela de inscrição ---------------- */

var hsPronto = false, hsCarregando = false;
function carregaHS(cb){
	if(hsPronto){ cb(); return; }
	if(hsCarregando){ setTimeout(function(){ carregaHS(cb); }, 200); return; }
	hsCarregando = true;
	var s = document.createElement('script');
	s.src = 'https://js.hsforms.net/forms/embed/v2.js';
	s.async = true;
	s.onload = function(){ hsPronto = true; hsCarregando = false; cb(); };
	s.onerror = function(){ hsCarregando = false; if(falha) falha.hidden = false; };
	document.head.appendChild(s);
}

/* Cada abertura ganha um alvo próprio e um número de série.
   O hbspt.forms.create é assíncrono: abrir um material, fechar antes de ele
   terminar e abrir outro fazia o formulário atrasado cair no alvo do segundo,
   levando o lead para a isca errada. O número de série descarta o atrasado. */
var serie = 0;
function montaForm(m){
	if(!alvo) return;
	var meu = ++serie;
	alvo.innerHTML = '';
	if(falha) falha.hidden = true;
	var card = porSlug(m.slug);
	var guid = card ? card.dataset.cgForm : '';
	if(!guid){ if(falha) falha.hidden = false; return; }

	var caixa = document.createElement('div');
	caixa.id = 'cg-form-' + meu;
	alvo.appendChild(caixa);

	carregaHS(function(){
		if(meu !== serie) return;                       // já abriram outro material
		if(!window.hbspt || !window.hbspt.forms){ if(falha) falha.hidden = false; return; }
		try{
			window.hbspt.forms.create({
				portalId: CFG.portal,
				formId: guid,
				region: CFG.region,
				target: '#cg-form-' + meu,
				css: '',                       // sem isto o HubSpot devolve o form dentro de um iframe
				pageName: 'Conteúdos gratuitos: ' + m.titulo,
				onFormReady: function(){
					if(meu === serie) return;
					var velho = document.getElementById('cg-form-' + meu);  // chegou tarde
					if(velho) velho.remove();
				}
			});
		}catch(e){ if(falha) falha.hidden = false; }
	});
}

function abre(slug){
	var m = dadoDo(slug);
	if(!m || !modal) return;
	var card = porSlug(slug);

	document.getElementById('cg-modal-formato').textContent = m.formato;
	document.getElementById('cg-modal-esp').textContent = m.esp.join(' · ');
	document.getElementById('cg-modal-titulo').textContent = m.titulo;
	document.getElementById('cg-modal-desc').textContent = m.desc || (card && card.dataset.cgDesc) || '';

	var img = document.getElementById('cg-modal-img');
	var fundo = m.capa || '';
	if(!fundo && card){
		var bg = getComputedStyle(card).backgroundImage;   // reserva, se a capa sair do catálogo
		if(bg && bg !== 'none') fundo = bg.replace(/^url\(["']?/,'').replace(/["']?\)$/,'');
	}
	img.style.backgroundImage = fundo ? 'url("' + fundo + '")' : '';

	slugAberto = slug;
	devolverFoco = document.activeElement;
	modal.hidden = false;
	document.body.classList.add('cg-travado');
	trocaURL(CFG.base + slug + '/');
	montaForm(m);

	var x = modal.querySelector('.cg-modal__x');
	if(x) x.focus();
}

function fecha(){
	if(!modal || modal.hidden) return;
	modal.hidden = true;
	document.body.classList.remove('cg-travado');
	serie++;                                   // invalida um formulário ainda em voo
	if(alvo) alvo.innerHTML = '';
	slugAberto = '';
	trocaURL(CFG.base);
	if(devolverFoco && devolverFoco.focus){ devolverFoco.focus(); }
	devolverFoco = null;
}

function trocaURL(url){
	try{ history.replaceState(history.state, '', url); }catch(e){}
}

if(modal){
	modal.addEventListener('click', function(ev){
		if(ev.target.closest('[data-cg-fechar]')) fecha();
	});
	document.addEventListener('keydown', function(ev){
		if(modal.hidden) return;
		if(ev.key === 'Escape'){ fecha(); return; }
		if(ev.key !== 'Tab') return;
		var foco = modal.querySelectorAll('a[href],button,input,select,textarea,[tabindex]:not([tabindex="-1"])');
		var vis = [].slice.call(foco).filter(function(el){ return el.offsetParent !== null; });
		if(!vis.length) return;
		var pri = vis[0], ult = vis[vis.length-1];
		if(ev.shiftKey && document.activeElement === pri){ ev.preventDefault(); ult.focus(); }
		else if(!ev.shiftKey && document.activeElement === ult){ ev.preventDefault(); pri.focus(); }
	});
}

/* O plugin make-column-clickable escuta o clique no próprio cartão e abriria a LP
   numa aba nova. A fase de captura chega antes dele, então o modal ganha. */
document.addEventListener('click', function(ev){
	var card = ev.target.closest('.cg-card[data-cg-slug]');
	if(!card) return;
	if(ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button !== 0) return; // abrir em nova aba continua valendo
	ev.preventDefault();
	ev.stopPropagation();
	abre(card.dataset.cgSlug);
}, true);

/* ---------------- partida ---------------- */

marcaChips();
aplica();
// o Swiper inicializa depois de nós; uma segunda passada pega o slidesPerView real
setTimeout(aplica, 900);
var reMedir;
window.addEventListener('resize', function(){
	clearTimeout(reMedir);
	reMedir = setTimeout(aplica, 250);   // quantos cabem na vista muda com a largura
});
if(CFG.abrir){ abre(CFG.abrir); }
})();
JS;
}
