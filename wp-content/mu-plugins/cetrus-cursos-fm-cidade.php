<?php
/**
 * Plugin Name: Cetrus - cidade dos cursos FM
 * Description: Nas paginas de curso com SKU/codigo iniciado em FM, oculta o seletor de unidade (o Lyceum devolve "UNIDADE SAO PAULO" para turmas de outras cidades) e mostra, logo abaixo da duracao, "Localizacao: Cidade - UF" a partir das cidades marcadas no produto.
 * Version:     1.1.0
 * Author:      Cetrus / Sanar
 *
 * PROBLEMA (13/10/2026)
 * Os cursos FM_* acontecem fora de Sao Paulo (FM_RMM1, FM_IMU1, FM_BTP1, FM_UTM1
 * e FM_MGF1 em Fortaleza; FM_UTE1, FM_UMS1, FM_UPM1 e FM_DGO1 em Manaus), mas a
 * turma deles no Lyceum vem com physicalUnit "UNIDADE SAO PAULO". O shortcode
 * [lyceum_course_selects] do integracao-lyceum-main monta o seletor "Selecione a
 * unidade" com esse valor, e a pagina anunciava Sao Paulo.
 *
 * FONTES DE DADO
 * - Curso FM: meta `_sku` (WooCommerce) ou `v2_codigo_do_curso` (ACF "Codigo do
 *   Curso") comecando com FM. Hoje os dois sao iguais nos 9 produtos FM.
 * - Localizacao: taxonomia `v2_cidade` (metabox "Cidades" do produto), no
 *   formato "Cidade - UF" (Fortaleza - CE). A taxonomia deveria ser estado >
 *   cidade, mas nem sempre e: ha cidades no topo (Teresina, Brasilia) e estados
 *   pendurados em cidade (Piaui sob Teresina). Por isso:
 *   - estado = termo cujo nome e o de uma UF, a nao ser que o pai tenha o mesmo
 *     nome (Sao Paulo e Rio de Janeiro, cidades, dentro do estado homonimo);
 *   - cidade = qualquer outro termo marcado (o mais especifico, se houver dois
 *     na mesma linha: Recife + Boa Viagem -> Boa Viagem);
 *   - UF = o primeiro estado acima da cidade; sem estado acima, o estado marcado
 *     no proprio produto, se for um so.
 *   Sem cidade marcada, aparece o nome do estado. Sem UF, so a cidade.
 *
 * COMO FUNCIONA (so na pagina de um produto FM, nada muda nas demais)
 * 1. A saida de [show-duracao] do produto da pagina ganha um marcador
 *    <span data-cetrus-duracao>. E o unico seletor usado para achar a linha de
 *    duracao, no card do desktop e na aba "Informacoes Basicas" do mobile, sem
 *    depender de texto, id de elemento do Elementor ou posicao no HTML.
 * 2. Logo abaixo da linha de duracao entra uma copia dela (mesmas classes, entao
 *    mesma tipografia e espacamento) com icone de mapa, "Localizacao:" e o
 *    "Cidade - UF".
 * 3. O seletor de unidade some (display:none, sem ocupar espaco). Como o JS do
 *    plugin so libera a turma depois de escolher a unidade e valida
 *    "unidade && turma", a unidade unica ja vem escolhida, o seletor de turma
 *    vem liberado e, ao escolher a turma, a unidade passa a ser a da turma
 *    (data-unit). O formulario HubSpot do popup continua recebendo
 *    contact__ct__unit_site e contact__ct__class_site como antes.
 * 4. Se o JS falhar ou nao rodar, o seletor de unidade volta a aparecer: o
 *    formulario fica como era, nunca travado.
 *
 * REVERTER: apagar este arquivo do servidor E do repositorio.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CETRUS_FM_PREFIXO = 'FM';

/**
 * Codigos do curso cadastrados no produto (SKU e Codigo do Curso), em maiusculas.
 *
 * @param int $produto_id ID do produto.
 * @return string[]
 */
function cetrus_fm_codigos( $produto_id ) {
	$codigos = array();

	foreach ( array( '_sku', 'v2_codigo_do_curso' ) as $meta ) {
		$valor = strtoupper( trim( (string) get_post_meta( $produto_id, $meta, true ) ) );
		if ( '' !== $valor ) {
			$codigos[] = $valor;
		}
	}

	return array_values( array_unique( $codigos ) );
}

