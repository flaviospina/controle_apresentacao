<?php
/**
 * SlideRemote — api/estado.php (GET)
 *
 * Devolve o estado atual da sessão em JSON. É consultada por polling
 * tanto pela lousa (apresentar.php) quanto pelo celular (controle.php).
 *
 * Parâmetros:
 *   c      código de pareamento (4 dígitos)
 *   papel  "controle" quando quem consulta é o celular — registra a
 *          presença dele para o indicador "pareado" da lousa
 *
 * Resposta 200: { ok, slide_atual, total_slides, blackout, ativa,
 *                 controle_conectado, origem, file_id, atualizado_em }
 */

require dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responderJson(['erro' => 'Use o método GET.'], 405);
}

$codigo = isset($_GET['c']) ? trim((string) $_GET['c']) : '';
$papel  = isset($_GET['papel']) ? (string) $_GET['papel'] : '';

if (!validarCodigoSessao($codigo)) {
    responderJson(['erro' => 'Código de sessão inválido. Digite os 4 dígitos mostrados na lousa.'], 400);
}

$bd = conectarBanco();

// Registra a presença do celular (mantém a sessão viva e acende o
// indicador verde na lousa).
if ($papel === 'controle') {
    $bd->prepare('UPDATE sessoes SET controle_visto_em = NOW() WHERE codigo = ? AND ativa = 1')
       ->execute([$codigo]);
}

$sessao = buscarSessaoPorCodigo($bd, $codigo);

if ($sessao === null) {
    responderJson(['erro' => 'Sessão não encontrada. Confira o código mostrado na lousa.'], 404);
}

responderJson(formatarEstadoSessao($sessao));
