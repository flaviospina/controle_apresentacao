<?php
/**
 * SlideRemote — api/laser.php
 *
 * Canal rápido da caneta laser. A posição NÃO passa pelo banco de dados:
 * fica em um arquivinho JSON em cache/ (cache/laser_CODIGO.json), porque o
 * celular envia ~10 posições por segundo enquanto o laser está ligado.
 *
 *   POST  c, x, y, ativo   grava a posição (x e y normalizados de 0 a 1)
 *   GET   c                devolve { ativo, x, y }
 *
 * O laser é considerado desligado quando a posição fica sem atualização
 * por mais de 2 segundos (ou quando o celular envia ativo=0).
 */

require dirname(__DIR__) . '/config.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {
    $codigo = isset($_POST['c']) ? trim((string) $_POST['c']) : '';
    if (!validarCodigoSessao($codigo)) {
        responderJson(['erro' => 'Código de sessão inválido.'], 400);
    }

    $arquivo = arquivoLaser($codigo);
    $ativo   = isset($_POST['ativo']) && $_POST['ativo'] === '1';

    if (!$ativo) {
        @unlink($arquivo);
        responderJson(['ok' => true, 'ativo' => false]);
    }

    // Posição normalizada (0 a 1) em relação à área do slide.
    $x = isset($_POST['x']) ? (float) $_POST['x'] : 0.5;
    $y = isset($_POST['y']) ? (float) $_POST['y'] : 0.5;
    $x = max(0.0, min(1.0, $x));
    $y = max(0.0, min(1.0, $y));

    @file_put_contents($arquivo, json_encode(['x' => $x, 'y' => $y]), LOCK_EX);
    responderJson(['ok' => true, 'ativo' => true]);
}

if ($metodo === 'GET') {
    $codigo = isset($_GET['c']) ? trim((string) $_GET['c']) : '';
    if (!validarCodigoSessao($codigo)) {
        responderJson(['erro' => 'Código de sessão inválido.'], 400);
    }

    if (!laserAtivo($codigo)) {
        responderJson(['ok' => true, 'ativo' => false]);
    }

    $dados = json_decode((string) @file_get_contents(arquivoLaser($codigo)), true);
    if (!is_array($dados) || !isset($dados['x'], $dados['y'])) {
        responderJson(['ok' => true, 'ativo' => false]);
    }

    responderJson([
        'ok'    => true,
        'ativo' => true,
        'x'     => (float) $dados['x'],
        'y'     => (float) $dados['y'],
    ]);
}

responderJson(['erro' => 'Use GET ou POST.'], 405);
