<?php
/**
 * Plugin Name: Cetrus - Black November 2026 (camada temporaria)
 * Description: Versao Black do site, ligada e desligada MANUALMENTE por commit no GitHub (chave CETRUS_BLACK_VIRADA) ou, em emergencia, em Ferramentas > Black November 2026. Troca, na hora de renderizar, cada template do Elementor pelo modelo "[BLACK-2026] #<id original>" e acrescenta a camada visual (classe no body + CSS). Desligada nao muda nada. Documentacao: PROJETO_PADRONIZACAO_PAGINAS_CETRUS.md na raiz do repositorio.
 * Version:     0.4.1
 * Author:      Cetrus / Sanar
 *
 * 0.4.1 (29/10/2026): popups abertos por id fixo em shortcodes (inscricao do Lyceum) apontam para o
 * modelo Black; antes o botao "Quero me inscrever" nao abria nada na versao Black.
 * 0.4.0 (28/10/2026, noite): a virada passa a ser por commit no GitHub. A chave CETRUS_BLACK_VIRADA
 * (logo abaixo) vem "desligado"; o commit de 01/11 a muda para "ligado" e o de 01/12 a devolve para
 * "desligado", cada um seguido de implantacao manual. O painel continua com dois modos de
 * emergencia que valem por cima do codigo (forcar desligado / forcar ligado), sem deploy.
 * 0.3.0 (28/10/2026): SEM virada automatica. Nao ha data de inicio nem de fim no codigo: uma pessoa
 * liga a versao Black (modo "ligado") e depois a desliga (modo "desligado"). Padrao = desligado.
 * 0.2.0 (28/10/2026): validado no staging (filtro do Elementor Pro 4.1.3 confirmado, varredura de
 * 438 URLs com SEO identico); telas que ficam iguais a producao em cetrus_black_tela_fora().
 *
 * COMO FUNCIONA
 * - Os templates originais NAO sao editados e continuam donos das condicoes de
 *   exibicao. Cada versao Black e um template novo do Elementor, sem condicoes,
 *   com o titulo "[BLACK-2026] #12073 <qualquer texto>" (12073 = id do original
 *   NAQUELE site; os ids dos modelos mudam de um site para outro, por isso o
 *   vinculo e pelo titulo e nao por id fixo no codigo).
 * - Com a versao Black ligada, quando o Elementor escolhe o template de uma localizacao
 *   (header, footer, single product...), este arquivo devolve o id do modelo no
 *   lugar do original. Produto novo cadastrado em novembro cai na condicao do
 *   original (modalidade, tag...) e, por isso, recebe a versao Black sozinho.
 * - Paginas do Artifact (Home, Vitrine, Conteudos gratuitos): o modelo e do tipo
 *   pagina com titulo "[BLACK-2026] #<id da pagina>". A pagina original continua
 *   sendo servida (URL e todo o SEO do Yoast sao dela); so o conteudo do Elementor
 *   e trocado pelo do modelo.
 * - Desligada, o filtro devolve o id original: o rollback nao apaga nada do banco
 *   (produtos, leads, pedidos e edicoes continuam).
 *
 * CHAVES (todas manuais; nada liga ou desliga sozinho)
 * - GitHub (padrao): CETRUS_BLACK_VIRADA abaixo. "ligado" = versao Black para todos.
 *   Vale depois da implantacao (WP.com > Implantacoes) do commit que a altera.
 * - Painel, Ferramentas > Black November 2026 (cada troca fica registrada com usuario e horario):
 *     "seguir o GitHub" (padrao) | "forcar desligado" (emergencia) | "forcar ligado".
 *   Ou WP-CLI: wp option update cetrus_black_2026_modo desligado (github | ligado).
 * - Previa so para quem esta logado com permissao de edicao:
 *     ?cetrus_black=1 (forca ligado) e ?cetrus_black=0 (forca desligado).
 *   Logado nao passa pelo cache de pagina, entao a previa nao vaza.
 *
 * REVERTER DE VEZ: chave em "desligado" (efeito na implantacao, ate ~5 min de cache
 * de borda) e depois remover este arquivo e a pasta cetrus-black-november-2026/ do
 * repositorio e do servidor. Os modelos [BLACK-2026] podem ficar como rascunho.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * CHAVE DA VIRADA PELO GITHUB: "desligado" ou "ligado".
 * 01/11/2026: commit que troca "desligado" por "ligado" + implantacao. 01/12/2026: commit que volta
 * para "desligado" + implantacao. Nada mais precisa mudar no codigo para ligar ou desligar.
 */
