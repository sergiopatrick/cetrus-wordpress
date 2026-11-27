/*
 * Teste do mu-plugin cetrus-lyceum-vagas-esgotadas.php (e da leitura fresca do
 * cetrus-lyceum-api-cache.php) SEM PHP local: PHP 8.3 em WebAssembly, com as classes
 * REAIS do integracao-lyceum-main (interface, registro e Lyceum_CourseProcessor).
 *
 * Como rodar (27/11/2026, aba do wp-admin do cetrus.com.br, que permite o jsDelivr):
 * 1. Ler os fontes do plugin pelo editor de plugins (so leitura):
 *      /wp-admin/plugin-editor.php?plugin=integracao-lyceum-main/integracao-lyceum.php&file=<arquivo>
 *    -> textarea #newcontent de
 *      includes/processors/interface-response-processor.php
 *      includes/processors/class-response-processor-registry.php
 *      includes/processors/class-course-processor.php
 * 2. Colar este arquivo no console e rodar:
 *      await cetrusVagasHarness({ iface, registry, processor, vagas, cache })
 *    (vagas/cache = conteudo dos dois mu-plugins deste repositorio).
 *
 * Dados de teste = turmas reais de PG_DOR2 lidas da products-api em 27/11/2026
 * (02/02/2027 SP com 30/30 vagas).
 */
