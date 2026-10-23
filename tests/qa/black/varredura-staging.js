/*
 * Cetrus · Black November 2026 · varredura original × Black no staging.
 *
 * Como rodar: no wp-admin do staging (logado), colar antes
 * black-november-2026/ferramentas/transforma-black.js e console-staging.js (window.cb), depois
 * este arquivo. Roda em segundo plano (~20 min para 439 URLs) e guarda tudo em window.__val:
 *   __val.feitos / __val.total, __val.fim (fim), __val.erros, __val.linhas (TSV:
 *   caminho, status orig, status black, templates orig, templates black, nº H1, nº H2 orig,
 *   nº H2 black, problemas ou "OK").
 * Resumo: __val.linhas.filter(l => !l.endsWith('\tOK'))
 *
 * Compara por URL: status; title; description; canonical; robots; og:title; tipos do JSON-LD do
 * Yoast; H1; H2 (sem ordem, descontados os acréscimos aprovados, seção 10 do PROJETO_...); classe
 * Black; template esperado = clone do template do original; páginas com modelo Black.
 * Última rodada: 23/10/2026 (black-november-2026/validacao/staging-20261023-varredura.md).
 */
window.__val = { feitos: 0, total: 0, linhas: [], erros: [], inicio: Date.now(), fim: null };
(function () {
	var clones = Object.assign({}, cb.clones);
	var paginasBlack = { 10946: 35650, 11768: 35655, 27615: 35661 };
	var h2Extra = {
		'/': ['As melhores condições do ano para a sua formação.', 'Aprenda com quem é referência.'],
		'/vitrine/': ['Planeje hoje o médico que você será amanhã.'],
		'/conteudos-gratuitos/': ['Do conteúdo gratuito à prática em pacientes reais.']
	};
	function txt(s) { return s.replace(/<[^>]+>/g, '').replace(/&nbsp;|&#160;/g, ' ').replace(/&amp;/g, '&').replace(/&#8211;/g, '–').replace(/\s+/g, ' ').trim(); }
	function meta(t, re) { var m = t.match(re); return m ? m[1] : ''; }
	function analisa(t) {
		var head = t.slice(0, t.indexOf('</head>') + 7);
		var tpl = [], re = /data-elementor-type="([^"]+)" data-elementor-id="(\d+)"/g, m;
		while ((m = re.exec(t))) tpl.push(m[1] + ':' + m[2]);
		var h1 = []; re = /<h1\b[^>]*>([\s\S]*?)<\/h1>/g; while ((m = re.exec(t))) h1.push(txt(m[1]));
		var h2 = []; re = /<h2\b[^>]*>([\s\S]*?)<\/h2>/g; while ((m = re.exec(t))) h2.push(txt(m[1]));
		var ld = (t.match(/<script type="application\/ld\+json" class="yoast-schema-graph">([\s\S]*?)<\/script>/) || [])[1] || '';
		return {
			title: meta(head, /<title>([\s\S]*?)<\/title>/), desc: meta(head, /<meta name="description" content="([^"]*)"/),
			canon: meta(head, /<link rel="canonical" href="([^"]*)"/), robots: meta(head, /<meta name=['"]robots['"] content=['"]([^'"]*)['"]/),
			og: meta(head, /<meta property="og:title" content="([^"]*)"/), ld: (ld.match(/"@type":"[^"]+"/g) || []).join(','),
			h1: h1, h2: h2, tpl: [...new Set(tpl)],
			black: /\bcetrus-black-2026\b/.test((t.match(/<body[^>]*class="([^"]*)"/) || [])[1] || '')
		};
	}
	async function pega(u, modo) {
		var r = await fetch(u + (u.includes('?') ? '&' : '?') + 'cetrus_black=' + modo + '&qa=val' + Date.now(), { credentials: 'include' });
		return { st: r.status, a: analisa(await r.text()) };
	}
	async function um(u) {
		try {
			var o = await pega(u, 0), b = await pega(u, 1), a = o.a, k = b.a, prob = [];
			if (o.st !== b.st) prob.push('status ' + o.st + '/' + b.st);
			['title', 'desc', 'canon', 'robots', 'og', 'ld'].forEach(function (f) { if (a[f] !== k[f]) prob.push(f); });
			if (JSON.stringify(a.h1) !== JSON.stringify(k.h1)) prob.push('h1');
			var path = new URL(u, location.origin).pathname, extra = h2Extra[path] || [];
			var h2b = k.h2.filter(function (x) { return !extra.includes(x); }), faltam = extra.filter(function (x) { return !k.h2.includes(x); });
			if ([...a.h2].sort().join('|') !== [...h2b].sort().join('|') || faltam.length) prob.push('h2(' + a.h2.length + '/' + k.h2.length + ')');
			if (a.black) prob.push('orig-com-classe');
			if (!k.black) prob.push('black-sem-classe');
			var esperado = a.tpl.map(function (x) { var p = x.split(':'), id = +p[1]; return paginasBlack[id] ? null : p[0] + ':' + (clones[id] || id); }).filter(Boolean);
			var falta = esperado.filter(function (x) { return !k.tpl.includes(x); });
			if (falta.length) prob.push('tpl falta ' + falta.join(' '));
			var sobra = k.tpl.filter(function (x) { return clones[+x.split(':')[1]]; });
			if (sobra.length) prob.push('tpl original ' + sobra.join(' '));
			var pg = a.tpl.map(function (x) { return +x.split(':')[1]; }).find(function (id) { return paginasBlack[id]; });
			if (pg && !k.tpl.some(function (x) { return +x.split(':')[1] === paginasBlack[pg]; })) prob.push('pagina sem modelo ' + paginasBlack[pg]);
			__val.linhas.push([path, o.st, b.st, a.tpl.join(' '), k.tpl.join(' '), a.h1.length, a.h2.length, k.h2.length, prob.join('; ') || 'OK'].join('\t'));
		} catch (e) { __val.erros.push(u + ' ' + e.message); }
		__val.feitos++;
	}
	window.__valP = (async function () {
		var urls = ['/', '/vitrine/', '/conteudos-gratuitos/', '/?s=ultrassonografia', '/modalidades/pos-graduacao/', '/modalidades/fellowship/', '/especialidades/', '/pagina-que-nao-existe-qa/'];
		for (var p = 1; p < 10; p++) {
			var r = await fetch('/wp-json/wp/v2/product?per_page=100&status=publish&_fields=link&page=' + p, { credentials: 'include', headers: { 'X-WP-Nonce': cb.nonceRest } });
			if (!r.ok) break;
			var l = await r.json();
			if (!l.length) break;
			l.forEach(function (x) { urls.push(x.link.replace(location.origin, '')); });
		}
		__val.total = urls.length;
		var fila = urls.slice();
		await Promise.all([1, 2, 3, 4].map(async function () { while (fila.length) await um(fila.shift()); }));
		__val.fim = Date.now();
		return 'fim';
	})();
})();
