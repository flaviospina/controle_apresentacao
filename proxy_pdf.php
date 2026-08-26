<?php
/**
 * SlideRemote — proxy_pdf.php
 *
 * Duas funções:
 *
 *   GET  ?file_id=...   Baixa a apresentação do Google (contornando o CORS,
 *                       que impede o download direto pelo navegador), guarda
 *                       em cache/{FILE_ID}.pdf e serve o PDF. Nas próximas
 *                       vezes serve direto do cache.
 *
 *   POST (multipart)    Recebe o upload manual de um PDF (plano B, para
 *                       quando o Drive estiver bloqueado na rede ou a
 *                       apresentação não estiver compartilhada). Devolve um
 *                       file_id interno ("up_..." ) em JSON.
 */

require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    receberUpload();
}

// ---------------------------------------------------------------------------
// GET — servir a apresentação (do cache ou baixando do Google)
// ---------------------------------------------------------------------------

$fileId = isset($_GET['file_id']) ? trim((string) $_GET['file_id']) : '';

if ($fileId === '' || !validarFileId($fileId)) {
    responderJson(['erro' => 'Identificador de arquivo inválido.'], 400);
}

// O FILE_ID já foi validado por regex, então é seguro usá-lo no caminho.
$caminhoCache = PASTA_CACHE . '/' . $fileId . '.pdf';

if (is_file($caminhoCache)) {
    servirPdf($caminhoCache);
}

// PDFs de upload não podem ser "rebaixados": se sumiram do cache, acabou.
if (strpos($fileId, 'up_') === 0) {
    responderJson([
        'erro' => 'O PDF enviado não está mais disponível no servidor. '
                . 'Volte à tela inicial e envie o arquivo novamente.',
    ], 404);
}

baixarDoGoogle($fileId, $caminhoCache);
servirPdf($caminhoCache);

// ---------------------------------------------------------------------------
// Funções
// ---------------------------------------------------------------------------

/**
 * Baixa a apresentação do Google e grava em $caminhoCache.
 * Tenta primeiro a exportação do Google Slides; se o arquivo não for uma
 * apresentação (ex.: um PDF guardado no Drive), tenta o download direto
 * do Drive. Encerra com erro JSON em caso de falha.
 */
function baixarDoGoogle(string $fileId, string $caminhoCache): void
{
    if (!function_exists('curl_init')) {
        responderJson([
            'erro' => 'A extensão cURL do PHP não está habilitada neste servidor. '
                    . 'Ative-a no cPanel em "Select PHP Version" > "Extensions".',
        ], 500);
    }

    limparCacheAntigo();

    $tentativas = [
        // Exportação em PDF de uma apresentação do Google Slides.
        'https://docs.google.com/presentation/d/' . $fileId . '/export/pdf',
        // Download direto de um arquivo do Drive (ex.: PDF já pronto).
        'https://drive.google.com/uc?export=download&id=' . $fileId,
    ];

    $ultimoErro = ['mensagem' => 'Falha desconhecida ao baixar a apresentação.', 'http' => 502];

    foreach ($tentativas as $url) {
        $resultado = baixarUrl($url, $caminhoCache);

        if ($resultado['ok']) {
            return;
        }

        // Arquivo grande no Drive: o Google exibe uma página de confirmação
        // ("não foi possível verificar vírus"). Extrai o token e tenta de novo.
        if ($resultado['confirm'] !== null) {
            $urlConfirmada = 'https://drive.google.com/uc?export=download'
                           . '&confirm=' . rawurlencode($resultado['confirm'])
                           . '&id=' . $fileId;
            $resultado = baixarUrl($urlConfirmada, $caminhoCache);
            if ($resultado['ok']) {
                return;
            }
        }

        $ultimoErro = $resultado;
    }

    responderJson(['erro' => $ultimoErro['mensagem']], $ultimoErro['http']);
}

