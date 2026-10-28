/*
 * Cetrus · Black November 2026 · transformador dos clones do Elementor.
 *
 * SEMPRE partir dos dados do template ORIGINAL (nao do clone ja convertido): a conversao
 * nao e idempotente (um fundo ja grafite seria lido como "marinho" e mudaria de novo).
 *
 * Converte os dados (_elementor_data) de um clone "[BLACK-2026] #<id>" para a paleta do
 * Artifact (secao 3 do PROJETO_PADRONIZACAO_PAGINAS_CETRUS.md), SEM mudar estrutura,
 * textos, links, shortcodes, tags dinamicas ou ids de elementos. So cores (e, quando
 * pedido, ajustes pontuais por id de elemento). Nunca rodar em template original.
 *
 * Como usar (console de uma pagina do wp-admin do site, logado):
 *   1. colar este arquivo;
 *   2. const r = cetrusBlack.transformar(JSON.parse(dadosDoClone), { kit: CORES_DO_KIT });
 *      r.elementos = dados novos; r.log = cada troca feita (para revisar e versionar);
 *   3. salvar no CLONE pelo AJAX do Elementor (save_builder), nunca no original.
 *
 * Regra por papel da propriedade:
 *   fundo  (background*, *_background*, overlay, tabs_*_background*) -> grafite/superficie
 *   borda  (*border*)                                                  -> branco 10% (ativo: 55%)
 *   texto  (o resto que e cor)                                          -> #F3F3F6 / #C9CAD6 / lavanda
 *   botao  (widget button)                                              -> claro #F3F3F6 com texto #111214
 * Cores que nao sao da marca (verde do WhatsApp, transparentes) ficam como estao.
 */