if ( ! defined( 'CETRUS_BLACK_VIRADA' ) ) {
	define( 'CETRUS_BLACK_VIRADA', 'desligado' );
}

const CETRUS_BLACK_VERSAO   = '0.4.1';
const CETRUS_BLACK_PREFIXO  = '[BLACK-2026]';
const CETRUS_BLACK_OPCAO    = 'cetrus_black_2026_modo'; // modo do painel: github (padrao), desligado ou ligado
const CETRUS_BLACK_REGISTRO = 'cetrus_black_2026_registro'; // quem ligou/desligou e quando
const CETRUS_BLACK_CACHE    = 'cetrus_black_2026_mapa';
// Tipos de modelo do Elementor aceitos como versao Black de uma PAGINA (Home, Vitrine...).
const CETRUS_BLACK_TIPOS_PAGINA = array( 'page', 'section', 'container' );
// Telas que ficam iguais a producao (cetrus_black_tela_fora): graduacao e fellowship v2.
const CETRUS_BLACK_FORA_MODALIDADES = array( 149 );  // v2_modalidade graduacao -> template 19379
const CETRUS_BLACK_FORA_TAGS        = array( 1262 ); // product_tag teste-fellow -> template 28779 (fellowship v2)

/**
 * Modo escolhido no painel: "github" (padrao: vale CETRUS_BLACK_VIRADA), "desligado" ou "ligado"
 * (manuais, por cima do codigo). Qualquer outro valor, inclusive o "auto" da 0.2.0, conta como github.
 *
 * @return string
 */
function cetrus_black_modo_painel() {
	$modo = get_option( CETRUS_BLACK_OPCAO, 'github' );
	return in_array( $modo, array( 'ligado', 'desligado' ), true ) ? $modo : 'github';
}

/**
 * Modo que vale agora para visitantes: "ligado" ou "desligado".
 *
 * @return string
 */
