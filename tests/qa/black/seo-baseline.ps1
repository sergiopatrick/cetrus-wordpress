<#
  Retrato de SEO por pagina (somente leitura) - projeto Black November 2026.

  Uso (PowerShell 5.1, sem Python/Node):
    powershell -ExecutionPolicy Bypass -File seo-baseline.ps1 -Base https://cetrus.com.br -Rotulo prod-20261022
    powershell -ExecutionPolicy Bypass -File seo-baseline.ps1 -Base https://cetrus.com.br -Rotulo prod-depois -Urls urls.txt

  Sem -Urls, monta a lista pelos sitemaps do Yoast (product, page, v2_modalidade).
  Saida: black-november-2026/baseline/<rotulo>.tsv (uma linha por URL) e
  <rotulo>-robots.txt / <rotulo>-sitemaps.txt. Compare dois retratos com seo-diff.ps1.

  Cada pedido leva query aleatoria (?qa=...) para furar o cache de borda, e o
  UA e de navegador comum: UA de bot recebe desafio JavaScript do edge (tests/qa/LEIA-ME.md, item 3).
  Site privado (staging) responde "Site privado": rode o staging pelo navegador (seo-baseline.js).
#>
param(
  [Parameter(Mandatory = $true)][string]$Base,
  [Parameter(Mandatory = $true)][string]$Rotulo,
  [string]$Urls = '',
  [string]$Saida = '',
  [int]$Limite = 0
)

