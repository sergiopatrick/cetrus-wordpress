<?php
/**
 * Plugin Name: Cetrus - WhatsApp flutuante
 * Description: Botao flutuante de WhatsApp da equipe comercial no canto inferior direito da home. HTML e CSS proprios, sem plugin nem biblioteca; um JS curto so para nao ficar embaixo do banner de cookies, dos pop-ups do HubSpot e do botao "Subir ao inicio".
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * ONDE APARECE
 * So na home (is_front_page). Nas outras paginas este arquivo nao imprime nada:
 * nem HTML, nem CSS, nem JS. Para levar o botao a outras paginas basta o filtro
 * cetrus_wa_exibir (ex.: add_filter( 'cetrus_wa_exibir', '__return_true' ) para
 * o site todo); o resto do componente ja e o mesmo em qualquer pagina.
 *
 * NUMERO E MENSAGEM
 * Constantes CETRUS_WA_TELEFONE e CETRUS_WA_MENSAGEM, logo abaixo (ou o filtro
 * cetrus_wa_config). A mensagem vai em texto puro: a codificacao da URL e feita
 * aqui, no mesmo formato do link de /fale-pelo-whatsapp/ (api.whatsapp.com/send/).
 *
 * RASTREAMENTO: NENHUM CODIGO NOVO, DE PROPOSITO
 * O GTM-MKCGH2J ja tem um acionador de clique em link com "Click URL contem
 * https://api.whatsapp.com/", que dispara o evento MM_click_whatsapp para o GA4
 * (G-640TX0LEC1) e para o Pixel da Meta. Este botao e um <a href> comum para essa
 * URL: entra nesse fluxo sozinho, uma vez por clique e sob as mesmas regras de
 * consentimento do resto do site. Um dataLayer.push aqui so dobraria o evento.
 * Para separar o botao dos outros links de WhatsApp no GA4, use o id do link
 * (variavel Click ID no GTM; link_id no evento click das medicoes otimizadas).
 *
 * VIZINHOS NO CANTO DA TELA (medidos na home em 21/10/2026)
 * - "Subir ao inicio" (widget 1701d0c, fixo): 48 px a 24 px das bordas no
 *   desktop, 44 px a 16 px no mobile. O botao fica no mesmo eixo vertical.
 * - Banner de cookies do HubSpot (#hs-eu-cookie-confirmation): largura total,
 *   no rodape da tela, enquanto o visitante nao responde.
 * - Pop-ups slide-in do HubSpot na home (Lista VIP / Outubro Rosa): canto
 *   inferior esquerdo.
 * O JS empilha o botao acima de qualquer um desses que esteja na coluna dele e
 * o esconde enquanto algo cobrir mais da metade de baixo da tela. O deslocamento
 * e por transform, que nao conta para o CLS. Sem JS, o botao fica no canto.
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CETRUS_WA_TELEFONE = '5511982450256';
const CETRUS_WA_MENSAGEM = 'Olá! Vim pelo site e gostaria de obter mais informações sobre os cursos do Cetrus.';

/**
 * Telefone, mensagem e rotulo acessivel do botao.
 *
 * @return array{telefone: string, mensagem: string, rotulo: string}
 */
function cetrus_wa_config() {
	$config = (array) apply_filters(
		'cetrus_wa_config',
		array(
			'telefone' => CETRUS_WA_TELEFONE,
			'mensagem' => CETRUS_WA_MENSAGEM,
			'rotulo'   => 'Fale com o Cetrus pelo WhatsApp (abre em nova aba)',
		)
	);

	return array(
		'telefone' => preg_replace( '/\D+/', '', (string) ( $config['telefone'] ?? '' ) ),
		'mensagem' => (string) ( $config['mensagem'] ?? '' ),
		'rotulo'   => (string) ( $config['rotulo'] ?? '' ),
	);
}

/**
 * Link do WhatsApp: https://api.whatsapp.com/send/?phone=...&text=...&type=phone_number&app_absent=0
 *
 * @param array $config Saida de cetrus_wa_config().
 * @return string
 */
function cetrus_wa_url( $config ) {
	return 'https://api.whatsapp.com/send/?' . http_build_query(
		array(
			'phone'      => $config['telefone'],
			'text'       => $config['mensagem'],
			'type'       => 'phone_number',
			'app_absent' => '0',
		),
		'',
		'&',
		PHP_QUERY_RFC1738
	);
}

/**
 * Se a pagina atual mostra o botao. Fora do front, no editor do Elementor e sem
 * telefone, nunca.
 *
 * @return bool
 */
