/*
 * Cetrus · Black November 2026 · SO STAGING: instala o mu-plugin e o CSS como snippets do Code
 * Snippets (o staging nao tem Implantacoes do WP.com; a producao recebe os arquivos pelo deploy).
 *
 * Le os arquivos do commit indicado no GitHub e cria (ou atualiza, se ja existirem pelo nome):
 *   "[BLACK-2026] Camada Black November 2026"  = wp-content/mu-plugins/cetrus-black-november-2026.php
 *   "[BLACK-2026] CSS da camada Black (staging)" = black.css impresso no <head> so quando
 *     cetrus_black_na_tela() (em producao o mu-plugin enfileira o arquivo)
 * Confere o SHA-256 do que ficou gravado contra o arquivo do GitHub.
 *
 * Uso (console de qualquer pagina do wp-admin do STAGING, logado como administrador):
 *   var RAW = 'https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<commit>/';
 *   (0, eval)(await fetch(RAW + 'black-november-2026/ferramentas/staging-snippets.js').then(r => r.text()));
 *   await cetrusStaging.instalar(RAW);
 */
(function () {
	var S = window.cetrusStaging = {};
	if (!/wpcomstaging\.com$/.test(location.hostname)) {
		S.instalar = function () { throw new Error('staging-snippets.js e so para o staging (' + location.hostname + ')'); };
		return;
	}
	var nonce = null;
	async function rest(caminho, opcoes) {
		if (!nonce) nonce = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'include' }).then(function (r) { return r.text(); });
		opcoes = opcoes || {};
		opcoes.credentials = 'include';
		opcoes.headers = Object.assign({ 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }, opcoes.headers || {});
		return (await fetch('/wp-json' + caminho, opcoes)).json();
	}
	async function sha(s) { return [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)))].map(function (b) { return b.toString(16).padStart(2, '0'); }).join(''); }
	async function grava(lista, nome, code, scope) {
		var atual = lista.filter(function (s) { return s.name === nome; })[0];
		var corpo = JSON.stringify({ name: nome, code: code, scope: scope, active: true });
		var r = atual
			? await rest('/code-snippets/v1/snippets/' + atual.id, { method: 'POST', body: corpo })
			: await rest('/code-snippets/v1/snippets', { method: 'POST', body: corpo });
		if (!r || !r.id) throw new Error(nome + ': ' + JSON.stringify(r).slice(0, 200));
		var lido = await rest('/code-snippets/v1/snippets/' + r.id);
		return { id: r.id, ativo: lido.active, erro: r.code_error || null, igual: (await sha(lido.code)) === (await sha(code)) };
	}
	S.instalar = async function (raw) {
		var plugin = await fetch(raw + 'wp-content/mu-plugins/cetrus-black-november-2026.php').then(function (r) { return r.text(); });
		var css = await fetch(raw + 'wp-content/mu-plugins/cetrus-black-november-2026/black.css').then(function (r) { return r.text(); });
		if (plugin.indexOf('<?php') !== 0 || css.indexOf('\nCSS\n') >= 0) throw new Error('arquivos inesperados');
		var codigo116 = plugin.replace(/^<\?php\s*\n/, '');
		var codigo117 = "add_action( 'wp_head', function () {\n\tif ( ! function_exists( 'cetrus_black_na_tela' ) || ! cetrus_black_na_tela() ) {\n\t\treturn;\n\t}\n\techo \"<style id='cetrus-black-2026-css'>\\n\" . <<<'CSS'\n" + css.replace(/\s+$/, '') + "\nCSS\n\t. \"\\n</style>\\n\";\n}, 30 );";
		var lista = await rest('/code-snippets/v1/snippets?per_page=200');
		lista = Array.isArray(lista) ? lista : [];
		return {
			plugin: await grava(lista, '[BLACK-2026] Camada Black November 2026', codigo116, 'global'),
			css: await grava(lista, '[BLACK-2026] CSS da camada Black (staging)', codigo117, 'front-end'),
			sha_plugin_arquivo: await sha(plugin)
		};
	};
})();
