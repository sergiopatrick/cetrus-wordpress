<?php
/**
 * Plugin Name: Cetrus - Black November 2026 (camada temporaria)
 * Description: Liga a versao Black do site so dentro da janela da campanha. Troca, na hora de renderizar, cada template do Elementor pelo clone "[BLACK-2026] #<id original>" e acrescenta a camada visual (classe no body + CSS). Fora da janela nao muda nada. Documentacao: PROJETO_PADRONIZACAO_PAGINAS_CETRUS.md na raiz do repositorio.
 * Version:     0.1.0
 * Author:      Cetrus / Sanar
 *
 * RASCUNHO 0.1.0 (22/10/2026), AINDA NAO TESTADO NO STAGING NEM IMPLANTADO.
 * Falta confirmar no Elementor Pro 4.1.3 que o filtro
 * elementor/theme/get_location_templates/template_id e aplicado na resolucao
 * das condicoes (secao 14 do documento do projeto).
 *
 * COMO FUNCIONA
 * - Os templates originais NAO sao editados e continuam donos das condicoes de
 *   exibicao. Cada versao Black e um template novo do Elementor, sem condicoes,
 *   com o titulo "[BLACK-2026] #12073 <qualquer texto>" (12073 = id do original
 *   NAQUELE site; em producao os ids dos clones mudam na importacao, por isso o
 *   vinculo e pelo titulo e nao por id fixo no codigo).
 * - Dentro da janela, quando o Elementor escolhe o template de uma localizacao
 *   (header, footer, single product...), este arquivo devolve o id do clone no
 *   lugar do original. Produto novo cadastrado em novembro cai na condicao do
 *   original (modalidade, tag...) e, por isso, recebe a versao Black sozinho.
 * - Paginas do Artifact (Home, Vitrine, Conteudos gratuitos): o clone e um modelo
 *   do Elementor (tipo pagina/secao/container) com titulo "[BLACK-2026] #<id da
 *   pagina>". A pagina original continua sendo servida (URL e todo o SEO do Yoast
 *   sao dela); so o conteudo do Elementor e trocado pelo do modelo.
 * - Fora da janela o filtro devolve o id original: o rollback e automatico e
 *   nao apaga nada do banco (produtos, leads, pedidos e edicoes continuam).
 *
 * CHAVES
 * - Janela: CETRUS_BLACK_INICIO / CETRUS_BLACK_FIM (horario de Brasilia).
 * - Emergencia, sem deploy: Ferramentas > Black November 2026 (modo auto,
 *   ligado ou desligado), ou WP-CLI:
 *     wp option update cetrus_black_2026_modo desligado
 * - Previa so para quem esta logado com permissao de edicao:
 *     ?cetrus_black=1 (forca ligado) e ?cetrus_black=0 (forca desligado).
 *   Logado nao passa pelo cache de pagina, entao a previa nao vaza.
 *
 * REVERTER DE VEZ: modo "desligado" (efeito imediato, ate ~5 min de cache de
 * borda) e depois remover este arquivo e a pasta cetrus-black-november-2026/ do
 * repositorio e do servidor. Os clones [BLACK-2026] podem ficar como rascunho.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CETRUS_BLACK_VERSAO  = '0.1.0';
const CETRUS_BLACK_INICIO  = '2026-11-01T00:00:00-03:00';
const CETRUS_BLACK_FIM     = '2026-12-01T00:00:00-03:00'; // PENDENTE: confirmar a data de encerramento com o comercial.
const CETRUS_BLACK_PREFIXO = '[BLACK-2026]';
const CETRUS_BLACK_OPCAO   = 'cetrus_black_2026_modo';
const CETRUS_BLACK_CACHE   = 'cetrus_black_2026_mapa';
// Tipos de modelo do Elementor aceitos como versao Black de uma PAGINA (Home, Vitrine...).
const CETRUS_BLACK_TIPOS_PAGINA = array( 'page', 'section', 'container' );

/**
 * Modo gravado no banco: auto (segue a janela), ligado ou desligado.
 *
 * @return string
 */
function cetrus_black_modo() {
	$modo = get_option( CETRUS_BLACK_OPCAO, 'auto' );
	return in_array( $modo, array( 'auto', 'ligado', 'desligado' ), true ) ? $modo : 'auto';
}

/**
 * @param int|null $agora Timestamp; padrao = agora.
 * @return bool
 */
function cetrus_black_na_janela( $agora = null ) {
	$agora = null === $agora ? time() : (int) $agora;
	return $agora >= strtotime( CETRUS_BLACK_INICIO ) && $agora < strtotime( CETRUS_BLACK_FIM );
}

