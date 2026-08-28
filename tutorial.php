<?php
/**
 * SlideRemote — tutorial.php
 *
 * Tutorial ilustrado de uso do sistema, com as telas reais da lousa e do
 * celular. Pode ser impresso (Ctrl+P) para deixar junto da lousa digital.
 */
require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SlideRemote — Tutorial de uso</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='18' fill='%230b1628'/%3E%3Ctext x='50' y='70' font-size='58' text-anchor='middle' fill='%2322d3ee' font-family='Arial'%3ES%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/estilo.css">
</head>
<body class="pagina-tutorial">

<header class="tutorial-topo">
  <h1 class="logo">Slide<span>Remote</span></h1>
  <p class="subtitulo">Tutorial de uso — passo a passo com as telas reais</p>
  <nav>
    <a class="link-tutorial" href="apresentar.php">← Voltar para a tela da lousa</a>
  </nav>
</header>

<main class="tutorial-conteudo">

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">1</span> Antes de começar: compartilhe a apresentação</h2>
    <p>
      No <strong>Google Slides</strong> (ou no arquivo PDF dentro do Drive), clique no botão
      <strong>Compartilhar</strong>, mude o <em>Acesso geral</em> de "Restrito" para
      <strong>"Qualquer pessoa com o link"</strong> (papel <em>Leitor</em>) e use
      <strong>Copiar link</strong>. É esse link que a lousa vai usar.
    </p>
    <p class="atencao">
      ⚠ Não use o link de <em>Arquivo → Compartilhar → Publicar na web</em> (ele contém
      <code>/d/e/</code> no endereço e não funciona). Sempre o botão <strong>Compartilhar</strong>.
    </p>
    <p>
      <strong>Plano B:</strong> baixe a apresentação em PDF
      (<em>Arquivo → Fazer download → Documento PDF</em>) e leve em um pendrive ou no
      próprio celular — serve para o caso de o Drive estar bloqueado na rede da escola.
    </p>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">2</span> Na lousa digital: abra o SlideRemote</h2>
    <p>
      No navegador da lousa, acesse <strong>apresentar.php</strong>, cole o link da
      apresentação no campo e toque em <strong>Apresentar</strong>. Se preferir o plano B,
      toque em <strong>Enviar um arquivo PDF</strong> e escolha o arquivo.
    </p>
    <figure>
      <img src="assets/tutorial/lousa_inicial.png" alt="Tela inicial da lousa com o campo do link">
      <figcaption>Tela inicial na lousa: cole o link e toque em "Apresentar".</figcaption>
    </figure>
    <p>
      Uma barra de progresso mostra o preparo dos slides. Quando terminar, aparece o
      painel de conexão do celular.
    </p>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">3</span> Conecte o celular</h2>
    <div class="par-imagens">
      <figure class="larga">
        <img src="assets/tutorial/lousa_pareamento.png" alt="Painel com QR Code e código de 4 dígitos na lousa">
        <figcaption>A lousa mostra o QR Code e o código de 4 dígitos.</figcaption>
      </figure>
      <figure class="estreita">
        <img src="assets/tutorial/celular_codigo.png" alt="Tela do celular pedindo o código de 4 dígitos">
        <figcaption>No celular, digite o código (conecta sozinho no 4º dígito).</figcaption>
      </figure>
    </div>
    <p>
      <strong>Jeito mais rápido:</strong> aponte a câmera do celular para o QR Code —
      abre a tela de controle já conectada. <strong>Ou</strong> acesse
      <strong>controle.php</strong> no navegador do celular e digite o código mostrado na lousa.
    </p>
    <p>
      Quando o celular conecta, o painel some da lousa e o pontinho no canto inferior
      direito fica <strong style="color:#34d399;">verde</strong>. Toque na tela da lousa
      uma vez para entrar em <strong>tela cheia</strong>.
      (Tecle <kbd>C</kbd> na lousa para ver o código de novo a qualquer momento.)
    </p>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">4</span> Controle os slides</h2>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_controle.png" alt="Tela de controle no celular">
        <figcaption>Tela de controle no celular.</figcaption>
      </figure>
      <figure class="larga">
        <img src="assets/tutorial/lousa_apresentando.png" alt="Lousa exibindo o slide com o indicador verde">
        <figcaption>A lousa reage em menos de 1 segundo a cada toque.</figcaption>
      </figure>
    </div>
    <ul>
      <li><strong>Próximo / Anterior</strong> — as duas áreas grandes no centro. Dá para usar com
          uma mão e sem olhar: o botão maior, embaixo, é sempre o <em>Próximo</em>, e o celular
          <strong>vibra</strong> a cada comando.</li>
      <li><strong>Miniaturas "Atual" e "Próximo"</strong> — no alto, para você saber o que vem a seguir.</li>
      <li><strong>Contador "2 / 5"</strong> — slide atual e total, no cabeçalho.</li>
      <li><strong>Cronômetro</strong> — conta o tempo de apresentação; <em>toque nele para zerar</em>.</li>
      <li>A tela do celular <strong>não apaga</strong> durante a apresentação (em aparelhos compatíveis).</li>
    </ul>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">5</span> Pule direto para um slide</h2>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_grade.png" alt="Grade de miniaturas no celular">
        <figcaption>Grade de miniaturas: o slide atual fica destacado em azul.</figcaption>
      </figure>
      <div class="texto-ao-lado">
        <p>
          Toque em <strong>Ir para slide</strong> e escolha a miniatura. Útil para voltar
          a um gráfico durante as perguntas, por exemplo, sem passar slide por slide.
        </p>
      </div>
    </div>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">6</span> Tela preta (pausa)</h2>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_blackout.png" alt="Botão de tela preta ativo no celular">
        <figcaption>Botão "Tela preta" ligado (fica âmbar).</figcaption>
      </figure>
      <div class="texto-ao-lado">
        <p>
          <strong>Tela preta</strong> escurece a lousa na hora — bom para trazer a atenção
          da turma de volta para você. Toque de novo para reexibir o slide exatamente
          onde estava.
        </p>
      </div>
    </div>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">7</span> Trave a lousa contra toques acidentais</h2>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_travado.png" alt="Botão Lousa travada ativo no celular">
        <figcaption>Com a trava ligada, o botão fica azul e muda para "Lousa travada".</figcaption>
      </figure>
      <figure class="larga">
        <img src="assets/tutorial/lousa_travada.png" alt="Aviso de toque travado no canto da lousa">
        <figcaption>A lousa mostra o aviso "🔒 Toque da lousa travado" no canto.</figcaption>
      </figure>
    </div>
    <p>
      Com <strong>Travar lousa</strong> ligado, encostar na lousa (ou nas setas do teclado
      dela) <strong>não troca de slide</strong> — só o celular comanda. Se alguém tocar,
      o aviso pisca para explicar por que nada aconteceu. Toque de novo no botão para destravar.
    </p>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">8</span> Caneta laser</h2>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_laser.png" alt="Laser ligado no celular com a barra Centralizar">
        <figcaption>Laser ligado: aparece a barra com o botão "Centralizar".</figcaption>
      </figure>
      <figure class="larga">
        <img src="assets/tutorial/lousa_laser.png" alt="Ponto vermelho do laser sobre o slide na lousa">
        <figcaption>O ponto vermelho segue o movimento do celular.</figcaption>
      </figure>
    </div>
    <ol>
      <li><strong>Aponte o celular para a lousa</strong> (segure como um controle remoto de TV)
          e toque em <strong>Laser</strong>. O ponto nasce no centro da tela.</li>
      <li><strong>Gire o pulso</strong> para os lados para mover na horizontal e
          <strong>incline</strong> para cima/baixo para a vertical. Movimentos curtos bastam.</li>
      <li>O ponto "fugiu"? Aponte o celular de volta para o meio da lousa e toque em
          <strong>🎯 Centralizar</strong> — o ponto volta ao centro na hora.</li>
      <li>Toque em <strong>Laser</strong> de novo para desligar.</li>
    </ol>
    <p class="atencao">
      📱 No <strong>iPhone</strong>, o navegador pede permissão de "movimento e orientação"
      no primeiro uso — aceite para o laser funcionar.
    </p>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_touchpad.png" alt="Modo touchpad do laser">
        <figcaption>Sem sensor de movimento, abre o modo touchpad.</figcaption>
      </figure>
      <div class="texto-ao-lado">
        <p>
          Se o celular <strong>não tiver sensor</strong> (ou a permissão for negada), abre
          automaticamente um <strong>touchpad</strong>: arraste o dedo na área pontilhada
          e o ponto acompanha na lousa. <strong>Desligar laser</strong> fecha o modo.
        </p>
      </div>
    </div>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">9</span> Encerrar a apresentação</h2>
    <div class="par-imagens">
      <figure class="estreita">
        <img src="assets/tutorial/celular_fim.png" alt="Tela de apresentação encerrada no celular">
        <figcaption>Depois de confirmar, as duas telas mostram o encerramento.</figcaption>
      </figure>
      <div class="texto-ao-lado">
        <p>
          Toque em <strong>Encerrar</strong> e confirme. A lousa mostra
          "Apresentação encerrada" com um botão para começar outra.
          A sessão morre junto — o código de 4 dígitos deixa de valer.
        </p>
      </div>
    </div>
  </section>

  <!-- ================================================================ -->
  <section class="passo">
    <h2><span class="num">10</span> Dicas rápidas</h2>
    <ul>
      <li>Se a internet do celular cair, a lousa <strong>mantém o slide atual</strong> —
          nada volta ao início. O pontinho do cabeçalho do celular fica vermelho até a
          conexão voltar.</li>
      <li>As <strong>setas do teclado</strong> da lousa continuam funcionando como reserva
          (◀ ▶ trocam slide, <kbd>B</kbd> tela preta, <kbd>F</kbd> tela cheia,
          <kbd>C</kbd> mostra o código) — exceto com a trava ligada.</li>
      <li>Faça um <strong>ensaio na rede da escola</strong> alguns dias antes: teste o link
          real, o QR Code no Wi-Fi e no 4G e o plano B do PDF.</li>
      <li>Mensagens de erro e soluções detalhadas estão no <strong>README.md</strong>
          do sistema (seção "Problemas comuns").</li>
    </ul>
    <p class="dica">💡 Este tutorial pode ser impresso (Ctrl+P) e deixado junto da lousa digital.</p>
  </section>

</main>

<footer class="tutorial-rodape">
  <div class="faixa-logos">
    <img src="assets/logos/logo1.png" alt="Logo 1">
    <img src="assets/logos/logo2.png" alt="Logo 2">
    <img src="assets/logos/logo3.png" alt="Logo 3">
  </div>
  <p class="dica">SlideRemote — controle de apresentações pela rede, sem hardware apontador.</p>
</footer>

</body>
</html>