function cetrus_wa_exibir() {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return false;
	}

	if ( '' === cetrus_wa_config()['telefone'] ) {
		return false;
	}

	return (bool) apply_filters( 'cetrus_wa_exibir', is_front_page() );
}

/** CSS no <head>: o botao ja nasce no lugar, sem piscar. */
add_action(
	'wp_head',
	function () {
		if ( ! cetrus_wa_exibir() ) {
			return;
		}

		echo "<style id='cetrus-whatsapp-flutuante-css'>\n" . cetrus_wa_css() . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	20
);

/** Link e JS no rodape. */
add_action(
	'wp_footer',
	function () {
		if ( ! cetrus_wa_exibir() ) {
			return;
		}

		$config = cetrus_wa_config();

		/* Mesmo icone (Font Awesome "whatsapp") do botao "Fale Conosco" do cabecalho. */
		$icone = '<svg aria-hidden="true" focusable="false" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/></svg>';

		printf(
			'<a id="cetrus-whatsapp-flutuante" class="cetrus-wa" href="%1$s" target="_blank" rel="noopener" aria-label="%2$s" data-cetrus-wa-posicao="inferior_direita">%3$s</a>' . "\n",
			esc_url( cetrus_wa_url( $config ) ),
			esc_attr( $config['rotulo'] ),
			$icone // phpcs:ignore WordPress.Security.EscapeOutput
		);

		echo "<script id='cetrus-whatsapp-flutuante-js'>\n(function () {\n" . cetrus_wa_js() . "\n})();\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	20
);

/**
 * Estilo do botao. 56 px no desktop e 52 px ate 1024 px (tablet e mobile do
 * Elementor, onde o "Subir ao inicio" tambem diminui para 44 px a 16 px das
 * bordas); a margem direita mantem os dois no mesmo eixo. --cetrus-wa-desvio e
 * posto pelo JS.
 *
 * @return string
 */
function cetrus_wa_css() {
	return <<<'CSS'
.cetrus-wa {
	--cetrus-wa-tamanho: 56px;
	--cetrus-wa-icone: 30px;
	--cetrus-wa-direita: 20px;
	--cetrus-wa-baixo: 20px;
	position: fixed;
	right: calc(var(--cetrus-wa-direita) + env(safe-area-inset-right, 0px));
	bottom: calc(var(--cetrus-wa-baixo) + env(safe-area-inset-bottom, 0px));
	z-index: 9990;
	display: flex;
	align-items: center;
	justify-content: center;
	box-sizing: border-box;
	width: var(--cetrus-wa-tamanho);
	height: var(--cetrus-wa-tamanho);
	margin: 0;
	padding: 0;
	border-radius: 50%;
	background-color: #25d366;
	color: #fff;
	box-shadow: 0 4px 12px rgba(0, 0, 0, .2);
	text-decoration: none;
	-webkit-tap-highlight-color: transparent;
	transform: translateY(calc(-1 * var(--cetrus-wa-desvio, 0px)));
	transition: background-color .2s ease, box-shadow .2s ease, opacity .2s ease, visibility .2s;
}
.cetrus-wa--pronto {
	transition: background-color .2s ease, box-shadow .2s ease, opacity .2s ease, visibility .2s, transform .25s ease;
}
.cetrus-wa svg {
	display: block;
	width: var(--cetrus-wa-icone);
	height: var(--cetrus-wa-icone);
	fill: #fff;
}
@media (hover: hover) {
	.cetrus-wa:hover {
		background-color: #1ebe5d;
		box-shadow: 0 6px 16px rgba(0, 0, 0, .24);
		transform: translateY(calc(-1 * var(--cetrus-wa-desvio, 0px) - 2px));
	}
}
.cetrus-wa:focus-visible {
	outline: 2px solid transparent;
	box-shadow: 0 0 0 3px #fff, 0 0 0 6px #003b6c, 0 4px 12px rgba(0, 0, 0, .2);
}
.cetrus-wa:focus:not(:focus-visible) {
	outline: none;
}
.cetrus-wa--oculto {
	opacity: 0;
	visibility: hidden;
	pointer-events: none;
}
@media (max-width: 1024px) {
	.cetrus-wa {
		--cetrus-wa-tamanho: 52px;
		--cetrus-wa-icone: 28px;
		--cetrus-wa-direita: 12px;
		--cetrus-wa-baixo: 16px;
	}
}
@media (prefers-reduced-motion: reduce) {
	.cetrus-wa,
	.cetrus-wa--pronto {
		transition: none;
	}
	.cetrus-wa:hover {
		transform: translateY(calc(-1 * var(--cetrus-wa-desvio, 0px)));
	}
}
@media (forced-colors: active) {
	.cetrus-wa {
		border: 2px solid ButtonText;
	}
	.cetrus-wa svg {
		fill: ButtonText;
	}
}
@media print {
	.cetrus-wa {
		display: none;
	}
}

CSS;
}

/**
 * Corpo do script do rodape: empilha o botao acima do que estiver fixo no
 * rodape da tela, na mesma coluna que ele.
 *
 * @return string
 */
function cetrus_wa_js() {
	return <<<'JS'
	'use strict';
	var botao = document.getElementById('cetrus-whatsapp-flutuante');
	if (!botao || !window.getComputedStyle) return;

	/* Banner de cookies e pop-ups do HubSpot, o "Subir ao inicio" da home e as
	   barras de CTA fixas dos templates de curso (mesmo seletor do script do
	   banner de cookies do site). */
	var OBSTACULOS = [
		'#hs-eu-cookie-confirmation',
		'#hs-web-interactives-bottom-anchor > *',
		'#hs-web-interactives-floating-bottom-right-anchor > *',
		'.elementor-fixed',
		'.e-con[data-settings*=\'"position":"fixed"\']'
	].join(',');
	var VIGIADOS = ['hs-eu-cookie-confirmation', 'hs-web-interactives-bottom-anchor', 'hs-web-interactives-floating-bottom-right-anchor'];
	var FOLGA = 12;
	var quadro = window.requestAnimationFrame || setTimeout;
	var agendado = false;

	function visivel(el) {
		var estilo = getComputedStyle(el);
		return estilo.visibility !== 'hidden' && parseFloat(estilo.opacity) > 0;
	}

	function medir() {
		agendado = false;
		var altura = window.innerHeight || document.documentElement.clientHeight;
		var base = parseFloat(getComputedStyle(botao).bottom) || 0;
		var caixa = botao.getBoundingClientRect();
		var tamanho = caixa.height;
		var fundo = altura - base;
		var obstaculos = [];

		Array.prototype.forEach.call(document.querySelectorAll(OBSTACULOS), function (el) {
			if (el === botao || el.contains(botao)) return;
			var r = el.getBoundingClientRect();
			if (!r.width || !r.height || r.top >= altura || r.bottom <= 0) return;
			if (r.right <= caixa.left || r.left >= caixa.right) return;
			if (visivel(el)) obstaculos.push(r);
		});

		/* Sobe o botao ate nao encostar em nada; cada obstaculo pode empurrar
		   para cima de outro (banner de cookies -> "Subir ao inicio"). */
		for (var volta = 0, mudou = true; mudou && volta < 10; volta++) {
			mudou = false;
			obstaculos.forEach(function (r) {
				if (r.top - FOLGA < fundo && r.bottom + FOLGA > fundo - tamanho) {
					fundo = r.top - FOLGA;
					mudou = true;
				}
			});
		}

		var oculto = fundo - tamanho < altura / 2;
		botao.style.setProperty('--cetrus-wa-desvio', (oculto ? 0 : Math.max(0, Math.round(altura - base - fundo))) + 'px');
		botao.classList.toggle('cetrus-wa--oculto', oculto);
	}

	function agendar() {
		if (agendado) return;
		agendado = true;
		quadro(medir);
	}

	/* Os slide-ins do HubSpot entram com animacao: mede de novo quando assentam. */
	function mudanca() {
		agendar();
		setTimeout(agendar, 700);
	}

	var vigia = window.MutationObserver ? new MutationObserver(mudanca) : null;
	var vigiados = [];

	function vigiar() {
		if (!vigia) return;
		VIGIADOS.forEach(function (id) {
			var el = document.getElementById(id);
			if (!el || vigiados.indexOf(el) !== -1) return;
			vigiados.push(el);
			vigia.observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style'] });
		});
	}

	/* Primeira medida antes da proxima pintura; a animacao do transform so liga
	   depois dela, para o botao nao "subir" na frente do visitante ao carregar. */
	quadro(function () {
		medir();
		quadro(function () {
			botao.classList.add('cetrus-wa--pronto');
		});
	});

	window.addEventListener('resize', agendar);
	window.addEventListener('load', mudanca);
	if (vigia) {
		vigiar();
		/* O HubSpot poe o banner e os pop-ups direto no <body>, depois do load. */
		new MutationObserver(function () {
			vigiar();
			mudanca();
		}).observe(document.body, { childList: true });
	}
JS;
}