/**
 * Previa por parametro, so para usuario logado que pode editar.
 *
 * @return bool|null null = sem previa nesta requisicao.
 */
function cetrus_black_previa() {
	if ( ! isset( $_GET['cetrus_black'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return null;
	}
	if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
		return null;
	}
	return '1' === (string) wp_unslash( $_GET['cetrus_black'] ); // phpcs:ignore WordPress.Security.NonceVerification
}

/**
 * A versao Black vale nesta requisicao?
 *
 * Nunca no painel, no editor/preview do Elementor (para nao misturar o clone
 * com o original que esta sendo editado), em REST ou feed.
 *
 * @return bool
 */
function cetrus_black_ativo() {
	static $ativo = null;
	if ( null !== $ativo ) {
		return $ativo;
	}
	if ( ! did_action( 'wp' ) ) {
		return false; // cedo demais para decidir; nao guarda em cache.
	}
	if ( ( is_admin() && ! wp_doing_ajax() ) || is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$ativo = false;
		return $ativo;
	}
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['elementor_library'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$ativo = false;
		return $ativo;
	}

	$previa = cetrus_black_previa();
	if ( null !== $previa ) {
		$ativo = $previa;
	} elseif ( 'ligado' === cetrus_black_modo() ) {
		$ativo = true;
	} elseif ( 'desligado' === cetrus_black_modo() ) {
		$ativo = false;
	} else {
		$ativo = cetrus_black_na_janela();
	}
	$ativo = (bool) apply_filters( 'cetrus_black_ativo', $ativo );
	return $ativo;
}

/**
 * Vinculos original => clone, lidos dos titulos "[BLACK-2026] #<id> ...".
 *
 * So entram clones publicados cujo original existe e tem o mesmo tipo de
 * template (header com header, product com product...). Havendo dois clones
 * para o mesmo original, vale o de id maior e o caso aparece como alerta na
 * tela de Ferramentas.
 *
 * @param bool $com_alertas Devolve tambem a lista de problemas encontrados.
 * @return array<int,int>|array{mapa: array<int,int>, alertas: string[], linhas: array[]}
 */
function cetrus_black_mapa( $com_alertas = false ) {
	static $cache = null;
	if ( ! $com_alertas && null !== $cache ) {
		return $cache;
	}
	if ( ! $com_alertas ) {
		$salvo = get_transient( CETRUS_BLACK_CACHE );
		if ( is_array( $salvo ) ) {
			$cache = $salvo;
			return $cache;
		}
	}

	global $wpdb;
	$clones = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ID, post_title, post_status, post_modified FROM {$wpdb->posts}
			 WHERE post_type = 'elementor_library' AND post_status IN ('publish','draft','private')
			 AND post_title LIKE %s ORDER BY ID ASC",
			$wpdb->esc_like( CETRUS_BLACK_PREFIXO ) . '%'
		)
	);

	$mapa    = array();
	$alertas = array();
	$linhas  = array();
	foreach ( (array) $clones as $c ) {
		$linha = array(
			'clone'    => (int) $c->ID,
			'titulo'   => $c->post_title,
			'status'   => $c->post_status,
			'alterado' => $c->post_modified,
			'original' => 0,
			'tipo'     => (string) get_post_meta( $c->ID, '_elementor_template_type', true ),
			'ok'       => false,
		);
		if ( ! preg_match( '/^' . preg_quote( CETRUS_BLACK_PREFIXO, '/' ) . '\s*#(\d+)\b/', $c->post_title, $m ) ) {
			$alertas[] = sprintf( 'Clone %d sem "#<id do original>" no titulo.', $c->ID );
			$linhas[]  = $linha;
			continue;
		}
		$original          = (int) $m[1];
		$linha['original'] = $original;
		$tipo_post         = get_post_type( $original );
		$tipo_original     = (string) get_post_meta( $original, '_elementor_template_type', true );
		if ( 'page' === $tipo_post ) {
			// Pagina: o clone e um modelo de pagina/secao do Elementor (sem URL propria).
			$tipo_original = in_array( $linha['tipo'], CETRUS_BLACK_TIPOS_PAGINA, true ) ? $linha['tipo'] : 'page';
		}
		if ( 'elementor_library' !== $tipo_post && 'page' !== $tipo_post ) {
			$alertas[] = sprintf( 'Clone %d aponta para #%d, que nao e template do Elementor nem pagina neste site.', $c->ID, $original );
		} elseif ( $tipo_original !== $linha['tipo'] ) {
			$alertas[] = sprintf( 'Clone %d (%s) e original #%d (%s) tem tipos diferentes.', $c->ID, $linha['tipo'], $original, $tipo_original );
		} elseif ( 'publish' !== $c->post_status ) {
			$alertas[] = sprintf( 'Clone %d esta como %s; so clone publicado entra na troca.', $c->ID, $c->post_status );
		} else {
			if ( isset( $mapa[ $original ] ) ) {
				$alertas[] = sprintf( 'Mais de um clone para #%d (%d e %d); vale o %d.', $original, $mapa[ $original ], $c->ID, $c->ID );
			}
			$mapa[ $original ] = (int) $c->ID;
			$linha['ok']       = true;
		}
		$linhas[] = $linha;
	}

	set_transient( CETRUS_BLACK_CACHE, $mapa, 10 * MINUTE_IN_SECONDS );
	$cache = $mapa;
	return $com_alertas ? array(
		'mapa'    => $mapa,
		'alertas' => $alertas,
		'linhas'  => $linhas,
	) : $mapa;
}