function cetrus_black_modo() {
	$painel = cetrus_black_modo_painel();
	if ( 'github' !== $painel ) {
		return $painel;
	}
	return 'ligado' === CETRUS_BLACK_VIRADA ? 'ligado' : 'desligado';
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
 * A versao Black vale nesta requisicao? So com o modo "ligado" (ou na previa de quem edita).
 *
 * Nunca no painel, no editor/preview do Elementor (para nao misturar o modelo
 * com o original que esta sendo editado), em REST ou feed.
 *
 * @return bool
 */
function cetrus_black_ativo() {
	static $ativo = null;
	if ( null !== $ativo ) {
		return $ativo;
	}
	// Desde o init (usuario ja carregado). Nao esperar o 'wp': nos arquivos de produto e na
	// busca o Elementor Pro escolhe o template no pre_get_posts e guarda a escolha.
	if ( ! did_action( 'init' ) ) {
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
	} else {
		// Trava: sem nenhum modelo [BLACK-2026] publicado (montar-black.js ainda nao rodou neste site),
		// ligar so poria a classe e o CSS Black por cima dos templates originais.
		$ativo = 'ligado' === cetrus_black_modo() && ! empty( cetrus_black_mapa() );
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
		if ( ! cetrus_black_na_tela() ) {
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

/** @return int Id do clone se este the_content e o da pagina principal, ou 0. */
function cetrus_black_clone_no_conteudo() {
	$clone = cetrus_black_clone_da_pagina();
	if ( ! $clone || ! in_the_loop() || ! is_main_query() || (int) get_the_ID() !== (int) get_queried_object_id() || ! class_exists( '\Elementor\Plugin' ) ) {
		return 0;
	}
	return $clone;
}

/**
 * Sem o builder do original, o Elementor devolve o post_content (texto reserva com os
 * shortcodes) e o do_shortcode (prioridade 11) o executaria antes da troca. Shortcodes que
 * imprimem uma vez por pagina (cetrus_newsletter) ficariam vazios no clone. Visto em 23/10/2026.
 */
add_filter(
	'the_content',
	function ( $content ) {
		return cetrus_black_clone_no_conteudo() ? '' : $content;
	},
	1
);

add_filter(
	'the_content',
	function ( $content ) {
		$clone = cetrus_black_clone_no_conteudo();
		if ( ! $clone ) {
			return $content;
		}
		$html = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $clone );
		return '' !== $html ? $html : $content;
	},
	20
);

/**
 * Telas que ficam IGUAIS A PRODUCAO mesmo com a campanha ligada (decisao de 28/10/2026): nelas
 * nada muda, nem header, barra e rodape, nem classe no body ou CSS.
 * - paginas sem modelo [BLACK-2026] (institucionais, /especialidades/, curriculos, 404, posts);
 * - arquivo de especialidade (template 25011);
 * - graduacao (modalidade 149, template 19379) e fellowship v2 (tag 1262, template 28779).
 *
 * Antes do 'wp' (pre_get_posts, quando o Elementor Pro escolhe o template dos arquivos e da
 * busca) so os arquivos sao decididos; produto e pagina esperam o objeto da consulta.
 *
 * @return bool
 */
function cetrus_black_tela_fora() {
	if ( ! did_action( 'parse_query' ) ) {
		return false;
	}
	if ( is_tax( 'v2_especialidade' ) ) {
		$fora = true;
	} elseif ( is_search() || is_post_type_archive( 'product' ) || is_tax( array( 'v2_modalidade', 'v2_cidade' ) ) ) {
		$fora = false;
	} elseif ( ! did_action( 'wp' ) ) {
		return false;
	} elseif ( is_singular( 'product' ) ) {
		$id   = (int) get_queried_object_id();
		$fora = has_term( CETRUS_BLACK_FORA_MODALIDADES, 'v2_modalidade', $id ) || has_term( CETRUS_BLACK_FORA_TAGS, 'product_tag', $id );
	} else {
		$fora = ! cetrus_black_clone_da_pagina();
	}
	return (bool) apply_filters( 'cetrus_black_tela_fora', $fora );
}

/**
 * A versao Black vale NESTA TELA? Modo "ligado" (ou previa de quem edita) e tela fora da lista
 * de cetrus_black_tela_fora(). E o que decide troca de template, classe, CSS e shortcodes.
 *
 * @return bool
 */
function cetrus_black_na_tela() {
	return cetrus_black_ativo() && ! cetrus_black_tela_fora();
}

add_filter(
	'body_class',
	function ( $classes ) {
		if ( cetrus_black_na_tela() ) {
			// cetrus-black-conteudo: nome antigo das regras de conteudo do black.css (23/10); hoje
			// toda tela com Black tem conteudo Black, entao as duas classes andam juntas.
			$classes[] = 'cetrus-black-2026';
			$classes[] = 'cetrus-black-conteudo';
			// Modalidade no body (cetrus-black-mod-pos-graduacao...): o template 12073 atende pos E
			// intensivos, e os ajustes so da pos (Artifact) precisam distinguir os dois.
			if ( is_singular( 'product' ) ) {
				$termos = get_the_terms( get_queried_object_id(), 'v2_modalidade' );
				foreach ( is_array( $termos ) ? $termos : array() as $termo ) {
					$classes[] = 'cetrus-black-mod-' . sanitize_html_class( $termo->slug );
				}
			}
		}
		return $classes;
	}
);

/**
 * "Quem viu este curso tambem se interessou por:" (carrossel 366d4ed do template 12073 e das
 * variantes) lista os produtos "upsell" do WooCommerce. Curso sem upsell cadastrado mostra o
 * carrossel vazio, na producao tambem (ex.: medicina-canabinoide-pratica-clinica-e-prescricao,
 * 28/10/2026). Na versao Black, SO quando nao ha upsell, entram cursos da mesma especialidade e,
 * se faltar, da mesma modalidade. Fora da campanha nada muda.
 */
add_filter(
	'woocommerce_product_get_upsell_ids',
	function ( $ids, $produto ) {
		if ( ! empty( $ids ) || ! is_object( $produto ) || ! did_action( 'wp' ) || ! is_singular( 'product' ) ) {
			return $ids;
		}
		if ( (int) $produto->get_id() !== (int) get_queried_object_id() || ! cetrus_black_na_tela() ) {
			return $ids;
		}
		return cetrus_black_relacionados( (int) $produto->get_id() );
	},
	10,
	2
);

/**
 * Cursos publicados e visiveis no catalogo com a mesma especialidade (depois a mesma modalidade).
 * So entram cursos com v2_miniatura_titulo: e o titulo do card do carrossel (sem ele o card sai sem nome).
 *
 * @param int $id     Produto atual.
 * @param int $limite Maximo de cursos.
 * @return int[]
 */
function cetrus_black_relacionados( $id, $limite = 8 ) {
	$chave = 'cetrus_black_rel2_' . $id;
	$salvo = get_transient( $chave );
	if ( is_array( $salvo ) ) {
		return $salvo;
	}
	$ids = array();
	foreach ( array( 'v2_especialidade', 'v2_modalidade' ) as $tax ) {
		$termos = wp_get_post_terms( $id, $tax, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $termos ) || empty( $termos ) ) {
			continue;
		}
		$achados = get_posts(
			array(
				'post_type'        => 'product',
				'post_status'      => 'publish',
				'posts_per_page'   => $limite - count( $ids ),
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'post__not_in'     => array_merge( array( $id ), $ids ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => 'v2_miniatura_titulo',
						'value'   => '',
						'compare' => '!=',
					),
				),
				'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'taxonomy' => $tax,
						'terms'    => array_map( 'intval', $termos ),
					),
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'name',
						'terms'    => array( 'exclude-from-catalog' ),
						'operator' => 'NOT IN',
					),
				),
			)
		);
		$ids = array_merge( $ids, array_map( 'intval', $achados ) );
		if ( count( $ids ) >= $limite ) {
			break;
		}
	}
	$ids = array_slice( array_values( array_unique( $ids ) ), 0, $limite );
	set_transient( $chave, $ids, HOUR_IN_SECONDS );
	return $ids;
}