(function (raiz) {
	var T = {
		grafite: '#0E0F12', grafite2: '#141518', secao: '#121316', topo: '#0B0C0E', superficie: '#191A1E',
		heroA: '#23242A', heroB: '#121316',
		texto: '#F3F3F6', corpo: '#C9CAD6', apoio: '#A9AAB8', lavanda: '#8C8EB6', lavandaClara: '#D4D5E8',
		botaoTexto: '#111214', borda: 'rgba(255,255,255,0.10)', bordaForte: 'rgba(255,255,255,0.55)'
	};

	// Familias de cor do site original (hex sem alfa, maiusculo) -> grupo.
	var GRUPOS = {
		claro: ['#FFFFFF', '#F2F3FB', '#F9FAFB', '#E1E8F5', '#E9EBED', '#FAFAFA', '#F5F5F5', '#EEEEEE', '#E8FFEB'],
		marinho: ['#002452', '#0A1E39', '#0A192C', '#00326C', '#011024', '#000000', '#111212', '#020101'],
		medio: ['#003B6C', '#2A6390', '#3E88C2'],
		acento: ['#3D93D7', '#9BB1DF'],
		cinzaEscuro: ['#404444', '#595F5F', '#54595F', '#7A7A7A'],
		cinzaClaro: ['#C3C6C6', '#AAAAAA'],
		preservar: ['#1C9C2E', '#10751E', '#25D366', '#095614', '#F2295B']
	};

	function grupoDe(hex) {
		for (var g in GRUPOS) { if (GRUPOS[g].indexOf(hex) >= 0) return g; }
		// fora da lista: decide pela luminancia
		var r = parseInt(hex.substr(1, 2), 16), gg = parseInt(hex.substr(3, 2), 16), b = parseInt(hex.substr(5, 2), 16);
		var l = (0.2126 * r + 0.7152 * gg + 0.0722 * b) / 255;
		return l > 0.8 ? 'claro' : (l < 0.25 ? 'marinho' : 'cinzaEscuro');
	}

	// "#RRGGBB" | "#RRGGBBAA" | "#RGB" -> { hex: '#RRGGBB', alfa: 'AA'|'' }
	function parteHex(v) {
		var s = String(v).trim().toUpperCase();
		if (/^#[0-9A-F]{3}$/.test(s)) s = '#' + s[1] + s[1] + s[2] + s[2] + s[3] + s[3];
		if (!/^#[0-9A-F]{6}([0-9A-F]{2})?$/.test(s)) return null;
		return { hex: s.substr(0, 7), alfa: s.substr(7, 2) };
	}
	function comAlfa(nova, alfa) {
		if (!alfa || alfa === 'FF') return nova;
		if (nova.indexOf('rgba') === 0) return nova; // ja tem alfa proprio
		return nova + alfa;
	}

	function papel(chave, widget) {
		var k = chave.toLowerCase();
		if (widget === 'button' || /^button_/.test(k)) {
			if (/border/.test(k)) return 'botaoBorda';
			if (/background/.test(k) || (widget === 'button' && /^background_color/.test(k))) return 'botaoFundo';
			if (/text|hover_color$|^color$/.test(k)) return 'botaoTexto';
		}
		if (/border/.test(k)) return 'borda';
		if (/background|overlay|_bg_|bg_color/.test(k)) return 'fundo';
		return 'texto';
	}

	function novaCor(grupo, p, ctx) {
		if (grupo === 'preservar') return null;
		switch (p) {
			// Botao de fundo escuro/colorido vira o primario claro do Artifact (#F3F3F6, texto #111214);
			// botao de fundo claro vira outline (transparente, borda 55%, texto branco).
			case 'botaoFundo':
				if (ctx.estado) return ctx.botaoClaroOriginal ? 'rgba(255,255,255,0.08)' : '#FFFFFF';
				return ctx.botaoClaroOriginal ? 'transparent' : T.texto;
			case 'botaoTexto':
				return ctx.botaoClaroOriginal ? '#FFFFFF' : T.botaoTexto;
			case 'botaoBorda':
				return T.bordaForte;
			case 'borda':
				return (grupo === 'medio' || grupo === 'acento') ? T.bordaForte : T.borda;
			case 'fundo':
				if (grupo === 'claro') return ctx.profundidade === 0 ? (ctx.alterna ? T.secao : T.grafite) : T.superficie;
				if (grupo === 'marinho' || grupo === 'medio') return ctx.profundidade === 0 ? T.grafite2 : T.superficie;
				if (grupo === 'acento') return T.superficie;
				return T.superficie;
			default: // texto
				if (ctx.estado) return '#FFFFFF'; // hover/ativo: no escuro, o destaque e o branco
				if (ctx.menu) return T.texto;     // Artifact: itens do menu em #F3F3F6
				if (grupo === 'claro') return T.texto;
				if (grupo === 'cinzaClaro') return T.apoio;
				if (grupo === 'acento' || grupo === 'medio') return T.lavandaClara;
				return ctx.titulo ? T.texto : T.corpo;
		}
	}

	var WIDGETS_MENU = ['mega-menu', 'nav-menu', 'icon-list', 'jkit_nav_menu'];

	/**
	 * @param {Array} elementos  _elementor_data ja decodificado (do CLONE)
	 * @param {Object} opcoes    {
	 *   kit: { '<id global>': '#hex' },
	 *   ajustes: { '<id elemento>': { chave: valor } },
	 *   fundoTopo: '#0B0C0E'   // header/rodape: fundo dos containers de 1o nivel
	 *   imagens: [{ de: /cetrus-logo\.svg/, para: { url, id } }]  // ex.: logo colorido -> branco
	 * }
	 */
	function transformar(elementos, opcoes) {
		opcoes = opcoes || {};
		var kit = opcoes.kit || {};
		var ajustes = opcoes.ajustes || {};
		var imagens = opcoes.imagens || [];
		var log = [];
		var topo = 0;
		var copia = JSON.parse(JSON.stringify(elementos));

		function corOriginal(s, g, k) {
			if (g[k]) {
				var m = /id=([a-z0-9]+)/i.exec(g[k]);
				if (m && kit[m[1]]) return kit[m[1]];
				return null;
			}
			return typeof s[k] === 'string' ? s[k] : null;
		}

		function anda(lista, prof) {
			lista.forEach(function (el) {
				var s = el.settings = el.settings || {};
				var g = s.__globals__ = s.__globals__ || {};
				var widget = el.widgetType || '';
				var ctx = {
					profundidade: prof,
					alterna: prof === 0 ? (topo++ % 2 === 1) : false,
					titulo: widget === 'heading' || widget === 'theme-post-title',
					botaoClaroOriginal: false
				};
				if (widget === 'button') {
					var fb = corOriginal(s, g, 'background_color');
					var pf = fb && parteHex(fb);
					// fundo claro OU transparente (botao "fantasma", ex.: links "Corpo docente") -> texto claro
					ctx.botaoClaroOriginal = !!(pf && (grupoDe(pf.hex) === 'claro' || pf.alfa === '00'));
					if (!fb) {
						// sem fundo proprio = herda o azul do Kit (#003B6C): vira o primario claro explicito
						s.background_color = T.texto;
						if (!s.button_text_color && !g.button_text_color) s.button_text_color = T.botaoTexto;
						if (!s.button_background_hover_color && !g.button_background_hover_color) s.button_background_hover_color = '#FFFFFF';
						if (!s.hover_color && !g.hover_color) s.hover_color = T.botaoTexto;
						log.push({ id: el.id, widget: widget, chave: 'background_color', de: '(Kit #003B6C)', para: T.texto });
					}
				}
				var chaves = Object.keys(s).concat(Object.keys(g));
				chaves.filter(function (k, i) { return chaves.indexOf(k) === i && k !== '__globals__' && /color/i.test(k); }).forEach(function (k) {
					var orig = corOriginal(s, g, k);
					if (!orig) return;
					var ph = parteHex(orig);
					if (!ph || ph.alfa === '00') return; // transparente: fica
					var p = papel(k, widget);
					ctx.estado = /hover|active/i.test(k);
					ctx.menu = WIDGETS_MENU.indexOf(widget) >= 0;
					var nova = (p === 'fundo' && prof === 0 && opcoes.fundoTopo && grupoDe(ph.hex) !== 'preservar' && !ctx.estado)
						? opcoes.fundoTopo
						: novaCor(grupoDe(ph.hex), p, ctx);
					if (!nova) return;
					nova = comAlfa(nova, ph.alfa);
					if (nova.toUpperCase() === String(s[k] || '').toUpperCase() && !g[k]) return;
					log.push({ id: el.id, widget: widget || el.elType, chave: k, de: (g[k] ? g[k] + ' ' : '') + orig, para: nova });
					s[k] = nova;
					if (g[k]) delete g[k];
				});
				// CSS personalizado do elemento (Avancado > CSS personalizado): reescreve so as cores,
				// com o mesmo papel por propriedade (color = texto, background = fundo, border = borda).
				if (typeof s.custom_css === 'string' && s.custom_css) {
					var antes = s.custom_css;
					s.custom_css = antes.replace(/([a-z-]+)\s*:\s*([^;{}]*#[0-9a-fA-F]{3,8}[^;{}]*)/g, function (tudo, prop, valor) {
						var pc = /background/.test(prop) ? 'fundo' : (/border|outline/.test(prop) ? 'borda' : (/^(color|fill|stroke|-webkit-text-fill-color|text-decoration-color)$/.test(prop) ? 'texto' : null));
						if (!pc) return tudo;
						return prop + ':' + valor.replace(/#[0-9a-fA-F]{3,8}\b/g, function (h) {
							var ph2 = parteHex(h);
							if (!ph2 || ph2.alfa === '00') return h;
							var n = novaCor(grupoDe(ph2.hex), pc, { profundidade: 1, titulo: false, estado: false, menu: false });
							return n ? comAlfa(n, ph2.alfa) : h;
						});
					});
					if (s.custom_css !== antes) log.push({ id: el.id, widget: widget || el.elType, chave: 'custom_css', de: antes.slice(0, 120), para: s.custom_css.slice(0, 120) });
				}
				// Referencias diretas a outros modelos (botao "abrir popup 29363", widget de modelo,
				// loop item): no clone, apontam para o clone correspondente, senao a pagina Black
				// carrega tambem o popup/modelo original. opcoes.remap = { '<id original>': <id clone> }.
				var remap = opcoes.remap || {};
				if (Object.keys(remap).length) {
					var trocaId = function (txt) {
						return txt
							.replace(/(%22popup%22%3A%22)(\d+)(%22)/g, function (t, a, id, b) { return remap[id] ? a + remap[id] + b : t; })
							.replace(/("popup"\s*:\s*")(\d+)(")/g, function (t, a, id, b) { return remap[id] ? a + remap[id] + b : t; });
					};
					Object.keys(s.__dynamic__ || {}).forEach(function (k) {
						var v = s.__dynamic__[k];
						if (typeof v !== 'string') return;
						var n2 = trocaId(v);
						if (n2 !== v) { log.push({ id: el.id, widget: widget || el.elType, chave: '__dynamic__.' + k, de: v.slice(0, 80), para: n2.slice(0, 80) }); s.__dynamic__[k] = n2; }
					});
					['template_id', 'popup'].forEach(function (k) {
						if (s[k] && remap[String(s[k])]) { log.push({ id: el.id, widget: widget || el.elType, chave: k, de: s[k], para: remap[String(s[k])] }); s[k] = String(remap[String(s[k])]); }
					});
				}
				// imagens (ex.: logo colorido -> logo branco) em widgets de imagem e no logo do site
				['image', 'site_logo', 'logo'].forEach(function (k) {
					var img = s[k];
					if (!img || typeof img !== 'object' || !img.url) return;
					imagens.forEach(function (t) {
						if (t.de.test(img.url)) {
							log.push({ id: el.id, widget: widget || el.elType, chave: k, de: img.url, para: t.para.url });
							s[k] = Object.assign({}, img, t.para);
						}
					});
				});
				if (ajustes[el.id]) {
					Object.keys(ajustes[el.id]).forEach(function (k) {
						log.push({ id: el.id, widget: widget || el.elType, chave: k, de: JSON.stringify(s[k]), para: JSON.stringify(ajustes[el.id][k]), ajuste: true });
						s[k] = ajustes[el.id][k];
						if (g[k]) delete g[k];
					});
				}
				if (el.elements && el.elements.length) anda(el.elements, prof + 1);
			});
		}
		anda(copia, 0);
		return { elementos: copia, log: log };
	}

	raiz.cetrusBlack = { transformar: transformar, tokens: T, grupos: GRUPOS };
})(typeof window !== 'undefined' ? window : globalThis);
