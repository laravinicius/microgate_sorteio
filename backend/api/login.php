<?php
// =====================================================================
// POST /api/login.php
// Autentica um participante já cadastrado usando a combinação
// e-mail + celular. Devolve o token para o front-end continuar o fluxo.
//
// Body (JSON): { email, celular }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$email   = trim((string)($dados['email'] ?? ''));
$celular = trim((string)($dados['celular'] ?? ''));

if ($email === '' || !validar_email($email)) {
    erro(422, 'Informe um e-mail válido.');
}
$celularNormalizado = normalizar_celular($celular);
if (mb_strlen($celularNormalizado) < 10) {
    erro(422, 'Informe um número de celular válido (com DDD).');
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT p.token, p.nome_completo, ns.numero AS numero_sorte
     FROM participantes p
     LEFT JOIN numeros_sorte ns ON ns.participante_id = p.id
     WHERE p.email = :email AND p.celular = :celular
     LIMIT 1'
);
$stmt->execute(['email' => $email, 'celular' => $celularNormalizado]);
$participante = $stmt->fetch();

if (!$participante) {
    erro(401, 'E-mail e/ou celular não conferem com um cadastro existente.');
}

sucesso([
    'participante_token' => $participante['token'],
    'nome'               => $participante['nome_completo'],
    'ja_cadastrado'      => true,
    'tem_numero'         => $participante['numero_sorte'] !== null,
    'numero_sorte'       => $participante['numero_sorte'],
]);
