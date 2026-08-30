<?php
/**
 * Plugin Name: Cetrus - Qualidade do Photon
 * Description: Aplica compressao com perdas nas imagens PNG servidas pelo Jetpack Photon / Image CDN. Sem isso o Photon converte PNG para WebP SEM PERDAS e entrega arquivos ate 40x maiores que o necessario.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE SO PNG (medido em 30/08/2026 sobre as 361 imagens do corpus de QA):
 *   PNG   111 imagens  24,00 MB -> 3,94 MB  (-83,6%)  diferenca visual maxima RMS 1,3
 *   WebP  147 imagens   5,84 MB -> 4,22 MB  (-27,8%)  RMS ate 7,4, perda de geracao
 *   JPEG  103 imagens   3,06 MB -> 3,50 MB  (+14,9%)  reencoda para cima, so piora
 * O defeito real era PNG virando WebP lossless. JPEG e WebP ja chegam comprimidos
 * e o Photon lida bem com eles, entao ficam de fora.
 *
 * ATENCAO: a comparacao visual PRECISA compor sobre fundo solido antes de medir.
 * WebP com perdas deixa RGB residual sob alpha=0 e uma metrica ingenua acusa
 * diferenca gigante em logos transparentes onde o olho nao ve nada.
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio (o deploy do
 * WordPress.com faz merge, entao arquivo removido so no servidor volta).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'jetpack_photon_pre_args',
	/**
	 * @param array|string $args      Argumentos do Photon (array ou query string).
	 * @param string       $image_url URL original da imagem.
	 * @param string|null  $scheme    Esquema.
	 * @return array|string
	 */
	function ( $args, $image_url = '', $scheme = null ) {
		if ( ! is_string( $image_url ) || '' === $image_url ) {
			return $args;
		}

		$caminho = (string) wp_parse_url( $image_url, PHP_URL_PATH );
		if ( 'png' !== strtolower( (string) pathinfo( $caminho, PATHINFO_EXTENSION ) ) ) {
			return $args;
		}

		/**
		 * 82 validado imagem a imagem contra o lossless: diferenca visual maxima
		 * RMS 1,3 em 111 PNGs. Filtro para quem quiser calibrar sem editar o arquivo.
		 */
		$quality = (int) apply_filters( 'cetrus_photon_quality', 82 );
		if ( $quality < 40 || $quality > 100 ) {
			$quality = 82;
		}

		if ( is_array( $args ) ) {
			if ( ! isset( $args['quality'] ) ) {
				$args['quality'] = $quality;
			}
			if ( ! isset( $args['strip'] ) ) {
				$args['strip'] = 'info';
			}
			return $args;
		}

		if ( is_string( $args ) ) {
			$parsed = array();
			parse_str( $args, $parsed );
			if ( ! isset( $parsed['quality'] ) ) {
				$parsed['quality'] = $quality;
			}
			if ( ! isset( $parsed['strip'] ) ) {
				$parsed['strip'] = 'info';
			}
			return http_build_query( $parsed );
		}

		return $args;
	},
	20,
	3
);
