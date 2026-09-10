<?php
// =====================================================================
// POST /api/numeros-sorte.php
// Lista todos os participantes que possuem número da sorte.
// Apenas administradores (is_admin = true) podem acessar.
//
// Body (JSON): { participante_token }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
exigir_admin();

$pdo = get_pdo();

$stmt = $pdo->query(
    'SELECT ns.numero, p.nome_completo, p.email, p.celular
     FROM numeros_sorte ns
     JOIN participantes p ON ns.participante_id = p.id
     ORDER BY ns.gerado_em'
);

$participantes = $stmt->fetchAll();

sucesso([
    'total'        => count($participantes),
    'participantes' => $participantes,
]);
