/*
 * Cetrus · Black November 2026 · varredura original × Black no staging.
 *
 * Como rodar: no wp-admin do staging (logado), colar este arquivo (ou carregar do GitHub raw).
 * Os modelos [BLACK-2026] sao descobertos pelo titulo (os ids mudam a cada montagem).
 * 28/10: telas iguais a producao (fellowship v2 28779, graduacao 19379, especialidade 25011,
 * 404 e paginas sem modelo) precisam sair SEM troca e SEM classe Black. Roda em segundo plano (~20 min para 439 URLs) e guarda tudo em window.__val:
 *   __val.feitos / __val.total, __val.fim (fim), __val.erros, __val.linhas (TSV:
 *   caminho, status orig, status black, templates orig, templates black, nº H1, nº H2 orig,
 *   nº H2 black, problemas ou "OK").
 * Resumo: __val.linhas.filter(l => !l.endsWith('\tOK'))
 * Repassar so algumas URLs: window.__valUrls = ['/cursos/x/', ...] antes de carregar o arquivo.
 * H2 a mais que sao so rotulos dos cards do "Quem viu" automatico (D8) saem como
 * "OK (quem viu automatico: +N h2)".
 *
 * Compara por URL: status; title; description; canonical; robots; og:title; tipos do JSON-LD do
 * Yoast; H1; H2 (sem ordem, descontados os acréscimos aprovados, seção 10 do PROJETO_...); classe
 * Black; template esperado = clone do template do original; páginas com modelo Black.
 * Última rodada: 23/10/2026 (black-november-2026/validacao/staging-20261023-varredura.md).
 */