window.cetrusVagasHarness = async function (fontes) {
	const base = 'https://cdn.jsdelivr.net/npm/@php-wasm/web-8-3@3.1.55/asyncify/';
	let src = await fetch(base + 'php_8_3.js').then((r) => r.text());
	src = src.replace("import dependencyFilename from './8_3_33/php_8_3.wasm';", "const dependencyFilename = '" + base + "8_3_33/php_8_3.wasm';");
	const loader = await import(URL.createObjectURL(new Blob([src], { type: 'text/javascript' })));
	const uni = await import('https://cdn.jsdelivr.net/npm/@php-wasm/universal@3.1.55/+esm');
	const php = new uni.PHP(await uni.loadPHPRuntime(loader, {}));

	for (const [nome, codigo] of Object.entries(fontes)) {
		await php.writeFile('/tmp/' + nome + '.php', codigo);
	}

	const stubs = `<?php
define('ABSPATH','/'); define('MINUTE_IN_SECONDS',60); define('HOUR_IN_SECONDS',3600);
$GLOBALS['F']=[]; $GLOBALS['OPT']=['lyceum_api_key'=>'k','lyceum_api_url'=>'https://kong.app-prod.sanar.cloud/products-api/v1/product']; $GLOBALS['TR']=[]; $GLOBALS['ROTAS']=[]; $GLOBALS['HTTP']=[];
function add_filter($h,$cb,$p=10,$a=1){ $GLOBALS['F'][$h][$p][]=$cb; } function add_action($h,$cb,$p=10,$a=1){ add_filter($h,$cb,$p,$a); }
function apply_filters($h,$v,...$x){ $l=$GLOBALS['F'][$h]??[]; ksort($l); foreach($l as $cbs) foreach($cbs as $cb){ $v=$cb($v,...$x);} return $v; }
function do_action($h,...$x){ } function did_action($h){ return 1; }
function __($s,$d=''){ return $s; } function get_option($k,$d=false){ return $GLOBALS['OPT'][$k]??$d; }
function get_transient($k){ return $GLOBALS['TR'][$k]??false; } function set_transient($k,$v,$t){ $GLOBALS['TR'][$k]=$v; return true; }
function wp_json_encode($d){ return json_encode($d); } function esc_html($s){ return htmlspecialchars((string)$s, ENT_QUOTES); } function esc_url_raw($u){ return $u; }
function rest_url($p){ return 'https://cetrus.com.br/wp-json/'.$p; } function wp_parse_url($u,$c=-1){ return parse_url($u,$c); }
function remove_accents($s){ return strtr($s,['Ã'=>'A','ã'=>'a','Á'=>'A','á'=>'a','Â'=>'A','â'=>'a','É'=>'E','é'=>'e','Ê'=>'E','ê'=>'e','Í'=>'I','í'=>'i','Ó'=>'O','ó'=>'o','Õ'=>'O','õ'=>'o','Ô'=>'O','ô'=>'o','Ú'=>'U','ú'=>'u','Ç'=>'C','ç'=>'c']); }
class WP_Error { public $m; function __construct($c='',$m=''){ $this->m=$m; } function get_error_message(){ return $this->m; } }
function is_wp_error($x){ return $x instanceof WP_Error; }
class WP_REST_Response { public $data; public $status; public $headers=[]; function __construct($d=null,$s=200){ $this->data=$d; $this->status=$s; } function header($k,$v){ $this->headers[$k]=$v; } }
class FakeReq { public $p; function __construct($p){ $this->p=$p; } function get_param($k){ return $this->p[$k]??null; } }
function register_rest_route($ns,$r,$a){ $GLOBALS['ROTAS'][$ns.$r]=$a; }
function wp_remote_retrieve_response_code($r){ return is_array($r) ? ($r['response']['code']??0) : 0; } function wp_remote_retrieve_body($r){ return is_array($r) ? ($r['body']??'') : ''; }
/* HTTP falso com a mesma ordem do WordPress: pre_http_request curto-circuita; senao "rede" + http_response. */
function wp_remote_get($url,$args=[]){
  $args += ['method'=>'GET'];
  $pre = apply_filters('pre_http_request', false, $args, $url);
  if ($pre !== false) { $GLOBALS['HTTP'][]='cache'; return $pre; }
  $GLOBALS['HTTP'][]='rede';
  $r = ['headers'=>[], 'body'=>json_encode($GLOBALS['API']), 'response'=>['code'=>200,'message'=>'OK'], 'cookies'=>[], 'filename'=>null];
  return apply_filters('http_response', $r, $args, $url);
}
function t($id,$cid,$ini,$fim,$a,$s,$u,$st='ACTIVE'){ return ['packAcademics'=>['lyceum'=>['id'=>$id,'course'=>['id'=>$cid,'name'=>'Intervencao em Dor'],'status'=>$st,'startDate'=>$ini.'T03:00:00.000Z','endDate'=>$fim.'T03:00:00.000Z','seats'=>['amount'=>$a,'sold'=>$s],'physicalUnit'=>['name'=>$u]]]]; }
$SP='UNIDADE SÃO PAULO'; $RC='UNIDADE RECIFE';
$GLOBALS['DOR2'] = [
  t('PG_DOR2.RC1.2703.1','PG_DOR2','2027-03-08','2028-01-16',20,6,$RC),
  t('PG_DOR2.RC1.2707.1','PG_DOR2','2027-07-26','2028-05-21',18,6,$RC),
  t('PG_DOR2.SP1.2702.1','PG_DOR2','2027-02-02','2027-10-03',30,30,$SP),
  t('PG_DOR2.SP1.2703.1','PG_DOR2','2027-03-14','2027-11-28',30,17,$SP),
  t('PG_DOR2.SP1.2705.1','PG_DOR2','2027-05-23','2028-01-16',30,1,$SP),
  t('PG_DOR2.SP1.2707.1','DOR2','2027-07-04','2028-04-23',30,1,$SP),
  t('PG_DOR2.SP1.2709.1','DOR2','2027-09-05','2028-12-31',30,0,$SP),
];
foreach (['iface','registry','processor'] as $n) { $c=file_get_contents('/tmp/'.$n.'.php'); token_get_all($c, TOKEN_PARSE); eval('?>'.$c); }
Lyceum_ResponseProcessorRegistry::get_instance()->register('FE_', new class implements Lyceum_ResponseProcessorInterface { function supports($c){ return strpos((string)$c,'FE_')===0; } function process($r,$l,$c=''){ return ['fellowship'=>true]; } });
Lyceum_ResponseProcessorRegistry::get_instance()->register('COURSE_', new Lyceum_CourseProcessor());
foreach (['cache','vagas'] as $n) { $c=file_get_contents('/tmp/'.$n.'.php'); token_get_all($c, TOKEN_PARSE); eval('?>'.$c); }
function proc($codigo,$dados){ $p=Lyceum_ResponseProcessorRegistry::get_instance()->get_processor($codigo); $mn=substr($codigo,strrpos($codigo,'_')+1); return $p->process($dados,$mn,$codigo); }
function linhas($r){ return implode(';', array_map(function($t){ return substr($t['startDate'],0,10).'/'.(strpos($t['city'],'RECIFE')!==false?'RC':'SP').'/'.$t['seats']['amount'].'-'.$t['seats']['sold'].($t['esgotada']?'/ESG':''); }, $r['turmas'])); }
`;
	await php.writeFile('/tmp/stubs.php', stubs);

	const HTML = '<select class=\\"custom-select course-select\\" data-lyceum-course-select disabled>\\n<option value=\\"\\" disabled selected>Selecione a turma</option>\\n<option data-unit=\\"UNIDADE RECIFE\\" value=\\"PG_DOR2.RC1.2703.1\\">\\n   08/03/2027 a 16/01/2028   </option>\\n<option data-unit=\\"UNIDADE SÃO PAULO\\" value=\\"PG_DOR2.SP1.2702.1\\">\\n   02/02/2027 a 03/10/2027   </option>\\n</select>';
	const casos = {
		envelope_instalado: ["echo get_class(Lyceum_ResponseProcessorRegistry::get_instance()->get_processor('PG_DOR2')) === 'Lyceum_CourseProcessor' ? 'original' : 'envelope';", 'envelope'],
		fellowship_intocado: ["echo json_encode(proc('FE_GR49', $GLOBALS['DOR2']));", '{"fellowship":true}'],
		// Pedido 1 (validado 27/11): SP = 3 com vaga (14/03, 23/05, 04/07) + 02/02 esgotada visivel; 05/09 sai. Recife intacto.
		dor2_limite_e_esgotada: ["echo linhas(proc('PG_DOR2', $GLOBALS['DOR2']));", '2027-02-02/SP/30-30/ESG;2027-03-08/RC/20-6;2027-03-14/SP/30-17;2027-05-23/SP/30-1;2027-07-04/SP/30-1;2027-07-26/RC/18-6'],
		dor2_registro: ["proc('PG_DOR2', $GLOBALS['DOR2']); $r=&cetrus_vagas_registro(); echo $r['curso'],' ',implode(',',array_keys(cetrus_vagas_ids_esgotados())),' ',$r['turmas'][0]['data'];", 'PG_DOR2 PG_DOR2.SP1.2702.1 02/02/2027'],
		// Pedido 2 em outro curso (sem limite): esgotada aparece, as demais intactas
		outro_curso: ["$d=[t('GO_COLP.SP1.2612.1','GO_COLP','2026-12-14','2027-01-01',28,28,'UNIDADE SÃO PAULO'), t('GO_COLP.SP1.2701.1','GO_COLP','2027-01-20','2027-02-01',28,3,'UNIDADE SÃO PAULO')]; echo linhas(proc('GO_COLP',$d));", '2026-12-14/SP/28-28/ESG;2027-01-20/SP/28-3'],
		vendida_acima: ["$d=[t('US_GIO1.SP1.2701.1','US_GIO1','2027-01-04','2027-02-01',31,32,'UNIDADE SÃO PAULO')]; $r=proc('US_GIO1',$d); echo linhas($r),' ',var_export($r['available'],true);", '2027-01-04/SP/31-32/ESG true'],
		todas_esgotadas_continuam_visiveis: ["$d=[t('X_AB1.SP1.1','X_AB1','2027-01-04','2027-02-01',10,10,'UNIDADE SÃO PAULO'), t('X_AB1.SP1.2','X_AB1','2027-03-04','2027-04-01',10,11,'UNIDADE SÃO PAULO')]; $r=proc('X_AB1',$d); echo count($r['turmas']),' ',var_export($r['available'],true);", '2 true'],
		// O que o plugin ja descartava continua descartado
		sem_capacidade_fica_fora: ["$d=[t('X_AB1.SP1.1','X_AB1','2027-01-04','2027-02-01',0,0,'UNIDADE SÃO PAULO')]; $r=proc('X_AB1',$d); echo count($r['turmas']),' ',var_export($r['available'],true);", '0 false'],
		inativa_fica_fora: ["$d=[t('X_AB1.SP1.1','X_AB1','2027-01-04','2027-02-01',10,10,'UNIDADE SÃO PAULO','INACTIVE')]; echo count(proc('X_AB1',$d)['turmas']);", '0'],
		monitoria_e_fellowship_fora: ["$d=[t('MV_AB1.SP1.1','X_AB1','2027-01-04','2027-02-01',10,10,'UNIDADE SÃO PAULO'), t('FE_AB1.SP.1','X_AB1','2027-01-04','2027-02-01',4,4,'UNIDADE SÃO PAULO')]; echo count(proc('X_AB1',$d)['turmas']);", '0'],
		outro_course_id_fora: ["$d=[t('PG_XYZ1.SP1.1','PG_XYZ1','2027-01-04','2027-02-01',10,10,'UNIDADE SÃO PAULO')]; echo count(proc('PG_DOR2',$d)['turmas']);", '0'],
		vagas_reais_no_lyceum: ["$r=proc('PG_DOR2', $GLOBALS['DOR2']); $s=[]; foreach($r['lyceum'] as $i){ $l=$i['packAcademics']['lyceum']; $s[]=$l['seats']['sold']; } sort($s); echo implode(',',$s),' ',count($r['lyceum']);", '1,1,6,6,17,30 6'],
		dados_originais_intocados: ["$d=$GLOBALS['DOR2']; proc('PG_DOR2',$d); echo $d[2]['packAcademics']['lyceum']['seats']['sold'];", '30'],
		// Seletor
		opcao_marcada: [`proc('PG_DOR2', $GLOBALS['DOR2']); echo apply_filters('do_shortcode_tag', "${HTML}", 'lyceum_course_selects', [], []);`, null],
		opcao_disabled: [`proc('PG_DOR2', $GLOBALS['DOR2']); $h=apply_filters('do_shortcode_tag', "${HTML}", 'lyceum_course_selects', [], []); echo substr_count($h,'data-cetrus-esgotada="1"'),' ',(strpos($h,'02/02/2027 a 03/10/2027 - Vagas esgotadas</option>')!==false?'rotulo':'sem-rotulo'),' ',(strpos($h,'value="PG_DOR2.SP1.2702.1" disabled')!==false?'disabled':'nao');`, '1 rotulo disabled'],
		outro_shortcode_intocado: [`proc('PG_DOR2', $GLOBALS['DOR2']); echo apply_filters('do_shortcode_tag', "${HTML}", 'lyceum_course_button', [], []) === "${HTML}" ? 'igual' : 'mudou';`, 'igual'],
		// Checagem ao vivo (REST) + leitura fresca do cache
		rest_esgotada: ["$GLOBALS['API']=$GLOBALS['DOR2']; foreach($GLOBALS['F']['rest_api_init'] as $cbs) foreach($cbs as $cb) $cb(); $cb=$GLOBALS['ROTAS']['cetrus/v1/turma-vagas']['callback']; $r=$cb(new FakeReq(['curso'=>'PG_DOR2','turma'=>'pg_dor2.sp1.2702.1'])); echo $r->status,' ',json_encode([$r->data['encontrada'],$r->data['esgotada'],$r->data['esgotadas']]),' ',$r->headers['Cache-Control'];", '200 [true,true,["PG_DOR2.SP1.2702.1"]] no-store, no-cache, must-revalidate, max-age=0'],
		rest_com_vaga: ["$GLOBALS['API']=$GLOBALS['DOR2']; $r=cetrus_vagas_rest_verificar(new FakeReq(['curso'=>'PG_DOR2','turma'=>'PG_DOR2.SP1.2703.1'])); echo $r->status,' ',json_encode([$r->data['encontrada'],$r->data['esgotada']]);", '200 [true,false]'],
		rest_parametro_ruim: ["$r=cetrus_vagas_rest_verificar(new FakeReq(['curso'=>'PG_DOR2\\'','turma'=>'x'])); echo $r->status;", '400'],
		rest_fellowship_recusado: ["$r=cetrus_vagas_rest_verificar(new FakeReq(['curso'=>'FE_GR49','turma'=>'FE_GR49.SP.271'])); echo $r->status;", '400'],
		rest_limite_por_ip: ["$GLOBALS['API']=$GLOBALS['DOR2']; $s=[]; for($i=0;$i<42;$i++){ $s[]=cetrus_vagas_rest_verificar(new FakeReq(['curso'=>'PG_DOR2','turma'=>'PG_DOR2.SP1.2703.1']))->status; } echo implode(',',array_slice($s,38));", '200,200,429,429'],
		rest_ignora_cache: ["$GLOBALS['API']=$GLOBALS['DOR2']; $url=$GLOBALS['OPT']['lyceum_api_url'].'?'.http_build_query(['skip'=>0,'take'=>50,'filter'=>json_encode(['status'=>'PUBLISHED','lyceumClassId'=>['DOR2']])]); wp_remote_get($url); $GLOBALS['API'][3]['packAcademics']['lyceum']['seats']['sold']=30; $h1=wp_remote_get($url); $r=cetrus_vagas_rest_verificar(new FakeReq(['curso'=>'PG_DOR2','turma'=>'PG_DOR2.SP1.2703.1'])); $h2=wp_remote_get($url); $b=json_decode($h2['body'],true); echo implode(',',$GLOBALS['HTTP']),' ',json_encode($r->data['esgotada']),' ',($b[3]['packAcademics']['lyceum']['seats']['sold']===30?'cache-renovado':'cache-velho'),' ',var_export(cetrus_lyapi_leitura_fresca(),true);", 'rede,cache,rede,cache true cache-renovado false'],
		rest_api_fora: ["$GLOBALS['OPT']['lyceum_api_key']=''; $r=cetrus_vagas_rest_verificar(new FakeReq(['curso'=>'PG_DOR2','turma'=>'PG_DOR2.SP1.2703.1'])); echo $r->status,' ',$r->data['erro'];", '503 lyceum']
	};
	const out = {};
	for (const [nome, [codigo, esperado]] of Object.entries(casos)) {
		const r = await php.run({ code: `<?php require '/tmp/stubs.php'; ${codigo}` });
		const t = (r.text || '').trim();
		out[nome] = (esperado === null ? 'INFO ' : (t === esperado ? 'OK   ' : 'FALHA ')) + t + (r.errors ? ' ERR:' + r.errors : '');
	}
	return out;
};
