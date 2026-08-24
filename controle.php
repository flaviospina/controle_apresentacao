<?php
/**
 * SlideRemote — controle.php (tela do CELULAR)
 *
 * Abra pelo QR Code mostrado na lousa (já entra conectado) ou digite o
 * código de 4 dígitos. Layout mobile-first, com botões grandes para uso
 * com uma mão e sem precisar olhar para a tela.
 */
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#0d1117">
<title>SlideRemote — Controle</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='18' fill='%231f4e8c'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3ES%3C/text%3E%3C/svg%3E">
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

  <footer id="rodape-controle">
    <button type="button" id="botao-blackout" aria-pressed="false">
      <span aria-hidden="true">◼</span> Tela preta
    </button>
    <button type="button" id="botao-grade">
      <span aria-hidden="true">▦</span> Ir para slide
    </button>
    <button type="button" id="botao-encerrar">
      <span aria-hidden="true">⏻</span> Encerrar
    </button>
  </footer>

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
