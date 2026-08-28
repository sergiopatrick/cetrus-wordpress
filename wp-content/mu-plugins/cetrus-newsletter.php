<?php
/**
 * Plugin Name: Cetrus - Newsletter
 * Description: Bloco de inscricao na newsletter (HubSpot 5259dfdc), com campos ocultos populados e evento proprio no dataLayer.
 * Version:     1.0.0
 * Author:      Cetrus / Sanar
 *
 * POR QUE TODO O CODIGO VIVE AQUI
 * O deploy versionado do site cobre apenas wp-content/mu-plugins/. Code Snippets e Elementor
 * Custom Code funcionam, mas vivem no banco e ninguem revisa. No Elementor entra so o slot:
 * um widget de shortcode com [cetrus_newsletter].
 *
 * TRES ARMADILHAS DESTE SITE QUE O CODIGO ABAIXO EVITA
 *
 * 1. IDENTIFICADOR EXCLUSIVO. A home ja serve TRES divs com id="hubspot-forms" duplicado
 *    (popups 29363, 14050 e 20500) e duas macros do GTM leem essa classe tratando apenas os
 *    casos de 1 e 2 elementos. Um quarto elemento com esse identificador degrada o parametro
 *    'carreira' dos leads de curso. Aqui o alvo e #cetrus-newsletter-alvo, e no onFormReady
 *    removemos a classe .hbspt-form que a lib do HubSpot injeta.
 *
 * 2. CAMPOS OCULTOS. O embed que o CRM entregou e cru: nao popula nada. Sem isto, todo inscrito
 *    entra sem unidade de negocio e sem origem, e nao da para segmentar nem atribuir depois.
 *
 * 3. EVENTO PROPRIO. O listener do GTM escuta QUALQUER hsFormCallback e empurra
 *    'novo_cadastro_site_generico', que aciona GA4, Meta e a conversao Google Ads AW-1036772166.
 *    Empurramos 'cetrus_newsletter_submit' com o GUID para o GTM poder criar a excecao.
 *    ENQUANTO A EXCECAO NAO EXISTIR NO GTM, cada inscricao conta como lead de curso.
 */

if (!defined('ABSPATH')) exit;

define('CETRUS_NEWS_OPT', 'cetrus_newsletter');

function cetrus_news_config() {
    return wp_parse_args((array) get_option(CETRUS_NEWS_OPT, []), [
        'enabled'   => 0,                                        // 0 = so preview por query string
        'portal_id' => '9321751',
        'region'    => 'na1',
        'form_id'   => '5259dfdc-18fa-4c1f-94ea-3b34ef59a3ca',
        'slots'     => ['home' => 1, 'rodape' => 0, 'produto' => 0],
    ]);
}

/** Visivel para o publico so com enabled=1; ?cetrus_newsletter=1 libera o preview em producao. */
function cetrus_news_ativo($slot) {
    $c = cetrus_news_config();
    if (empty($c['slots'][$slot])) return false;
    if (!empty($c['enabled'])) return true;
    return isset($_GET['cetrus_newsletter']);
}

function cetrus_news_lib() {
    if (wp_script_is('cetrus-hsforms', 'enqueued')) return;
    wp_enqueue_script('cetrus-hsforms', 'https://js.hsforms.net/forms/embed/v2.js', [], null, true);
}

/**
 * Le as UTMs da sessao. O Code Snippet 90 ja persiste UTM em cookie neste site;
 * caimos para a query string quando o cookie nao existe.
 */
function cetrus_news_utms() {
    $out = [];
    foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content'] as $k) {
        $v = '';
        if (isset($_COOKIE[$k]))      $v = $_COOKIE[$k];
        elseif (isset($_GET[$k]))     $v = $_GET[$k];
        $out[$k] = sanitize_text_field(wp_unslash($v));
    }
    return $out;
}