/*
 * Popups abertos por id fixo no HTML de shortcodes. Ex.: inscricao do Lyceum
 * ([lyceum_course_selects] e o botao "Quero me inscrever"), com data-popup-inscricao-id e
 * data-popup-target="29363". Na versao Black o popup impresso na pagina e o modelo
 * [BLACK-2026] (outro id) e o botao nao abria nada (achado no teste de 29/10). So na
 * versao Black, a saida dos shortcodes passa a apontar para o modelo.
 */

/**
 * Id do modelo Black de um popup original, ou 0.
 *
 * @param int|string $id Popup original.
 * @return int
 */
function cetrus_black_popup_clone( $id ) {
	$id   = (int) $id;
	$mapa = cetrus_black_mapa();
	if ( ! $id || ! isset( $mapa[ $id ] ) || 'popup' !== get_post_meta( $id, '_elementor_template_type', true ) ) {
		return 0;
	}
	return (int) $mapa[ $id ];
}

/**
 * Troca ids de popup original pelos do modelo Black em atributos data-* e em links de acao
 * do Elementor (#elementor-action:action=popup:open&settings=<base64 de {"id":N,...}>).
 *
 * @param string $html HTML de um shortcode.
 * @return string
 */
function cetrus_black_troca_popups( $html ) {
	$html = preg_replace_callback(
		'/(data-(?:popup-inscricao-id|popup-target|elementor-popup-id)=["\'])(\d+)(["\'])/',
		function ( $m ) {
			$clone = cetrus_black_popup_clone( $m[2] );
			return $clone ? $m[1] . $clone . $m[3] : $m[0];
		},
		$html
	);
	return preg_replace_callback(
		'/(popup(?::|%3A)open(?:&amp;|&|%26)settings(?:=|%3D))([A-Za-z0-9+\/=%]+)/',
		function ( $m ) {
			$b64   = rawurldecode( $m[2] );
			$conf  = json_decode( (string) base64_decode( $b64, true ), true );
			$clone = is_array( $conf ) && isset( $conf['id'] ) ? cetrus_black_popup_clone( $conf['id'] ) : 0;
			if ( ! $clone ) {
				return $m[0];
			}
			$conf['id'] = $clone;
			$novo       = base64_encode( wp_json_encode( $conf ) );
			// link com URL-encode (settings%3D): o base64 novo vai codificado tambem
			return $m[1] . ( false !== strpos( $m[1], '%' ) ? rawurlencode( $novo ) : $novo );
		},
		$html
	);
}

add_filter(
	'do_shortcode_tag',
	function ( $saida ) {
		if ( ! is_string( $saida ) || false === strpos( $saida, 'popup' ) || ! cetrus_black_na_tela() ) {
			return $saida;
		}
		return cetrus_black_troca_popups( $saida );
	},
	20
);