/** Qualquer gravacao em template do Elementor invalida o mapa. */
foreach ( array( 'save_post_elementor_library', 'trashed_post', 'deleted_post', 'untrashed_post' ) as $cetrus_black_gancho ) {
	add_action(
		$cetrus_black_gancho,
		function () {
			delete_transient( CETRUS_BLACK_CACHE );
		}
	);
}
unset( $cetrus_black_gancho );

/**
 * A troca em si: o Elementor Pro passa por aqui cada template candidato de uma
 * localizacao, ja com as condicoes do original avaliadas.
 */
add_filter(
	'elementor/theme/get_location_templates/template_id',
	function ( $template_id ) {
		if ( ! cetrus_black_ativo() ) {
			return $template_id;
		}
		$mapa = cetrus_black_mapa();
		return isset( $mapa[ (int) $template_id ] ) ? $mapa[ (int) $template_id ] : $template_id;
	}
);

/**
 * Paginas (Home, Vitrine, Conteudos gratuitos): a pagina original continua sendo
 * a pagina servida (URL, title, description, canonical, breadcrumb e schema do
 * Yoast sao os dela); so o conteudo do Elementor e trocado pelo do modelo
 * "[BLACK-2026] #<id da pagina>".
 *
 * @return int Id do modelo Black da pagina atual, ou 0.
 */
function cetrus_black_clone_da_pagina() {
	if ( ! cetrus_black_ativo() || ! is_page() ) {
		return 0;
	}
	$mapa = cetrus_black_mapa();
	$id   = (int) get_queried_object_id();
	return isset( $mapa[ $id ] ) ? (int) $mapa[ $id ] : 0;
}

/** O original nao e montado (nem o CSS dele carregado) quando ha clone. */
add_filter(
	'elementor/frontend/builder_content_data',
	function ( $data, $post_id ) {
		$clone = cetrus_black_clone_da_pagina();
		return ( $clone && (int) $post_id === (int) get_queried_object_id() ) ? array() : $data;
	},
	10,
	2
);

/** CSS do clone no <head>, como o de qualquer pagina do Elementor. */
add_action(
	'wp_enqueue_scripts',
	function () {
		$clone = cetrus_black_clone_da_pagina();
		if ( $clone && class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			\Elementor\Core\Files\CSS\Post::create( $clone )->enqueue();
		}
	},
	20
);

add_filter(
	'the_content',
	function ( $content ) {
		$clone = cetrus_black_clone_da_pagina();
		if ( ! $clone || ! in_the_loop() || ! is_main_query() || (int) get_the_ID() !== (int) get_queried_object_id() || ! class_exists( '\Elementor\Plugin' ) ) {
			return $content;
		}
		$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $clone );
		return '' !== $html ? $html : $content;
	},
	20
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( cetrus_black_ativo() ) {
			$classes[] = 'cetrus-black-2026';
		}
		return $classes;
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		// Sem o arquivo (ex.: staging com o codigo colado no Code Snippets) o CSS
		// vai por fora; as regras so pegam com a classe no body, entao nada vaza.
		if ( ! cetrus_black_ativo() || ! file_exists( WPMU_PLUGIN_DIR . '/cetrus-black-november-2026/black.css' ) ) {
			return;
		}
		wp_enqueue_style(
			'cetrus-black-2026',
			WPMU_PLUGIN_URL . '/cetrus-black-november-2026/black.css',
			array(),
			CETRUS_BLACK_VERSAO
		);
	},
	20
);

