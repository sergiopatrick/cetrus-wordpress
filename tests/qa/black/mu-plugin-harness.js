/*
 * Teste do mu-plugin cetrus-black-november-2026.php SEM PHP local (PHP 8.3 em WebAssembly).
 *
 * Ultima rodada: 23/10/2026, 17 casos, 13 verificados OK + 4 informativos; MD5 143d0675...5ac8
 * (= arquivo do repositorio = snippet 116). Atalho usado em 23/10: rodar na aba do wp-admin do
 * staging (o CSP permite o jsDelivr) e montar o base64 a partir de '<?php\n' + code do snippet
 * 116 lido pela REST, sem colar o plugin.
 * Como rodar (22/10/2026: 13 casos, 11 verificados OK + 2 informativos; MD5 fed39b40...):
 * Dica: declarar como window.cetrusHarness = async function (b64) {...} se o console
 * nao expuser funcoes declaradas no escopo global.
 * 1. Abrir https://cdn.jsdelivr.net/npm/@php-wasm/web-8-3@3.1.55/ em um navegador.
 * 2. Gerar o base64 do plugin (PowerShell):
 *      [Convert]::ToBase64String([IO.File]::ReadAllBytes('wp-content\mu-plugins\cetrus-black-november-2026.php'))
 *    e o MD5 para conferir: Get-FileHash -Algorithm MD5 <arquivo>
 * 3. No console da pagina: colar este arquivo inteiro, depois
 *      await cetrusHarness('<base64>')
 *
 * Os stubs imitam so o que o plugin usa do WordPress; o filtro do Elementor Pro
 * NAO e exercitado de verdade aqui (isso so no staging, teste 6 do documento).
 */
