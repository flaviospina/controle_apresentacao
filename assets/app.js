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
  // CELULAR — controle.php
  // ==================================================================

  function iniciarControle() {
    var telaCodigo    = $('tela-codigo');
    var telaControle  = $('tela-controle');
    var formCodigo    = $('form-codigo');
    var campoCodigo   = $('campo-codigo');
    var botaoConectar = $('botao-conectar');
    var erroCodigo    = $('erro-codigo');
    var pontoConexao  = $('ponto-conexao');
    var contador      = $('contador');
    var cronometroEl  = $('cronometro');
    var minis         = $('minis');
    var miniAtual     = $('mini-atual');
    var miniProximo   = $('mini-proximo');
    var botaoAnterior = $('botao-anterior');
    var botaoProximo  = $('botao-proximo');
    var botaoBlackout = $('botao-blackout');
    var botaoGrade    = $('botao-grade');
    var botaoEncerrar = $('botao-encerrar');
    var gradeOverlay  = $('grade-overlay');
    var gradeSlides   = $('grade-slides');
    var fimOverlay    = $('fim-overlay');

    var controle = {
      codigo: null,
      fileId: null,
      slideAtual: 1,
      totalSlides: 0,
      blackout: false,
      encerrada: false,
      miniaturas: [],        // dataURLs por página (1-based no índice 0)
      gradeMontada: false,
      inicioCronometro: null,
      timerPolling: null,
      timerCronometro: null,
      consultaEmVoo: false,
      wakeLock: null
    };

    // ---------------- Entrada pelo código ou QR Code ----------------

    var parametros = new URLSearchParams(location.search);
    var codigoUrl = (parametros.get('c') || '').trim();

    formCodigo.addEventListener('submit', function (evento) {
      evento.preventDefault();
      var codigo = campoCodigo.value.replace(/\D/g, '');
      if (codigo.length !== 4) {
        mostrarErroCodigo('O código tem 4 dígitos.');
        return;
      }
      entrar(codigo);
    });

    // Conecta sozinho quando o 4º dígito é digitado.
    campoCodigo.addEventListener('input', function () {
      campoCodigo.value = campoCodigo.value.replace(/\D/g, '').slice(0, 4);
      if (campoCodigo.value.length === 4) {
        entrar(campoCodigo.value);
      }
    });

    if (/^[0-9]{4}$/.test(codigoUrl)) {
      entrar(codigoUrl);
    } else {
      campoCodigo.focus();
    }

    function mostrarErroCodigo(mensagem) {
      erroCodigo.textContent = mensagem;
      erroCodigo.hidden = false;
      botaoConectar.disabled = false;
    }

    function entrar(codigo) {
      erroCodigo.hidden = true;
      botaoConectar.disabled = true;
      getJson(API.estado + '?c=' + encodeURIComponent(codigo) + '&papel=controle')
        .then(function (estado) {
          controle.codigo = codigo;
          // Guarda o código na URL: se a página recarregar, reconecta sozinho.
          try { history.replaceState(null, '', 'controle.php?c=' + codigo); } catch (e) { /* ok */ }
          telaCodigo.hidden = true;
          telaControle.hidden = false;
          iniciarCronometro();
          iniciarPollingControle();
          pedirWakeLock();
          aplicarEstadoControle(estado);
          carregarMiniaturas(estado.file_id);
        })
        .catch(function (erro) {
          mostrarErroCodigo(erro.message);
        });
    }

    // ---------------- Comandos ----------------

    function comando(acao, valor) {
      vibrar();
      postForm(API.comando, { c: controle.codigo, acao: acao, valor: valor, papel: 'controle' })
        .then(function (estado) { aplicarEstadoControle(estado); })
        .catch(function (erro) {
          if (erro.httpStatus === 404) { encerrarControle(); }
          // Outras falhas: o polling atualiza em seguida.
        });
    }

    botaoProximo.addEventListener('click',  function () { comando('proximo'); });
    botaoAnterior.addEventListener('click', function () { comando('anterior'); });
    botaoBlackout.addEventListener('click', function () { comando('blackout'); });

    botaoEncerrar.addEventListener('click', function () {
      if (window.confirm('Encerrar a apresentação na lousa?')) {
        comando('encerrar');
      }
    });

    botaoGrade.addEventListener('click', function () {
      montarGrade();
      gradeOverlay.hidden = false;
    });

    $('fechar-grade').addEventListener('click', function () {
      gradeOverlay.hidden = true;
    });

    $('botao-novo-codigo').addEventListener('click', function () {
      location.href = 'controle.php';
    });

    // ---------------- Estado e polling ----------------

    function iniciarPollingControle() {
      controle.timerPolling = setInterval(function () {
        if (controle.consultaEmVoo || controle.encerrada) { return; }
        controle.consultaEmVoo = true;
        getJson(API.estado + '?c=' + encodeURIComponent(controle.codigo) + '&papel=controle')
          .then(function (estado) {
            pontoConexao.className = 'ok';
            pontoConexao.title = 'Conectado';
            aplicarEstadoControle(estado);
          })
          .catch(function () {
            pontoConexao.className = 'falha';
            pontoConexao.title = 'Sem conexão — tentando de novo…';
          })
          .then(function () { controle.consultaEmVoo = false; });
      }, INTERVALO_POLLING_MS);
    }

    function aplicarEstadoControle(estado) {
      if (!estado) { return; }

      if (!estado.ativa) {
        encerrarControle();
        return;
      }

      controle.fileId      = estado.file_id;
      controle.slideAtual  = estado.slide_atual;
      controle.totalSlides = estado.total_slides;
      controle.blackout    = estado.blackout;

      contador.textContent = estado.total_slides > 0
        ? estado.slide_atual + ' / ' + estado.total_slides
        : estado.slide_atual + ' / –';

      botaoBlackout.classList.toggle('ativo', estado.blackout);
      botaoBlackout.setAttribute('aria-pressed', estado.blackout ? 'true' : 'false');

      atualizarMinis();
      marcarSlideNaGrade();
    }

    function encerrarControle() {
      if (controle.encerrada) { return; }
      controle.encerrada = true;
      if (controle.timerPolling)    { clearInterval(controle.timerPolling); }
      if (controle.timerCronometro) { clearInterval(controle.timerCronometro); }
      gradeOverlay.hidden = true;
      fimOverlay.hidden = false;
      vibrar();
      if (controle.wakeLock) {
        try { controle.wakeLock.release(); } catch (e) { /* ok */ }
      }
    }

    // ---------------- Miniaturas (atual, próximo e grade) ----------------

    async function carregarMiniaturas(fileId) {
      if (!fileId) { return; }
      try {
        var documento = await abrirPdf(fileId, null);
        for (var numero = 1; numero <= documento.numPages; numero++) {
          var pagina = await documento.getPage(numero);
          var base = pagina.getViewport({ scale: 1 });
          var viewport = pagina.getViewport({ scale: 240 / base.width });
          var canvas = document.createElement('canvas');
          canvas.width  = Math.floor(viewport.width);
          canvas.height = Math.floor(viewport.height);
          await pagina.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
          controle.miniaturas[numero - 1] = canvas.toDataURL('image/jpeg', 0.72);
          if (numero === 1 || numero === controle.slideAtual || numero === controle.slideAtual + 1) {
            atualizarMinis();
          }
        }
        controle.gradeMontada = false; // remonta com as imagens completas
        atualizarMinis();
      } catch (erro) {
        // Sem miniaturas o controle continua funcionando normalmente.
        minis.hidden = true;
      }
    }

    function atualizarMinis() {
      var atual   = controle.miniaturas[controle.slideAtual - 1] || null;
      var proximo = controle.miniaturas[controle.slideAtual] || null;

      if (!atual && !proximo) { return; }
      minis.hidden = false;

      if (atual) { miniAtual.src = atual; }
      miniAtual.style.visibility = atual ? 'visible' : 'hidden';

      if (proximo) {
        miniProximo.src = proximo;
        miniProximo.style.visibility = 'visible';
      } else {
        miniProximo.style.visibility = 'hidden'; // último slide
      }
    }

    function montarGrade() {
      if (controle.gradeMontada && gradeSlides.childNodes.length === controle.totalSlides) {
        marcarSlideNaGrade();
        return;
      }
      gradeSlides.innerHTML = '';
      for (var numero = 1; numero <= controle.totalSlides; numero++) {
        (function (n) {
          var botao = document.createElement('button');
          botao.type = 'button';
          botao.className = 'grade-item';
          if (controle.miniaturas[n - 1]) {
            var img = document.createElement('img');
            img.src = controle.miniaturas[n - 1];
            img.alt = 'Slide ' + n;
            botao.appendChild(img);
          }
          var rotulo = document.createElement('span');
          rotulo.textContent = n;
          botao.appendChild(rotulo);
          botao.addEventListener('click', function () {
            gradeOverlay.hidden = true;
            comando('ir_para', n);
          });
          gradeSlides.appendChild(botao);
        })(numero);
      }
      controle.gradeMontada = true;
      marcarSlideNaGrade();
    }

    function marcarSlideNaGrade() {
      if (gradeOverlay.hidden) { return; }
      var itens = gradeSlides.children;
      for (var i = 0; i < itens.length; i++) {
        itens[i].classList.toggle('atual', i === controle.slideAtual - 1);
      }
    }

    // ---------------- Cronômetro ----------------

    function iniciarCronometro() {
      controle.inicioCronometro = Date.now();
      controle.timerCronometro = setInterval(atualizarCronometro, 1000);
      atualizarCronometro();
    }

    function atualizarCronometro() {
      var segundos = Math.floor((Date.now() - controle.inicioCronometro) / 1000);
      var h = Math.floor(segundos / 3600);
      var m = Math.floor((segundos % 3600) / 60);
      var s = segundos % 60;
      var mm = (m < 10 ? '0' : '') + m;
      var ss = (s < 10 ? '0' : '') + s;
      cronometroEl.textContent = h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    cronometroEl.addEventListener('click', function () {
      controle.inicioCronometro = Date.now();
      atualizarCronometro();
      vibrar();
    });

    // ---------------- Wake Lock (tela sempre acesa) ----------------

    async function pedirWakeLock() {
      // Degradação silenciosa: sem suporte, apenas não mantém a tela acesa.
      try {
        if (navigator.wakeLock && navigator.wakeLock.request) {
          controle.wakeLock = await navigator.wakeLock.request('screen');
        }
      } catch (e) { /* sem suporte ou sem permissão */ }
    }

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible' && !controle.encerrada && controle.codigo) {
        pedirWakeLock();
      }
    });
  }

  // ------------------------------------------------------------------
  // Ponto de entrada
  // ------------------------------------------------------------------

  document.addEventListener('DOMContentLoaded', function () {
    var pagina = document.body.getAttribute('data-pagina');
    if (pagina === 'apresentar') { iniciarApresentar(); }
    if (pagina === 'controle')   { iniciarControle(); }
  });
})();