// Reserva do script do Lyceum (lyceum_course_vars.popup_config.inscricao_id), usada quando o
// shortcode nao traz o id: tambem aponta para o modelo Black. Depois dos scripts do rodape.
add_action(
	'wp_footer',
	function () {
		if ( ! cetrus_black_na_tela() ) {
			return;
		}
		$pares = array();
		foreach ( array_keys( cetrus_black_mapa() ) as $original ) {
			$clone = cetrus_black_popup_clone( $original );
			if ( $clone ) {
				$pares[ $original ] = $clone;
			}
		}
		if ( $pares ) {
			printf(
				"<script>(function(m){var v=window.lyceum_course_vars;if(v&&v.popup_config&&m[v.popup_config.inscricao_id]){v.popup_config.inscricao_id=String(m[v.popup_config.inscricao_id]);}})(%s);</script>\n",
				wp_json_encode( $pares )
			);
		}
	},
	99
);

/**
 * O curso aberto e de pos-graduacao (modalidade pos-graduacao)?
 *
 * @return bool
 */
function cetrus_black_e_pos() {
	return is_singular( 'product' ) && has_term( 'pos-graduacao', 'v2_modalidade', get_queried_object_id() );
}

/**
 * [cetrus_black_selo] - selo "Condicoes exclusivas" (decisao D5: vale para todos os cursos).
 * Fora da versao Black nao imprime nada, entao pode ficar nos clones sem efeito no site normal.
 */
add_shortcode(
	'cetrus_black_selo',
	function ( $atts ) {
		if ( ! cetrus_black_na_tela() ) {
			return '';
		}
		$atts = shortcode_atts( array( 'texto' => 'Condições exclusivas' ), $atts, 'cetrus_black_selo' );
		return '<span class="cbn-selo">' . esc_html( $atts['texto'] ) . '</span>';
	}
);

/**
 * [cetrus_black_pertencimento] - frase "Todo <especialidade> que hoje e referencia, um dia precisou
 * tomar essa decisao." do Artifact (artboard Produto), SO em pos-graduacao. Paragrafo, nao heading,
 * para nao mudar a estrutura de H2 da pagina (SEO).
 */