window.__val = { feitos: 0, total: 0, linhas: [], erros: [], inicio: Date.now(), fim: null };
(function () {
	var clones = {}, paginasBlack = {}, nonceRest = null;
	var SEM_BLACK = [28779, 19379, 25011, 14718]; // templates das telas iguais a producao
	var h2Extra = {
		'/': ['As melhores condições do ano para a sua formação.', 'Aprenda com quem é referência.'],
		'/vitrine/': ['Planeje hoje o médico que você será amanhã.'],
		'/conteudos-gratuitos/': ['Do conteúdo gratuito à prática em pacientes reais.']
	};
	// H2 que saem de proposito na versao Black (28/10: newsletter fora da Home)
	var h2Removidos = { '/': ['Assine a newsletter do Cetrus'] };
	function txt(s) { return s.replace(/<[^>]+>/g, '').replace(/&nbsp;|&#160;/g, ' ').replace(/&amp;/g, '&').replace(/&#8211;/g, '–').replace(/\s+/g, ' ').trim(); }
	function meta(t, re) { var m = t.match(re); return m ? m[1] : ''; }
	function menos(x, y) { var r = x.slice(); y.forEach(function (v) { var i = r.indexOf(v); if (i >= 0) r.splice(i, 1); }); return r; }
	function analisa(t) {
		var head = t.slice(0, t.indexOf('</head>') + 7);
		var tpl = [], re = /data-elementor-type="([^"]+)" data-elementor-id="(\d+)"/g, m;
		while ((m = re.exec(t))) tpl.push(m[1] + ':' + m[2]);
		var h1 = []; re = /<h1\b[^>]*>([\s\S]*?)<\/h1>/g; while ((m = re.exec(t))) h1.push(txt(m[1]));
		var h2 = []; re = /<h2\b[^>]*>([\s\S]*?)<\/h2>/g; while ((m = re.exec(t))) h2.push(txt(m[1]));
		// H2 depois de "Quem viu este curso" (rotulos dos cards do carrossel)
		var h2QuemViu = [], iq = t.indexOf('Quem viu este curso');
		if (iq >= 0) { re = /<h2\b[^>]*>([\s\S]*?)<\/h2>/g; re.lastIndex = iq; while ((m = re.exec(t))) h2QuemViu.push(txt(m[1])); }
		var ld = (t.match(/<script type="application\/ld\+json" class="yoast-schema-graph">([\s\S]*?)<\/script>/) || [])[1] || '';
		return {
			title: meta(head, /<title>([\s\S]*?)<\/title>/), desc: meta(head, /<meta name="description" content="([^"]*)"/),
			canon: meta(head, /<link rel="canonical" href="([^"]*)"/), robots: meta(head, /<meta name=['"]robots['"] content=['"]([^'"]*)['"]/),
			og: meta(head, /<meta property="og:title" content="([^"]*)"/), ld: (ld.match(/"@type":"[^"]+"/g) || []).join(','),
			h1: h1, h2: h2, h2QuemViu: h2QuemViu, tpl: [...new Set(tpl)],
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
			var rem = h2Removidos[path] || [], h2a = a.h2.filter(function (x) { return rem.indexOf(x) < 0; });
			// "Quem viu" vazio no original recebe cursos so no Black (D8): os H2 a mais sao os rotulos dos cards
			var aMais = menos(h2b, h2a), aMenos = menos(h2a, h2b);
			var quemViu = aMais.length > 0 && !aMenos.length && menos(aMais, k.h2QuemViu).length === 0;
			if (((aMais.length || aMenos.length) && !quemViu) || faltam.length) prob.push('h2(' + a.h2.length + '/' + k.h2.length + ')');
			var nota = quemViu ? ' (quem viu automatico: +' + aMais.length + ' h2)' : '';
			if (a.black) prob.push('orig-com-classe');
			var ids = a.tpl.map(function (x) { return +x.split(':')[1]; });
			var telaBlack = !ids.some(function (id) { return SEM_BLACK.indexOf(id) >= 0; }) && (a.tpl.some(function (x) { return /^(product|search-results|product-archive):/.test(x); }) || ids.some(function (id) { return paginasBlack[id]; }));
			if (!telaBlack) {
				if (k.black) prob.push('tela-igual-a-producao-com-classe-black');
				if (a.tpl.join(' ') !== k.tpl.join(' ')) prob.push('tela-igual-a-producao-com-troca: ' + k.tpl.join(' '));
				__val.linhas.push([path, o.st, b.st, a.tpl.join(' '), k.tpl.join(' '), a.h1.length, a.h2.length, k.h2.length, (prob.join('; ') || 'OK') + ' (igual a producao)'].join('	'));
				__val.feitos++;
				return;
			}
			if (!k.black) prob.push('black-sem-classe');
			var esperado = a.tpl.map(function (x) { var p = x.split(':'), id = +p[1]; return paginasBlack[id] ? null : p[0] + ':' + (clones[id] || id); }).filter(Boolean);
			var falta = esperado.filter(function (x) { return !k.tpl.includes(x); });
			if (falta.length) prob.push('tpl falta ' + falta.join(' '));
			var sobra = k.tpl.filter(function (x) { return clones[+x.split(':')[1]]; });
			if (sobra.length) prob.push('tpl original ' + sobra.join(' '));
			var pg = a.tpl.map(function (x) { return +x.split(':')[1]; }).find(function (id) { return paginasBlack[id]; });
			if (pg && !k.tpl.some(function (x) { return +x.split(':')[1] === paginasBlack[pg]; })) prob.push('pagina sem modelo ' + paginasBlack[pg]);
			__val.linhas.push([path, o.st, b.st, a.tpl.join(' '), k.tpl.join(' '), a.h1.length, a.h2.length, k.h2.length, (prob.join('; ') || 'OK') + nota].join('\t'));
		} catch (e) { __val.erros.push(u + ' ' + e.message); }
		__val.feitos++;
	}
	window.__valP = (async function () {
		nonceRest = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'include' }).then(function (r) { return r.text(); });
		var lib = await fetch('/wp-json/wp/v2/elementor_library?context=edit&per_page=100&status=publish&search=' + encodeURIComponent('[BLACK-2026]') + '&_fields=id,title', { credentials: 'include', headers: { 'X-WP-Nonce': nonceRest } }).then(function (r) { return r.json(); });
		(Array.isArray(lib) ? lib : []).forEach(function (p) { var m = /^\[BLACK-2026\]\s*#(\d+)\b/.exec(p.title.raw || ''); if (m) clones[m[1]] = p.id; });
		[10946, 11768, 27615].forEach(function (id) { if (clones[id]) paginasBlack[id] = clones[id]; });
		__val.modelos = Object.keys(clones).length;
		var urls = ['/', '/vitrine/', '/conteudos-gratuitos/', '/?s=ultrassonografia', '/modalidades/pos-graduacao/', '/modalidades/fellowship/', '/especialidades/', '/especialidades/radiologia-e-diagnostico-por-imagem/', '/institucional/', '/pagina-que-nao-existe-qa/'];
		if (Array.isArray(window.__valUrls)) urls = window.__valUrls.slice(); // repassar so uma lista
		for (var p = 1; p < 10 && !Array.isArray(window.__valUrls); p++) {
			var r = await fetch('/wp-json/wp/v2/product?per_page=100&status=publish&_fields=link&page=' + p, { credentials: 'include', headers: { 'X-WP-Nonce': nonceRest } });
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