/* ------------------------------------------------------------------------- *
 * Ferramentas > Black November 2026: estado, chave de emergencia e vinculos.
 * ------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Black November 2026', 'Black November 2026', 'manage_options', 'cetrus-black-2026', 'cetrus_black_tela' );
	}
);

add_action(
	'admin_post_cetrus_black_modo',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissao.' );
		}
		check_admin_referer( 'cetrus_black_modo' );
		$modo = isset( $_POST['modo'] ) ? sanitize_key( wp_unslash( $_POST['modo'] ) ) : 'auto';
		if ( in_array( $modo, array( 'auto', 'ligado', 'desligado' ), true ) ) {
			update_option( CETRUS_BLACK_OPCAO, $modo, false );
		}
		delete_transient( CETRUS_BLACK_CACHE );
		wp_safe_redirect( admin_url( 'tools.php?page=cetrus-black-2026&salvo=1' ) );
		exit;
	}
);

/** Tela de Ferramentas. */
function cetrus_black_tela() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$dados  = cetrus_black_mapa( true );
	$modo   = cetrus_black_modo();
	$janela = cetrus_black_na_janela();
	$efeito = 'ligado' === $modo || ( 'auto' === $modo && $janela );
	$fuso   = new DateTimeZone( 'America/Sao_Paulo' );
	$fmt    = function ( $iso ) use ( $fuso ) {
		return ( new DateTime( $iso ) )->setTimezone( $fuso )->format( 'd/m/Y H:i' );
	};

	echo '<div class="wrap"><h1>Black November 2026</h1>';
	if ( isset( $_GET['salvo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Modo gravado. Visitantes veem a mudanca em ate ~5 minutos (cache de borda).</p></div>';
	}
	printf(
		'<p>Janela: <strong>%s</strong> a <strong>%s</strong> (Brasilia). Agora: <strong>%s</strong>. Modo: <strong>%s</strong>. Versao Black para visitantes: <strong>%s</strong>.</p>',
		esc_html( $fmt( CETRUS_BLACK_INICIO ) ),
		esc_html( $fmt( CETRUS_BLACK_FIM ) ),
		$janela ? 'dentro da janela' : 'fora da janela',
		esc_html( $modo ),
		$efeito ? 'LIGADA' : 'desligada'
	);
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'cetrus_black_modo' );
	echo '<input type="hidden" name="action" value="cetrus_black_modo"><select name="modo">';
	foreach ( array(
		'auto'      => 'auto (segue a janela)',
		'ligado'    => 'ligado (forca a versao Black)',
		'desligado' => 'desligado (rollback imediato)',
	) as $valor => $rotulo ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $valor ), selected( $modo, $valor, false ), esc_html( $rotulo ) );
	}
	echo '</select> ';
	submit_button( 'Gravar modo', 'primary', 'submit', false );
	echo '</form>';

	printf(
		'<p>Previa (so logado): <a href="%1$s" target="_blank">home com Black</a> | <a href="%2$s" target="_blank">home sem Black</a>. Em qualquer pagina: <code>?cetrus_black=1</code> ou <code>?cetrus_black=0</code>.</p>',
		esc_url( add_query_arg( 'cetrus_black', '1', home_url( '/' ) ) ),
		esc_url( add_query_arg( 'cetrus_black', '0', home_url( '/' ) ) )
	);

	foreach ( $dados['alertas'] as $alerta ) {
		echo '<div class="notice notice-warning inline"><p>' . esc_html( $alerta ) . '</p></div>';
	}
	echo '<h2>Vinculos original &rarr; clone</h2><table class="widefat striped"><thead><tr><th>Original</th><th>Clone Black</th><th>Tipo</th><th>Status</th><th>Alterado</th><th>Na troca</th></tr></thead><tbody>';
	if ( empty( $dados['linhas'] ) ) {
		echo '<tr><td colspan="6">Nenhum template com titulo iniciado por ' . esc_html( CETRUS_BLACK_PREFIXO ) . '. Sem clones, a troca nao faz nada.</td></tr>';
	}
	foreach ( $dados['linhas'] as $l ) {
		printf(
			'<tr><td>%s</td><td><a href="%s">%d</a> %s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
			$l['original'] ? esc_html( '#' . $l['original'] . ' ' . get_the_title( $l['original'] ) ) : '-',
			esc_url( admin_url( 'post.php?post=' . $l['clone'] . '&action=elementor' ) ),
			(int) $l['clone'],
			esc_html( $l['titulo'] ),
			esc_html( $l['tipo'] ),
			esc_html( $l['status'] ),
			esc_html( $l['alterado'] ),
			$l['ok'] ? 'sim' : 'nao'
		);
	}
	echo '</tbody></table></div>';
}
