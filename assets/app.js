/**
 * SlideRemote — assets/app.js
 *
 * Um único arquivo para as duas telas:
 *   - apresentar.php (lousa digital)  → iniciarApresentar()
 *   - controle.php   (celular)        → iniciarControle()
 *
 * Escrito em JavaScript compatível com Chrome de lousas digitais Android
 * mais antigas: const/let, async/await e fetch — sem recursos experimentais.
 */
(function () {
  'use strict';

  var API = {
    sessao:  'api/sessao.php',
    estado:  'api/estado.php',
    comando: 'api/comando.php',
    proxy:   'proxy_pdf.php'
  };

  var INTERVALO_POLLING_MS = 500;

  // ------------------------------------------------------------------
  // Helpers comuns
  // ------------------------------------------------------------------

  function $(id) { return document.getElementById(id); }

  /** POST application/x-www-form-urlencoded; devolve o JSON da resposta. */
  async function postForm(url, dados) {
    var corpo = new URLSearchParams();
    Object.keys(dados).forEach(function (chave) {
      if (dados[chave] !== undefined && dados[chave] !== null) {
        corpo.append(chave, String(dados[chave]));
      }
    });
    var resposta = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: corpo.toString(),
      cache: 'no-store'
    });
    var json = null;
    try { json = await resposta.json(); } catch (e) { /* resposta sem JSON */ }
    if (!resposta.ok) {
      var mensagem = (json && json.erro) ? json.erro : ('Erro de comunicação (HTTP ' + resposta.status + ').');
      var erro = new Error(mensagem);
      erro.httpStatus = resposta.status;
      erro.resposta = json;
      throw erro;
    }
    return json;
  }

  /** GET que devolve o JSON da resposta (mesmo contrato do postForm). */
  async function getJson(url) {
    var resposta = await fetch(url, { cache: 'no-store' });
    var json = null;
    try { json = await resposta.json(); } catch (e) { /* resposta sem JSON */ }
    if (!resposta.ok) {
      var mensagem = (json && json.erro) ? json.erro : ('Erro de comunicação (HTTP ' + resposta.status + ').');
      var erro = new Error(mensagem);
      erro.httpStatus = resposta.status;
      erro.resposta = json;
      throw erro;
    }
    return json;
  }

  /**
   * Baixa o PDF pelo proxy e abre com PDF.js.
   * Faz o download via fetch (e não passando a URL ao PDF.js) para poder
   * ler a mensagem de erro em JSON que o proxy devolve quando algo falha.
   */
  async function abrirPdf(fileId, aoProgredirDownload) {
    var resposta = await fetch(API.proxy + '?file_id=' + encodeURIComponent(fileId), { cache: 'no-store' });
    if (!resposta.ok) {
      var json = null;
      try { json = await resposta.json(); } catch (e) { /* sem JSON */ }
      throw new Error((json && json.erro) ? json.erro : ('Falha ao baixar a apresentação (HTTP ' + resposta.status + ').'));
    }
    if (aoProgredirDownload) { aoProgredirDownload(); }
    var dados = await resposta.arrayBuffer();
    if (typeof pdfjsLib === 'undefined') {
      throw new Error('A biblioteca PDF.js não carregou. Verifique o acesso à internet (CDN).');
    }
    pdfjsLib.GlobalWorkerOptions.workerSrc = window.SLIDEREMOTE_PDFJS_WORKER || '';
    return pdfjsLib.getDocument({ data: dados }).promise;
  }

  /** Monta a URL da tela do celular (controle.php) a partir da atual. */
  function urlDoControle(codigo) {
    var base = location.origin + location.pathname.replace(/[^/]*$/, '');
    return base + 'controle.php?c=' + encodeURIComponent(codigo);
  }

  /** Vibração curta como confirmação tátil (silencioso onde não houver). */
  function vibrar() {
    try {
      if (navigator.vibrate) { navigator.vibrate(30); }
    } catch (e) { /* sem suporte */ }
  }

  // ==================================================================
  // LOUSA — apresentar.php
  // ==================================================================

  function iniciarApresentar() {
    var telaInicial     = $('tela-inicial');
    var telaApresentacao= $('tela-apresentacao');
    var formLink        = $('form-link');
    var campoLink       = $('campo-link');
    var botaoApresentar = $('botao-apresentar');
    var campoPdf        = $('campo-pdf');
    var erroInicial     = $('erro-inicial');
    var palco           = $('palco');
    var carregando      = $('carregando');
    var carregandoTexto = $('carregando-texto');
    var progresso       = $('progresso');
    var cortina         = $('cortina');
    var painel          = $('painel-pareamento');
    var indicador       = $('indicador-conexao');
    var avisoTeclas     = $('aviso-teclas');
    var telaEncerrada   = $('tela-encerrada');

    var sessao = {
      codigo: null,
      fileId: null,
      slideAtual: 1,
      totalSlides: 0,
      blackout: false,
      encerrada: false,
      conectado: false,
      jaConectou: false,     // o celular já se conectou ao menos uma vez
      canvases: [],
      timerPolling: null,
      consultaEmVoo: false
    };

    // ---------------- Tela inicial ----------------

    function mostrarErroInicial(mensagem) {
      erroInicial.textContent = mensagem;
      erroInicial.hidden = false;
      botaoApresentar.disabled = false;
      campoPdf.disabled = false;
    }

    formLink.addEventListener('submit', function (evento) {
      evento.preventDefault();
      var link = campoLink.value.trim();
      if (link === '') {
        mostrarErroInicial('Cole o link da apresentação para começar.');
        return;
      }
      erroInicial.hidden = true;
      botaoApresentar.disabled = true;
      botaoApresentar.textContent = 'Criando sessão…';
      postForm(API.sessao, { link: link })
        .then(function (dados) { return iniciarSessao(dados); })
        .catch(function (erro) {
          botaoApresentar.textContent = 'Apresentar';
          mostrarErroInicial(erro.message);
        });
    });

    campoPdf.addEventListener('change', function () {
      if (!campoPdf.files || campoPdf.files.length === 0) { return; }
      var arquivo = campoPdf.files[0];
      erroInicial.hidden = true;
      campoPdf.disabled = true;

      var dadosFormulario = new FormData();
      dadosFormulario.append('arquivo', arquivo);

      fetch(API.proxy, { method: 'POST', body: dadosFormulario })
        .then(function (resposta) {
          return resposta.json().then(function (json) {
            if (!resposta.ok) { throw new Error(json && json.erro ? json.erro : 'Falha no envio do PDF.'); }
            return json;
          });
        })
        .then(function (json) { return postForm(API.sessao, { file_id: json.file_id }); })
        .then(function (dados) { return iniciarSessao(dados); })
        .catch(function (erro) {
          campoPdf.value = '';
          mostrarErroInicial(erro.message);
        });
    });

    // ---------------- Início da sessão ----------------

    async function iniciarSessao(dados) {
      sessao.codigo = dados.codigo;
      sessao.fileId = dados.file_id;

      telaInicial.hidden = true;
      telaApresentacao.hidden = false;
      carregando.hidden = false;
      carregandoTexto.textContent = 'Baixando a apresentação…';
      progresso.style.width = '5%';

      montarPareamento(dados.codigo);

      try {
        await carregarPdf(dados.file_id);
      } catch (erro) {
        // Volta para a tela inicial com a mensagem do proxy.
        telaApresentacao.hidden = true;
        telaInicial.hidden = false;
        botaoApresentar.disabled = false;
        botaoApresentar.textContent = 'Apresentar';
        campoPdf.disabled = false;
        campoPdf.value = '';
        mostrarErroInicial(erro.message);
        return;
      }

      carregando.hidden = true;
      painel.hidden = false;
      avisoTeclas.hidden = false;
      setTimeout(function () { avisoTeclas.hidden = true; }, 8000);

      iniciarPolling();
    }

    function montarPareamento(codigo) {
      $('codigo-sessao').textContent = codigo;
      $('url-controle').textContent =
        location.host + location.pathname.replace(/[^/]*$/, '') + 'controle.php';
      try {
        if (typeof QRCode !== 'undefined') {
          new QRCode($('qrcode'), {
            text: urlDoControle(codigo),
            width: 200,
            height: 200,
            correctLevel: QRCode.CorrectLevel.M
          });
        }
      } catch (e) { /* sem QR, o código digitado resolve */ }
    }

    // ---------------- PDF.js: pré-renderização ----------------

    async function carregarPdf(fileId) {
      var documento = await abrirPdf(fileId, function () {
        carregandoTexto.textContent = 'Preparando os slides…';
        progresso.style.width = '15%';
      });

      var total = documento.numPages;
      var dpr = Math.min(window.devicePixelRatio || 1, 2);

      for (var numero = 1; numero <= total; numero++) {
        var pagina = await documento.getPage(numero);

        // Escala para preencher a tela da lousa com nitidez (limitada para
        // não estourar memória em aparelhos mais fracos).
        var base   = pagina.getViewport({ scale: 1 });
        var escala = Math.min(
          (window.innerWidth  * dpr) / base.width,
          (window.innerHeight * dpr) / base.height
        );
        escala = Math.max(0.5, Math.min(escala, 3));
        var viewport = pagina.getViewport({ scale: escala });

        var canvas = document.createElement('canvas');
        canvas.width  = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        canvas.className = 'slide';

        await pagina.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;

        palco.appendChild(canvas);
        sessao.canvases.push(canvas);

        carregandoTexto.textContent = 'Preparando slide ' + numero + ' de ' + total + '…';
        progresso.style.width = (15 + Math.round((numero / total) * 85)) + '%';
      }

      sessao.totalSlides = total;
      mostrarSlide(1);

      // Informa o total ao servidor (o celular monta a grade com isso).
      try {
        var estado = await postForm(API.comando, { c: sessao.codigo, acao: 'definir_total', valor: total });
        aplicarEstado(estado);
      } catch (e) { /* o polling corrige em seguida */ }
    }

    function mostrarSlide(numero) {
      if (numero < 1 || numero > sessao.canvases.length) { return; }
      for (var i = 0; i < sessao.canvases.length; i++) {
        sessao.canvases[i].classList.toggle('atual', i === numero - 1);
      }
      sessao.slideAtual = numero;
    }

    // ---------------- Polling do estado ----------------

    function iniciarPolling() {
      sessao.timerPolling = setInterval(consultarEstado, INTERVALO_POLLING_MS);
    }

    function consultarEstado() {
      if (sessao.consultaEmVoo || sessao.encerrada) { return; }
      sessao.consultaEmVoo = true;
      getJson(API.estado + '?c=' + encodeURIComponent(sessao.codigo))
        .then(function (estado) { aplicarEstado(estado); })
        .catch(function () {
          // Sem conexão com o servidor: mantém o slide atual e sinaliza.
          indicador.className = 'sem-servidor';
          indicador.title = 'Sem conexão com o servidor — o slide atual foi mantido';
        })
        .then(function () { sessao.consultaEmVoo = false; });
    }

    function aplicarEstado(estado) {
      if (!estado) { return; }

      if (!estado.ativa) {
        encerrarApresentacao();
        return;
      }

      if (estado.slide_atual !== sessao.slideAtual) {
        mostrarSlide(estado.slide_atual);
      }

      if (estado.blackout !== sessao.blackout) {
        sessao.blackout = estado.blackout;
        cortina.hidden = !estado.blackout;
      }

      sessao.conectado = estado.controle_conectado;
      if (estado.controle_conectado) {
        indicador.className = 'conectado';
        indicador.title = 'Celular conectado';
        if (!sessao.jaConectou) {
          sessao.jaConectou = true;
          painel.hidden = true; // o professor já conectou: libera a tela
        }
      } else {
        indicador.className = 'aguardando';
        indicador.title = 'Aguardando o celular conectar';
      }
    }

    function encerrarApresentacao() {
      sessao.encerrada = true;
      if (sessao.timerPolling) { clearInterval(sessao.timerPolling); }
      cortina.hidden = true;
      painel.hidden = true;
      telaEncerrada.hidden = false;
      if (document.exitFullscreen && document.fullscreenElement) {
        document.exitFullscreen().catch(function () {});
      }
    }

    $('botao-nova').addEventListener('click', function () { location.reload(); });

    // ---------------- Comandos locais (fallback pelo teclado) ----------------

    function comandoLocal(acao, valor) {
      postForm(API.comando, { c: sessao.codigo, acao: acao, valor: valor })
        .then(function (estado) { aplicarEstado(estado); })
        .catch(function () { /* o polling tenta de novo em 500 ms */ });
    }

    document.addEventListener('keydown', function (evento) {
      if (sessao.encerrada || sessao.codigo === null || sessao.totalSlides === 0) { return; }
      var tecla = evento.key;
      if (tecla === 'ArrowRight' || tecla === 'PageDown' || tecla === ' ') {
        evento.preventDefault();
        comandoLocal('proximo');
      } else if (tecla === 'ArrowLeft' || tecla === 'PageUp') {
        evento.preventDefault();
        comandoLocal('anterior');
      } else if (tecla === 'b' || tecla === 'B') {
        comandoLocal('blackout');
      } else if (tecla === 'c' || tecla === 'C') {
        painel.hidden = !painel.hidden;
      } else if (tecla === 'f' || tecla === 'F') {
        alternarTelaCheia();
      }
    });

    // Primeiro toque/clique na tela entra em tela cheia; os toques
    // seguintes avançam o slide (útil na própria lousa).
    telaApresentacao.addEventListener('click', function (evento) {
      if (sessao.encerrada || sessao.totalSlides === 0) { return; }
      if (painel.contains(evento.target) || telaEncerrada.contains(evento.target)) { return; }
      if (!estaEmTelaCheia()) {
        alternarTelaCheia();
      } else {
        comandoLocal('proximo');
      }
    });

    function estaEmTelaCheia() {
      return !!(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function alternarTelaCheia() {
      var raiz = document.documentElement;
      if (!estaEmTelaCheia()) {
        var pedido = raiz.requestFullscreen || raiz.webkitRequestFullscreen;
        if (pedido) {
          try {
            var promessa = pedido.call(raiz);
            if (promessa && promessa.catch) { promessa.catch(function () {}); }
          } catch (e) { /* alguns navegadores antigos */ }
        }
      }
    }
  }

  // ==================================================================
  // CELULAR — controle.php (implementado na Fase 5)
  // ==================================================================

  function iniciarControle() {
    if (window.SLIDEREMOTE_INICIAR_CONTROLE) {
      window.SLIDEREMOTE_INICIAR_CONTROLE();
    }
  }

  // ------------------------------------------------------------------
  // Ponto de entrada
  // ------------------------------------------------------------------

  document.addEventListener('DOMContentLoaded', function () {
    var pagina = document.body.getAttribute('data-pagina');
    if (pagina === 'apresentar') { iniciarApresentar(); }
    if (pagina === 'controle')   { iniciarControle(); }
  });

  // Expostos para a Fase 5 (controle.php) reutilizar.
  window.SLIDEREMOTE = {
    API: API,
    INTERVALO_POLLING_MS: INTERVALO_POLLING_MS,
    postForm: postForm,
    getJson: getJson,
    abrirPdf: abrirPdf,
    vibrar: vibrar
  };
})();
