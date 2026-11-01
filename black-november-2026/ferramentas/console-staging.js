/*
 * Cetrus · Black November 2026 · ferramentas de console para o wp-admin (staging ou producao).
 *
 * Uso: abrir /wp-admin/edit.php?post_type=elementor_library (pagina que traz o
 * elementorCommonConfig com o nonce do AJAX do Elementor), colar transforma-black.js e
 * depois este arquivo. Tudo grava SO em modelos "[BLACK-2026] #<id>", nunca no original.
 *
 *   await cb.clonar(11545, 'header')            -> cria "[BLACK-2026] #11545 <nome>"
 *   await cb.converter(11545)                   -> cores do ORIGINAL -> grava no clone
 *   await cb.salvar(idClone, elementos, ps)      -> save_builder no clone
 *   await cb.dados(id)                           -> { elementos, ps, titulo }
 *
 * cb.clones: mapa original -> clone do STAGING (black-november-2026/staging/clones-staging-20261022.tsv).
 * Em producao os ids serao outros: montar o mapa pela tela Ferramentas > Black November 2026.
 */
(function () {
	var ajax = window.elementorCommonConfig && window.elementorCommonConfig.ajax;
	var cb = window.cb = window.cb || {};

	cb.kit = {
		c8edb45: '#002452', a324a9f: '#003B6C', b310495: '#9BB1DF', '243199b': '#3D93D7', d5c5f63: '#F2F3FB',
		'2b2abc8': '#404444', '5fb8232': '#595F5F', '9c98396': '#C3C6C6', fde34b3: '#E9EBED', '579eab4': '#FFFFFF',
		b8de8cc: '#F9FAFB', primary: '#6EC1E4', secondary: '#54595F', text: '#7A7A7A', accent: '#61CE70'
	};
	cb.logoBranco = { url: location.origin + '/wp-content/uploads/2024/12/logo-cetrus-branco-1.svg', id: 10927, source: 'library' };
	cb.clones = cb.clones || {
		10919: 35510, 11204: 35617, 11545: 35507, 12073: 35513, 12747: 35516, 12753: 35519, 14050: 35522,
		19062: 35525, 19225: 35528, 19379: 35531, 20396: 35534, 20497: 35537, 20500: 35540, 21114: 35543,
		23382: 35546, 25011: 35549, 26931: 35552, 28008: 35555, 28094: 35558, 28779: 35561, 29363: 35564,
		29791: 35620, 32359: 35567, 34626: 35623, 34795: 35570,
		// paginas (23/10): Home, Vitrine, Conteudos gratuitos
		10946: 35650, 11768: 35655, 27615: 35661
	};
	// headers e rodape: fundo do 1o nivel = #0B0C0E (Artifact)
	cb.fundoTopo = { 10919: '#0B0C0E', 11545: '#0B0C0E', 20396: '#0B0C0E', 20497: '#0B0C0E', 23382: '#0B0C0E' };
	// ajustes pontuais por template original: { idOriginal: { idElemento: { chave: valor } } }
	cb.ajustes = cb.ajustes || { 11545: { f611309: { custom_css: '' } } };
	// referencias que, nos clones, apontam para outros clones (popups, cards, faixa)
	cb.remap = function () {
		var r = {};
		[14050, 20500, 28094, 29363, 11204, 29791, 34626].forEach(function (id) { if (cb.clones[id]) r[id] = cb.clones[id]; });
		return r;
	};

	cb.nonceRest = null;
	cb.rest = async function (caminho, opcoes) {
		if (!cb.nonceRest) cb.nonceRest = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce').then(function (r) { return r.text(); });
		opcoes = opcoes || {};
		opcoes.headers = Object.assign({ 'X-WP-Nonce': cb.nonceRest, 'Content-Type': 'application/json' }, opcoes.headers || {});
		var r = await fetch('/wp-json' + caminho, opcoes);
		return r.json();
	};
	cb.elementor = async function (acoes) {
		var fd = new FormData();
		fd.append('action', 'elementor_ajax');
		fd.append('_nonce', ajax.nonce);
		fd.append('actions', JSON.stringify(acoes));
		var r = await fetch(ajax.url, { method: 'POST', body: fd, credentials: 'include' });
		return r.json();
	};
	cb.dados = async function (id) {
		var d = await cb.rest('/wp/v2/elementor_library/' + id + '?context=edit&_fields=title,meta._elementor_data,meta._elementor_page_settings,meta._elementor_template_type');
		if (!d.meta) {
			d = await cb.rest('/wp/v2/pages/' + id + '?context=edit&_fields=title,meta._elementor_data,meta._elementor_page_settings');
		}
		return { elementos: JSON.parse(d.meta._elementor_data || '[]'), ps: d.meta._elementor_page_settings, titulo: d.title.raw, tipo: d.meta._elementor_template_type };
	};
	cb.ehClone = function (id) {
		return Object.keys(cb.clones).some(function (k) { return cb.clones[k] === id; });
	};
	cb.salvar = async function (idClone, elementos, ps) {
		if (!cb.ehClone(idClone)) return { erro: 'recusado: ' + idClone + ' nao e um clone [BLACK-2026] do mapa' };
		var resp = await cb.elementor({ s: { action: 'save_builder', data: { editor_post_id: idClone, status: 'publish', elements: elementos, settings: ps || {} } } });
		var d = resp && resp.data && resp.data.responses && resp.data.responses.s;
		return d && d.success ? { ok: true } : { erro: JSON.stringify((d && d.data) || resp).slice(0, 300) };
	};
	cb.clonar = async function (idOriginal, tipo, nome, conteudo) {
		if (cb.clones[idOriginal]) return { id: cb.clones[idOriginal], jaExistia: true };
		var o = conteudo ? null : await cb.dados(idOriginal);
		var titulo = '[BLACK-2026] #' + idOriginal + ' ' + (nome || (o && o.titulo) || '');
		var resp = await cb.elementor({ c: { action: 'save_template', data: { post_id: idOriginal, source: 'local', type: tipo, title: titulo, content: JSON.stringify(conteudo || (o && o.elementos)) } } });
		var d = resp && resp.data && resp.data.responses && resp.data.responses.c;
		if (!(d && d.success)) return { erro: JSON.stringify(d && d.data).slice(0, 300) };
		cb.clones[idOriginal] = d.data.template_id;
		return { id: d.data.template_id, titulo: titulo };
	};
	cb.converter = async function (idOriginal, extra) {
		var clone = cb.clones[idOriginal];
		if (!clone) return { erro: 'sem clone para ' + idOriginal };
		var o = await cb.dados(idOriginal);
		var r = window.cetrusBlack.transformar(o.elementos, Object.assign({
			kit: cb.kit,
			fundoTopo: cb.fundoTopo[idOriginal],
			imagens: [{ de: /cetrus-logo[^/]*\.svg/i, para: cb.logoBranco }],
			ajustes: cb.ajustes[idOriginal] || {},
			remap: cb.remap()
		}, extra || {}));
		if (extra && typeof extra.depois === 'function') extra.depois(r.elementos, r.log);
		var s = await cb.salvar(clone, r.elementos, o.ps);
		return { original: idOriginal, clone: clone, trocas: r.log.length, salvo: s };
	};
	// id de elemento do Elementor: 7 hex, nunca vazio (id vazio trava o editor)
	cb.uid = function () { return Math.random().toString(16).slice(2, 9).padEnd(7, '0'); };
	cb.acha = function (elementos, id) {
		var achado = null;
		(function anda(l, pai) { l.forEach(function (e, i) { if (achado) return; if (e.id === id) achado = { el: e, pai: pai, lista: l, i: i }; else if (e.elements) anda(e.elements, e); }); })(elementos, null);
		return achado;
	};
	return 'cb pronto: ' + Object.keys(cb.clones).length + ' clones no mapa';
})();
