<?php
/**
 * Plugin Name: Cetrus - WhatsApp com o curso e a origem
 * Description: Nas paginas de curso, troca o texto fixo dos links de WhatsApp pelo nome e codigo do curso e pela origem da visita.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE
 * Os templates de produto (12073, 19062, 19225, 26931, 28008, 28779, 32359, 34795
 * e os clones [BLACK-2026]) tem o botao "#whatsapp-btn" com o texto fixo
 * "gostaria de mais informacoes sobre a Formacao Inicial em USG do Cetrus".
 * Quem clicava no curso de POCUS chegava ao consultor falando de outro curso.
 *
 * O QUE FAZ
 * So em produto (is_singular product). Um JS curto reescreve o parametro "text"
 * de todo link api.whatsapp.com/send e wa.me da pagina, no carregamento e de novo
 * no clique (pega links que nascem depois, em popup). O numero do link nao muda.
 * A mensagem fica assim
 *   Ola! Vim pela pagina do curso <nome> no site do Cetrus e gostaria de mais
 *   informacoes. (Ref. <codigo> | <origem>)
 * A origem vem do cookie sbjs_current (sourcebuster do WooCommerce, ja no site),
 * com as UTMs da URL na frente quando existem. Exemplos "google / cpc",
 * "facebook / paid", "direto".
 *
 * RASTREAMENTO
 * O link continua sendo api.whatsapp.com, entao o MM_click_whatsapp do GTM segue
 * contando igual. Nada novo no dataLayer.
 *
 * REVERTER
 * Apagar este arquivo do servidor E do repositorio. Os templates nao foram tocados.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nome e codigo do curso da pagina atual, ou null fora de produto.
 *
 * @return array{nome: string, codigo: string}|null
 */
function cetrus_wa_curso_dados() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return null;
	}
	if ( ! is_singular( 'product' ) ) {
		return null;
	}

	$id     = get_queried_object_id();
	$nome   = trim( wp_strip_all_tags( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) ) );
	$codigo = (string) get_post_meta( $id, 'v2_codigo_do_curso', true );
	if ( '' === $codigo ) {
		$codigo = (string) get_post_meta( $id, '_sku', true );
	}

	if ( '' === $nome ) {
		return null;
	}

	return array(
		'nome'   => $nome,
		'codigo' => trim( $codigo ),
	);
}

add_action(
	'wp_footer',
	function () {
		$dados = cetrus_wa_curso_dados();
		if ( null === $dados ) {
			return;
		}
		?>
<script id="cetrus-whatsapp-curso">
(function () {
	var CURSO = <?php echo wp_json_encode( $dados ); ?>;

	function cookie(nome) {
		var m = document.cookie.match(new RegExp('(?:^|; )' + nome + '=([^;]*)'));
		if (!m) return '';
		try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; }
	}

	function origem() {
		try {
			var q = new URLSearchParams(location.search);
			if (q.get('utm_source')) return q.get('utm_source') + (q.get('utm_medium') ? ' / ' + q.get('utm_medium') : '');
			var sb = cookie('sbjs_current');
			if (sb) {
				var p = {};
				sb.split('|||').forEach(function (par) { var i = par.indexOf('='); if (i > 0) p[par.slice(0, i)] = par.slice(i + 1); });
				if (p.typ === 'typein') return 'direto';
				if (p.src && p.src !== '(none)') return p.src + (p.mdm && p.mdm !== '(none)' ? ' / ' + p.mdm : '');
			}
			if (document.referrer) return new URL(document.referrer).hostname.replace(/^www\./, '');
		} catch (e) {}
		return 'direto';
	}

	function mensagem() {
		var ref = [CURSO.codigo, origem()].filter(Boolean).join(' | ');
		return 'Olá! Vim pela página do curso ' + CURSO.nome + ' no site do Cetrus e gostaria de mais informações.' + (ref ? ' (Ref. ' + ref + ')' : '');
	}

	function ehWhatsapp(a) {
		return a && a.href && /^https:\/\/(api\.whatsapp\.com\/send|wa\.me\/)/.test(a.href);
	}

	function reescrever(a) {
		if (!ehWhatsapp(a)) return;
		try {
			var u = new URL(a.href);
			u.searchParams.set('text', mensagem());
			a.href = u.toString();
			a.setAttribute('data-cetrus-wa-curso', '1');
		} catch (e) {}
	}

	function todos() {
		[].forEach.call(document.querySelectorAll('a[href*="api.whatsapp.com/send"], a[href*="wa.me/"]'), reescrever);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', todos); else todos();

	// links criados depois (popup) e origem atualizada na hora do clique
	document.addEventListener('click', function (e) {
		var a = e.target && e.target.closest ? e.target.closest('a') : null;
		if (ehWhatsapp(a)) reescrever(a);
	}, true);
})();
</script>
		<?php
	},
	99
);