/**
 * Baixa uma URL para um arquivo temporário e, se o conteúdo for um PDF
 * válido, move para $caminhoDestino.
 *
 * Retorna:
 *   ['ok' => true]                                   em caso de sucesso;
 *   ['ok' => false, 'mensagem', 'http', 'confirm']   em caso de falha
 *   ("confirm" traz o token da página de confirmação do Drive, se houver).
 */
function baixarUrl(string $url, string $caminhoDestino): array
{
    $falha = function (string $mensagem, int $http, ?string $confirm = null): array {
        return ['ok' => false, 'mensagem' => $mensagem, 'http' => $http, 'confirm' => $confirm];
    };

    $caminhoTemporario = $caminhoDestino . '.baixando.' . bin2hex(random_bytes(4));
    $arquivo = fopen($caminhoTemporario, 'wb');
    if ($arquivo === false) {
        return $falha('Não foi possível gravar na pasta cache/. Verifique as permissões da pasta (755).', 500);
    }

    $arquivoCookies = PASTA_CACHE . '/cookies.' . bin2hex(random_bytes(4)) . '.tmp';

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FILE           => $arquivo,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_COOKIEJAR      => $arquivoCookies,
        CURLOPT_COOKIEFILE     => $arquivoCookies,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
        // Se o download passar do limite, o cURL aborta sozinho.
        CURLOPT_MAXFILESIZE    => PDF_TAMANHO_MAXIMO,
    ]);

    $sucesso     = curl_exec($curl);
    $erroCurl    = curl_errno($curl);
    $codigoHttp  = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $urlEfetiva  = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    fclose($arquivo);
    @unlink($arquivoCookies);

    $limpar = function () use ($caminhoTemporario): void {
        @unlink($caminhoTemporario);
    };

    if ($erroCurl === CURLE_OPERATION_TIMEDOUT) {
        $limpar();
        return $falha('Tempo esgotado ao baixar a apresentação. A rede pode estar lenta ou o '
                    . 'arquivo é muito grande — tente novamente ou use a opção de enviar o PDF.', 504);
    }

    if ($sucesso === false || $erroCurl !== 0) {
        $limpar();
        return $falha('Não foi possível conectar ao Google para baixar a apresentação. '
                    . 'Verifique a conexão do servidor e tente novamente.', 502);
    }

    // Redirecionado para tela de login = arquivo não é público.
    if (strpos($urlEfetiva, 'accounts.google.com') !== false) {
        $limpar();
        return $falha(mensagemNaoCompartilhado(), 403);
    }

    if ($codigoHttp === 404) {
        $limpar();
        return $falha('Apresentação não encontrada. Confira se o link está correto '
                    . 'e se o arquivo não foi excluído do Drive.', 404);
    }

    if ($codigoHttp === 403 || $codigoHttp === 401) {
        $limpar();
        return $falha(mensagemNaoCompartilhado(), 403);
    }

    if ($codigoHttp !== 200) {
        $limpar();
        return $falha('O Google respondeu com um erro inesperado (HTTP ' . $codigoHttp . '). '
                    . 'Tente novamente em instantes.', 502);
    }

    // Confere a assinatura do arquivo: todo PDF começa com "%PDF".
    $inicio = (string) file_get_contents($caminhoTemporario, false, null, 0, 2048);

    if (strncmp($inicio, '%PDF', 4) === 0) {
        if (filesize($caminhoTemporario) > PDF_TAMANHO_MAXIMO) {
            $limpar();
            return $falha('A apresentação passa do limite de '
                        . (int) (PDF_TAMANHO_MAXIMO / 1024 / 1024) . ' MB.', 413);
        }
        if (!@rename($caminhoTemporario, $caminhoDestino)) {
            $limpar();
            return $falha('Não foi possível gravar o PDF na pasta cache/.', 500);
        }
        return ['ok' => true, 'mensagem' => '', 'http' => 200, 'confirm' => null];
    }

    // Não é PDF: veio uma página HTML do Google. Descobre o motivo.
    $confirm = null;
    if (preg_match('/confirm=([0-9A-Za-z_-]+)/', $inicio, $m)) {
        $confirm = $m[1];
    } elseif (preg_match('/name="confirm"\s+value="([^"]+)"/', $inicio, $m)) {
        $confirm = $m[1];
    }
    $limpar();

    if ($confirm !== null) {
        return $falha('Confirmação de download necessária.', 502, $confirm);
    }

    if (stripos($inicio, '<html') !== false || stripos($inicio, '<!doctype') !== false) {
        return $falha(mensagemNaoCompartilhado(), 403);
    }

    return $falha('O arquivo baixado não é uma apresentação nem um PDF. '
                . 'Confira se o link aponta para um Google Slides ou para um arquivo PDF.', 415);
}

