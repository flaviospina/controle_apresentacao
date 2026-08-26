<?php
/**
 * SlideRemote — api/comando.php (POST)
 *
 * Recebe os comandos do celular (e alguns da própria lousa) e atualiza
 * o estado da sessão.
 *
 * Parâmetros (form-urlencoded):
 *   c      código de pareamento (4 dígitos)
 *   acao   proximo | anterior | ir_para | blackout | travar | encerrar | definir_total
 *   valor  número do slide (ir_para) ou total de slides (definir_total)
 *   papel  "controle" quando o comando vem do celular
 *
 * Resposta 200: o estado já atualizado, no mesmo formato de estado.php —
 * assim o celular reflete a mudança imediatamente, sem esperar o polling.
 */

require dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['erro' => 'Use o método POST.'], 405);
}

$codigo = isset($_POST['c']) ? trim((string) $_POST['c']) : '';
$acao   = isset($_POST['acao']) ? (string) $_POST['acao'] : '';
$valor  = isset($_POST['valor']) ? (int) $_POST['valor'] : 0;
$papel  = isset($_POST['papel']) ? (string) $_POST['papel'] : '';

if (!validarCodigoSessao($codigo)) {
    responderJson(['erro' => 'Código de sessão inválido.'], 400);
}

$bd     = conectarBanco();
$sessao = buscarSessaoPorCodigo($bd, $codigo);

if ($sessao === null || !(bool) $sessao['ativa']) {
    responderJson(['erro' => 'Sessão não encontrada ou já encerrada.'], 404);
}

// Marca a presença do celular junto com o comando, quando for o caso.
$marcaControle = ($papel === 'controle') ? ', controle_visto_em = NOW()' : '';

// Com a lousa travada, comandos de navegação vindos da PRÓPRIA lousa
// (toque acidental, teclado) são recusados — só o celular comanda.
$acoesDeNavegacao = ['proximo', 'anterior', 'ir_para', 'blackout'];
if ($papel !== 'controle'
    && (bool) ($sessao['lousa_travada'] ?? false)
    && in_array($acao, $acoesDeNavegacao, true)) {
    responderJson(formatarEstadoSessao($sessao));
}

// Todas as trocas de slide usam LEAST/GREATEST direto no SQL: a atualização
// é atômica e nunca sai do intervalo [1, total_slides], mesmo com a lousa e
// o celular comandando ao mesmo tempo.
switch ($acao) {
    case 'proximo':
        $sql    = "UPDATE sessoes SET slide_atual = LEAST(GREATEST(total_slides, 1), slide_atual + 1) $marcaControle
                    WHERE id = ? AND ativa = 1";
        $params = [$sessao['id']];
        break;

    case 'anterior':
        $sql    = "UPDATE sessoes SET slide_atual = GREATEST(1, slide_atual - 1) $marcaControle
                    WHERE id = ? AND ativa = 1";
        $params = [$sessao['id']];
        break;

    case 'ir_para':
        $total = (int) $sessao['total_slides'];
        if ($valor < 1 || ($total > 0 && $valor > $total)) {
            responderJson(['erro' => "Slide fora do intervalo (1 a $total)."], 422);
        }
        $sql    = "UPDATE sessoes SET slide_atual = ? $marcaControle WHERE id = ? AND ativa = 1";
        $params = [$valor, $sessao['id']];
        break;

    case 'blackout':
        $sql    = "UPDATE sessoes SET blackout = 1 - blackout $marcaControle WHERE id = ? AND ativa = 1";
        $params = [$sessao['id']];
        break;

    case 'travar':
        // Liga/desliga a trava de toque da lousa (comando do celular).
        $sql    = "UPDATE sessoes SET lousa_travada = 1 - lousa_travada $marcaControle WHERE id = ? AND ativa = 1";
        $params = [$sessao['id']];
        break;

    case 'encerrar':
        $sql    = "UPDATE sessoes SET ativa = 0 $marcaControle WHERE id = ?";
        $params = [$sessao['id']];
        break;

    case 'definir_total':
        // Enviado pela lousa quando o PDF termina de carregar.
        if ($valor < 1 || $valor > 2000) {
            responderJson(['erro' => 'Total de slides inválido.'], 422);
        }
        $sql    = "UPDATE sessoes SET total_slides = ?, slide_atual = LEAST(slide_atual, ?) $marcaControle
                    WHERE id = ? AND ativa = 1";
        $params = [$valor, $valor, $sessao['id']];
        break;

    default:
        responderJson(['erro' => 'Ação desconhecida. Use: proximo, anterior, ir_para, blackout, travar, encerrar ou definir_total.'], 422);
}

$stmt = $bd->prepare($sql);
$stmt->execute($params);

$sessao = buscarSessaoPorCodigo($bd, $codigo);
responderJson(formatarEstadoSessao($sessao));
