<?php
// =====================================================================
// POST /api/atualizar-cadastro.php
// Atualiza os dados do participante autenticado pelo token.
// O e-mail NÃO é editável: a identidade vem da conta Google.
//
// Body (JSON): { participante_token, nome?, celular?, empresa?, consentimento? }
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
$celularRaw   = trim((string)($dados['celular'] ?? ''));
$empresa      = trim((string)($dados['empresa'] ?? ''));
$consentimento = ($dados['consentimento'] ?? false) === true;

if ($nome !== '' && mb_strlen($nome) < 3) {
    erro(422, 'Informe o nome completo.');
}

// Celular é opcional no cadastro via Google; se informado, precisa ser válido.
$celular = null;
if ($celularRaw !== '') {
    $celular = normalizar_celular($celularRaw);
    if (mb_strlen($celular) < 10) {
        erro(422, 'Informe um número de celular válido (com DDD).');
    }
}

// Ao concluir o cadastro (primeiro consentimento), o celular é obrigatório.
if ($consentimento && $celular === null) {
    erro(422, 'Informe o número de celular para concluir o cadastro.');
}

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT p.id, p.nome_completo, p.consentimento_em
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

$stmt = $pdo->prepare(
    'UPDATE participantes
     SET nome_completo      = COALESCE(NULLIF(:nome, \'\'), nome_completo),
         celular            = :celular,
         empresa            = :empresa,
         consentimento_em   = COALESCE(consentimento_em, :cons_em)
     WHERE token = :token
     RETURNING id'
);

try {
    $stmt->execute([
        'nome'    => $nome,
        'celular' => $celular,
        'empresa' => $empresa !== '' ? $empresa : null,
        'cons_em' => $gravarConsentimento ? date('c') : null,
        'token'   => $participanteToken,
    ]);
} catch (PDOException $e) {
    // 23505 = unique_violation (celular de outro cadastro)
    if ($e->getCode() === '23505') {
        erro(409, 'Celular já cadastrado para outra pessoa.');
    }
    error_log('[sorteio] Erro ao atualizar cadastro: ' . $e->getMessage());
    erro(500, 'Erro ao salvar os dados. Tente novamente.');
}

sucesso([
    'nome'      => $nome !== '' ? $nome : $participante['nome_completo'],
    'celular'   => $celular,
    'empresa'   => $empresa !== '' ? $empresa : null,
]);