/**
 * Mensagem padrão para arquivo sem compartilhamento público.
 */
function mensagemNaoCompartilhado(): string
{
    return 'A apresentação não está compartilhada publicamente. No Google Slides ou no Drive, '
         . 'clique em "Compartilhar", mude o acesso geral para "Qualquer pessoa com o link" '
         . 'e tente novamente. Alternativa: baixe o PDF no seu computador e use a opção '
         . '"Enviar PDF" na tela inicial.';
}

/**
 * Envia o PDF ao navegador e encerra.
 */
function servirPdf(string $caminho): void
{
    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string) filesize($caminho));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($caminho);
    exit;
}

/**
 * Remove do cache PDFs baixados do Drive com mais de 72 horas e arquivos
 * temporários órfãos, para não acumular lixo no servidor compartilhado.
 * Uploads ("up_*.pdf") seguem a validade das sessões (6 h) com folga de 24 h.
 */
function limparCacheAntigo(): void
{
    $agora = time();
    foreach ((array) glob(PASTA_CACHE . '/*') as $caminho) {
        $nome = basename($caminho);
        if ($nome === '.htaccess' || $nome === 'index.html' || !is_file($caminho)) {
            continue;
        }
        $idade = $agora - (int) filemtime($caminho);
        $limite = 72 * 3600;
        if (strpos($nome, 'up_') === 0) {
            $limite = 24 * 3600;
        } elseif (strpos($nome, 'laser_') === 0
            || strpos($nome, '.tmp') !== false || strpos($nome, '.baixando.') !== false) {
            $limite = 3600;
        }
        if ($idade > $limite) {
            @unlink($caminho);
        }
    }
}

/**
 * POST — recebe o upload manual de um PDF e devolve o file_id interno.
 */
function receberUpload(): void
{
    if (!isset($_FILES['arquivo'])) {
        responderJson(['erro' => 'Nenhum arquivo foi enviado.'], 400);
    }

    $upload = $_FILES['arquivo'];

    if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
        responderJson([
            'erro' => 'O PDF passa do limite de tamanho do servidor. Se possível, exporte a '
                    . 'apresentação com qualidade menor ou aumente "upload_max_filesize" no cPanel '
                    . '("Select PHP Version" > "Options").',
        ], 413);
    }

    if ($upload['error'] !== UPLOAD_ERR_OK) {
        responderJson(['erro' => 'Falha no envio do arquivo (código ' . (int) $upload['error'] . '). Tente novamente.'], 400);
    }

    if ($upload['size'] > PDF_TAMANHO_MAXIMO) {
        responderJson([
            'erro' => 'O PDF passa do limite de ' . (int) (PDF_TAMANHO_MAXIMO / 1024 / 1024) . ' MB.',
        ], 413);
    }

    $inicio = (string) file_get_contents($upload['tmp_name'], false, null, 0, 4);
    if (strncmp($inicio, '%PDF', 4) !== 0) {
        responderJson(['erro' => 'O arquivo enviado não é um PDF válido.'], 415);
    }

    limparCacheAntigo();

    $fileId  = 'up_' . bin2hex(random_bytes(8));
    $destino = PASTA_CACHE . '/' . $fileId . '.pdf';

    if (!move_uploaded_file($upload['tmp_name'], $destino)) {
        responderJson(['erro' => 'Não foi possível gravar o PDF na pasta cache/. Verifique as permissões (755).'], 500);
    }

    responderJson(['ok' => true, 'file_id' => $fileId], 201);
}
