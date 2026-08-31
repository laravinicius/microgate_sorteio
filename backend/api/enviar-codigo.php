<?php
// =====================================================================
// POST /api/enviar-codigo.php
// Envia código de verificação de 6 dígitos para o e-mail informado.
// Body (JSON): { email }
// =====================================================================

declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/email.php';

exigir_post();
$dados = json_input();

$email = mb_strtolower(trim((string)($dados['email'] ?? '')));
if ($email === '' || !validar_email($email)) {
    erro(422, 'Informe um e-mail válido.');
}

$pdo = get_pdo();

// Limpa códigos expirados ou usados deste e-mail (manutenção)
$pdo->prepare('DELETE FROM codigos_verificacao WHERE email = :email AND (expira_em < now() OR usado_em IS NOT NULL)')
    ->execute(['email' => $email]);

// Rate limit: máx 1 envio por minuto por e-mail
$stmt = $pdo->prepare('SELECT criado_em FROM codigos_verificacao WHERE email = :email ORDER BY criado_em DESC LIMIT 1');
$stmt->execute(['email' => $email]);
$ultimo = $stmt->fetch();
if ($ultimo && strtotime($ultimo['criado_em']) > time() - 60) {
    erro(429, 'Aguarde um minuto antes de solicitar novo código.');
}

verificar_rate_limit('enviar-codigo', 3, 60);

// Rate limit diário: máx 20 e-mails por IP por dia (proteção SMTP reputation)
verificar_rate_limit('email-daily', 20, 86400);

// Gera código de 6 dígitos
$codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiraEm = date('c', time() + 600); // 10 minutos

// Insere no banco
$stmt = $pdo->prepare(
    'INSERT INTO codigos_verificacao (email, codigo, expira_em)
     VALUES (:email, :codigo, :expira_em)'
);
$stmt->execute([
    'email'     => $email,
    'codigo'    => $codigo,
    'expira_em' => $expiraEm,
]);

// Prepara e-mail
$subject = "Seu código: {$codigo} - Sorteio Microgate";
$textBody = "Seu código de verificação é: {$codigo}\n\n"
    . "Este código expira em 10 minutos.\n"
    . "Se você não solicitou, ignore este e-mail.\n";

$htmlBody = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h2 style="color: #1a1a2e; margin-top: 0;">Verificação de E-mail</h2>
        <p style="color: #333; font-size: 16px;">Seu código de verificação para o Sorteio Microgate:</p>
        <div style="background: #f0f0f0; border-radius: 8px; padding: 24px; text-align: center; margin: 24px 0;">
            <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #1a1a2e; font-family: monospace;">' . implode(' ', str_split($codigo)) . '</span>
        </div>
        <p style="color: #666; font-size: 14px;">Este código expira em <strong>10 minutos</strong>.</p>
        <p style="color: #999; font-size: 12px;">Se você não solicitou este código, ignore este e-mail.</p>
        <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
        <p style="color: #999; font-size: 12px;">Sorteio Microgate Informática</p>
    </div>
</body>
</html>';

$enviado = enviarEmail($email, $subject, $htmlBody, $textBody);
if (!$enviado) {
    // Remove o código se falhou o envio (para não deixar órfão)
    $pdo->prepare('DELETE FROM codigos_verificacao WHERE email = :email AND codigo = :codigo')
        ->execute(['email' => $email, 'codigo' => $codigo]);
    erro(500, 'Não foi possível enviar o e-mail. Tente novamente em instantes.');
}

sucesso([
    'expira_em' => $expiraEm,
]);