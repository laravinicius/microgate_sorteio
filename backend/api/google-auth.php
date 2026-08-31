<?php
// =====================================================================
// POST /api/google-auth.php
// Único ponto de autenticação do sorteio: login exclusivo com Google.
// Valida o ID token (JWT) junto ao Google e:
//   - vincula conta já cadastrada pelo e-mail (grava google_sub), ou
//   - cria participante novo com os dados verificados do Google.
//
// Body (JSON): { credential }  (ID token emitido pelo Google Identity Services)
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

verificar_rate_limit('google-auth', 5, 60);

$credential = trim((string)($dados['credential'] ?? ''));
if ($credential === '') {
    erro(422, 'Credencial do Google ausente.');
}

$clientId = get_google_client_id();
if ($clientId === '') {
    error_log('[sorteio] GOOGLE_CLIENT_ID não configurado em backend/config/google.php.');
    erro(500, 'Autenticação com Google indisponível no momento.');
}

// Verifica o ID token no endpoint oficial do Google (sem precisar do client_secret).
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
$resposta = @file_get_contents($url, false, $ctx);

if ($resposta === false) {
    erro(502, 'Não foi possível validar o login com Google. Tente novamente.');
}
$payload = json_decode($resposta, true);
if (!is_array($payload) || isset($payload['error']) || isset($payload['error_description'])) {
    erro(401, 'Login com Google não autorizado.');
}

// O token precisa ter sido emitido para o nosso Client ID.
if (($payload['aud'] ?? '') !== $clientId) {
    erro(401, 'Login com Google não autorizado.');
}
if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)) {
    erro(401, 'Login com Google não autorizado.');
}
if (!isset($payload['exp']) || (int)$payload['exp'] < time()) {
    erro(401, 'Sessão do Google expirada. Entre novamente.');
}
$emailVerificado = $payload['email_verified'] ?? false;
if ($emailVerificado !== true && $emailVerificado !== 'true') {
    erro(401, 'E-mail do Google não verificado.');
}

$googleSub = trim((string)($payload['sub'] ?? ''));
$email     = mb_strtolower(trim((string)($payload['email'] ?? '')));
$nome      = trim((string)($payload['name'] ?? ''));
if ($googleSub === '' || $email === '' || !validar_email($email)) {
    erro(401, 'Dados insuficientes retornados pelo Google.');
}

$pdo = get_pdo();

// Consulta base (já com o número da sorte quando existir)
$colunas = 'SELECT p.id, p.token, p.is_admin, p.celular, p.cpf, p.consentimento_em, p.nome_completo,
                   ns.numero AS numero_sorte
            FROM participantes p
            LEFT JOIN numeros_sorte ns ON ns.participante_id = p.id';

$buscar = function (PDO $pdo, string $sql, array $params): ?array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $linha = $stmt->fetch();
    return $linha !== false ? $linha : null;
};

function responder_login(array $p): void
{
    sucesso([
        'participante_token' => $p['token'],
        'nome'               => $p['nome_completo'],
        'is_admin'           => (bool)$p['is_admin'],
        'tem_numero'         => $p['numero_sorte'] !== null,
        'numero_sorte'       => $p['numero_sorte'],
        'precisa_completar'  => $p['celular'] === null || $p['cpf'] === null || $p['consentimento_em'] === null,
    ]);
}

// 1) Já vinculado a esta conta Google
$participante = $buscar($pdo, "$colunas WHERE p.google_sub = :sub LIMIT 1", ['sub' => $googleSub]);
if ($participante) {
    responder_login($participante);
}

// 2) E-mail já cadastrado (cadastro antigo do formulário) -> vincula a conta Google
$participante = $buscar($pdo, "$colunas WHERE p.email = :email LIMIT 1", ['email' => $email]);
if ($participante) {
    if ($participante['google_sub'] !== null && $participante['google_sub'] !== $googleSub) {
        erro(409, 'Este e-mail já está vinculado a outra conta do Google.');
    }
    $upd = $pdo->prepare('UPDATE participantes SET google_sub = :sub WHERE id = :id');
    $upd->execute(['sub' => $googleSub, 'id' => $participante['id']]);
    responder_login($participante);
}

// 3) Novo participante via Google (nome + e-mail verificados; celular/consentimento virão depois)
try {
    $stmt = $pdo->prepare(
        'INSERT INTO participantes (nome_completo, email, google_sub, ip_origem, user_agent)
         VALUES (:nome, :email, :sub, :ip, :ua)
         RETURNING id, token, is_admin, celular, cpf, consentimento_em, nome_completo'
    );
    $stmt->execute([
        'nome'  => $nome,
        'email' => $email,
        'sub'   => $googleSub,
        'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
    $participante = $stmt->fetch();
} catch (PDOException $e) {
    // 23505 = unique_violation (corrida: o mesmo e-mail cadastrado entre a consulta e o INSERT)
    if ($e->getCode() === '23505') {
        $participante = $buscar($pdo, "$colunas WHERE p.email = :email LIMIT 1", ['email' => $email]);
        if (!$participante) {
            throw $e;
        }
        if ($participante['google_sub'] !== null && $participante['google_sub'] !== $googleSub) {
            erro(409, 'Este e-mail já está vinculado a outra conta do Google.');
        }
        $upd = $pdo->prepare('UPDATE participantes SET google_sub = :sub WHERE id = :id');
        $upd->execute(['sub' => $googleSub, 'id' => $participante['id']]);
        responder_login($participante);
    }
    error_log('[sorteio] Erro ao criar participante via Google: ' . $e->getMessage());
    erro(500, 'Erro ao concluir o login com Google. Tente novamente.');
}

$participante['numero_sorte'] = null;
responder_login($participante);
