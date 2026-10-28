/*
 * Cetrus · Black November 2026 · montagem dos modelos [BLACK-2026] (staging OU producao).
 *
 * Recria TODOS os modelos Black a partir dos templates/paginas ORIGINAIS do site em que roda.
 * Assim a versao Black nasce do conteudo atual daquele site (nada de levar conteudo velho do
 * staging para a producao) e pode ser refeita quantas vezes for preciso (sempre parte do original).
 *
 * Regras de seguranca:
 * - so grava em posts do tipo elementor_library cujo titulo comeca com "[BLACK-2026] #<id>";
 *   nunca em original, pagina, produto ou configuracao;
 * - modo simulacao ({ simular: true }) so le e devolve o relatorio;
 * - fora da janela (ou com o mu-plugin desligado) os modelos sao inertes: ninguem os ve.
 *
 * Uso (console de /wp-admin/edit.php?post_type=elementor_library, logado como administrador):
 *   var BASE = 'https://raw.githubusercontent.com/sergiopatrick/cetrus-wordpress/<commit>/black-november-2026/';
 *   for (const f of ['ferramentas/transforma-black.js', 'ferramentas/montar-black.js'])
 *     (0, eval)(await fetch(BASE + f).then(r => r.text()));
 *   var rel = await cetrusMontar.executar({ base: BASE, simular: true });  // ensaio, nao grava
 *   var rel = await cetrusMontar.executar({ base: BASE });                 // grava
 *   console.table(rel.itens); rel.avisos;
 *   await cetrusMontar.verificarOriginais();   // hash dos originais (prova de que nao mudaram)
 *   await cetrusMontar.desmontar();            // pos-campanha: modelos [BLACK-2026] viram rascunho
 *
 * Telas que ficam iguais a producao (decisao de 28/10/2026) NAO tem modelo: fellowship v2 (28779),
 * graduacao (19379 + header 23382), arquivo de especialidade (25011), 404 (14718) e paginas fora
 * do Artifact. O mu-plugin tambem as exclui (cetrus_black_tela_fora).
 */
