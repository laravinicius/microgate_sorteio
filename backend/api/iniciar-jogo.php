<?php
// =====================================================================
// POST /api/iniciar-jogo.php
// Cria uma sessão de jogo para o participante já cadastrado.
// Bloqueia quem já tem número da sorte gerado (1 jogo por pessoa).
//
// Body (JSON): { participante_token, jogo }  jogo: 'firewall_defense' | 'patch_panel_rush'
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$participanteToken = trim((string)($dados['participante_token'] ?? ''));
$jogo               = trim((string)($dados['jogo'] ?? ''));

$jogosValidos = ['firewall_defense', 'patch_panel_rush'];
if ($participanteToken === '') {
    erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
}
if (!in_array($jogo, $jogosValidos, true)) {
    erro(422, 'Jogo inválido.');
}

$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT id FROM participantes WHERE token = :token');
$stmt->execute(['token' => $participanteToken]);
$participante = $stmt->fetch();

if (!$participante) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}

// Verifica se o participante já tem número gerado (replay não gera novo número)
$stmt = $pdo->prepare('SELECT numero FROM numeros_sorte WHERE participante_id = :id');
$stmt->execute(['id' => $participante['id']]);
$jaTemNumero = $stmt->fetch();

$stmt = $pdo->prepare(
    'INSERT INTO sessoes_jogo (participante_id, jogo)
     VALUES (:pid, :jogo)
     RETURNING token'
);
$stmt->execute(['pid' => $participante['id'], 'jogo' => $jogo]);
$sessao = $stmt->fetch();

sucesso([
    'sessao_token'  => $sessao['token'],
    'jogo'          => $jogo,
    'ja_tem_numero' => $jaTemNumero !== false,
    'numero_sorte'  => $jaTemNumero !== false ? $jaTemNumero['numero'] : null,
]);
