<?php
/**
 * SlideRemote — apresentar.php (tela da LOUSA DIGITAL)
 *
 * Abra esta página no navegador da lousa, informe o link do Google
 * Slides/Drive (ou envie um PDF) e conecte o celular pelo QR Code ou
 * pelo código de 4 dígitos para controlar a apresentação.
 */
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>SlideRemote — Apresentação</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='18' fill='%231f4e8c'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='white' font-family='Arial'%3ES%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/estilo.css">
</head>
<body class="pagina-apresentar" data-pagina="apresentar">

<!-- ============================= Tela inicial ============================ -->
<section id="tela-inicial" class="tela tela-clara">
  <div class="cartao cartao-inicial">
    <h1 class="logo">Slide<span>Remote</span></h1>
    <p class="subtitulo">Controle a apresentação da lousa pelo seu celular</p>

    <form id="form-link" autocomplete="off">
      <label class="rotulo" for="campo-link">Link do Google Slides ou do Google Drive</label>
      <input type="text" id="campo-link" class="campo-texto" spellcheck="false"
             placeholder="https://docs.google.com/presentation/d/…">
      <button type="submit" id="botao-apresentar" class="botao botao-primario">Apresentar</button>
    </form>

    <div class="separador"><span>ou</span></div>

    <label class="botao botao-secundario" for="campo-pdf">Enviar um arquivo PDF</label>
    <input type="file" id="campo-pdf" class="visualmente-oculto" accept="application/pdf,.pdf">
    <p class="dica">
      Use o PDF quando o Drive estiver bloqueado na rede da escola
      ou quando a apresentação não estiver compartilhada.
    </p>

    <p id="erro-inicial" class="mensagem-erro" hidden></p>
  </div>
</section>

<!-- ========================= Tela de apresentação ======================== -->
<section id="tela-apresentacao" class="tela tela-escura" hidden>
  <div id="palco"></div>

  <div id="carregando" class="sobreposicao" hidden>
    <div class="cartao cartao-escuro">
      <p id="carregando-texto">Baixando a apresentação…</p>
      <div class="barra-progresso"><div id="progresso"></div></div>
    </div>
  </div>

  <div id="cortina" hidden></div>

  <aside id="painel-pareamento" hidden>
    <h2>Conecte o celular</h2>
    <div id="qrcode" aria-label="QR Code para conectar o celular"></div>
    <p class="pareamento-instrucao">
      Aponte a câmera do celular para o QR Code<br>
      ou acesse <strong id="url-controle"></strong> e digite o código:
    </p>
    <p id="codigo-sessao" aria-label="Código de pareamento"></p>
    <p class="dica dica-clara">
      O painel some quando o celular conectar — tecle <kbd>C</kbd> para vê-lo de novo.
    </p>
  </aside>

  <div id="indicador-conexao" class="aguardando" title="Aguardando o celular conectar"></div>

  <div id="indicador-trava" class="chip-trava" hidden>&#128274; Toque da lousa travado</div>

  <div id="laser-ponto" hidden></div>

  <div id="aviso-teclas" class="aviso-flutuante" hidden>
    Toque na tela: tela cheia &nbsp;·&nbsp; Setas ◀ ▶: trocar slide &nbsp;·&nbsp;
    <kbd>B</kbd>: tela preta &nbsp;·&nbsp; <kbd>C</kbd>: código
  </div>

  <div id="tela-encerrada" class="sobreposicao" hidden>
    <div class="cartao cartao-escuro">
      <h2>Apresentação encerrada</h2>
      <p class="dica dica-clara">Obrigado por usar o SlideRemote.</p>
      <button type="button" class="botao botao-primario" id="botao-nova">Nova apresentação</button>
    </div>
  </div>
</section>

<!-- Bibliotecas via CDN (QR Code e PDF.js em build compatível com
     navegadores Android mais antigos, comuns em lousas digitais) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.min.js"></script>
<script>window.SLIDEREMOTE_PDFJS_WORKER = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/legacy/build/pdf.worker.min.js';</script>
<script src="assets/app.js"></script>
</body>
</html>