async function cetrusHarness(b64) {
	const base = 'https://cdn.jsdelivr.net/npm/@php-wasm/web-8-3@3.1.55/asyncify/';
	let src = await fetch(base + 'php_8_3.js').then((r) => r.text());
	src = src.replace("import dependencyFilename from './8_3_33/php_8_3.wasm';", "const dependencyFilename = '" + base + "8_3_33/php_8_3.wasm';");
	const loader = await import(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
	const uni = await import('https://cdn.jsdelivr.net/npm/@php-wasm/universal@3.1.55/+esm');
	const php = new uni.PHP(await uni.loadPHPRuntime(loader, {}));
	await php.writeFile('/tmp/p.b64', b64);

	const stubs = `<?php
define('ABSPATH','/'); define('MINUTE_IN_SECONDS',60); define('WPMU_PLUGIN_URL','https://x/wp-content/mu-plugins'); define('WPMU_PLUGIN_DIR','/tmp/mu');
@mkdir('/tmp/mu/cetrus-black-november-2026', 0777, true); file_put_contents('/tmp/mu/cetrus-black-november-2026/black.css', '/* */');
$GLOBALS['F']=[]; $GLOBALS['OPT']=[]; $GLOBALS['LOGADO']=false; $GLOBALS['PODE']=false; $GLOBALS['ESTILOS']=[];
function add_filter($h,$cb,$p=10,$a=1){ $GLOBALS['F'][$h][]=$cb; } function add_action($h,$cb,$p=10,$a=1){ $GLOBALS['F'][$h][]=$cb; }
function apply_filters($h,$v){ return $v; } function run_filters($h,$v){ foreach(($GLOBALS['F'][$h]??[]) as $cb){ $v=$cb($v);} return $v; }
function get_option($k,$d=false){ return $GLOBALS['OPT'][$k]??$d; } function update_option($k,$v,$a=null){ $GLOBALS['OPT'][$k]=$v; return true; }
function did_action($h){ return $GLOBALS['ACOES'][$h] ?? 1; } function is_admin(){ return false; } function wp_doing_ajax(){ return false; } function is_feed(){ return false; }
function is_user_logged_in(){ return $GLOBALS['LOGADO']; } function current_user_can($c){ return $GLOBALS['PODE']; } function wp_unslash($v){ return $v; }
function get_transient($k){ return false; } function set_transient($k,$v,$t){ return true; } function delete_transient($k){ return true; }
$GLOBALS['META']=[900=>'product',901=>'header',902=>'product',903=>'header',904=>'product',905=>'page',12073=>'product',11545=>'header',19225=>'product',10946=>'wp-page'];
function get_post_meta($id,$k,$s){ return $GLOBALS['META'][$id]??''; }
function get_post_type($id){ if($id===10946) return 'page'; return in_array($id,[12073,11545,19225],true)?'elementor_library':false; }
function wp_enqueue_style($h,$u,$d,$v){ $GLOBALS['ESTILOS'][]=$u; }
$GLOBALS['SC']=[]; function add_shortcode($n,$cb){ $GLOBALS['SC'][$n]=$cb; } function shortcode_atts($p,$a,$n=''){ return array_merge($p,(array)$a); }
function is_singular($t=''){ return $GLOBALS['SINGULAR']??false; } function get_the_terms($id,$tax){ return $GLOBALS['TERMOS']??[]; }
function has_term($t,$tax,$p=null){ foreach(($GLOBALS['TERMOS']??[]) as $o){ if(in_array($o->slug,(array)$t,true)||in_array($o->term_id,(array)$t,true)) return true; } return false; }
function sanitize_html_class($c){ return preg_replace('/[^A-Za-z0-9_-]/','',$c); } function esc_html($s){ return htmlspecialchars($s); } function esc_attr($s){ return htmlspecialchars($s); }
function is_search(){ return false; } function is_post_type_archive($t=''){ return false; } function is_tax($t='',$x=''){ return in_array($GLOBALS['TAX'] ?? '', (array)$t, true); }
$GLOBALS['QID']=10946;
function is_page(){ return true; } function get_queried_object_id(){ return $GLOBALS['QID']; } function in_the_loop(){ return true; } function is_main_query(){ return true; } function get_the_ID(){ return $GLOBALS['QID']; }
function run_filters2($h,$v,$a){ foreach(($GLOBALS['F'][$h]??[]) as $cb){ $v=$cb($v,$a);} return $v; }
eval('namespace Elementor; class Plugin { public static $instance; public $frontend; } class FakeFront { function get_builder_content_for_display($id){ return "<div data-elementor-id=\\"".$id."\\">black</div>"; } } Plugin::$instance = new Plugin(); Plugin::$instance->frontend = new FakeFront();');
class FakeDB { public $posts='wp_posts'; function esc_like($s){ return addcslashes($s,'_%\\\\'); } function prepare($q,...$a){ return $q; }
 function get_results($q){ $r=[]; foreach([[900,'[BLACK-2026] #12073 Produto','publish'],[901,'[BLACK-2026] #11545 Header','draft'],[902,'[BLACK-2026] sem id','publish'],[903,'[BLACK-2026] #19225 tipo errado','publish'],[904,'[BLACK-2026] #12073 duplicado','publish'],[905,'[BLACK-2026] #10946 Home Black','publish']] as $x){ $o=new stdClass; $o->ID=$x[0]; $o->post_title=$x[1]; $o->post_status=$x[2]; $o->post_modified='2026-10-22 10:00:00'; $r[]=$o; } return $r; } }
$wpdb = new FakeDB();
$c = base64_decode(file_get_contents('/tmp/p.b64'));
token_get_all($c, TOKEN_PARSE);
eval('?>' . $c);
`;
	await php.writeFile('/tmp/stubs.php', stubs);

	const F = "'elementor/theme/get_location_templates/template_id'";
	const casos = {
		lint_md5: ["echo md5(base64_decode(file_get_contents('/tmp/p.b64')));", null],
		ligado: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; echo cetrus_black_ativo()?'ligada':'desligada';", 'ligada'],
		desligado: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='desligado'; echo cetrus_black_ativo()?'ligada':'desligada';", 'desligada'],
		// 28/10 (0.3.0): sem virada automatica; padrao e valor antigo "auto" = desligado
		padrao_desligado: ["echo cetrus_black_ativo()?'ligada':'desligada';", 'desligada'],
		auto_legado_desligado: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='auto'; echo cetrus_black_ativo()?'ligada':'desligada';", 'desligada'],
		previa_editor: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='desligado'; $_GET['cetrus_black']='1'; $GLOBALS['LOGADO']=true; $GLOBALS['PODE']=true; echo cetrus_black_ativo()?'ligada':'desligada';", 'ligada'],
		previa_anonimo: ["$_GET['cetrus_black']='1'; echo cetrus_black_ativo()?'ligada':'desligada';", null],
		editor_elementor: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $_GET['elementor-preview']='123'; echo cetrus_black_ativo()?'ligada':'desligada';", 'desligada'],
		mapa: ["$d=cetrus_black_mapa(true); echo json_encode($d['mapa']),' ',count($d['alertas']);", '{"12073":904,"10946":905} 4'],
		pagina_ligada: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; echo run_filters('the_content','ORIGINAL'),' | ',count(run_filters2('elementor/frontend/builder_content_data',[1,2],10946)),',',count(run_filters2('elementor/frontend/builder_content_data',[1,2],905));", '<div data-elementor-id="905">black</div> | 0,2'],
		pagina_desligada: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='desligado'; echo run_filters('the_content','ORIGINAL'),' | ',count(run_filters2('elementor/frontend/builder_content_data',[1,2],10946));", 'ORIGINAL | 2'],
		pagina_sem_clone: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['QID']=11768; echo run_filters('the_content','ORIGINAL');", 'ORIGINAL'],
		troca: [`$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; echo run_filters(${F},12073),',',run_filters(${F},11545),',',run_filters(${F},99),' ',implode(',',run_filters('body_class',['x']));`, '904,11545,99 x,cetrus-black-2026,cetrus-black-conteudo'],
		troca_desligada: [`$GLOBALS['OPT']['cetrus_black_2026_modo']='desligado'; echo run_filters(${F},12073),' ',implode(',',run_filters('body_class',['x']));`, '12073 x'],
		// 23/10: o 1o filtro do the_content (prioridade 1) zera o texto reserva da pagina com modelo Black
		conteudo_reserva_zerado: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; var_export(($GLOBALS['F']['the_content'][0])('[cetrus_newsletter]'));", "''"],
		conteudo_reserva_desligado: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='desligado'; var_export(($GLOBALS['F']['the_content'][0])('[cetrus_newsletter]'));", "'[cetrus_newsletter]'"],
		classe_modalidade: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['SINGULAR']=true; $o=new stdClass; $o->slug='pos-graduacao'; $o->term_id=147; $GLOBALS['TERMOS']=[$o]; echo implode(',',run_filters('body_class',['x']));", null],
		conteudo_nao_black: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['QID']=11768; echo implode(',',run_filters('body_class',['x']));", 'x'],
		// 28/10: telas que ficam iguais a producao (cetrus_black_tela_fora)
		graduacao_fora: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['SINGULAR']=true; $o=new stdClass; $o->slug='graduacao'; $o->term_id=149; $GLOBALS['TERMOS']=[$o]; echo run_filters('elementor/theme/get_location_templates/template_id',12073),' ',implode(',',run_filters('body_class',['x']));", '12073 x'],
		fellowship_v2_fora: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['SINGULAR']=true; $o=new stdClass; $o->slug='teste-fellow'; $o->term_id=1262; $GLOBALS['TERMOS']=[$o]; echo run_filters('elementor/theme/get_location_templates/template_id',12073);", '12073'],
		especialidade_fora: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['TAX']='v2_especialidade'; echo run_filters('elementor/theme/get_location_templates/template_id',12073);", '12073'],
		modalidade_dentro: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['TAX']='v2_modalidade'; $GLOBALS['QID']=11768; echo run_filters('elementor/theme/get_location_templates/template_id',12073);", '904'],
		pagina_antes_do_wp: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; $GLOBALS['ACOES']=['wp'=>0]; $GLOBALS['QID']=11768; echo cetrus_black_na_tela()?'indefinido-segue':'fora';", 'indefinido-segue'],
		shortcodes: ["$GLOBALS['OPT']['cetrus_black_2026_modo']='ligado'; echo implode(',',array_keys($GLOBALS['SC']));", null]
	};
	const out = {};
	for (const [nome, [codigo, esperado]] of Object.entries(casos)) {
		const r = await php.run({ code: `<?php require '/tmp/stubs.php'; ${codigo}` });
		const t = (r.text || '').trim();
		out[nome] = (esperado === null ? 'INFO ' : (t === esperado ? 'OK   ' : 'FALHA ')) + t + (r.errors ? ' ERR:' + r.errors : '');
	}
	return out;
}
