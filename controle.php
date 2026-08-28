<?php
/**
 * SlideRemote — controle.php (tela do CELULAR)
 *
 * Abra pelo QR Code mostrado na lousa (já entra conectado) ou digite o
 * código de 4 dígitos. Layout mobile-first, com botões grandes para uso
 * com uma mão e sem precisar olhar para a tela.
 */
require __DIR__ . '/config.php';

// URL base absoluta (as redes sociais exigem endereço completo na og:image).
$urlBase = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
         . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0b1628">
<title>SlideRemote — Controle</title>
<meta name="description" content="Controle remoto da apresentação: avance e volte slides, tela preta, caneta laser e trava da lousa, direto do celular.">
<!-- Compartilhamento em redes sociais / WhatsApp (Open Graph) -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="SlideRemote">
<meta property="og:title" content="SlideRemote — controle remoto da apresentação">
<meta property="og:description" content="Controle a apresentação da lousa pelo celular: slides, tela preta, caneta laser e trava de toque.">
<meta property="og:url" content="<?php echo htmlspecialchars($urlBase . '/controle.php'); ?>">
<meta property="og:image" content="<?php echo htmlspecialchars($urlBase . '/assets/og-imagem.png'); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="pt_BR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="SlideRemote — controle remoto da apresentação">
<meta name="twitter:description" content="Controle a apresentação da lousa pelo celular, sem hardware apontador.">
<meta name="twitter:image" content="<?php echo htmlspecialchars($urlBase . '/assets/og-imagem.png'); ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='18' fill='%230b1628'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='%2322d3ee' font-family='Arial'%3ES%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/estilo.css">
</head>
<body class="pagina-controle" data-pagina="controle">

<!-- ========================== Tela do código ============================ -->
<section id="tela-codigo" class="tela tela-clara">
  <div class="cartao">
    <h1 class="logo">Slide<span>Remote</span></h1>
    <p class="subtitulo">Digite o código de 4 dígitos mostrado na lousa</p>

    <form id="form-codigo" autocomplete="off">
      <input type="text" id="campo-codigo" class="campo-codigo" inputmode="numeric"
             pattern="[0-9]*" maxlength="4" placeholder="••••"
             aria-label="Código de 4 dígitos">
      <button type="submit" id="botao-conectar" class="botao botao-primario">Conectar</button>
    </form>

    <p id="erro-codigo" class="mensagem-erro" hidden></p>

    <!-- Logos institucionais: troque os três arquivos em assets/logos/
         (ou o src abaixo) pelas logos definitivas. -->
    <div class="faixa-logos">
      <img src="assets/logos/logo1.png" alt="Logo 1">
      <img src="assets/logos/logo2.png" alt="Logo 2">
      <img src="assets/logos/logo3.png" alt="Logo 3">
    </div>
  </div>
</section>

<!-- ========================== Tela do controle ========================== -->
<section id="tela-controle" hidden>

  <header id="cabecalho-controle">
    <span id="ponto-conexao" class="ok" title="Conectado"></span>
    <span class="titulo-app">SlideRemote</span>
    <span id="contador" aria-label="Slide atual e total">– / –</span>
    <button type="button" id="cronometro" title="Toque para zerar o cronômetro">00:00</button>
  </header>

  <div id="minis" hidden>
    <figure>
      <img id="mini-atual" alt="Slide atual">
      <figcaption>Atual</figcaption>
    </figure>
    <figure>
      <img id="mini-proximo" alt="Próximo slide">
      <figcaption>Próximo</figcaption>
    </figure>
  </div>

  <main id="area-toques">
    <button type="button" id="botao-anterior" class="botao-toque">
      <span class="seta" aria-hidden="true">◀</span>
      <span class="rotulo-toque">Anterior</span>
    </button>
    <button type="button" id="botao-proximo" class="botao-toque">
      <span class="seta" aria-hidden="true">▶</span>
      <span class="rotulo-toque">Próximo</span>
    </button>
  </main>

  <!-- Aparece só com o laser ligado no modo sensor: recentraliza o ponto -->
  <div id="barra-laser" hidden>
    <span>Mova o celular para apontar</span>
    <button type="button" id="botao-centralizar">&#127919; Centralizar</button>
  </div>

  <footer id="rodape-controle">
    <button type="button" id="botao-laser" class="rodape-meio" aria-pressed="false">
      <span aria-hidden="true">☄</span> <em id="rotulo-laser">Laser</em>
    </button>
    <button type="button" id="botao-blackout" class="rodape-meio" aria-pressed="false">
      <span aria-hidden="true">◼</span> Tela preta
    </button>
    <button type="button" id="botao-travar" class="rodape-meio" aria-pressed="false">
      <span aria-hidden="true">&#128274;</span> <em id="rotulo-travar">Travar lousa</em>
    </button>
    <button type="button" id="botao-grade" class="rodape-largo">
      <span aria-hidden="true">▦</span> Ir para slide
    </button>
    <button type="button" id="botao-encerrar" class="rodape-largo">
      <span aria-hidden="true">⏻</span> Encerrar
    </button>
  </footer>

  <!-- Modo touchpad do laser: usado quando o celular não tem sensor de
       movimento (ou a permissão foi negada). -->
  <div id="laser-touch-overlay" hidden>
    <p>Arraste o dedo para mover o laser na lousa</p>
    <div id="laser-touch-area" aria-label="Área de toque do laser"></div>
    <button type="button" id="botao-fechar-laser" class="botao botao-primario">Desligar laser</button>
  </div>

  <!-- Grade de miniaturas para pular direto a um slide -->
  <div id="grade-overlay" hidden>
    <header>
      <span>Ir para o slide</span>
      <button type="button" id="fechar-grade" aria-label="Fechar">✕</button>
    </header>
    <div id="grade-slides"></div>
  </div>

  <div id="fim-overlay" class="sobreposicao" hidden>
    <div class="cartao">
      <h2>Apresentação encerrada</h2>
      <p class="dica">A lousa também foi avisada.</p>
      <button type="button" id="botao-novo-codigo" class="botao botao-primario">Conectar a outra sessão</button>
    </div>
  </div>

</section>

<!-- PDF.js (build legado) só é usado para gerar as miniaturas; se falhar,
     o controle continua funcionando sem elas. -->
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>window.SLIDEREMOTE_PDFJS_WORKER = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';</script>
<script src="assets/app.js"></script>
</body>
</html>