/**
 * Diz se o produto e um curso FM (SKU ou Codigo do Curso iniciado em FM).
 *
 * @param int $produto_id ID do produto.
 * @return bool
 */
function cetrus_fm_e_curso_fm( $produto_id ) {
	$e_fm = false;

	foreach ( cetrus_fm_codigos( $produto_id ) as $codigo ) {
		if ( 0 === strpos( $codigo, CETRUS_FM_PREFIXO ) ) {
			$e_fm = true;
			break;
		}
	}

	return (bool) apply_filters( 'cetrus_fm_e_curso_fm', $e_fm, $produto_id );
}

/** Sigla de cada UF, pelo nome do estado sem acento e em minusculas. */
const CETRUS_FM_UFS = array(
	'acre'                => 'AC',
	'alagoas'             => 'AL',
	'amapa'               => 'AP',
	'amazonas'            => 'AM',
	'bahia'               => 'BA',
	'ceara'               => 'CE',
	'distrito federal'    => 'DF',
	'espirito santo'      => 'ES',
	'goias'               => 'GO',
	'maranhao'            => 'MA',
	'mato grosso'         => 'MT',
	'mato grosso do sul'  => 'MS',
	'minas gerais'        => 'MG',
	'para'                => 'PA',
	'paraiba'             => 'PB',
	'parana'              => 'PR',
	'pernambuco'          => 'PE',
	'piaui'               => 'PI',
	'rio de janeiro'      => 'RJ',
	'rio grande do norte' => 'RN',
	'rio grande do sul'   => 'RS',
	'rondonia'            => 'RO',
	'roraima'             => 'RR',
	'santa catarina'      => 'SC',
	'sao paulo'           => 'SP',
	'sergipe'             => 'SE',
	'tocantins'           => 'TO',
);

/**
 * Nome do termo sem entidades HTML (o WordPress grava "&" como "&amp;").
 *
 * @param WP_Term $termo Termo da v2_cidade.
 * @return string
 */
function cetrus_fm_nome( $termo ) {
	return html_entity_decode( $termo->name, ENT_QUOTES, 'UTF-8' );
}

/**
 * Sigla da UF cujo nome e o do termo ("Ceara" -> "CE"), ou ''.
 *
 * @param WP_Term $termo Termo da v2_cidade.
 * @return string
 */
function cetrus_fm_uf_do_nome( $termo ) {
	$chave = trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( remove_accents( cetrus_fm_nome( $termo ) ) ) ) );

	return array_key_exists( $chave, CETRUS_FM_UFS ) ? CETRUS_FM_UFS[ $chave ] : '';
}

/**
 * Sigla da UF se o termo for um estado, ou '' se for uma cidade.
 *
 * Sao Paulo e Rio de Janeiro dentro do estado de mesmo nome sao cidades.
 *
 * @param WP_Term $termo Termo da v2_cidade.
 * @return string
 */
function cetrus_fm_uf_se_estado( $termo ) {
	$uf = cetrus_fm_uf_do_nome( $termo );

	if ( '' !== $uf && $termo->parent ) {
		$pai = get_term( (int) $termo->parent, 'v2_cidade' );
		if ( $pai instanceof WP_Term && cetrus_fm_uf_do_nome( $pai ) === $uf ) {
			return '';
		}
	}

	return $uf;
}

/**
 * Sigla do primeiro estado acima da cidade na hierarquia, ou ''.
 *
 * @param WP_Term $cidade Termo da v2_cidade que nao e estado.
 * @return string
 */
function cetrus_fm_uf_acima( $cidade ) {
	foreach ( get_ancestors( $cidade->term_id, 'v2_cidade', 'taxonomy' ) as $id ) {
		$ancestral = get_term( (int) $id, 'v2_cidade' );
		if ( $ancestral instanceof WP_Term ) {
			$uf = cetrus_fm_uf_se_estado( $ancestral );
			if ( '' !== $uf ) {
				return $uf;
			}
		}
	}

	return '';
}

