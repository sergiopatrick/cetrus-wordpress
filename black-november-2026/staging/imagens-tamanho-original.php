<?php
/**
 * SO STAGING · imagens no arquivo original (snippet do Code Snippets, escopo front-end).
 *
 * Na producao o WP.com gera os tamanhos reduzidos (-1024x683, -768x512...) sob demanda na CDN:
 * o arquivo nao existe no disco e a borda o cria na hora (ate um tamanho inventado responde 200).
 * No staging privado isso nao acontece e todo <img> em tamanho reduzido da 404, na versao normal
 * e na Black. Aqui, so no staging, a URL de um tamanho que nao existe no disco vira a do arquivo
 * original (quando ele existe). NAO vai para a producao; desfazer = desativar o snippet.
 *
 * Instalacao: staging-snippets.js nao instala este arquivo; ver secao 0 do documento central.
 */
if ( false === strpos( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '.wpcomstaging.com' ) ) {
	return;
}

add_action(
	'template_redirect',
	function () {
		$up   = wp_get_upload_dir();
		$base = preg_replace( '#^https?:#', '', $up['baseurl'] );
		ob_start(
			function ( $html ) use ( $up, $base ) {
				$novo = preg_replace_callback(
					'#((?:https?:)?' . preg_quote( $base, '#' ) . ')(/[^"\'\s()<>]+?)-\d{2,4}x\d{2,4}(\.(?:jpe?g|png|webp|gif))#i',
					function ( $m ) use ( $up ) {
						if ( file_exists( $up['basedir'] . substr( $m[0], strlen( $m[1] ) ) ) ) {
							return $m[0];
						}
						return file_exists( $up['basedir'] . $m[2] . $m[3] ) ? $m[1] . $m[2] . $m[3] : $m[0];
					},
					$html
				);
				return null === $novo ? $html : $novo;
			}
		);
	},
	0
);