$ErrorActionPreference = 'Stop'
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'
$Base = $Base.TrimEnd('/')
if (-not $Saida) { $Saida = Join-Path $PSScriptRoot '..\..\..\black-november-2026\baseline' }
New-Item -ItemType Directory -Force $Saida | Out-Null
$tmp = Join-Path $Saida (".tmp-" + [guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Force $tmp | Out-Null
$utf8 = New-Object System.Text.UTF8Encoding($false)

function Baixar([string]$url, [string]$arquivo) {
  $sep = '?'; if ($url.Contains('?')) { $sep = '&' }
  $u = $url + $sep + 'qa=' + [guid]::NewGuid().ToString('N').Substring(0, 10)
  $w = & curl.exe -s -L --max-redirs 5 --compressed -A $UA -o $arquivo -w '%{http_code}|%{num_redirects}|%{url_effective}|%{time_total}' $u
  return $w
}

function Texto([string]$html) {
  $t = [regex]::Replace($html, '<[^>]+>', ' ')
  $t = [System.Net.WebUtility]::HtmlDecode($t)
  return ([regex]::Replace($t, '\s+', ' ')).Trim()
}

function Attr([string]$tag, [string]$nome) {
  $m = [regex]::Match($tag, '\s' + $nome + '\s*=\s*("([^"]*)"|''([^'']*)'')', 'IgnoreCase')
  if (-not $m.Success) { return $null }
  if ($m.Groups[2].Success) { return [System.Net.WebUtility]::HtmlDecode($m.Groups[2].Value) }
  return [System.Net.WebUtility]::HtmlDecode($m.Groups[3].Value)
}

function Meta([string]$html, [string]$chave, [string]$valor) {
  foreach ($m in [regex]::Matches($html, '<meta\b[^>]*>', 'IgnoreCase')) {
    if ((Attr $m.Value $chave) -eq $valor) { return (Attr $m.Value 'content') }
  }
  return ''
}

function Limpa([string]$s) { if ($null -eq $s) { return '' }; return ($s -replace '[\t\r\n]+', ' ').Trim() }

# ---- lista de URLs
$lista = @()
if ($Urls) {
  $lista = Get-Content $Urls | Where-Object { $_ -and -not $_.StartsWith('#') }
} else {
  $sm = Join-Path $tmp 'sm.xml'
  $log = @()
  foreach ($s in @('product-sitemap.xml', 'page-sitemap.xml', 'v2_modalidade-sitemap.xml')) {
    $null = Baixar "$Base/$s" $sm
    $xml = [IO.File]::ReadAllText($sm, [Text.Encoding]::UTF8)
    $locs = [regex]::Matches($xml, '<loc>([^<]+)</loc>') | ForEach-Object { $_.Groups[1].Value }
    $log += "$s`t$($locs.Count)"
    $lista += $locs
  }
  [IO.File]::WriteAllLines((Join-Path $Saida "$Rotulo-sitemaps.txt"), [string[]]$log, $utf8)
}
$lista = $lista | Where-Object { $_ -notmatch '\.(jpg|jpeg|png|webp|gif|svg|pdf)$' } | Select-Object -Unique
if ($Limite -gt 0) { $lista = $lista | Select-Object -First $Limite }

# robots.txt
$rb = Join-Path $tmp 'robots.txt'
$null = Baixar "$Base/robots.txt" $rb
Copy-Item $rb (Join-Path $Saida "$Rotulo-robots.txt") -Force

$cols = 'url', 'http', 'redirs', 'final', 'title', 'description', 'canonical', 'robots', 'og_title', 'og_url',
'h1', 'n_h1', 'h2', 'n_h2', 'n_h3', 'jsonld', 'breadcrumb', 'templates', 'n_links', 'n_links_int', 'n_img', 'n_img_sem_alt',
'palavras', 'gtm', 'hsforms', 'sel_turma', 'whatsapp', 'php_erro', 'segundos'
$linhas = New-Object System.Collections.Generic.List[string]
$linhas.Add(($cols -join "`t"))

$i = 0
foreach ($url in $lista) {
  $i++
  $arq = Join-Path $tmp 'p.html'
  if ([IO.File]::Exists($arq)) { [IO.File]::Delete($arq) }
  $w = (Baixar $url $arq).Split('|')
  $html = ''
  if (Test-Path $arq) { $html = [IO.File]::ReadAllText($arq, [Text.Encoding]::UTF8) }
  $final = $w[2] -replace '[?&]qa=[0-9a-f]{10}$', ''

  $title = ''
  $mt = [regex]::Match($html, '<title[^>]*>(.*?)</title>', 'Singleline,IgnoreCase')
  if ($mt.Success) { $title = Texto $mt.Groups[1].Value }
  $canon = ''
  foreach ($m in [regex]::Matches($html, '<link\b[^>]*>', 'IgnoreCase')) { if ((Attr $m.Value 'rel') -eq 'canonical') { $canon = Attr $m.Value 'href'; break } }

  $hs = @{ 1 = @(); 2 = @(); 3 = @() }
  foreach ($m in [regex]::Matches($html, '<h([1-3])\b[^>]*>(.*?)</h\1>', 'Singleline,IgnoreCase')) {
    $hs[[int]$m.Groups[1].Value] += (Texto $m.Groups[2].Value)
  }

  $tipos = @(); $bc = ''
  foreach ($m in [regex]::Matches($html, '<script[^>]+application/ld\+json[^>]*>(.*?)</script>', 'Singleline,IgnoreCase')) {
    try {
      $o = $m.Groups[1].Value | ConvertFrom-Json
      $itens = @($o); if ($o.'@graph') { $itens = @($o.'@graph') }
      foreach ($x in $itens) {
        $tipos += (@($x.'@type') -join '/')
        if ($x.'@type' -eq 'BreadcrumbList') { $bc = (@($x.itemListElement) | ForEach-Object { [System.Net.WebUtility]::HtmlDecode([string]$_.name) }) -join ' > ' }
      }
    } catch { $tipos += 'JSONLD_INVALIDO' }
  }

  $tpls = ([regex]::Matches($html, 'data-elementor-type="([^"]+)"\s+data-elementor-id="(\d+)"') | ForEach-Object { $_.Groups[1].Value + ':' + $_.Groups[2].Value } | Select-Object -Unique) -join ' '
  $body = $html
  $mb = [regex]::Match($html, '<body\b.*</body>', 'Singleline,IgnoreCase'); if ($mb.Success) { $body = $mb.Value }
  $semScript = [regex]::Replace($body, '<(script|style|noscript)\b.*?</\1>', ' ', 'Singleline,IgnoreCase')
  $palavras = 0; $tx = Texto $semScript; if ($tx) { $palavras = ($tx -split ' ').Count }
  $as = [regex]::Matches($body, '<a\b[^>]*\bhref\s*=\s*"([^"]*)"', 'IgnoreCase')
  $host_ = ([uri]$Base).Host
  $int = ($as | Where-Object { $_.Groups[1].Value.StartsWith('/') -or $_.Groups[1].Value.Contains($host_) }).Count
  $imgs = [regex]::Matches($body, '<img\b[^>]*>', 'IgnoreCase')
  $semAlt = ($imgs | Where-Object { $_.Value -notmatch '\salt\s*=' }).Count

  $v = @(
    $url, $w[0], $w[1], $final, $title,
    (Meta $html 'name' 'description'), $canon, (Meta $html 'name' 'robots'),
    (Meta $html 'property' 'og:title'), (Meta $html 'property' 'og:url'),
    ($hs[1] -join ' || '), $hs[1].Count, (($hs[2] | ForEach-Object { if ($_.Length -gt 90) { $_.Substring(0, 90) } else { $_ } }) -join ' || '), $hs[2].Count, $hs[3].Count,
    (($tipos | Select-Object -Unique) -join ' '), $bc, $tpls,
    $as.Count, $int, $imgs.Count, $semAlt, $palavras,
    ([regex]::Matches($html, 'googletagmanager\.com/gtm\.js')).Count,
    ([regex]::Matches($html, 'hsforms\.net|hbspt\.forms')).Count,
    ([regex]::Matches($html, 'Selecione a turma')).Count,
    ([regex]::Matches($html, 'api\.whatsapp\.com|wa\.me/')).Count,
    ([regex]::Matches($html, 'Fatal error|Uncaught Error|Warning</b>:')).Count,
    $w[3]
  )
  $linhas.Add((($v | ForEach-Object { Limpa ([string]$_) }) -join "`t"))
  if ($i % 25 -eq 0) { Write-Host "$i / $($lista.Count)" }
}

$out = Join-Path $Saida "$Rotulo.tsv"
[IO.File]::WriteAllLines($out, $linhas.ToArray(), $utf8)
[IO.Directory]::Delete($tmp, $true)
Write-Host "OK $($lista.Count) URLs -> $out"
