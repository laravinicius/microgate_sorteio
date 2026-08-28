<?php
// =====================================================================
// POST /api/verificar-codigo.php
// Verifica o código de 6 dígitos e autentica/cria o participante.
// Body (JSON): { email, codigo }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$email = mb_strtolower(trim((string)($dados['email'] ?? '')));
$codigo = trim((string)($dados['codigo'] ?? ''));

if ($email === '' || !validar_email($email)) {
    erro(422, 'E-mail inválido.');
}
if ($codigo === '' || !preg_match('/^\d{6}$/', $codigo)) {
    erro(422, 'Código inválido. Informe 6 dígitos.');
}

$pdo = get_pdo();

// Busca código válido (não expirado, não usado)
$stmt = $pdo->prepare(
    'SELECT id, tentativas FROM codigos_verificacao
     WHERE email = :email AND codigo = :codigo
       AND expira_em > now()
       AND usado_em IS NULL
     LIMIT 1'
);
$stmt->execute(['email' => $email, 'codigo' => $codigo]);
$cod = $stmt->fetch();

if (!$cod) {
    erro(401, 'Código inválido ou expirado.');
}

// Incrementa tentativas
$tentativas = (int)$cod['tentativas'] + 1;
if ($tentativas >= 3) {
    $pdo->prepare('UPDATE codigos_verificacao SET usado_em = now(), tentativas = :t WHERE id = :id')
        ->execute(['t' => $tentativas, 'id' => $cod['id']]);
    erro(401, 'Máximo de tentativas excedido. Solicite um novo código.');
}
$pdo->prepare('UPDATE codigos_verificacao SET tentativas = :t WHERE id = :id')
    ->execute(['t' => $tentativas, 'id' => $cod['id']]);

// Marca como usado
$pdo->prepare('UPDATE codigos_verificacao SET usado_em = now() WHERE id = :id')
    ->execute(['id' => $cod['id']]);

// Busca ou cria participante
$colunas = 'SELECT p.id, p.token, p.is_admin, p.celular, p.consentimento_em, p.nome_completo, p.cpf,
                   ns.numero AS numero_sorte
            FROM participantes p
            LEFT JOIN numeros_sorte ns ON ns.participante_id = p.id';

$buscar = function (PDO $pdo, string $sql, array $params): ?array {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $linha = $st->fetch();
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
        'precisa_completar'  => $p['nome_completo'] === null || $p['nome_completo'] === '' || $p['cpf'] === null || $p['celular'] === null || $p['consentimento_em'] === null,
    ]);
}

// 1) Já existe com este e-mail
$participante = $buscar($pdo, "$colunas WHERE p.email = :email LIMIT 1", ['email' => $email]);
if ($participante) {
    responder_login($participante);
}

// 2) Novo participante via e-mail
try {
    $stmt = $pdo->prepare(
        'INSERT INTO participantes (nome_completo, email, cpf, celular, empresa, consentimento_em, ip_origem, user_agent, google_sub)
         VALUES (:nome, :email, :cpf, :celular, :empresa, :consentimento, :ip, :ua, NULL)
         RETURNING id, token, is_admin, celular, consentimento_em, nome_completo, cpf'
    );
    $stmt->execute([
        'nome'         => '',
        'email'        => $email,
        'cpf'          => null,
        'celular'      => null,
        'empresa'      => null,
        'consentimento' => null,
        'ip'           => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'           => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
    $participante = $stmt->fetch();
} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        $participante = $buscar($pdo, "$colunas WHERE p.email = :email LIMIT 1", ['email' => $email]);
        if (!$participante) {
            throw $e;
        }
        responder_login($participante);
    }
    error_log('[sorteio] Erro ao criar participante via e-mail: ' . $e->getMessage());
    erro(500, 'Erro ao concluir o login. Tente novamente.');
}

$participante['numero_sorte'] = null;
responder_login($participante);