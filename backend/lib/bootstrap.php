<?php
// =====================================================================
// Bootstrap comum a todos os endpoints da API.
// =====================================================================

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Ajuste aqui se o front-end um dia rodar em outro domínio/porta.
// Como front-end e API estão no mesmo servidor/domínio, CORS normalmente
// nem é necessário. Deixe comentado a menos que precise.
// header('Access-Control-Allow-Origin: https://seu-dominio.com.br');

function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function responder(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function erro(int $statusCode, string $mensagem, array $extra = []): void
{
    responder($statusCode, array_merge(['sucesso' => false, 'erro' => $mensagem], $extra));
}

function sucesso(array $dados = []): void
{
    responder(200, array_merge(['sucesso' => true], $dados));
}

function get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/../config/database.php';

    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['dbname'],
        $cfg['sslmode'] ?? 'prefer'
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('[sorteio] Falha de conexão com o banco: ' . $e->getMessage());
        erro(503, 'Não foi possível conectar ao banco de dados no momento.');
    }

    return $pdo;
}

// Só aceita POST em todos os endpoints da API (simplicidade e segurança básica)
function exigir_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erro(405, 'Método não permitido.');
    }
}

function validar_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Normaliza celular para apenas dígitos, ex: (11) 99999-8888 -> 11999998888
function normalizar_celular(string $celular): string
{
    return preg_replace('/\D/', '', $celular) ?? '';
}
