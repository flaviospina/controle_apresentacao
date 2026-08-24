<?php
/**
 * SlideRemote — Configuração central
 *
 * ÚNICO arquivo que precisa ser editado na instalação.
 * Preencha as credenciais do banco de dados criadas no cPanel
 * (MySQL Databases) antes de enviar para o servidor.
 *
 * IMPORTANTE: este arquivo nunca deve ser acessado diretamente
 * pelo navegador — ele apenas é incluído pelos demais scripts.
 */

// ---------------------------------------------------------------------------
// Credenciais do banco de dados (edite estas quatro linhas)
// ---------------------------------------------------------------------------
// No HostGator o nome do banco e do usuário costumam ter o prefixo da conta,
// por exemplo: 'minhaconta_slideremote'.
define('BD_SERVIDOR', 'localhost');
define('BD_NOME',     'SEU_BANCO_AQUI');
define('BD_USUARIO',  'SEU_USUARIO_AQUI');
define('BD_SENHA',    'SUA_SENHA_AQUI');

// ---------------------------------------------------------------------------
// Ajustes gerais (normalmente não é preciso mexer)
// ---------------------------------------------------------------------------

// Fuso horário usado nos registros de data/hora das sessões.
date_default_timezone_set('America/Sao_Paulo');

// Sessões sem atividade há mais tempo que isto são consideradas encerradas
// e removidas na criação de uma nova sessão (não depende de cron).
define('SESSAO_VALIDADE_HORAS', 6);

// Pasta onde os PDFs baixados do Google Drive ficam em cache.
define('PASTA_CACHE', __DIR__ . '/cache');

// Tamanho máximo aceito para PDF (download do Drive ou upload manual), em bytes.
define('PDF_TAMANHO_MAXIMO', 50 * 1024 * 1024); // 50 MB

// Em produção não exibimos erros do PHP na tela (evita vazar detalhes).
// Durante testes locais, troque para '1' se precisar depurar.
ini_set('display_errors', '0');
error_reporting(E_ALL);

/**
 * Abre (uma única vez por requisição) a conexão PDO com o MySQL.
 *
 * - charset utf8mb4 para acentuação correta;
 * - exceções em caso de erro (facilita o tratamento nas APIs);
 * - prepared statements reais (emulação desligada).
 */
function conectarBanco(): PDO
{
    static $conexao = null;

    if ($conexao === null) {
        $dsn = 'mysql:host=' . BD_SERVIDOR . ';dbname=' . BD_NOME . ';charset=utf8mb4';
        try {
            $conexao = new PDO($dsn, BD_USUARIO, BD_SENHA, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Nunca exibir a mensagem original: ela pode conter usuário/host.
            responderJson([
                'erro' => 'Não foi possível conectar ao banco de dados. '
                        . 'Confira as credenciais em config.php.',
            ], 500);
        }
    }

    return $conexao;
}

/**
 * Envia uma resposta JSON com o código HTTP indicado e encerra o script.
 * Usada por todas as APIs para manter o formato uniforme.
 */
function responderJson(array $dados, int $codigoHttp = 200): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------------------
// Identificação de arquivos do Google Drive / Google Slides
// ---------------------------------------------------------------------------

/**
 * Verifica se um FILE_ID tem formato aceitável antes de qualquer uso.
 * Aceita IDs do Drive (letras, números, "-" e "_") e os IDs internos
 * de PDFs enviados por upload ("up_" + 16 dígitos hexadecimais).
 */
function validarFileId(string $fileId): bool
{
    return preg_match('/^[A-Za-z0-9_-]{20,100}$/', $fileId) === 1
        || preg_match('/^up_[a-f0-9]{16}$/', $fileId) === 1;
}

/**
 * Extrai o FILE_ID de um link do Google Slides / Google Drive.
 *
 * Formatos reconhecidos:
 *   - https://docs.google.com/presentation/d/ID/edit...
 *   - https://drive.google.com/file/d/ID/view...
 *   - https://drive.google.com/open?id=ID  (e qualquer URL com ?id=ID)
 *   - o próprio ID colado diretamente
 *   - links encurtados (resolvidos seguindo o redirecionamento)
 *
 * Retorna ['ok' => true, 'file_id' => ...] em caso de sucesso ou
 * ['ok' => false, 'erro' => mensagem em português] em caso de falha.
 */
function extrairFileId(string $entrada, bool $seguirRedirecionamento = true): array
{
    $entrada = trim($entrada);

    if ($entrada === '') {
        return ['ok' => false, 'erro' => 'Informe o link da apresentação.'];
    }

    // O usuário colou o ID puro, sem URL.
    if (preg_match('/^[A-Za-z0-9_-]{20,100}$/', $entrada)) {
        return ['ok' => true, 'file_id' => $entrada];
    }

    // Link de PUBLICAÇÃO (/d/e/2PACX-...): esse ID não permite exportar o PDF.
    if (preg_match('#/d/e/[A-Za-z0-9_-]{15,}#', $entrada)) {
        return [
            'ok'   => false,
            'erro' => 'Este é um link de publicação na web (contém "/d/e/"). '
                    . 'Use o link de COMPARTILHAMENTO: no Google Slides, clique em '
                    . '"Compartilhar" e depois em "Copiar link".',
        ];
    }

    // Formato /presentation/d/ID, /file/d/ID etc.
    if (preg_match('#/(?:presentation|document|spreadsheets|file)/d/([A-Za-z0-9_-]{20,100})#', $entrada, $m)) {
        return ['ok' => true, 'file_id' => $m[1]];
    }

    // Formato ...?id=ID (drive.google.com/open?id=..., uc?id=... etc.)
    if (preg_match('/[?&]id=([A-Za-z0-9_-]{20,100})/', $entrada, $m)) {
        return ['ok' => true, 'file_id' => $m[1]];
    }

    // Pode ser um link encurtado: segue o redirecionamento e tenta de novo.
    if ($seguirRedirecionamento && preg_match('#^https?://#i', $entrada)) {
        $urlFinal = resolverRedirecionamento($entrada);
        if ($urlFinal !== null && $urlFinal !== $entrada) {
            return extrairFileId($urlFinal, false);
        }
    }

    return [
        'ok'   => false,
        'erro' => 'Não foi possível reconhecer este link. Cole o link de compartilhamento '
                . 'do Google Slides ou do arquivo no Google Drive.',
    ];
}

/**
 * Segue os redirecionamentos de um link (ex.: encurtadores) e devolve a
 * URL final, ou null se não foi possível resolver.
 */
function resolverRedirecionamento(string $url): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_NOBODY         => true,      // só os cabeçalhos interessam
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (SlideRemote)',
    ]);
    curl_exec($curl);
    $urlFinal = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);

    return is_string($urlFinal) && $urlFinal !== '' ? $urlFinal : null;
}
