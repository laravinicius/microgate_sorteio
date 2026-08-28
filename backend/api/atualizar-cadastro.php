<?php
// =====================================================================
// POST /api/atualizar-cadastro.php
// Atualiza os dados do participante autenticado pelo token.
// O e-mail NÃO é editável: a identidade vem da conta Google ou do login por e-mail.
//
// Body (JSON): { participante_token, nome?, cpf?, celular?, empresa?, consentimento? }
//   - consentimento: true grava a data/hora do aceite (LGPD art. 7º, I).
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$participanteToken = trim((string)($dados['participante_token'] ?? ''));
if ($participanteToken === '') {
    erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
}

$nome         = trim((string)($dados['nome'] ?? ''));
$cpfRaw       = trim((string)($dados['cpf'] ?? ''));
$celularRaw   = trim((string)($dados['celular'] ?? ''));
$empresa      = trim((string)($dados['empresa'] ?? ''));
$consentimento = ($dados['consentimento'] ?? false) === true;

if ($nome !== '' && mb_strlen($nome) < 3) {
    erro(422, 'Informe o nome completo.');
}

// CPF: se informado, validar formato e dígitos
$cpf = null;
if ($cpfRaw !== '') {
    $cpf = normalizar_cpf($cpfRaw);
    if (strlen($cpf) !== 11 || !validar_cpf($cpf)) {
        erro(422, 'CPF inválido.');
    }
}

// Celular: se informado, validar
$celular = null;
if ($celularRaw !== '') {
    $celular = normalizar_celular($celularRaw);
    if (mb_strlen($celular) < 10) {
        erro(422, 'Informe um número de celular válido (com DDD).');
    }
}

// Ao concluir o cadastro (primeiro consentimento), exige nome, CPF e celular
if ($consentimento) {
    if ($nome === '' || $cpf === null || $celular === null) {
        erro(422, 'Para concluir o cadastro, informe nome completo, CPF e celular.');
    }
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT p.id, p.nome_completo, p.cpf, p.consentimento_em
     FROM participantes p
     WHERE p.token = :token
     LIMIT 1'
);
$stmt->execute(['token' => $participanteToken]);
$participante = $stmt->fetch();

if (!$participante) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}

$gravarConsentimento = $consentimento && $participante['consentimento_em'] === null;

// Não permite alterar CPF se já preenchido (regra de negócio: 1 CPF por pessoa)
$cpfJaPreenchido = $participante['cpf'] !== null && $participante['cpf'] !== '';
if ($cpfJaPreenchido && $cpf !== null && $cpf !== $participante['cpf']) {
    erro(409, 'CPF já cadastrado e não pode ser alterado.');
}

$stmt = $pdo->prepare(
    'UPDATE participantes
     SET nome_completo      = COALESCE(NULLIF(:nome, \'\'), nome_completo),
         cpf                = COALESCE(:cpf, cpf),
         celular            = :celular,
         empresa            = :empresa,
         consentimento_em   = COALESCE(consentimento_em, :cons_em)
     WHERE token = :token
     RETURNING id'
);

try {
    $stmt->execute([
        'nome'    => $nome,
        'cpf'     => $cpf,
        'celular' => $celular,
        'empresa' => $empresa !== '' ? $empresa : null,
        'cons_em' => $gravarConsentimento ? date('c') : null,
        'token'   => $participanteToken,
    ]);
} catch (PDOException $e) {
    // 23505 = unique_violation (cpf ou celular de outro cadastro)
    if ($e->getCode() === '23505') {
        $msg = strpos($e->getMessage(), 'cpf') !== false ? 'CPF já cadastrado para outra pessoa.' : 'Celular já cadastrado para outra pessoa.';
        erro(409, $msg);
    }
    error_log('[sorteio] Erro ao atualizar cadastro: ' . $e->getMessage());
    erro(500, 'Erro ao salvar os dados. Tente novamente.');
}

sucesso([
    'nome'      => $nome !== '' ? $nome : $participante['nome_completo'],
    'cpf'       => $cpf !== null ? $cpf : $participante['cpf'],
    'celular'   => $celular,
    'empresa'   => $empresa !== '' ? $empresa : null,
]);