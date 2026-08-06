<?php
// =====================================================================
// POST /api/finalizar-jogo.php
// Fecha a sessão de jogo e gera o número da sorte (único no banco).
//
// Body (JSON): { participante_token, sessao_token, pontuacao, segundos_jogados }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

exigir_post();
$dados = json_input();

$sessaoToken         = trim((string)($dados['sessao_token'] ?? ''));
$participanteToken   = trim((string)($dados['participante_token'] ?? ''));
$pontuacao           = (int)($dados['pontuacao'] ?? 0);
$segundosJogados     = (float)($dados['segundos_jogados'] ?? 0);

if ($sessaoToken === '') {
    erro(422, 'Sessão de jogo inválida.');
}
if ($participanteToken === '') {
    erro(422, 'Cadastro não encontrado. Refaça o cadastro.');
}

// Limites de tempo validados no servidor: os minigames duram até 60s,
// então qualquer tempo acima disso (ou sessão velha) é inválido.
const SEGUNDOS_MINIMOS          = 5;   // duração mínima plausível de uma partida
const SEGUNDOS_MAXIMO           = 70;  // jogos duram até 60s + folga de rede
const SEGUNDOS_FOLGA_REPORTADO  = 15;  // tolerância entre o tempo reportado e o decorrido no servidor
const IDADE_SESSAO_MAXIMA       = 600; // 10 min: sessão antiga expira (token de sessão tem validade)

if ($pontuacao < 0 || $pontuacao > 50000) {
    erro(422, 'Pontuação inválida.');
}

$pdo = get_pdo();

// Confirma o participante dono da sessão antes de prosseguir.
$stmt = $pdo->prepare('SELECT id FROM participantes WHERE token = :token LIMIT 1');
$stmt->execute(['token' => $participanteToken]);
$participanteAuth = $stmt->fetch();
if (!$participanteAuth) {
    erro(404, 'Cadastro não encontrado. Refaça o cadastro.');
}

$stmt = $pdo->prepare(
    'SELECT sj.id, sj.jogo, sj.status, sj.participante_id,
            EXTRACT(EPOCH FROM (now() - sj.iniciado_em)) AS idade_sessao
     FROM sessoes_jogo sj
     WHERE sj.token = :token'
);
$stmt->execute(['token' => $sessaoToken]);
$sessao = $stmt->fetch();

if (!$sessao) {
    erro(404, 'Sessão de jogo não encontrada.');
}
if ((int)$sessao['participante_id'] !== (int)$participanteAuth['id']) {
    erro(403, 'Esta sessão de jogo não pertence ao participante.');
}
if ($sessao['status'] !== 'em_andamento') {
    erro(409, 'Esta sessão de jogo já foi finalizada.');
}

$idadeSessao = (float)($sessao['idade_sessao'] ?? 0);
if ($segundosJogados < SEGUNDOS_MINIMOS || $segundosJogados > SEGUNDOS_MAXIMO) {
    erro(422, 'Tempo de jogo inválido.');
}
if ($idadeSessao < SEGUNDOS_MINIMOS) {
    erro(422, 'Tempo de jogo inválido.');
}
if ($idadeSessao > IDADE_SESSAO_MAXIMA) {
    erro(422, 'Esta sessão de jogo expirou. Inicie uma nova partida.');
}
if ($segundosJogados > $idadeSessao + SEGUNDOS_FOLGA_REPORTADO) {
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

    // Administrador pode jogar, mas NÃO recebe número da sorte.
    $stmt = $pdo->prepare('SELECT is_admin FROM participantes WHERE id = :pid');
    $stmt->execute(['pid' => $sessao['participante_id']]);
    $admin = $stmt->fetch();
    if ($admin && $admin['is_admin']) {
        $stmt = $pdo->prepare(
            "UPDATE sessoes_jogo SET status = 'concluido', finalizado_em = now() WHERE id = :id"
        );
        $stmt->execute(['id' => $sessao['id']]);
        $pdo->commit();
        sucesso([
            'admin'        => true,
            'numero_sorte' => null,
            'jogo'         => $sessao['jogo'],
            'novo_numero'  => false,
        ]);
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
