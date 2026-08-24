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