function cetrus_news_render($atts = []) {
    $a = shortcode_atts(['slot' => 'home', 'titulo' => '', 'texto' => '', 'cabecalho' => 'sim'], $atts, 'cetrus_newsletter');
    $slot = sanitize_key($a['slot']);
    if (!cetrus_news_ativo($slot)) return '';

    static $ja = [];
    if (isset($ja[$slot])) return '';   // uma vez por slot por pagina
    $ja[$slot] = true;

    $c = cetrus_news_config();
    if (empty($c['form_id'])) return '';

    cetrus_news_lib();

    $titulo = $a['titulo'] !== '' ? $a['titulo'] : 'Assine a newsletter do Cetrus';
    $texto  = $a['texto']  !== '' ? $a['texto']  : 'Receba informações sobre novos cursos, eventos e conteúdos do seu interesse.';

    $pagina = is_singular('product') ? 'produto' : 'conteudo';
    $dados  = [
        'portalId'    => (string) $c['portal_id'],
        'formId'      => (string) $c['form_id'],
        'region'      => (string) $c['region'],
        'bait'        => 'newsletter_' . $slot,
        'pageType'    => $pagina,
        'businessUnit'=> 'cetrus',
        'utms'        => cetrus_news_utms(),
    ];

    ob_start(); ?>
<div class="cetrus-newsletter<?php echo $a['cabecalho'] === 'nao' ? ' cetrus-newsletter--so-form' : ''; ?>" data-slot="<?php echo esc_attr($slot); ?>">
  <div class="cetrus-newsletter__caixa">
  <?php if ($a['cabecalho'] !== 'nao') : ?>
  <div class="cetrus-newsletter__texto">
    <p class="cetrus-newsletter__eyebrow">Newsletter</p>
    <h2 class="cetrus-newsletter__titulo"><?php echo esc_html($titulo); ?></h2>
    <p class="cetrus-newsletter__sub"><?php echo esc_html($texto); ?></p>
  </div>
  <?php endif; ?>
  <div class="cetrus-newsletter__form">
    <div id="cetrus-newsletter-alvo-<?php echo esc_attr($slot); ?>" class="cetrus-newsletter-alvo"></div>
    <p class="cetrus-newsletter__fina">
      <svg class="cetrus-newsletter__cadeado" width="12" height="12" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
      </svg>
      Seus dados ficam com o Cetrus. Cancele a inscrição quando quiser.
    </p>
  </div>
  </div>
</div>
<script>
(function () {
  var cfg = <?php echo wp_json_encode($dados); ?>;
  var alvo = '#cetrus-newsletter-alvo-<?php echo esc_js($slot); ?>';

  function monta() {
    if (!window.hbspt || !window.hbspt.forms) return false;
    if (document.querySelector(alvo + ' form')) return true;   // ja montado

    window.hbspt.forms.create({
      portalId: cfg.portalId,
      formId:   cfg.formId,
      region:   cfg.region,
      target:   alvo,
      // Sem isto o HubSpot embute o formulario num IFRAME (medido: hs-form-iframe 280x302).
      // Dentro do iframe o nosso CSS nao alcanca e, pior, os campos ocultos nunca sao
      // preenchidos, que e justamente o motivo deste plugin existir. Passar css vazio
      // faz a lib renderizar inline, no DOM da propria pagina.
      css: '',
      onFormReady: function ($form) {
        // usar o formulario que a lib entrega, nunca consultar a partir do container:
        // se por qualquer motivo ele voltar a ser iframe, o container nao alcanca os campos.
        var form = ($form && $form[0]) ? $form[0] : document.querySelector(alvo + ' form');
        var raiz = document.querySelector(alvo);

        // a lib adiciona .hbspt-form; duas macros do GTM leem essa classe e so
        // tratam 1 ou 2 elementos. Trocamos por uma classe nossa.
        if (raiz) { raiz.classList.remove('hbspt-form'); raiz.classList.add('cetrus-newsletter-hsform'); }
        if (!form) return;

        var set = function (nome, valor) {
          if (!valor) return;
          var el = form.querySelector('[name="' + nome + '"]');
          if (!el) return;
          el.value = valor;
          el.dispatchEvent(new Event('input',  { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        };
        // layout em faixa: os rotulos viram placeholder para os campos caberem
        // na mesma linha do botao, sem empilhar e sem crescer a altura do bloco.
        form.querySelectorAll('.hs-form-field').forEach(function (campo) {
          var rot = campo.querySelector('label:not(.hs-error-msg)');
          var ent = campo.querySelector('input[type=text], input[type=email]');
          if (rot && ent && !ent.placeholder) {
            var txt = (rot.textContent || '').replace(/\*/g, '').trim();
            if (txt) ent.setAttribute('placeholder', txt);
          }
        });

        var botao = form.querySelector('input[type=submit], .hs-button');
        if (botao && botao.value && botao.value.indexOf('\u2192') === -1) {
          botao.value = botao.value.trim() + '  \u2192';
        }

        set('contact__ct__bait', cfg.bait);
        set('contact__ct__page_type', cfg.pageType);
        Object.keys(cfg.utms).forEach(function (k) { set(k, cfg.utms[k]); });

        // business_unit: NAO TOCAR.
        // E declarado como checkbox no HubSpot mas o embed renderiza como input oculto
        // ja preenchido com 'cetrus'. Escrever nele e disparar 'change' faz a validacao
        // do HubSpot limpar o grupo, e o campo chega VAZIO no CRM - medido em 28/08/2026.
        // So intervimos se o dia em que ele vier como checkbox de verdade.
        var bu = form.querySelector('[name="business_unit"]');
        if (bu && bu.type === 'checkbox' && !bu.checked) { bu.click(); }

        if (window.clarity) { try { window.clarity('set', 'cetrus_newsletter', cfg.bait); } catch (e) {} }
      },
      onFormSubmitted: function () {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
          event: 'cetrus_newsletter_submit',
          newsletter_slot: cfg.bait,
          hs_form_id: cfg.formId
        });
        if (window.clarity) { try { window.clarity('event', 'cetrus_newsletter_submit'); } catch (e) {} }
      }
    });
    return true;
  }

  if (!monta()) {
    var n = 0;
    var t = setInterval(function () { if (monta() || ++n > 60) clearInterval(t); }, 250);
  }
})();
</script>
<?php
    return ob_get_clean();
}
add_shortcode('cetrus_newsletter', 'cetrus_news_render');

/**
 * O container do Elementor que hospeda o bloco na home (92454f4) e visivel desde que o
 * bloco foi reposicionado. Como o shortcode devolve string vazia enquanto o slot esta
 * desligado, sem isto o visitante veria a faixa com titulo e subtitulo e nenhum formulario.
 * Esconde o container inteiro ate o slot ser ligado.
 */
define('CETRUS_NEWS_CONTAINER_HOME', 'elementor-element-92454f4');

/** CSS escopado. Cores do kit Elementor 10452, que sao os tokens Cetrus do Dende. */
add_action('wp_enqueue_scripts', function () {
    $css = '';
    if (!cetrus_news_ativo('home')) {
        $css .= '.' . CETRUS_NEWS_CONTAINER_HOME . '{display:none !important}';
    }
    // Faixa horizontal baixa: texto a esquerda, campos e botao colados numa linha so a direita.
    // Os rotulos viram placeholder no onFormReady, senao o formulario empilha e a faixa cresce.
    //
    // TOKENS DO DENDE (design system da Sanar) - nao inventar valor novo:
    //   #F2F3FB ColorBrandCetrusSurface | #002452 ColorBrandCetrusDark | #003B6C ColorBrandCetrusMain
    //   #C3C6C6 ColorNeutralLight       | #111212 ColorNeutralDarkest
    //   rgba(17,18,18,.65) ColorTextNeutralLigther
    //   #C61D1D ColorFeedbackMainError  | #E7F4EC ColorFeedbackSurfaceSuccess | #0C5728 ColorFeedbackOnSuccess
    //   tipografia: 1.25rem FontSizeLarge | 1rem FontSizeMedium | .875rem FontSizeSmall | .75rem FontSizeTiny
    //   espacamento: 4 8 12 16 20 24 32 40 48 px (Spacing1..12) | raio 8px BorderRadius2
    $css .= '
/* A cor precisa atravessar a tela inteira. O bloco vive dentro de um container
   BOXED do Elementor (1170px), entao pintar o bloco pinta so o miolo: quem tem
   largura total e o proprio container, e e nele que o fundo tem que ir. */
.elementor-element-92454f4{background-color:#F2F3FB}
.cetrus-newsletter{width:100%;background:transparent;padding:40px 24px}
.cetrus-newsletter__caixa{display:flex;flex-wrap:wrap;gap:24px 48px;align-items:center;
  justify-content:space-between;max-width:1170px;margin:0 auto}
.cetrus-newsletter__texto{flex:1 1 340px;min-width:260px}
.cetrus-newsletter__eyebrow{margin:0 0 8px;color:rgba(17,18,18,.65);font-size:.75rem;font-weight:600;
  letter-spacing:.12em;text-transform:uppercase;line-height:1}
.cetrus-newsletter__titulo{margin:0 0 8px;color:#002452;font-size:1.75rem;line-height:1.15;font-weight:700;
  letter-spacing:-.01em}
.cetrus-newsletter__sub{margin:0;color:rgba(17,18,18,.65);font-size:.875rem;line-height:1.5}
.cetrus-newsletter__form{flex:1 1 420px;min-width:280px}
.cetrus-newsletter--so-form{padding:24px}
.cetrus-newsletter--so-form .cetrus-newsletter__form{flex:1 1 100%}
.cetrus-newsletter__fina{display:flex;align-items:center;gap:6px;margin:12px 0 0;
  color:rgba(17,18,18,.65);font-size:.75rem;line-height:1.4}
.cetrus-newsletter__cadeado{flex:0 0 auto}

.cetrus-newsletter form.hs-form{display:flex;flex-wrap:wrap;align-items:flex-start;gap:8px}
.cetrus-newsletter .hs-form-field{flex:1 1 160px;min-width:0;margin:0;position:relative}
.cetrus-newsletter .legal-consent-container,.cetrus-newsletter .hs-richtext,
.cetrus-newsletter .hs_error_rollup,.cetrus-newsletter .submitted-message{flex:1 1 100%;order:10;margin:0}
.cetrus-newsletter .hs-form-field>label:not(.hs-error-msg){position:absolute;width:1px;height:1px;
  padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0}
.cetrus-newsletter .hs-form-field .input{margin:0}
.cetrus-newsletter input[type=text],.cetrus-newsletter input[type=email]{width:100%;height:48px;
  padding:0 16px;border:1px solid #C3C6C6;border-radius:8px;background:#fff;color:#111212;font-size:1rem}
.cetrus-newsletter input::placeholder{color:rgba(17,18,18,.55)}
.cetrus-newsletter input[type=text]:focus,.cetrus-newsletter input[type=email]:focus{
  outline:2px solid #003B6C;outline-offset:-1px;border-color:#003B6C}
.cetrus-newsletter .hs_submit,.cetrus-newsletter .actions{flex:0 0 auto;margin:0;padding:0}
/* a seta vai no texto do botao (onFormReady): o CSS do HubSpot vence background-image */
.cetrus-newsletter form.hs-form .hs-button{height:48px;padding:0 24px;border:0;border-radius:8px;
  background:#003B6C;color:#fff;font-size:1rem;font-weight:600;cursor:pointer;white-space:nowrap;
  }
.cetrus-newsletter form.hs-form .hs-button:hover{background-color:#002452}

.cetrus-newsletter .legal-consent-container{font-size:.75rem;color:rgba(17,18,18,.65);line-height:1.45}
.cetrus-newsletter .legal-consent-container a{color:#003B6C;text-decoration:underline}
.cetrus-newsletter .hs-form-booleancheckbox-display{display:flex;align-items:flex-start;gap:8px}
.cetrus-newsletter .hs-form-booleancheckbox-display input[type=checkbox]{margin:2px 0 0;width:16px;height:16px;
  accent-color:#003B6C;flex:0 0 auto}
.cetrus-newsletter .hs-error-msgs{position:absolute;top:100%;left:0;margin:4px 0 0;padding:0;list-style:none}
.cetrus-newsletter .hs-error-msg{color:#C61D1D;font-size:.75rem}
.cetrus-newsletter .submitted-message{color:#0C5728;background:#E7F4EC;border-radius:8px;padding:16px;font-size:1rem}

@media (max-width:860px){
  .cetrus-newsletter{padding:32px 24px}
  .cetrus-newsletter__caixa{gap:20px}
  .cetrus-newsletter__titulo{font-size:1.5rem}
  .cetrus-newsletter .hs-form-field{flex:1 1 100%}
  .cetrus-newsletter .hs_submit{flex:1 1 100%}
  .cetrus-newsletter .hs-button{width:100%}
  .cetrus-newsletter .hs-error-msgs{position:static}
}
';
    wp_register_style('cetrus-newsletter', false, [], '1.0.0');
    wp_enqueue_style('cetrus-newsletter');
    wp_add_inline_style('cetrus-newsletter', $css);
});

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('cetrus-newsletter', function ($args) {
        $sub = $args[0] ?? 'status';
        $c = cetrus_news_config();
        if ($sub === 'status') {
            WP_CLI::line('enabled  : ' . $c['enabled'] . ($c['enabled'] ? '' : '  (so preview com ?cetrus_newsletter=1)'));
            WP_CLI::line('form_id  : ' . $c['form_id']);
            WP_CLI::line('portal   : ' . $c['portal_id'] . ' | regiao ' . $c['region']);
            foreach ($c['slots'] as $k => $v) WP_CLI::line(sprintf('slot %-8s: %s', $k, $v ? 'ligado' : 'desligado'));
            return;
        }
        if ($sub === 'on' || $sub === 'off') {
            $c['enabled'] = ($sub === 'on') ? 1 : 0;
            update_option(CETRUS_NEWS_OPT, $c, false);
            WP_CLI::success('enabled=' . $c['enabled']);
            return;
        }
        WP_CLI::error('use status, on ou off');
    });
}
