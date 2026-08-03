<?php
// =====================================================================
// POST /api/finalizar-jogo.php
// Fecha a sessão de jogo e gera o número da sorte (único no banco).
//
// Body (JSON): { sessao_token, pontuacao, segundos_jogados }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$sessaoToken     = trim((string)($dados['sessao_token'] ?? ''));
$pontuacao       = (int)($dados['pontuacao'] ?? 0);
$segundosJogados = (float)($dados['segundos_jogados'] ?? 0);

if ($sessaoToken === '') {
    erro(422, 'Sessão de jogo inválida.');
}

// Duração mínima plausível: evita chamadas diretas à API sem realmente jogar.
// Os dois minigames atuais duram até 60s; exigimos ao menos 5s jogados.
const SEGUNDOS_MINIMOS = 5;

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT sj.id, sj.jogo, sj.status, sj.participante_id
     FROM sessoes_jogo sj
     WHERE sj.token = :token'
);
$stmt->execute(['token' => $sessaoToken]);
$sessao = $stmt->fetch();

if (!$sessao) {
    erro(404, 'Sessão de jogo não encontrada.');
}
if ($sessao['status'] !== 'em_andamento') {
    erro(409, 'Esta sessão de jogo já foi finalizada.');
}
if ($segundosJogados < SEGUNDOS_MINIMOS) {
    erro(422, 'Tempo de jogo inválido.');
}

$pdo->beginTransaction();
try {
    // Trava a sessão para evitar duas finalizações simultâneas (duplo clique / retry)
    $stmt = $pdo->prepare('SELECT status FROM sessoes_jogo WHERE id = :id FOR UPDATE');
    $stmt->execute(['id' => $sessao['id']]);
    $atual = $stmt->fetch();
    if (!$atual || $atual['status'] !== 'em_andamento') {
        $pdo->rollBack();
        erro(409, 'Esta sessão de jogo já foi finalizada.');
    }

    // Replay: participante já tem número da sorte -> não gera novo, só conclui a sessão.
    $stmt = $pdo->prepare('SELECT numero FROM numeros_sorte WHERE participante_id = :pid');
    $stmt->execute(['pid' => $sessao['participante_id']]);
    $jaTemNumero = $stmt->fetch();

    if ($jaTemNumero) {
        $stmt = $pdo->prepare(
            "UPDATE sessoes_jogo SET status = 'concluido', finalizado_em = now() WHERE id = :id"
        );
        $stmt->execute(['id' => $sessao['id']]);
        $pdo->commit();
        sucesso([
            'numero_sorte' => $jaTemNumero['numero'],
            'jogo'         => $sessao['jogo'],
            'novo_numero'  => false,
        ]);
    }

    // Gera número único de 6 dígitos, tentando algumas vezes em caso de colisão
    $numero  = null;
    $tentativas = 0;
    $insertNumero = $pdo->prepare(
        'INSERT INTO numeros_sorte (participante_id, sessao_jogo_id, jogo, numero, pontuacao)
         VALUES (:pid, :sid, :jogo, :numero, :pontuacao)'
    );

    while ($tentativas < 15) {
        $tentativas++;
        $candidato = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        try {
            $insertNumero->execute([
                'pid'       => $sessao['participante_id'],
                'sid'       => $sessao['id'],
                'jogo'      => $sessao['jogo'],
                'numero'    => $candidato,
                'pontuacao' => $pontuacao,
            ]);
            $numero = $candidato;
            break;
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                // numero duplicado (colisão) -> tenta outro
                // ou participante_id duplicado (já tem número) -> conclui e devolve o existente
                $stmt2 = $pdo->prepare('SELECT numero FROM numeros_sorte WHERE participante_id = :pid');
                $stmt2->execute(['pid' => $sessao['participante_id']]);
                $jaTem = $stmt2->fetch();
                if ($jaTem) {
                    $stmt = $pdo->prepare(
                        "UPDATE sessoes_jogo SET status = 'concluido', finalizado_em = now() WHERE id = :id"
                    );
                    $stmt->execute(['id' => $sessao['id']]);
                    $pdo->commit();
                    sucesso([
                        'numero_sorte' => $jaTem['numero'],
                        'jogo'         => $sessao['jogo'],
                        'novo_numero'  => false,
                    ]);
                }
                continue; // colisão de número, tenta de novo
            }
            throw $e;
        }
    }

    if ($numero === null) {
        $pdo->rollBack();
        erro(500, 'Não foi possível gerar um número da sorte único. Tente novamente.');
    }

    $stmt = $pdo->prepare(
        "UPDATE sessoes_jogo SET status = 'concluido', finalizado_em = now() WHERE id = :id"
    );
    $stmt->execute(['id' => $sessao['id']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[sorteio] Erro ao finalizar jogo: ' . $e->getMessage());
    erro(500, 'Erro ao finalizar o jogo. Tente novamente.');
}

sucesso([
    'numero_sorte' => $numero,
    'jogo'         => $sessao['jogo'],
    'novo_numero'  => true,
]);
