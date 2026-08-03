<?php
// =====================================================================
// POST /api/admin-usuarios.php
// Lista todos os participantes cadastrados com todas as suas informações.
// Apenas administradores (is_admin = true) podem acessar.
//
// Body (JSON): { participante_token }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$pdo = get_pdo();
$token = trim((string)($dados['participante_token'] ?? ''));
if ($token === '') {
    erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
}

$participante = get_participante_por_token($pdo, $token);
if (!$participante) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}
if (!$participante['is_admin']) {
    erro(403, 'Acesso restrito ao administrador.');
}

$stmt = $pdo->query(
    'SELECT p.id, p.token, p.nome_completo, p.email, p.celular, p.empresa,
            p.ip_origem, p.user_agent, p.is_admin, p.criado_em,
            ns.numero AS numero_sorte, ns.jogo, ns.pontuacao, ns.gerado_em
     FROM participantes p
     LEFT JOIN numeros_sorte ns ON ns.participante_id = p.id
     ORDER BY p.criado_em DESC'
);

$usuarios = $stmt->fetchAll();

sucesso([
    'total'    => count($usuarios),
    'usuarios' => $usuarios,
]);