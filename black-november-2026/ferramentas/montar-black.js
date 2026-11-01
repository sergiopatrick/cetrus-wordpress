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
 * - com o mu-plugin no modo "desligado" (padrao) os modelos sao inertes: ninguem os ve; a virada
 *   e manual (Ferramentas > Black November 2026).
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
		{ orig: 19225 }, { orig: 21114 }, { orig: 34795 }, { orig: 32359, passos: ['pacote'] }, { orig: 19062 },
		{ orig: 12753 }, { orig: 12747 },
		{ orig: 10946, pagina: true, nome: 'Home', montar: 'home' },
		{ orig: 11768, pagina: true, nome: 'Vitrine', montar: 'vitrine' },
		{ orig: 27615, pagina: true, nome: 'Conteúdos Gratuitos', montar: 'conteudos' }
	];
	M.COMPONENTES = ['barra-campanha', 'home-hero', 'home-condicoes-formulario', 'home-legado', 'home-autoridade', 'home-depoimentos', 'home-conteudos-destaque', 'home-fechamento', 'vitrine-eyebrow', 'vitrine-faixa', 'vitrine-ajuda', 'conteudos-ponte'];

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
	// widget usa _css_classes; container usa css_classes (com _css_classes o Elementor ignora a classe no container)
	function addClasse(el, c) {
		var s = el.settings || (el.settings = {}), k = el.elType === 'widget' ? '_css_classes' : 'css_classes';
		var atual = (s[k] || '').split(/\s+/).filter(Boolean);
		if (atual.indexOf(c) < 0) atual.push(c);
		s[k] = atual.join(' ');
	}

	/* ---------------- passos por template (ajustes do Artifact por cima da conversao) ----------------
	   Localizam os pontos pela ESTRUTURA (shortcode, posicao fixa, texto do titulo), nao por id: os
	   templates 12073, 26931 e 28008 sao copias com ids diferentes e a producao muda com o tempo. */
	M.passos = {
		// Barra de campanha logo abaixo do menu (nos headers da producao a faixa antiga nao existe mais).
		barraNoHeader: function (els, ctx) {
			if (JSON.stringify(els).indexOf('BLACK · barra de campanha') >= 0) return;
			var idBarra = ctx.remap[34626];
			// header que ainda tem a faixa antiga apontando para a 34626 (ex.: staging com banco de 19/10):
			// a faixa ja vira a barra pelo remap, entao nao acrescenta outra
			var jaTem = false;
			cada(els, function (e) { var t = e.settings && e.settings.template_id; if (t && (String(t) === '34626' || (idBarra && String(t) === String(idBarra)))) jaTem = true; });
			if (jaTem) return;
			var w = idBarra
				? { id: uid(), elType: 'widget', widgetType: 'template', settings: { template_id: String(idBarra), _title: 'BLACK · barra de campanha' }, elements: [] }
				: widgetHtml(ctx.comp['barra-campanha'], 'barra de campanha');
			els.push({ id: uid(), elType: 'container', isInner: false, settings: { content_width: 'full', padding: ZERO, margin: ZERO, _title: 'BLACK · barra de campanha', css_classes: 'cbn-barra-header' }, elements: [w] });
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
			var pert = { id: uid(), elType: 'container', isInner: false, settings: { content_width: 'full', padding: ZERO, margin: ZERO, _title: 'BLACK · pertencimento', css_classes: 'cbn-pert-wrap' }, elements: [widgetShortcode('[cetrus_black_pertencimento]', 'pertencimento')] };
			if (iq >= 0) els.splice(iq, 0, pert); else { av('"Quem viu este curso" nao achado; pertencimento no fim'); els.push(pert); }
		},

		// Pacotes (32359), so no modelo Black (30/10): no celular o formulario sumia. O CSS do Kit esconde
		// .cetrus-hs-form--inline ate 960 px e o botao do celular aponta para #inscricao, que nao existe.
		// Aqui o mesmo formulario do computador (mesmos campos ocultos) aparece no celular e o botao vai para #form.
		pacote: function (els, ctx) {
			var av = function (m) { ctx.rel.avisos.push('#' + ctx.item.orig + ': ' + m); };
			var form = 0, botoes = 0;
			cada(els, function (e) {
				var s = e.settings || {};
				if (e.widgetType === 'html' && /cetrus-hs-form--inline/.test(String(s.html || ''))) {
					addClasse(e, 'cbn-pacote-form');
					s.html += '\n<style>@media (max-width: 960px) { .cbn-pacote-form .cetrus-hs-form--inline { display: block; } }</style>';
					form++;
				}
				if (e.widgetType === 'button' && s.link && s.link.url === '#inscricao') {
					s.link.url = '#form';
					botoes++;
				}
			});
			if (!form) av('formulario inline nao achado (celular sem formulario)');
			if (!botoes) av('botao #inscricao nao achado');
			ctx.rel.avisos.push('#' + ctx.item.orig + ': pacote no celular, ' + form + ' formulario(s) e ' + botoes + ' botao(oes) ajustados');
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
			var conhecidos = ['75c936f', '0fe805b', 'ddd2426', '287991c'];
			exige(por, conhecidos, 'Home', ctx.rel);
			// faixa Lista VIP (76b95fe, template 35597): fica no topo, logo abaixo da barra do header. A Lista VIP
			// continua em novembro com "Garanta exclusividade na oferta" (decisao do Greg e do Sergio, 30/10).
			var faixaVip = por['76b95fe'];
			if (!faixaVip) ctx.rel.avisos.push('Home: faixa Lista VIP (76b95fe) nao achada; Home Black sem a faixa.');
			// saem: hero e buscas antigos (5c98cd0, 317b4e0, d3db8d1: substituidos pelo hero do Artifact),
			// newsletter (92454f4, pedido de 28/10), depoimentos originais (ddd2426: entram no formato do Artifact,
			// com os mesmos textos) e numeros originais (f4ad350: no lugar entra a secao "Legado" do Artifact,
			// pedido de 28/10 a noite).
			var saem = ['5c98cd0', '317b4e0', 'd3db8d1', '92454f4', 'ddd2426', 'f4ad350'];
			var depo = depoimentos(ctx.original.elementos.filter(function (e) { return e.id === 'ddd2426'; }), comp['home-depoimentos']);
			if (!depo.n) ctx.rel.avisos.push('Home: nenhum depoimento achado em ddd2426; secao de depoimentos ficou vazia.');
			var extras = els.filter(function (e) { return conhecidos.concat(saem, ['76b95fe']).indexOf(e.id) < 0; });
			extras.forEach(function (e) { ctx.rel.avisos.push('Home: container novo na pagina original (' + e.id + '), mantido antes do fechamento. Conferir.'); });
			// fundos das secoes como no artboard Home (regras no black.css, bloco "Home")
			addClasse(por['75c936f'], 'cbn-home-destaques');
			addClasse(por['287991c'], 'cbn-home-conteudos');
			addClasse(por['0fe805b'], 'cbn-home-fellowship');
			var cartao = (por['0fe805b'].elements || [])[0], colImg = null;
			if (cartao && cartao.elType === 'container') {
				addClasse(cartao, 'cbn-home-fellowship__card');
				colImg = (cartao.elements || []).filter(function (e) { return e.elType === 'container' && /"widgetType":"image"/.test(JSON.stringify(e)); })[0];
				if (colImg) addClasse(colImg, 'cbn-home-fellowship__img');
			}
			if (!colImg) ctx.rel.avisos.push('Home: cartao da secao fellowship (0fe805b) com estrutura diferente; fundos do Artifact so na secao.');
			// "Explore conteudos gratuitos": o 1o card (em 10/2026, Especial Outubro Rosa, que abre um modal)
			// vira o destaque da biblioteca de conteudos gratuitos, como no Artifact; os 3 artigos continuam
			var cg = acha([por['287991c']], 'dd26583');
			var velho = cg && (cg.el.elements || [])[0];
			if (velho && velho.elType === 'container') {
				var vs = velho.settings || {};
				cg.el.elements[0] = { id: uid(), elType: 'container', isInner: true, settings: { content_width: 'full', width: vs.width, width_tablet: vs.width_tablet, width_mobile: vs.width_mobile, padding: ZERO, margin: ZERO, _title: 'BLACK · destaque conteudos gratuitos', css_classes: 'cbn-home-cg-destaque' }, elements: [widgetHtml(comp['home-conteudos-destaque'], 'destaque conteudos gratuitos')] };
			} else ctx.rel.avisos.push('Home: card de destaque de "Explore conteudos gratuitos" (dd26583) nao achado; ficou o original.');
			return (faixaVip ? [faixaVip] : []).concat([caixa('hero', comp['home-hero']), por['75c936f'], caixa('condicoes', comp['home-condicoes-formulario']), caixa('legado', comp['home-legado']),
				caixa('autoridade', comp['home-autoridade']), por['0fe805b'], caixa('depoimentos', depo.html), por['287991c']]
				.concat(extras, [caixa('fechamento', comp['home-fechamento'])]));
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
			// hero: H1 e colunas como no artboard Material (pedido de 28/10 a noite; regras no black.css)
			var hero = els[0], h1 = null;
			cada([hero], function (e) { if (!h1 && e.widgetType === 'heading' && (e.settings || {}).header_size === 'h1') h1 = e; });
			if (h1) {
				var s = h1.settings, t = String(s.title || '');
				// so marca o negrito do Artifact; o texto do H1 nao muda (SEO)
				if (t.indexOf('<strong') < 0 && /gratuitos do Cetrus\s*$/.test(t)) s.title = t.replace(/gratuitos do Cetrus\s*$/, '<strong>gratuitos do Cetrus</strong>');
				else ctx.rel.avisos.push('Conteudos: H1 diferente do esperado ("' + semTags(t) + '"); negrito do Artifact nao aplicado.');
				// fonte do Artifact (Montserrat): o Elementor so carrega a fonte se ela estiver no widget
				s.typography_typography = 'custom';
				s.typography_font_family = 'Montserrat';
				s.typography_font_weight = '300';
				if (s.__globals__) delete s.__globals__.typography_typography;
				addClasse(h1, 'cbn-cg-h1');
			} else ctx.rel.avisos.push('Conteudos: H1 do hero nao achado.');
			var linha = (hero.elements || [])[0];
			if (linha && h1) {
				addClasse(hero, 'cbn-cg-hero');
				addClasse(linha, 'cbn-cg-hero-linha');
				(linha.elements || []).forEach(function (col) {
					var js = JSON.stringify(col);
					if (js.indexOf('"id":"' + h1.id + '"') >= 0) addClasse(col, 'cbn-cg-hero-txt');
					else if (/"widgetType":"image"/.test(js)) addClasse(col, 'cbn-cg-hero-img');
				});
			}
			// fundos alternados das secoes, como no artboard Material
			var fundos = [[/Como acessar/, 'cbn-cg-fundo-0e'], [/Lançamentos/, 'cbn-cg-fundo-00'], [/Aulas e cursos gratuitos/, 'cbn-cg-fundo-12'],
				[/Masterclasses/, 'cbn-cg-fundo-00'], [/E-books/, 'cbn-cg-fundo-12'], [/cetrus_newsletter/, 'cbn-cg-fundo-14'], [/Blog Educa/, 'cbn-cg-fundo-0e']];
			els.forEach(function (e, k) {
				if (k === 0 || /^BLACK · /.test((e.settings || {})._title || '')) return;
				var js = JSON.stringify(e), f = fundos.filter(function (p) { return p[0].test(js); })[0];
				if (f) addClasse(e, f[1]); else ctx.rel.avisos.push('Conteudos: secao ' + e.id + ' sem fundo do Artifact (secao nova?).');
			});
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

	// Ids citados entre aspas em codigo de widgets HTML: viram os dos modelos Black. Ex.: o popup 29363
	// (inscricao) cria o proprio formulario HubSpot procurando [data-elementor-id="29363"]; no modelo,
	// o documento impresso tem o id do modelo e o formulario nao aparecia (teste de 29/10).
	// O transformador ja remapeia popups e templates em __dynamic__/template_id, mas nao le HTML.
	function trocaIdsNoHtml(els, remap, orig, clone) {
		var mapa = Object.assign({}, remap), n = 0;
		mapa[orig] = clone;
		cada(els, function (e) {
			var s = e.settings;
			if (e.widgetType !== 'html' || !s || typeof s.html !== 'string') return;
			s.html = s.html.replace(/(["'])(\d{4,})\1/g, function (t, aspa, id) {
				if (!mapa[id] || String(mapa[id]) === id) return t;
				n++;
				return aspa + mapa[id] + aspa;
			});
			// link de acao do Elementor com o id dentro do base64 (#elementor-action:action=popup:open&settings=<base64>),
			// como o botao "Quero me inscrever" dos templates Sanar Pos, Pediatria e EAD (30/10). Mesma regra
			// de cetrus_black_troca_popups() no mu-plugin.
			s.html = s.html.replace(/(popup(?::|%3A)open(?:&amp;|&|%26)settings(?:=|%3D))([A-Za-z0-9+\/=%]+)/g, function (t, pre, b64) {
				var conf;
				try { conf = JSON.parse(atob(decodeURIComponent(b64))); } catch (e) { return t; }
				if (!conf || conf.id === undefined || !mapa[conf.id] || String(mapa[conf.id]) === String(conf.id)) return t;
				conf.id = typeof conf.id === 'string' ? String(mapa[conf.id]) : mapa[conf.id];
				n++;
				var novo = btoa(JSON.stringify(conf));
				return pre + (pre.indexOf('%') >= 0 ? encodeURIComponent(novo) : novo);
			});
		});
		return n;
	}
	M.trocaIdsNoHtml = trocaIdsNoHtml;

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
					if (linha.clone) linha.idsHtml = trocaIdsNoHtml(els, remap, item.orig, linha.clone);
				} else {
					if (!linha.clone) {
						linha.clone = await criar(item.orig, tipo, linha.titulo, els);
						linha.acao = 'criado';
					} else {
						linha.acao = 'atualizado';
					}
					// so depois de criar: o id do proprio modelo so existe agora
					linha.idsHtml = trocaIdsNoHtml(els, remap, item.orig, linha.clone);
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
