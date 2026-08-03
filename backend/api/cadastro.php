<?php
// =====================================================================
// POST /api/cadastro.php
// Cadastra o participante e devolve um token que o front-end usa nas
// próximas chamadas (iniciar-jogo / finalizar-jogo).
//
// Body (JSON): { nome, email, celular, empresa? }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

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

// Verifica se já existe cadastro com este e-mail ou celular
$stmt = $pdo->prepare(
    'SELECT p.token, ns.numero AS numero_sorte
     FROM participantes p
     LEFT JOIN numeros_sorte ns ON ns.participante_id = p.id
     WHERE p.email = :email OR p.celular = :celular
     LIMIT 1'
);
$stmt->execute(['email' => $email, 'celular' => $celularNormalizado]);
$existente = $stmt->fetch();

if ($existente) {
    // Já cadastrado: devolve o token existente para permitir continuar o fluxo.
    // O front redireciona para o número da sorte quando a pessoa já jogou.
    sucesso([
        'ja_cadastrado'      => true,
        'participante_token' => $existente['token'],
        'tem_numero'         => $existente['numero_sorte'] !== null,
        'numero_sorte'       => $existente['numero_sorte'],
    ]);
}

$stmt = $pdo->prepare(
    'INSERT INTO participantes (nome_completo, email, celular, empresa, ip_origem, user_agent)
     VALUES (:nome, :email, :celular, :empresa, :ip, :ua)
     RETURNING token'
);

try {
    $stmt->execute([
        'nome'    => $nome,
        'email'   => $email,
        'celular' => $celularNormalizado,
        'empresa' => $empresa !== '' ? $empresa : null,
        'ip'      => $_SERVER['REMOTE_ADDR'] ?? null,
        'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
} catch (PDOException $e) {
    // 23505 = unique_violation (corrida entre duas requisições simultâneas)
    if ($e->getCode() === '23505') {
        erro(409, 'E-mail ou celular já cadastrado.');
    }
    error_log('[sorteio] Erro ao cadastrar participante: ' . $e->getMessage());
    erro(500, 'Erro ao salvar cadastro. Tente novamente.');
}

$novo = $stmt->fetch();

sucesso([
    'ja_cadastrado'      => false,
    'participante_token' => $novo['token'],
]);
