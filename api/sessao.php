<?php
/**
 * SlideRemote — api/sessao.php (POST)
 *
 * Cria uma nova sessão de apresentação e devolve o código de pareamento.
 *
 * Parâmetros (form-urlencoded):
 *   link     link do Google Slides/Drive (ou o FILE_ID puro)  — origem "drive"
 *   file_id  ID interno "up_..." devolvido pelo upload        — origem "upload"
 *            (envie um OU outro)
 *
 * Resposta 201: { ok, codigo, file_id, origem }
 *
 * A limpeza de sessões paradas há mais de SESSAO_VALIDADE_HORAS acontece
 * aqui, a cada criação — sem depender de cron.
 */

require dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['erro' => 'Use o método POST.'], 405);
}

$link   = isset($_POST['link']) ? trim((string) $_POST['link']) : '';
$fileId = isset($_POST['file_id']) ? trim((string) $_POST['file_id']) : '';

if ($link === '' && $fileId === '') {
    responderJson(['erro' => 'Informe o link da apresentação ou envie um PDF.'], 400);
}

// Descobre o FILE_ID e a origem.
if ($fileId !== '' && strpos($fileId, 'up_') === 0) {
    // PDF enviado por upload: o arquivo precisa existir no cache.
    if (!validarFileId($fileId) || !is_file(PASTA_CACHE . '/' . $fileId . '.pdf')) {
        responderJson(['erro' => 'PDF enviado não encontrado. Envie o arquivo novamente.'], 404);
    }
    $origem = 'upload';
} else {
    $resultado = extrairFileId($link !== '' ? $link : $fileId);
    if (!$resultado['ok']) {
        responderJson(['erro' => $resultado['erro']], 422);
    }
    $fileId = $resultado['file_id'];
    $origem = 'drive';
}

$bd = conectarBanco();

// Limpeza: remove sessões sem atividade há mais de SESSAO_VALIDADE_HORAS.
$bd->prepare('DELETE FROM sessoes WHERE atualizada_em < NOW() - INTERVAL ' . (int) SESSAO_VALIDADE_HORAS . ' HOUR')
   ->execute();

// Sorteia um código de 4 dígitos que não esteja em uso por sessão ativa.
$codigo = null;
$consultaCodigo = $bd->prepare('SELECT id FROM sessoes WHERE codigo = ? AND ativa = 1 LIMIT 1');
for ($tentativa = 0; $tentativa < 60; $tentativa++) {
    $candidato = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $consultaCodigo->execute([$candidato]);
    if ($consultaCodigo->fetch() === false) {
        $codigo = $candidato;
        break;
    }
}
if ($codigo === null) {
    responderJson(['erro' => 'Não há códigos de sessão livres no momento. Tente novamente em instantes.'], 503);
}

$bd->prepare('INSERT INTO sessoes (codigo, file_id, origem) VALUES (?, ?, ?)')
   ->execute([$codigo, $fileId, $origem]);

responderJson([
    'ok'      => true,
    'codigo'  => $codigo,
    'file_id' => $fileId,
    'origem'  => $origem,
], 201);
