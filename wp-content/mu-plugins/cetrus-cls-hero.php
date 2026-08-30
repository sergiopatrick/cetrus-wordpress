<?php
/**
 * Plugin Name: Cetrus - CLS do hero da home
 * Description: Antecipa para o <head> o CSS do template 15396 (busca do hero), que o Elementor carrega no meio do body e por isso chega depois da primeira pintura.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * DIAGNOSTICO (30/08/2026, Chrome real via CDP, amostragem a cada 100 ms)
 * O CLS da home nao vem do carrossel nem das fontes. As duas hipoteses foram
 * testadas e descartadas:
 *   - carrossel: altura identica com e sem JavaScript (340/340 desktop, 311/311 mobile)
 *   - fontes:    bloqueando TODAS as fontes do site o CLS fica igual (0,0437)
 *
 * O que realmente acontece, no desktop:
 *   t= 759 ms  .search-form = 113 px  (.position-relative 67 + botao 46, empilhados)
 *   t=1251 ms  .search-form =  40 px  (os dois lado a lado, 40)
 *   sem JavaScript = 40 px desde o inicio, sem nenhum shift
 * A queda de 73 px sobe pela arvore: .elementor-element-80a8aef 293 -> 220 e
 * .elementor-element-75c936f 407 -> 333. Isso responde por 0,0436 dos 0,0438.
 *
 * O MARKUP NAO MUDA, so o estilo. A regra que poe o formulario em linha
 * (flex-direction: row) esta em uploads/elementor/css/post-15396.css, e esse
 * <link> sai no OFFSET 368.445 do HTML, com </head> no 103.228: o Elementor
 * imprime a folha do template no meio do BODY, e ela chega depois da pintura.
 * Sao 4,2 KB. Antecipar para o head elimina o intervalo sem alterar uma regra
 * sequer: e o mesmo arquivo, lido do disco, so que mais cedo.
 *
 * Se o Elementor regenerar o arquivo, este mu-plugin le a versao nova sozinho.
 * Se o arquivo nao existir, nao imprime nada e o site segue como esta hoje.
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CETRUS_CLS_TEMPLATE = 15396;

add_action(
	'wp_head',
	function () {
		if ( is_admin() || ! is_front_page() ) {
			return;
		}

		$arquivo = wp_upload_dir()['basedir'] . '/elementor/css/post-' . CETRUS_CLS_TEMPLATE . '.css';
		if ( ! is_readable( $arquivo ) ) {
			return;
		}

		$css = file_get_contents( $arquivo ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $css || '' === trim( $css ) || strlen( $css ) > 30000 ) {
			return;
		}

		echo "<style id='cetrus-cls-hero'>" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	2
);