add_shortcode(
	'cetrus_black_pertencimento',
	function () {
		if ( ! cetrus_black_na_tela() || ! cetrus_black_e_pos() ) {
			return '';
		}
		$palavras = (array) apply_filters( 'cetrus_black_pertencimento_palavras', array( 'ultrassonografista', 'cardiologista', 'radiologista', 'ginecologista', 'ortopedista', 'dermatologista' ) );
		$palavras = array_values( array_filter( array_map( 'sanitize_text_field', $palavras ) ) );
		if ( ! $palavras ) {
			return '';
		}
		ob_start();
		?>
<section class="cbn-pert" aria-label="Pertencimento">
	<p class="cbn-pert__t">
		<span class="cbn-sr">Todo <?php echo esc_html( $palavras[0] ); ?> que hoje é referência, um dia precisou tomar essa decisão.</span>
		<span aria-hidden="true">Todo <span class="cbn-pert__p" data-palavras="<?php echo esc_attr( wp_json_encode( $palavras ) ); ?>"><?php echo esc_html( $palavras[0] ); ?></span> que hoje é referência,<br class="cbn-pert__br"> <strong>um dia precisou tomar essa decisão.</strong></span>
	</p>
</section>
<script>
(function () {
	var el = document.currentScript && document.currentScript.previousElementSibling;
	var p = el && el.querySelector('.cbn-pert__p');
	if (!p || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
	var lista = JSON.parse(p.getAttribute('data-palavras') || '[]'), i = 0;
	if (lista.length < 2) return;
	setInterval(function () {
		p.classList.add('is-saindo');
		setTimeout(function () {
			i = (i + 1) % lista.length;
			p.textContent = lista[i];
			p.classList.remove('is-saindo');
		}, 300);
	}, 2600);
})();
</script>
		<?php
		return (string) ob_get_clean();
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		// Sem o arquivo (ex.: staging com o codigo colado no Code Snippets) o CSS
		// vai por fora; as regras so pegam com a classe no body, entao nada vaza.
		if ( ! cetrus_black_na_tela() || ! file_exists( WPMU_PLUGIN_DIR . '/cetrus-black-november-2026/black.css' ) ) {
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
		$modo = isset( $_POST['modo'] ) ? sanitize_key( wp_unslash( $_POST['modo'] ) ) : '';
		if ( in_array( $modo, array( 'github', 'ligado', 'desligado' ), true ) && $modo !== cetrus_black_modo_painel() ) {
			update_option( CETRUS_BLACK_OPCAO, $modo, false );
			// Registro da virada manual: quem, quando (Brasilia) e para qual modo. Guarda as 30 ultimas.
			$registro   = (array) get_option( CETRUS_BLACK_REGISTRO, array() );
			$usuario    = wp_get_current_user();
			$registro[] = array(
				'quando'  => ( new DateTime( 'now', new DateTimeZone( 'America/Sao_Paulo' ) ) )->format( 'd/m/Y H:i:s' ),
				'usuario' => $usuario ? $usuario->user_login : '?',
				'modo'    => $modo,
			);
			update_option( CETRUS_BLACK_REGISTRO, array_slice( $registro, -30 ), false );
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
	$painel = cetrus_black_modo_painel();
	$github = 'ligado' === CETRUS_BLACK_VIRADA ? 'ligado' : 'desligado';

	echo '<div class="wrap"><h1>Black November 2026</h1>';
	if ( isset( $_GET['salvo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success"><p>Modo gravado. Visitantes veem a mudanca em ate ~5 minutos (cache de borda).</p></div>';
	}
	printf(
		'<p style="font-size:15px">Versao Black para visitantes: <strong>%s</strong>. Nada liga ou desliga sozinho.</p>',
		'ligado' === $modo && ! empty( $dados['mapa'] ) ? 'LIGADA' : 'desligada'
	);
	printf(
		'<p>Chave do GitHub (<code>CETRUS_BLACK_VIRADA</code> em <code>wp-content/mu-plugins/cetrus-black-november-2026.php</code>, no codigo implantado): <strong>%s</strong>. Quem decide agora: <strong>%s</strong>.</p>',
		esc_html( $github ),
		'github' === $painel ? 'o GitHub' : esc_html( 'o painel (forcado ' . $painel . ')' )
	);
	if ( 'github' !== $painel ) {
		echo '<div class="notice notice-warning inline"><p>O painel esta forcando a versao Black ' . esc_html( $painel ) . '. Enquanto isso, um commit que mude a chave do GitHub nao tem efeito. Para voltar a seguir o GitHub, escolha "seguir o GitHub".</p></div>';
	}
	if ( 'ligado' === $modo && empty( $dados['mapa'] ) ) {
		echo '<div class="notice notice-error inline"><p>A chave esta ligada, mas nao ha nenhum modelo [BLACK-2026] publicado: a versao Black continua DESLIGADA para visitantes ate o montar-black.js rodar neste site.</p></div>';
	}
	echo '<p>Ficam iguais a producao mesmo com a versao Black ligada: paginas sem modelo [BLACK-2026] (institucionais, curriculos, 404), arquivo de especialidade, graduacao e fellowship v2 (<code>cetrus_black_tela_fora()</code>).</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'cetrus_black_modo' );
	echo '<input type="hidden" name="action" value="cetrus_black_modo"><select name="modo">';
	foreach ( array(
		'github'    => 'seguir o GitHub (padrao; hoje: ' . $github . ')',
		'desligado' => 'forcar desligado (emergencia / rollback sem implantacao)',
		'ligado'    => 'forcar ligado (sem implantacao)',
	) as $valor => $rotulo ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $valor ), selected( $painel, $valor, false ), esc_html( $rotulo ) );
	}
	echo '</select> ';
	submit_button( 'Gravar modo', 'primary', 'submit', false );
	echo '</form>';

	$registro = array_reverse( (array) get_option( CETRUS_BLACK_REGISTRO, array() ) );
	if ( $registro ) {
		echo '<h2>Registro das viradas</h2><table class="widefat striped" style="max-width:640px"><thead><tr><th>Quando (Brasilia)</th><th>Usuario</th><th>Modo</th></tr></thead><tbody>';
		foreach ( $registro as $r ) {
			printf( '<tr><td>%s</td><td>%s</td><td>%s</td></tr>', esc_html( $r['quando'] ?? '' ), esc_html( $r['usuario'] ?? '' ), esc_html( $r['modo'] ?? '' ) );
		}
		echo '</tbody></table>';
	}

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
