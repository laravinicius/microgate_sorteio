<?php
// =====================================================================
// POST /api/excluir-conta.php
// Exclui definitivamente o participante e todos os dados associados
// (sessões de jogo e número da sorte). Atende ao direito de eliminação
// previsto no art. 18, V da LGPD.
//
// Body (JSON): { participante_token }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$participanteToken = trim((string)($dados['participante_token'] ?? ''));
if ($participanteToken === '') {
    erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
}

$pdo = get_pdo();

$stmt = $pdo->prepare('SELECT id FROM participantes WHERE token = :token LIMIT 1');
$stmt->execute(['token' => $participanteToken]);
$participante = $stmt->fetch();

if (!$participante) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}

// ON DELETE CASCADE remove sessoes_jogo e numeros_sorte automaticamente.
$stmt = $pdo->prepare('DELETE FROM participantes WHERE id = :id');
$stmt->execute(['id' => $participante['id']]);

sucesso(['mensagem' => 'Seus dados foram excluídos com sucesso.']);
