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

// Client ID do OAuth "Login com Google" (público). Requer backend/config/google.php.
function get_google_client_id(): string
{
    static $clientId = null;
    if ($clientId === null) {
        $cfg = require __DIR__ . '/../config/google.php';
        $clientId = trim((string)($cfg['client_id'] ?? ''));
    }
    return $clientId;
}

// Normaliza celular para apenas dígitos, ex: (11) 99999-8888 -> 11999998888
function normalizar_celular(string $celular): string
{
    return preg_replace('/\D/', '', $celular) ?? '';
}

// Busca o participante (id, is_admin) pelo token de sessão. Retorna null se não existir.
function get_participante_por_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.is_admin
         FROM participantes p
         WHERE p.token = :token
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $linha = $stmt->fetch();
    return $linha !== false ? $linha : null;
}

// Confirma que o token pertence a um administrador.
function exigir_admin(): array
{
    $pdo = get_pdo();
    $token = trim((string)(json_input()['participante_token'] ?? ''));
    if ($token === '') {
        erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
    }
    $participante = get_participante_por_token($pdo, $token);
    if (!$participante) {
        erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
    }
    if (!$participante['is_admin']) {
        erro(403, 'Acesso restrito ao administrador.');
    }
    return $participante;
}

// Configuração de e-mail (SMTP). Requer backend/config/email.php.
function get_email_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/email.php';
    }
    return $cfg;
}

// Valida CPF brasileiro (algoritmo dos dígitos verificadores).
function validar_cpf(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digito = ($soma * 10) % 11;
        if ($digito === 10) $digito = 0;
        if ((int)$cpf[$t] !== $digito) {
            return false;
        }
    }
    return true;
}

// Normaliza CPF para apenas 11 dígitos.
function normalizar_cpf(string $cpf): string
{
    return preg_replace('/\D/', '', $cpf) ?? '';
}