(function () {
	var M = window.cetrusMontar = {};
	var ajax = window.elementorCommonConfig && window.elementorCommonConfig.ajax;
	M.PREFIXO = '[BLACK-2026]';

	// Cores globais do Kit 10452 (iguais em staging e producao; secao 3.2 do PROJETO_...).
	M.kit = {
		c8edb45: '#002452', a324a9f: '#003B6C', b310495: '#9BB1DF', '243199b': '#3D93D7', d5c5f63: '#F2F3FB',
		'2b2abc8': '#404444', '5fb8232': '#595F5F', '9c98396': '#C3C6C6', fde34b3: '#E9EBED', '579eab4': '#FFFFFF',
		b8de8cc: '#F9FAFB', primary: '#6EC1E4', secondary: '#54595F', text: '#7A7A7A', accent: '#61CE70'
	};
	M.logoBranco = { url: location.origin + '/wp-content/uploads/2024/12/logo-cetrus-branco-1.svg', id: 10927, source: 'library' };
	// headers e rodape: fundo do 1o nivel = #0B0C0E (Artifact)
	M.fundoTopo = { 10919: '#0B0C0E', 11545: '#0B0C0E', 20396: '#0B0C0E', 20497: '#0B0C0E' };
	// ajustes pontuais por template original: { idOriginal: { idElemento: { chave: valor } } }
	// (a faixa f611309 do header 11545 nao existe mais na producao desde 19/10; o ajuste fica inerte)
	M.ajustes = { 11545: { f611309: { custom_css: '' } } };
	// referencias que, nos modelos, apontam para outros modelos (popups, cards, faixa)
	M.REFERENCIADOS = [34626, 11204, 29791, 14050, 29363, 20500, 28094];

	// Ordem importa: quem e referenciado vem antes de quem referencia.
	M.lista = [
		{ orig: 34626, nome: 'Barra de campanha Black November', montar: 'barra' },
		{ orig: 11204, nome: 'Card Produto - v1' },
		{ orig: 29791 },
		{ orig: 14050 }, { orig: 29363 }, { orig: 20500 }, { orig: 28094 },
		{ orig: 11545, passos: ['barraNoHeader'] }, { orig: 20497, passos: ['barraNoHeader'] }, { orig: 20396, passos: ['barraNoHeader'] },
		{ orig: 10919 },
		{ orig: 12073, passos: ['pos'] }, { orig: 26931, passos: ['pos'] }, { orig: 28008, passos: ['pos'] },
		{ orig: 19225 }, { orig: 21114 }, { orig: 34795 }, { orig: 32359 }, { orig: 19062 },
		{ orig: 12753 }, { orig: 12747 },
		{ orig: 10946, pagina: true, nome: 'Home', montar: 'home' },
		{ orig: 11768, pagina: true, nome: 'Vitrine', montar: 'vitrine' },
		{ orig: 27615, pagina: true, nome: 'Conteúdos Gratuitos', montar: 'conteudos' }
	];
	M.COMPONENTES = ['barra-campanha', 'home-hero', 'home-condicoes-formulario', 'home-autoridade', 'home-depoimentos', 'home-fechamento', 'vitrine-eyebrow', 'vitrine-faixa', 'vitrine-ajuda', 'conteudos-ponte'];

	/* ---------------- acesso ao WordPress ---------------- */
	var nonceRest = null;
	async function rest(caminho, opcoes) {
		if (!nonceRest) nonceRest = await fetch('/wp-admin/admin-ajax.php?action=rest-nonce', { credentials: 'include' }).then(function (r) { return r.text(); });
		opcoes = opcoes || {};
		opcoes.credentials = 'include';
		opcoes.headers = Object.assign({ 'X-WP-Nonce': nonceRest, 'Content-Type': 'application/json' }, opcoes.headers || {});
		var r = await fetch('/wp-json' + caminho, opcoes);
		return r.json();
	}
	async function elementor(acoes) {
		if (!ajax) throw new Error('Abra /wp-admin/edit.php?post_type=elementor_library (falta o nonce do Elementor).');
		var fd = new FormData();
		fd.append('action', 'elementor_ajax');
		fd.append('_nonce', ajax.nonce);
		fd.append('actions', JSON.stringify(acoes));
		var r = await fetch(ajax.url, { method: 'POST', body: fd, credentials: 'include' });
		return r.json();
	}
	async function dados(id, pagina) {
		var campos = '?context=edit&_fields=id,title,modified,meta._elementor_data,meta._elementor_page_settings,meta._elementor_template_type';
		var d = await rest((pagina ? '/wp/v2/pages/' : '/wp/v2/elementor_library/') + id + campos);
		if (!d || !d.meta || typeof d.meta._elementor_data !== 'string') throw new Error('#' + id + ': sem _elementor_data (' + JSON.stringify(d).slice(0, 120) + ')');
		return { id: id, elementos: JSON.parse(d.meta._elementor_data || '[]'), ps: d.meta._elementor_page_settings || {}, titulo: d.title.raw, tipo: d.meta._elementor_template_type, modificado: d.modified, bruto: d.meta._elementor_data };
	}
	M.dados = dados;
	M.rest = rest;

	async function clonesExistentes() {
		var mapa = {}, pag = 1;
		for (;;) {
			var l = await rest('/wp/v2/elementor_library?context=edit&per_page=100&page=' + pag + '&status=publish,draft,private&search=' + encodeURIComponent(M.PREFIXO) + '&_fields=id,title,status');
			if (!Array.isArray(l) || !l.length) break;
			l.forEach(function (p) {
				var m = /^\[BLACK-2026\]\s*#(\d+)\b/.exec(p.title.raw || '');
				if (m && (!mapa[m[1]] || p.id > mapa[m[1]].id)) mapa[m[1]] = { id: p.id, titulo: p.title.raw, status: p.status };
			});
			if (l.length < 100) break;
			pag++;
		}
		return mapa;
	}
	M.clonesExistentes = clonesExistentes;

	async function salvar(idClone, elementos, ps) {
		var t = await rest('/wp/v2/elementor_library/' + idClone + '?context=edit&_fields=title');
		if (!t || !t.title || t.title.raw.indexOf(M.PREFIXO) !== 0) throw new Error('recusado: #' + idClone + ' nao e um modelo ' + M.PREFIXO);
		var resp = await elementor({ s: { action: 'save_builder', data: { editor_post_id: idClone, status: 'publish', elements: elementos, settings: ps || {} } } });
		var d = resp && resp.data && resp.data.responses && resp.data.responses.s;
		if (!(d && d.success)) throw new Error('save_builder #' + idClone + ': ' + JSON.stringify((d && d.data) || resp).slice(0, 200));
	}
	async function criar(orig, tipo, titulo, elementos) {
		var resp = await elementor({ c: { action: 'save_template', data: { post_id: orig, source: 'local', type: tipo, title: titulo, content: JSON.stringify(elementos) } } });
		var d = resp && resp.data && resp.data.responses && resp.data.responses.c;
		if (!(d && d.success)) throw new Error('save_template #' + orig + ': ' + JSON.stringify(d && d.data).slice(0, 200));
		return d.data.template_id;
	}

	/* ---------------- utilitarios de estrutura ---------------- */
	function uid() { return Math.random().toString(16).slice(2, 9).padEnd(7, '0'); } // id vazio trava o editor
	function acha(lista, id) {
		var achado = null;
		(function anda(l, pai) { l.forEach(function (e, i) { if (achado) return; if (e.id === id) achado = { el: e, pai: pai, lista: l, i: i }; else if (e.elements) anda(e.elements, e); }); })(lista, null);
		return achado;
	}
	function cada(lista, fn) { (function anda(l, pai) { l.forEach(function (e) { fn(e, pai); if (e.elements) anda(e.elements, e); }); })(lista, null); }
	var ZERO = { unit: 'px', top: '0', right: '0', bottom: '0', left: '0', isLinked: true };
	function caixa(nome, html) {
		return { id: uid(), elType: 'container', isInner: false, settings: { content_width: 'full', padding: ZERO, margin: ZERO, _title: 'BLACK · ' + nome }, elements: [widgetHtml(html, nome)] };
	}
	function widgetHtml(html, nome) { return { id: uid(), elType: 'widget', widgetType: 'html', settings: { html: html, _title: 'BLACK · ' + nome }, elements: [] }; }
	function widgetShortcode(sc, nome, classes) { return { id: uid(), elType: 'widget', widgetType: 'shortcode', settings: { shortcode: sc, _title: 'BLACK · ' + nome, _css_classes: classes || '' }, elements: [] }; }
	function esc(s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	function semTags(s) { var d = document.createElement('div'); d.innerHTML = String(s || ''); return (d.textContent || '').replace(/\s+/g, ' ').trim(); }
	function addClasse(el, c) { var s = el.settings || (el.settings = {}); var atual = (s._css_classes || '').split(/\s+/).filter(Boolean); if (atual.indexOf(c) < 0) atual.push(c); s._css_classes = atual.join(' '); }

	/* ---------------- passos por template (ajustes do Artifact por cima da conversao) ----------------
	   Localizam os pontos pela ESTRUTURA (shortcode, posicao fixa, texto do titulo), nao por id: os
	   templates 12073, 26931 e 28008 sao copias com ids diferentes e a producao muda com o tempo. */
	M.passos = {
		// Barra de campanha logo abaixo do menu (nos headers da producao a faixa antiga nao existe mais).
		barraNoHeader: function (els, ctx) {
			if (JSON.stringify(els).indexOf('BLACK · barra de campanha') >= 0) return;
			var idBarra = ctx.remap[34626];
			var w = idBarra
				? { id: uid(), elType: 'widget', widgetType: 'template', settings: { template_id: String(idBarra), _title: 'BLACK · barra de campanha' }, elements: [] }
				: widgetHtml(ctx.comp['barra-campanha'], 'barra de campanha');
			els.push({ id: uid(), elType: 'container', isInner: false, settings: { content_width: 'full', padding: ZERO, margin: ZERO, _title: 'BLACK · barra de campanha', _css_classes: 'cbn-barra-header' }, elements: [w] });
		},

		// Produto (12073 pos+intensivos, 26931 pediatria, 28008 Sanar): pecas do artboard Produto.
		pos: function (els, ctx) {
			var av = function (m) { ctx.rel.avisos.push('#' + ctx.item.orig + ': ' + m); };
			// 1. degrade grafite do hero: 1o container de nivel 0
			if (els[0] && els[0].elType === 'container') addClasse(els[0], 'cbn-hero-produto'); else av('hero nao achado (degrade)');
			// 2. selo "Condicoes exclusivas" ao lado de "Inscricoes abertas" ([mostrar_destaque_produto_hero])
			var selo = 0;
			cada(els, function (e, pai) {
				if (selo || !pai || e.elType !== 'widget') return;
				var s = e.settings || {};
				if (/\[mostrar_destaque_produto_hero\]/.test(String(s.title || s.shortcode || s.editor || ''))) {
					var i = pai.elements.indexOf(e);
					pai.elements.splice(i + 1, 0, widgetShortcode('[cetrus_black_selo]', 'selo condicoes exclusivas', 'cbn-selo-hero'));
					selo = 1;
				}
			});
			if (!selo) av('chip "Inscricoes abertas" nao achado (selo)');
			// 3. barra fixa do celular: so "Quero me inscrever" na pos (o CSS esconde o resto so na pos)
			var barra = els.filter(function (e) { var s = e.settings || {}; return e.elType === 'container' && (s.position === 'fixed' || s._position === 'fixed') && JSON.stringify(e).indexOf('[lyceum_course_button]') >= 0; })[0];
			if (barra) {
				addClasse(barra, 'cbn-barra-mobile');
				barra.elements.forEach(function (w) { if (w.widgetType === 'button' && /falar/i.test(String((w.settings || {}).text || ''))) addClasse(w, 'cbn-oculto-na-pos'); });
			} else av('barra fixa do celular nao achada');
			// 4. "Pertencimento" (so imprime em pos) antes de "Quem viu este curso"
			var iq = els.findIndex(function (e) { return /Quem viu este curso/.test(JSON.stringify(e)); });
			var pert = { id: uid(), elType: 'container', isInner: false, settings: { content_width: 'full', padding: ZERO, margin: ZERO, _title: 'BLACK · pertencimento', _css_classes: 'cbn-pert-wrap' }, elements: [widgetShortcode('[cetrus_black_pertencimento]', 'pertencimento')] };
			if (iq >= 0) els.splice(iq, 0, pert); else { av('"Quem viu este curso" nao achado; pertencimento no fim'); els.push(pert); }
		}
	};

	/* ---------------- montagem das paginas ---------------- */
	function exige(por, ids, nome, rel) {
		var faltam = ids.filter(function (id) { return !por[id]; });
		if (faltam.length) throw new Error(nome + ': elementos esperados nao existem mais na pagina original: ' + faltam.join(', ') + ' (a pagina mudou; revisar o montar-black.js)');
	}
	M.montar = {
		barra: function (els, comp) { return [caixa('barra de campanha', comp['barra-campanha'])]; },

		home: function (els, comp, ctx) {
			var por = {}; els.forEach(function (e) { por[e.id] = e; });
			var conhecidos = ['75c936f', 'f4ad350', '0fe805b', 'ddd2426', '287991c'];
			exige(por, conhecidos, 'Home', ctx.rel);
			// saem: faixa Lista VIP (76b95fe, a barra do header ocupa o lugar), hero e buscas antigos
			// (5c98cd0, 317b4e0, d3db8d1: substituidos pelo hero do Artifact), newsletter (92454f4, pedido
			// de 28/10) e depoimentos originais (ddd2426: entram no formato do Artifact, com os mesmos textos).
			var saem = ['76b95fe', '5c98cd0', '317b4e0', 'd3db8d1', '92454f4', 'ddd2426'];
			var depo = depoimentos(ctx.original.elementos.filter(function (e) { return e.id === 'ddd2426'; }), comp['home-depoimentos']);
			if (!depo.n) ctx.rel.avisos.push('Home: nenhum depoimento achado em ddd2426; secao de depoimentos ficou vazia.');
			var extras = els.filter(function (e) { return conhecidos.concat(saem).indexOf(e.id) < 0; });
			extras.forEach(function (e) { ctx.rel.avisos.push('Home: container novo na pagina original (' + e.id + '), mantido antes do fechamento. Conferir.'); });
			return [caixa('hero', comp['home-hero']), por['75c936f'], caixa('condicoes', comp['home-condicoes-formulario']), por['f4ad350'],
				caixa('autoridade', comp['home-autoridade']), por['0fe805b'], caixa('depoimentos', depo.html), por['287991c']]
				.concat(extras, [caixa('fechamento', comp['home-fechamento'])]);
		},

		vitrine: function (els, comp, ctx) {
			var por = {}; els.forEach(function (e) { por[e.id] = e; });
			exige(por, ['3c002a0', '49b95b3', '5dd2fd8'], 'Vitrine', ctx.rel);
			var d = acha(els, 'dead37d');
			if (!d) throw new Error('Vitrine: container dead37d (texto do hero) nao existe mais');
			d.el.elements.unshift(widgetHtml(comp['vitrine-eyebrow'], 'sobretitulo'));
			var i = els.findIndex(function (e) { return e.id === '3c002a0'; });
			els.splice(i + 1, 0, caixa('faixa corrida', comp['vitrine-faixa']));
			els.push(caixa('ajuda para decidir', comp['vitrine-ajuda']));
			return els;
		},

		conteudos: function (els, comp, ctx) {
			var i = els.findIndex(function (e) { return e.id === '531de49'; });
			if (i < 0) throw new Error('Conteudos gratuitos: prateleira 531de49 (Lancamentos) nao existe mais');
			els.splice(i + 1, 0, caixa('ponte conteudo curso', comp['conteudos-ponte']));
			return els;
		}
	};

	// Depoimentos da secao original -> cartoes do Artifact (sem repetir: o carrossel original duplica slides).
	function depoimentos(secao, modelo) {
		var vistos = {}, itens = [];
		cada(secao, function (e) {
			if (e.widgetType !== 'testimonial') return;
			var s = e.settings || {};
			var texto = semTags(s.testimonial_content), nome = semTags(s.testimonial_name), curso = semTags(s.testimonial_job);
			var chave = nome + '|' + texto.slice(0, 40);
			if (!texto || vistos[chave]) return;
			vistos[chave] = 1;
			var img = s.testimonial_image && s.testimonial_image.url;
			itens.push('\t\t\t<blockquote class="cbd__item"><p>“' + esc(texto) + '”</p><footer>' +
				(img ? '<img src="' + esc(img) + '" alt="" width="48" height="48" loading="lazy">' : '') +
				'<span><strong>' + esc(nome) + '</strong>' + (curso ? '<span>' + esc(curso) + '</span>' : '') + '</span></footer></blockquote>');
		});
		return { n: itens.length, html: modelo.replace('{{ITENS}}', itens.slice(0, 6).join('\n')) };
	}
	M.depoimentos = depoimentos;

	/* ---------------- execucao ---------------- */
	async function componentes(base) {
		var c = {};
		for (var i = 0; i < M.COMPONENTES.length; i++) {
			var nome = M.COMPONENTES[i];
			var t = await fetch(base + 'componentes/' + nome + '.html', { cache: 'no-store' }).then(function (r) { if (!r.ok) throw new Error('componente ' + nome + ': HTTP ' + r.status); return r.text(); });
			c[nome] = t.replace(/^\s*<!--[\s\S]*?-->\s*/, ''); // comentario de cabecalho fica so no repositorio
		}
		return c;
	}

	M.executar = async function (op) {
		op = op || {};
		if (!window.cetrusBlack || !window.cetrusBlack.transformar) throw new Error('Carregue antes o transforma-black.js');
		if (!op.base) throw new Error('Informe op.base (pasta black-november-2026/ no GitHub, fixada num commit)');
		var rel = { site: location.origin, simular: !!op.simular, inicio: new Date().toISOString(), itens: [], avisos: [] };
		var comp = await componentes(op.base);
		var existentes = await clonesExistentes();
		var lista = op.so ? M.lista.filter(function (x) { return op.so.indexOf(x.orig) >= 0; }) : M.lista;
		var remap = {};
		M.REFERENCIADOS.forEach(function (id) { if (existentes[id]) remap[id] = existentes[id].id; });

		for (var i = 0; i < lista.length; i++) {
			var item = lista[i], linha = { orig: item.orig, clone: existentes[item.orig] ? existentes[item.orig].id : null, acao: '', trocas: 0, erro: '' };
			try {
				var o = await dados(item.orig, item.pagina);
				var tipo = item.pagina ? 'page' : o.tipo;
				var r = window.cetrusBlack.transformar(JSON.parse(o.bruto), {
					kit: M.kit, fundoTopo: M.fundoTopo[item.orig], imagens: [{ de: /cetrus-logo[^/]*\.svg/i, para: M.logoBranco }],
					ajustes: M.ajustes[item.orig] || {}, remap: remap
				});
				var els = r.elementos;
				var ctx = { rel: rel, original: o, item: item, remap: remap, comp: comp };
				if (item.montar) els = M.montar[item.montar](els, comp, ctx);
				(item.passos || []).forEach(function (p) {
					if (!M.passos[p]) throw new Error('passo ' + p + ' nao definido');
					M.passos[p](els, ctx);
				});
				var ps = Object.assign({}, o.ps);
				delete ps.ep_custom_footer_script; // script de busca antiga da home: a pagina original ja o imprime
				linha.trocas = r.log.length;
				linha.titulo = M.PREFIXO + ' #' + item.orig + ' ' + (item.nome || o.titulo);
				if (op.simular) {
					linha.acao = linha.clone ? 'atualizaria' : 'criaria';
				} else {
					if (!linha.clone) {
						linha.clone = await criar(item.orig, tipo, linha.titulo, els);
						linha.acao = 'criado';
					} else {
						linha.acao = 'atualizado';
					}
					await salvar(linha.clone, els, ps);
				}
				if (linha.clone && M.REFERENCIADOS.indexOf(item.orig) >= 0) remap[item.orig] = linha.clone;
			} catch (e) {
				linha.erro = String(e.message || e);
				rel.avisos.push('#' + item.orig + ': ' + linha.erro);
			}
			rel.itens.push(linha);
		}
		rel.fim = new Date().toISOString();
		rel.mapa = rel.itens.filter(function (x) { return x.clone; }).map(function (x) { return x.orig + '\t' + x.clone + '\t' + x.acao; }).join('\n');
		return rel;
	};

	/* SHA-256 do _elementor_data de cada original (e da data de alteracao): guardar antes da campanha
	   e repetir no rollback para provar que os originais nao foram tocados pela versao Black. */
	M.verificarOriginais = async function () {
		var h = async function (s) { return [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)))].map(function (b) { return b.toString(16).padStart(2, '0'); }).join(''); };
		var linhas = ['original\ttipo\tmodificado\tsha256_elementor_data\ttitulo'];
		for (var i = 0; i < M.lista.length; i++) {
			var item = M.lista[i];
			try {
				var o = await dados(item.orig, item.pagina);
				linhas.push([item.orig, item.pagina ? 'page' : o.tipo, o.modificado, await h(o.bruto), o.titulo].join('\t'));
			} catch (e) { linhas.push(item.orig + '\terro\t\t\t' + e.message); }
		}
		return linhas.join('\n');
	};

	/* Pos-campanha (depois do rollback validado): os modelos [BLACK-2026] viram rascunho. Nao apaga
	   nada; publicar de novo e so mudar o status (servem de base para 2027). */
	M.desmontar = async function (op) {
		op = op || {};
		var existentes = await clonesExistentes(), feitos = [];
		for (var orig in existentes) {
			var c = existentes[orig];
			if (c.titulo.indexOf(M.PREFIXO) !== 0) continue;
			if (!op.simular && c.status !== 'draft') await rest('/wp/v2/elementor_library/' + c.id, { method: 'POST', body: JSON.stringify({ status: 'draft' }) });
			feitos.push(orig + '\t' + c.id + '\t' + c.status + ' -> draft' + (op.simular ? ' (simulado)' : ''));
		}
		return feitos.join('\n');
	};

	M.util = { acha: acha, cada: cada, caixa: caixa, widgetHtml: widgetHtml, widgetShortcode: widgetShortcode, addClasse: addClasse, uid: uid };
	return 'cetrusMontar pronto: ' + M.lista.length + ' modelos na lista';
})();