/**
 * Localizacao do curso no formato "Cidade - UF", lida da v2_cidade do produto.
 *
 * Mais de uma cidade vira "Cidade A - UF, Cidade B - UF". Ver FONTES DE DADO
 * no topo do arquivo para a regra completa.
 *
 * @param int $produto_id ID do produto.
 * @return string Localizacao, ou '' se nada estiver marcado.
 */
function cetrus_fm_localizacao( $produto_id ) {
	$termos = get_the_terms( $produto_id, 'v2_cidade' );
	if ( ! is_array( $termos ) ) {
		$termos = array();
	}

	$cidades = array();
	$estados = array();
	foreach ( $termos as $termo ) {
		$uf = cetrus_fm_uf_se_estado( $termo );
		if ( '' !== $uf ) {
			$estados[ $uf ] = true;
		} else {
			$cidades[ (int) $termo->term_id ] = $termo;
		}
	}

	// Duas cidades na mesma linha da hierarquia: fica a mais especifica.
	foreach ( $cidades as $cidade ) {
		foreach ( get_ancestors( $cidade->term_id, 'v2_cidade', 'taxonomy' ) as $ancestral ) {
			unset( $cidades[ (int) $ancestral ] );
		}
	}

	$uf_marcada = 1 === count( $estados ) ? (string) key( $estados ) : '';
	$partes     = array();

	foreach ( $cidades as $cidade ) {
		$nome = cetrus_fm_nome( $cidade );
		$uf   = cetrus_fm_uf_acima( $cidade );
		if ( '' === $uf ) {
			$uf = $uf_marcada;
		}

		// Nome que ja traz a UF ("Fortaleza - CE", "Fortaleza/CE") nao ganha outra.
		$partes[] = ( '' === $uf || preg_match( '#[\s/-]' . $uf . '$#i', $nome ) ) ? $nome : $nome . ' - ' . $uf;
	}

	if ( ! $partes ) {
		foreach ( $termos as $termo ) {
			$partes[] = cetrus_fm_nome( $termo );
		}
	}

	$localizacao = implode( ', ', array_unique( $partes ) );

	return (string) apply_filters( 'cetrus_fm_localizacao', $localizacao, $produto_id );
}

/**
 * ID do produto FM da pagina atual, ou 0 se a pagina nao for de um curso FM.
 *
 * @return int
 */
function cetrus_fm_produto_da_pagina() {
	static $produto = null;

	if ( null !== $produto ) {
		return $produto;
	}

	// Antes da query principal is_singular() ainda nao sabe responder; nao guarda.
	if ( ! did_action( 'wp' ) ) {
		return 0;
	}

	$produto = 0;
	if ( ! is_admin() && is_singular( 'product' ) ) {
		$id = (int) get_queried_object_id();
		if ( $id > 0 && cetrus_fm_e_curso_fm( $id ) ) {
			$produto = $id;
		}
	}

	return $produto;
}

/**
 * Marca a saida de [show-duracao] do produto da pagina. Os cards de outros
 * produtos (carrossel de relacionados) ficam de fora pelo get_the_ID().
 */
add_filter(
	'do_shortcode_tag',
	function ( $saida, $tag ) {
		if ( 'show-duracao' !== $tag || ! is_string( $saida ) || '' === trim( $saida ) ) {
			return $saida;
		}

		$produto = cetrus_fm_produto_da_pagina();
		if ( ! $produto || (int) get_the_ID() !== $produto || '' === cetrus_fm_localizacao( $produto ) ) {
			return $saida;
		}

		return '<span class="cetrus-fm-duracao" data-cetrus-duracao>' . $saida . '</span>';
	},
	10,
	2
);

/**
 * CSS e sinalizador de JS no <head>: esconde o seletor de unidade desde a
 * primeira pintura (sem piscar nem deslocar o card), mas so enquanto o JS
 * estiver de pe. Se o script do rodape nao confirmar ate o DOMContentLoaded, a
 * classe sai e o seletor volta. As duas regras ficam separadas de proposito:
 * navegador sem :has() descarta a regra inteira em que ele aparece, e a classe
 * posta pelo JS tem que continuar escondendo.
 */
