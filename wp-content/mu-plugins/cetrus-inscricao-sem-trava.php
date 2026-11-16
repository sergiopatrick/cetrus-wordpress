<?php
/**
 * Plugin Name: Cetrus - Inscricao sem trava no seletor de turma
 * Description: Na pagina de curso com seletor do Lyceum, ja deixa a unidade e a proxima turma marcadas quando so existe uma unidade, e abre o formulario mesmo sem turma escolhida, em vez de mostrar erro.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE
 * O botao "Quero me inscrever" do plugin integracao-lyceum-main
 * (assets/js/course-enrollment.js) bloqueia o clique enquanto unidade e turma
 * nao estao escolhidas e mostra "Selecione os campos corretamente.". Era o
 * atrito apontado no diagnostico da Wigoo (nov/2026), e o clique bloqueado
 * nao era medido.
 *
 * O QUE FAZ (so em produto, so onde existe [data-lyceum-enrollment-form])
 * 1. Se o curso tem UMA unidade so, marca a unidade e a turma com a data de
 *    inicio mais proxima, disparando "change" no select. O proprio plugin
 *    sincroniza os cards de computador e celular e libera o botao.
 * 2. Se mesmo assim a pessoa clicar sem unidade ou turma (curso com mais de
 *    uma unidade), abre o popup de inscricao do proprio card
 *    (data-popup-inscricao-id) em vez do erro. O formulario ja leva o codigo do
 *    curso (snippet 21, courseCode); unidade e turma vao vazias e o consultor
 *    confirma na conversa. O listener fica no window em captura, entao roda
 *    antes do listener de captura do plugin no document e o impede.
 *    Sem o modulo de popup do Elementor, nao interfere e o plugin segue igual.
 *
 * MEDICAO (dataLayer, para o GTM)
 * - inscricao_pre_selecionada  { curso, unidade, turma }
 * - inscricao_sem_turma        { curso, unidade, resultado: 'popup' | 'bloqueado' }
 *
 * REVERTER
 * Apagar este arquivo do servidor E do repositorio. O plugin do Lyceum nao foi tocado.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_footer',
	function () {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		?>
<script id="cetrus-inscricao-sem-trava">
(function () {
	function dl(evento, dados) {
		window.dataLayer = window.dataLayer || [];
		var o = { event: evento };
		for (var k in dados) if (Object.prototype.hasOwnProperty.call(dados, k)) o[k] = dados[k];
		window.dataLayer.push(o);
	}
	function forms() { return [].slice.call(document.querySelectorAll('[data-lyceum-enrollment-form]')); }
	function visivel(fs) {
		for (var i = 0; i < fs.length; i++) { var r = fs[i].getBoundingClientRect(); if (r.width > 0 && r.height > 0) return fs[i]; }
		return fs[0] || null;
	}
	function partes(f) {
		return f ? { u: f.querySelector('[data-lyceum-unit-select]'), c: f.querySelector('[data-lyceum-course-select]') } : {};
	}
	function opcoes(sel) { return [].filter.call(sel.options, function (o) { return o.value && !o.disabled; }); }
	function inicio(o) {
		var m = (o.textContent || '').match(/(\d{2})\/(\d{2})\/(\d{4})/);
		return m ? Number(m[3] + m[2] + m[1]) : 99999999;
	}
	function mudar(sel, valor) {
		sel.value = valor;
		sel.dispatchEvent(new Event('change', { bubbles: true }));
	}
	function codigo(f) { return (f && f.getAttribute('data-course-code')) || (typeof courseCode !== 'undefined' ? courseCode : ''); }

	function preselecionar() {
		var fs = forms(); if (!fs.length) return;
		var p = partes(fs[0]);
		if (!p.u || !p.c || p.u.value) return;
		var unidades = opcoes(p.u);
		if (unidades.length !== 1) return;
		mudar(p.u, unidades[0].value);
		var turmas = opcoes(p.c).sort(function (a, b) { return inicio(a) - inicio(b); });
		if (!turmas.length) return;
		mudar(p.c, turmas[0].value);
		dl('inscricao_pre_selecionada', { curso: codigo(fs[0]), unidade: p.u.value, turma: p.c.value });
	}

	window.addEventListener('click', function (e) {
		var btn = e.target && e.target.closest ? e.target.closest('[data-lyceum-enrollment-btn]') : null;
		if (!btn) return;
		var f = visivel(forms()); if (!f) return;
		var p = partes(f);
		if (!p.u || !p.c || (p.u.value && p.c.value)) return;
		var id = parseInt(f.getAttribute('data-popup-inscricao-id') || '', 10);
		var mod = window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup;
		if (!id || !mod || typeof mod.showPopup !== 'function') {
			dl('inscricao_sem_turma', { curso: codigo(f), unidade: p.u.value || '', resultado: 'bloqueado' });
			return;
		}
		e.preventDefault();
		e.stopImmediatePropagation();
		dl('inscricao_sem_turma', { curso: codigo(f), unidade: p.u.value || '', resultado: 'popup' });
		mod.showPopup({ id: id });
	}, true);

	function iniciar() { setTimeout(preselecionar, 0); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
</script>
		<?php
	},
	99
);
