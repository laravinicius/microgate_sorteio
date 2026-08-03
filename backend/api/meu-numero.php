<?php
// =====================================================================
// POST /api/meu-numero.php
// Consulta os dados do participante e o número da sorte dele (se houver).
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

$stmt = $pdo->prepare(
    'SELECT p.nome_completo, p.email, p.celular, p.empresa, p.is_admin,
            ns.numero AS numero_sorte, ns.jogo, ns.pontuacao, ns.gerado_em
     FROM participantes p
     LEFT JOIN numeros_sorte ns ON ns.participante_id = p.id
     WHERE p.token = :token
     LIMIT 1'
);
$stmt->execute(['token' => $participanteToken]);
$participante = $stmt->fetch();

if (!$participante) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}

sucesso([
    'nome'         => $participante['nome_completo'],
    'email'        => $participante['email'],
    'celular'      => $participante['celular'],
    'empresa'      => $participante['empresa'],
    'is_admin'     => (bool)$participante['is_admin'],
    'tem_numero'   => $participante['numero_sorte'] !== null,
    'numero_sorte' => $participante['numero_sorte'],
    'jogo'         => $participante['jogo'],
    'pontuacao'    => $participante['pontuacao'],
    'gerado_em'    => $participante['gerado_em'],
]);
