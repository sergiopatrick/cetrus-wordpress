<?php
/**
 * Plugin Name: Cetrus - Programa de indicacao
 * Description: Liga o formulario da pagina /programa-indicacao/ a planilha do CRM e preenche a data de inicio conforme a turma escolhida.
 * Version: 1.0.0
 * Author: Time de Martech
 *
 * O envio para a planilha usa o mesmo backend que ja alimenta a aba "Pagina1"
 * (opcao cetrus_indicacao_endpoint / modo cetrus_indicacao_modo).
 *   modo 'tss'  -> backend atual (payload empacotado, como o app original)
 *   modo 'json' -> Apps Script/webhook proprio (POST JSON simples)
 * Trocar de backend é so atualizar as duas opcoes, sem mexer em codigo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cetrus_Programa_Indicacao {

	const FORM_NAME     = 'Programa de indicacao Cetrus';
	const PAGE_SLUG     = 'programa-indicacao';
	const OPT_ENDPOINT  = 'cetrus_indicacao_endpoint';
	const OPT_MODE      = 'cetrus_indicacao_modo';
	const OPT_TOKEN     = 'cetrus_indicacao_token';
	const OPT_FAILURES  = 'cetrus_indicacao_falhas';

	/**
	 * Vazia de proposito. O endpoint e um webhook de escrita direta na planilha
	 * do CRM, sem autenticacao, e este repositorio e publico, entao a URL vive
	 * so na opcao cetrus_indicacao_endpoint, no banco.
	 * Para ver ou trocar, wp option get cetrus_indicacao_endpoint
	 */
	const DEFAULT_ENDPOINT = '';

	/** Ordem exata das colunas esperada pelo backend da planilha. */
	const CAMPOS = array(
		'referrerName',
		'referrerEmail',
		'referrerPhone',
		'course',
		'startDate',
		'friendName',
		'friendEmail',
		'friendPhone',
	);

	/** Turma => data de inicio (ISO). Alimenta o auto-preenchimento no front. */
	public static function turmas() {
		return apply_filters( 'cetrus_indicacao_turmas', array(
			'Pós-Graduação Lato Sensu Ressonância de Mama'                          => '2026-11-14',
			'Pós-Graduação Lato Sensu em Alergia e Imunologia Pediátrica'           => '2026-10-30',
			'Pós-Graduação Lato Sensu em Ecocardiografia Fetal Hibrido'             => '2026-10-23',
			'Pós-Graduação Lato Sensu em Ginecologia Endócrina e Reprodutiva'       => '2026-10-23',
			'Pós-Graduação Lato Sensu em Endoscopia Digestiva Alta Diagnóstica'     => '2026-10-16',
			'Pós-Graduação Lato Sensu em Medicina Regenerativa Musculoesquelética'  => '2026-10-14',
			'Pós-Graduação Lato Sensu em Histeroscopia Cirúrgica Ambulatorial'      => '2026-10-09',
			'Pós-Graduação Lato Sensu em Medicina Fetal'                            => '2026-09-25',
		) );
	}

	public function __construct() {
		add_action( 'elementor_pro/forms/new_record', array( $this, 'enviar_para_planilha' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'script_data_inicio' ), 99 );
		add_action( 'template_redirect', array( $this, 'sem_popup' ) );
	}

	/**
	 * A pagina e uma LP de campanha: popup de outra oferta por cima do formulario
	 * atrapalha a conversao. Para voltar a exibir, basta remover este hook.
	 */
	public function sem_popup() {
		if ( ! is_page( self::PAGE_SLUG ) ) {
			return;
		}
		if ( class_exists( '\ElementorPro\Modules\Popup\Module' ) ) {
			$popup = \ElementorPro\Modules\Popup\Module::instance();
			remove_action( 'wp_footer', array( $popup, 'print_popups' ) );
		}
	}

	/* ------------------------------------------------------------------ envio */

	public function enviar_para_planilha( $record, $handler ) {
		if ( self::FORM_NAME !== $record->get_form_settings( 'form_name' ) ) {
			return;
		}

		$fields = $record->get( 'fields' );
		$dados  = array();
		foreach ( self::CAMPOS as $campo ) {
			$dados[ $campo ] = isset( $fields[ $campo ]['value'] ) ? trim( (string) $fields[ $campo ]['value'] ) : '';
		}

		// Rede de seguranca: se a turma tem data conhecida, ela manda.
		$turmas = self::turmas();
		if ( isset( $turmas[ $dados['course'] ] ) && '' === $dados['startDate'] ) {
			$dados['startDate'] = $turmas[ $dados['course'] ];
		}

		$resultado = $this->post_backend( $dados );

		if ( is_wp_error( $resultado ) ) {
			$this->registrar_falha( $dados, $resultado->get_error_message() );
			$handler->add_error_message(
				'Não conseguimos registrar sua indicação agora. Tente novamente em instantes ou fale com a equipe Cetrus.'
			);
		}
	}

	private function post_backend( $dados ) {
		$endpoint = get_option( self::OPT_ENDPOINT, self::DEFAULT_ENDPOINT );
		$modo     = get_option( self::OPT_MODE, 'tss' );

		if ( empty( $endpoint ) ) {
			return new WP_Error( 'sem_endpoint', 'Endpoint da planilha nao configurado.' );
		}

		if ( 'json' === $modo ) {
			$corpo    = wp_json_encode( array_merge( $dados, array(
				'token'  => get_option( self::OPT_TOKEN, '' ),
				'origem' => 'cetrus.com.br/programa-indicacao',
			) ) );
			$headers  = array( 'content-type' => 'application/json' );
		} else {
			$corpo   = $this->payload_tss( $dados );
			$headers = array(
				'content-type'   => 'application/json',
				'x-tsr-serverFn' => 'true',
				'accept'         => 'application/x-tss-framed, application/x-ndjson, application/json',
				'origin'         => 'https://programa-indicacao-cetrus.lovable.app',
				'referer'        => 'https://programa-indicacao-cetrus.lovable.app/',
			);
		}

		$resposta = wp_remote_post( $endpoint, array(
			'timeout'     => 20,
			'headers'     => $headers,
			'body'        => $corpo,
			'user-agent'  => 'Cetrus-WP/1.0 (+https://cetrus.com.br/programa-indicacao/)',
			'redirection' => 5,
		) );

		if ( is_wp_error( $resposta ) ) {
			return $resposta;
		}

		$code = (int) wp_remote_retrieve_response_code( $resposta );
		$body = (string) wp_remote_retrieve_body( $resposta );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'http_' . $code, 'HTTP ' . $code . ' - ' . substr( $body, 0, 300 ) );
		}
		if ( 'tss' === $modo && false === strpos( $body, 'success' ) ) {
			return new WP_Error( 'resposta_inesperada', 'Resposta sem confirmacao: ' . substr( $body, 0, 300 ) );
		}

		return true;
	}

	/** Empacota os dados no formato que o backend atual espera. */
	private function payload_tss( $dados ) {
		$chaves = array();
		$vals   = array();
		foreach ( self::CAMPOS as $campo ) {
			$chaves[] = $campo;
			$vals[]   = array( 't' => 1, 's' => $dados[ $campo ] );
		}

		return wp_json_encode( array(
			't' => array(
				't' => 10,
				'i' => 0,
				'p' => array(
					'k' => array( 'data' ),
					'v' => array(
						array(
							't' => 10,
							'i' => 1,
							'p' => array( 'k' => $chaves, 'v' => $vals ),
							'o' => 0,
						),
					),
				),
				'o' => 0,
			),
			'f' => 63,
			'm' => array(),
		) );
	}

	/** Guarda as ultimas 20 falhas em option (nada de escrever no debug.log publico). */
	private function registrar_falha( $dados, $erro ) {
		$falhas = get_option( self::OPT_FAILURES, array() );
		if ( ! is_array( $falhas ) ) {
			$falhas = array();
		}
		array_unshift( $falhas, array(
			'quando' => current_time( 'mysql' ),
			'erro'   => $erro,
			'dados'  => $dados,
		) );
		update_option( self::OPT_FAILURES, array_slice( $falhas, 0, 20 ), false );
	}

	/* ------------------------------------------------------------------- front */

	public function script_data_inicio() {
		if ( ! is_page( self::PAGE_SLUG ) ) {
			return;
		}
		$turmas = wp_json_encode( self::turmas() );
		?>
<script id="cetrus-indicacao-data-inicio">
(function () {
	var turmas = <?php echo $turmas; // phpcs:ignore ?>;
	function preencher( curso ) {
		var campo = document.getElementById( 'form-field-startDate' );
		if ( ! campo ) { return; }
		var data = turmas[ curso ] || '';
		if ( data ) {
			campo.value = data;
			campo.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
	}
	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.id === 'form-field-course' ) { preencher( e.target.value ); }
	}, true );
	document.addEventListener( 'DOMContentLoaded', function () {
		var sel = document.getElementById( 'form-field-course' );
		if ( sel && sel.value ) { preencher( sel.value ); }
		telefones();
	} );

	/* Telefone: teclado numerico no celular e mascara (11) 99999-9999.
	   O tipo "tel" do Elementor recusa espaco no padrao, por isso os campos
	   sao de texto e a formatacao fica aqui. */
	function mascara( valor ) {
		var d = valor.replace( /\D/g, '' ).slice( 0, 11 );
		if ( d.length < 3 ) { return d; }
		if ( d.length <= 6 ) { return '(' + d.slice( 0, 2 ) + ') ' + d.slice( 2 ); }
		if ( d.length <= 10 ) { return '(' + d.slice( 0, 2 ) + ') ' + d.slice( 2, 6 ) + '-' + d.slice( 6 ); }
		return '(' + d.slice( 0, 2 ) + ') ' + d.slice( 2, 7 ) + '-' + d.slice( 7 );
	}
	function telefones() {
		[ 'form-field-referrerPhone', 'form-field-friendPhone' ].forEach( function ( id ) {
			var campo = document.getElementById( id );
			if ( ! campo ) { return; }
			campo.setAttribute( 'inputmode', 'tel' );
			campo.setAttribute( 'autocomplete', 'tel' );
			campo.addEventListener( 'input', function () {
				var fim = campo.selectionStart === campo.value.length;
				campo.value = mascara( campo.value );
				if ( fim ) { campo.setSelectionRange( campo.value.length, campo.value.length ); }
			} );
		} );
	}
})();
</script>
		<?php
	}
}

new Cetrus_Programa_Indicacao();
