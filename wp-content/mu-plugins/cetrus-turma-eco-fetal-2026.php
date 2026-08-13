<?php
/**
 * Plugin Name: Cetrus — Correção de data da turma (Ecocardiografia Fetal / EC_AEF1)
 * Description: Corrige APENAS a data exibida da turma do curso "Atualização em Ecocardiografia Fetal" (produto WooCommerce 16187), de 25–27/11/2027 para 07–09/10/2026, enquanto a turma não é ajustada na fonte (Lyceum). É um override de EXIBIÇÃO: o código da turma (EC_AEF1.SP1.2711.1) não é enviado em nenhuma matrícula — a inscrição do site é apenas um formulário de lead (nome/e-mail/mensagem). REMOVER este arquivo quando a turma for corrigida no Lyceum.
 * Author:      Sanar / Sergio Patrick
 * Version:     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Faz a substituição via output buffering somente na página do produto do curso.
 * O buffer só é transformado se contiver o curso (EC_AEF1) e a data errada,
 * então é impossível afetar qualquer outra página.
 */
add_action( 'template_redirect', function () {

	// Nunca no admin nem em requisições AJAX.
	if ( is_admin() || wp_doing_ajax() ) {
		return;
	}

	// Escopo: página individual de produto WooCommerce.
	if ( ! function_exists( 'is_singular' ) || ! is_singular( 'product' ) ) {
		return;
	}

	// Reforço de escopo: só o produto do curso de Ecocardiografia Fetal.
	if ( (int) get_queried_object_id() !== 16187 ) {
		return;
	}

	ob_start( function ( $html ) {

		if ( ! is_string( $html ) || $html === '' ) {
			return $html;
		}

		// Só transforma se for realmente a turma alvo (curso + data antiga).
		if ( strpos( $html, 'EC_AEF1' ) === false || strpos( $html, '25/11/2027 a 27/11/2027' ) === false ) {
			return $html;
		}

		$mapa = array(
			// Texto exibido no <option> do seletor "Selecione a turma".
			'25/11/2027 a 27/11/2027'  => '07/10/2026 a 09/10/2026',
			// Datas ISO no objeto JS lyceum_course_vars (mantém os dados coerentes).
			'2027-11-25T06:00:00.000Z' => '2026-10-07T06:00:00.000Z',
			'2027-11-27T06:00:00.000Z' => '2026-10-09T06:00:00.000Z',
		);

		return strtr( $html, $mapa );
	} );

}, 0 );
