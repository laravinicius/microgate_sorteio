<?php
// =====================================================================
// Cliente SMTP simples em PHP puro (SSL/TLS implícito porta 465).
// Sem dependências externas (sem composer).
// =====================================================================

declare(strict_types=1);

function smtp_read($socket, float $timeout = 5.0): string
{
    stream_set_timeout($socket, (int)$timeout, (int)(($timeout - (int)$timeout) * 1_000_000));
    $resp = '';
    while (!feof($socket)) {
        $line = fgets($socket, 512);
        if ($line === false) break;
        $resp .= $line;
        if (preg_match('/^\d{3} /', $line)) break;
    }
    return $resp;
}

function smtp_send($socket, string $cmd, string $expectedPrefix = ''): void
{
    fwrite($socket, $cmd . "\r\n");
    $resp = smtp_read($socket);
    if ($expectedPrefix !== '' && !str_starts_with(trim($resp), $expectedPrefix)) {
        throw new RuntimeException("SMTP erro: esperado {$expectedPrefix}, recebido: " . trim($resp));
    }
}

function enviarEmail(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    $cfg = get_email_config();

    $host = $cfg['host'];
    $port = (int)$cfg['port'];
    $user = $cfg['username'];
    $pass = $cfg['password'];
    $fromEmail = $cfg['from_email'];
    $fromName  = $cfg['from_name'];
    $useSsl    = (bool)($cfg['ssl'] ?? true);

    $target = ($useSsl ? 'ssl://' : 'tcp://') . $host . ':' . $port;

    $socket = @stream_socket_client($target, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log('[sorteio] SMTP conexão falhou: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, 10);

    try {
        // Banner
        $banner = smtp_read($socket);
        if (!str_starts_with(trim($banner), '220')) {
            throw new RuntimeException('SMTP banner inesperado: ' . trim($banner));
        }

        $hostname = gethostname() ?: 'localhost';
        smtp_send($socket, "EHLO {$hostname}", '250');

        // AUTH LOGIN
        smtp_send($socket, 'AUTH LOGIN', '334');
        smtp_send($socket, base64_encode($user), '334');
        smtp_send($socket, base64_encode($pass), '235');

        // MAIL FROM - usa o usuário autenticado
        smtp_send($socket, "MAIL FROM:<{$user}>", '250');

        // RCPT TO
        smtp_send($socket, "RCPT TO:<{$to}>", '250');

        // DATA
        smtp_send($socket, 'DATA', '354');

        $date = date('r');
        $messageId = '<' . bin2hex(random_bytes(8)) . '.' . time() . '@' . parse_url($fromEmail, PHP_URL_HOST) . '>';

        // Envia apenas text/plain (mais compatível, evita problemas de multipart)
        $textEncoded = quoted_printable_encode($textBody);

        $headers = [
            "Date: {$date}",
            "From: {$fromName} <{$fromEmail}>",
            "Reply-To: {$fromEmail}",
            "To: <{$to}>",
            "Subject: {$subject}",
            "Message-ID: {$messageId}",
            "MIME-Version: 1.0",
            "Precedence: bulk",
            "X-Priority: 3",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: quoted-printable",
            "",
        ];

        $data = implode("\r\n", $headers) . "\r\n" . $textEncoded . "\r\n.\r\n";
        fwrite($socket, $data);
        $resp = smtp_read($socket);
        if (!str_starts_with(trim($resp), '250')) {
            throw new RuntimeException('SMTP DATA falhou: ' . trim($resp));
        }

        // QUIT
        smtp_send($socket, 'QUIT', '221');

        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('[sorteio] SMTP erro: ' . $e->getMessage());
        @fclose($socket);
        return false;
    }
}