<?php
/**
 * Plugin Name: Cetrus - Entrega das fontes
 * Description: Preload do Roboto e controle de font-display. O WOFF2 em si e servido pelo proprio Elementor, com os arquivos preenchidos na fonte customizada 8829.
 * Version:     1.1.0
 * Author:      Cetrus / Sanar
 *
 * DIAGNOSTICO (30/08/2026, medido em Chrome real via CDP)
 * O CLS da home nao vem do carrossel. A checagem estrutural mostrou altura
 * IDENTICA com e sem JavaScript (340/340 no desktop, 311/311 no mobile), entao
 * o Swiper nao desloca nada. O culpado e o container .elementor-element-80a8aef
 * do hero: fica em 293 px enquanto o texto e desenhado com a fonte de sistema e
 * cai para 220 px quando o Roboto chega. Amostragem a cada 150 ms pegou a queda
 * em t=4245 ms contra document.fonts.ready em t=4261 ms. E reflow de fonte.
 *
 * O QUE FOI FEITO, EM DUAS PARTES
 * 1. Fora deste arquivo: a fonte customizada 8829 do Elementor tinha o campo
 *    woff2 VAZIO e so o ttf preenchido. Os dois WOFF2 foram gerados a partir dos
 *    proprios TTF (fontTools, sem resubset) e o campo foi preenchido. Agora o
 *    Elementor emite woff2 na frente e ttf como reserva, sozinho, do jeito nativo.
 *    Roboto-Regular: 168.260 bytes em TTF contra 63.512 em WOFF2.
 *    Backup do meta em ~/backups-cwv-20260830/elementor_font_files-8829.json
 * 2. Aqui: preload do Regular no inicio do <head>, para o download comecar junto
 *    com o CSS em vez de depois de mais de 40 folhas de estilo, e font-display
 *    explicito via filtro do proprio Elementor.
 *
 * POR QUE NAO REDECLARAR O @font-face NA MAO
 * Tentativa anterior (v1.0.0) declarava as faces de novo no fim do <head>. Nao
 * adiantou: medido em Chrome, o navegador continuou baixando o TTF do Elementor.
 * Ha DUAS declaracoes do Roboto vindas do Elementor, uma no post-10452.css e
 * outra no inline elementor-pro-custom-fonts-inline-css, e uma terceira por cima
 * so somava peso. Corrigir na origem e mais simples e mais previsivel.
 *
 * SOBRE font-display
 * 'swap' mantem a tipografia para todo mundo, mas ainda permite o reflow se a
 * fonte demorar. 'optional' zera o reflow de fonte de forma deterministica, ao
 * custo de alguns visitantes de primeira viagem verem a fonte de sistema naquele
 * carregamento. A troca e uma decisao de marca, nao tecnica: mude a constante
 * abaixo depois de decidir com quem cuida da identidade.
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio, e restaurar o meta
 * 8829 pelo backup. Os .woff2 em uploads/2024/07/ ficam inertes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CETRUS_FONTE_DISPLAY = 'swap';
const CETRUS_FONTE_PRELOAD = 'Roboto-Regular.woff2';

function cetrus_fontes_url( $arquivo ) {
	return content_url( '/uploads/2024/07/' . $arquivo );
}

/** Preload no comeco do head, antes de qualquer folha de estilo. */
add_action(
	'wp_head',
	function () {
		if ( is_admin() ) {
			return;
		}
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin="anonymous">' . "\n",
			esc_url( cetrus_fontes_url( CETRUS_FONTE_PRELOAD ) )
		);
	},
	1
);

/** font-display das fontes customizadas, pelo filtro do proprio Elementor Pro. */
add_filter(
	'elementor_pro/custom_fonts/font_display',
	function ( $display, $font_family = '', $data = array() ) {
		return CETRUS_FONTE_DISPLAY;
	},
	10,
	3
);
