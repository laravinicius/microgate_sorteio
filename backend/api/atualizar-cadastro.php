<?php
// =====================================================================
// POST /api/atualizar-cadastro.php
// Atualiza os dados do participante autenticado pelo token.
//
// Body (JSON): { participante_token, nome, email, celular, empresa? }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$participanteToken = trim((string)($dados['participante_token'] ?? ''));
if ($participanteToken === '') {
    erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
}

$nome     = trim((string)($dados['nome'] ?? ''));
$email    = trim((string)($dados['email'] ?? ''));
$celular  = trim((string)($dados['celular'] ?? ''));
$empresa  = trim((string)($dados['empresa'] ?? ''));

if ($nome === '' || mb_strlen($nome) < 3) {
    erro(422, 'Informe o nome completo.');
}
if ($email === '' || !validar_email($email)) {
    erro(422, 'Informe um e-mail válido.');
}
$celularNormalizado = normalizar_celular($celular);
if (mb_strlen($celularNormalizado) < 10) {
    erro(422, 'Informe um número de celular válido (com DDD).');
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT p.id
     FROM participantes p
     WHERE p.token = :token
     LIMIT 1'
);
$stmt->execute(['token' => $participanteToken]);
$participante = $stmt->fetch();

if (!$participante) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}

$stmt = $pdo->prepare(
    'UPDATE participantes
     SET nome_completo = :nome, email = :email, celular = :celular, empresa = :empresa
     WHERE token = :token
     RETURNING id'
);

try {
    $stmt->execute([
        'nome'    => $nome,
        'email'   => $email,
        'celular' => $celularNormalizado,
        'empresa' => $empresa !== '' ? $empresa : null,
        'token'   => $participanteToken,
    ]);
} catch (PDOException $e) {
    // 23505 = unique_violation (e-mail ou celular de outro cadastro)
    if ($e->getCode() === '23505') {
        erro(409, 'E-mail ou celular já cadastrado para outra pessoa.');
    }
    error_log('[sorteio] Erro ao atualizar cadastro: ' . $e->getMessage());
    erro(500, 'Erro ao salvar os dados. Tente novamente.');
}

sucesso([
    'nome'   => $nome,
    'email'  => $email,
    'celular' => $celularNormalizado,
    'empresa' => $empresa !== '' ? $empresa : null,
]);