add_action(
	'wp_head',
	function () {
		if ( ! cetrus_fm_produto_da_pagina() ) {
			return;
		}
		?>
<script id="cetrus-curso-fm-head">
document.documentElement.classList.add('cetrus-fm-js');
document.addEventListener('DOMContentLoaded', function () {
	if (!window.cetrusCursoFmOk) document.documentElement.classList.remove('cetrus-fm-js');
});
</script>
<style id="cetrus-curso-fm-css">
[data-lyceum-enrollment-form] .cetrus-fm-unidade-oculta { display: none !important; }
html.cetrus-fm-js [data-lyceum-enrollment-form] div:has(> [data-lyceum-unit-select]) { display: none !important; }
</style>
		<?php
	},
	20
);

/**
 * JS do rodape: libera o seletor de turma, esconde a unidade e insere a cidade.
 */
add_action(
	'wp_footer',
	function () {
		$produto = cetrus_fm_produto_da_pagina();
		if ( ! $produto ) {
			return;
		}

		$config = array(
			'produto'     => $produto,
			'localizacao' => cetrus_fm_localizacao( $produto ),
			'rotulo'      => (string) apply_filters( 'cetrus_fm_rotulo', 'Localização:' ),
		);

		$json = wp_json_encode( $config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( ! is_string( $json ) ) {
			return;
		}

		echo "<script id='cetrus-curso-fm'>\n(function (cfg) {\n" . cetrus_fm_js() . "\n})(" . $json . ");\n</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	5
);

/**
 * Corpo do script do rodape. Recebe `cfg` = {produto, localizacao, rotulo}.
 *
 * @return string
 */
function cetrus_fm_js() {
	return <<<'JS'
	'use strict';
	var raiz = document.documentElement;

	/* Mesmo icone que o card de curso (loop 11204) usa para a cidade. */
	var ICONE = '<svg aria-hidden="true" class="e-font-icon-svg e-fas-map-marker-alt" viewBox="0 0 384 512" xmlns="http://www.w3.org/2000/svg"><path d="M172.268 501.67C26.97 291.031 0 269.413 0 192 0 85.961 85.961 0 192 0s192 85.961 192 192c0 77.413-26.97 99.031-172.268 309.67-9.535 13.774-29.93 13.773-39.464 0zM192 272c44.183 0 80-35.817 80-80s-35.817-80-80-80-80 35.817-80 80 35.817 80 80 80z"></path></svg>';

	function cada(lista, fn) {
		Array.prototype.forEach.call(lista, fn);
	}

	/* Sem acento e sem caixa: "SAO PAULO" == "Sao Paulo". U+0300..U+036F sao os acentos soltos pelo NFD. */
	var ACENTOS = new RegExp('[' + String.fromCharCode(0x300) + '-' + String.fromCharCode(0x36f) + ']', 'g');

	function normaliza(texto) {
		return String(texto || '').normalize('NFD').replace(ACENTOS, '').toLowerCase().replace(/\s+/g, ' ').trim();
	}

	/* 1. Formularios de inscricao: unidade escolhida e oculta, turma liberada. */
	function prepararFormulario(form) {
		if (form.getAttribute('data-cetrus-fm') === '1') return;

		var unidade = form.querySelector('[data-lyceum-unit-select]');
		var turma = form.querySelector('[data-lyceum-course-select]');
		if (!unidade || !turma) return;

		var unidades = [];
		cada(unidade.options, function (opcao) {
			if (opcao.value && unidades.indexOf(opcao.value) === -1) unidades.push(opcao.value);
		});
		var temTurma = Array.prototype.some.call(turma.options, function (opcao) {
			return !!opcao.value;
		});

		if (unidades.length === 1) unidade.value = unidades[0];
		if (temTurma) turma.disabled = false;

		var caixa = unidade.parentElement;
		if (!caixa || caixa === form || caixa.contains(turma)) caixa = unidade;
		caixa.classList.add('cetrus-fm-unidade-oculta');

		form.setAttribute('data-cetrus-fm', '1');
	}

	/* Fase de captura: roda antes do handler do plugin, que valida unidade && turma. */
	document.addEventListener('change', function (evento) {
		var alvo = evento.target;
		if (!alvo || !alvo.matches || !alvo.matches('[data-lyceum-course-select]')) return;
		if (!alvo.closest || !alvo.closest('[data-cetrus-fm="1"]')) return;

		var opcao = alvo.options[alvo.selectedIndex];
		var unidade = opcao ? opcao.getAttribute('data-unit') : '';
		if (!unidade) return;

		cada(document.querySelectorAll('[data-cetrus-fm="1"] [data-lyceum-unit-select]'), function (seletor) {
			seletor.value = unidade;
		});
	}, true);

	/* 2. Linha "Localizacao:" logo abaixo da linha de duracao. */

	/* Ja existe linha cuja informacao e a propria cidade ("Local: Fortaleza",
	   "Fortaleza - CE")? Compara o valor depois do rotulo, nunca um trecho
	   qualquer: "Coordenacao: Dra. Ana Fortaleza" nao conta. */
	function cidadeJaExibida(linha) {
		var alvo = normaliza(cfg.localizacao);
		return Array.prototype.some.call(linha.parentNode.children, function (irmao) {
			if (irmao === linha) return false;
			if (irmao.hasAttribute('data-cetrus-cidade-linha') || irmao.querySelector('[data-cetrus-cidade]')) return true;
			if (!irmao.classList.contains('e-con') || irmao.querySelector('[data-lyceum-enrollment-form]')) return false;
			var texto = normaliza(irmao.textContent);
			var valor = texto.slice(texto.indexOf(':') + 1).trim();
			if (valor.indexOf(alvo) !== 0) return false;
			var resto = valor.slice(alvo.length);
			return resto === '' || /^[^a-z0-9]/.test(resto);
		});
	}

	function inserirCidade(marcador) {
		var linha = marcador.closest('.e-con');
		if (!linha || !linha.parentNode || cidadeJaExibida(linha)) return;

		var nova = linha.cloneNode(true);
		nova.classList.add('cetrus-fm-cidade');
		nova.setAttribute('data-cetrus-cidade-linha', '');

		/* A copia herda as classes (estilo), nao a identidade: sem id, data-id ou
		   data-element_type o Elementor nao liga handlers nela. */
		[nova].concat(Array.prototype.slice.call(nova.querySelectorAll('*'))).forEach(function (el) {
			['id', 'data-id', 'data-element_type', 'data-e-type', 'data-widget_type', 'data-settings'].forEach(function (atributo) {
				el.removeAttribute(atributo);
			});
		});

		cada(nova.querySelectorAll('.elementor-widget-icon .elementor-icon'), function (icone) {
			if (icone.querySelector('svg, i, img')) icone.innerHTML = ICONE;
		});

		var valor = nova.querySelector('[data-cetrus-duracao]');
		valor.removeAttribute('data-cetrus-duracao');
		valor.className = 'cetrus-fm-cidade-valor';
		valor.setAttribute('data-cetrus-cidade', '');
		valor.textContent = cfg.localizacao;

		var widgetValor = valor.closest('.elementor-widget');
		cada(nova.querySelectorAll('.elementor-widget-text-editor'), function (widget) {
			if (widget === widgetValor) return;
			var texto = widget.querySelector('p') || widget;
			texto.textContent = cfg.rotulo;
		});

		/* Rotulo e valor no mesmo paragrafo ("Duracao: 2 dias"), como no mobile. */
		if (widgetValor && widgetValor.classList.contains('elementor-widget-text-editor')) {
			var pai = valor.parentNode;
			while (valor.previousSibling) pai.removeChild(valor.previousSibling);
			while (valor.nextSibling) pai.removeChild(valor.nextSibling);
			pai.insertBefore(document.createTextNode(cfg.rotulo + ' '), valor);
		}

		linha.parentNode.insertBefore(nova, linha.nextSibling);
	}

	function aplicar() {
		try {
			cada(document.querySelectorAll('[data-lyceum-enrollment-form]'), prepararFormulario);
			window.cetrusCursoFmOk = true;
		} catch (erro) {
			raiz.classList.remove('cetrus-fm-js');
			if (window.console) console.warn('[cetrus-curso-fm] formulario', erro);
		}

		try {
			if (cfg.localizacao) cada(document.querySelectorAll('[data-cetrus-duracao]'), inserirCidade);
		} catch (erro) {
			if (window.console) console.warn('[cetrus-curso-fm] cidade', erro);
		}
	}

	aplicar();
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', aplicar);
JS;
}
