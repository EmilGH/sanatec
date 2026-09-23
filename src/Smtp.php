<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * A small SMTP client: enough to hand one message to an authenticated relay.
 *
 * PHP's mail() shells out to sendmail and needs a system-wide relay config
 * holding the password. This keeps the credentials in the one config file
 * that already lives outside the document root, and nothing else.
 *
 * Supports implicit TLS (port 465) and STARTTLS (port 587), AUTH LOGIN and
 * AUTH PLAIN. Throws RuntimeException with the server's reply on any failure.
 */
function smtp_send(array $cfg, string $to, string $subject, string $text, ?string $html = null): string
{
    $host = (string) ($cfg['host'] ?? '');
    $port = (int) ($cfg['port'] ?? 587);
    $enc  = (string) ($cfg['encryption'] ?? ($port === 465 ? 'ssl' : 'tls'));
    $user = (string) ($cfg['username'] ?? '');
    $pass = (string) ($cfg['password'] ?? '');
    $from = (string) ($cfg['from_address'] ?? $user);
    $name = (string) ($cfg['from_name'] ?? '');

    if ($host === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Mail is not configured.');
    }

    $target = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $sock = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if ($sock === false) {
        throw new RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
    }
    stream_set_timeout($sock, 15);

    $read = static function () use ($sock): string {
        $lines = '';
        while (($line = fgets($sock, 1024)) !== false) {
            $lines .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $lines;
    };
    $expect = static function (string $reply, string $codes, string $step): void {
        if (!in_array(substr($reply, 0, 3), explode(',', $codes), true)) {
            throw new RuntimeException("SMTP {$step}: " . trim($reply));
        }
    };
    $cmd = static function (string $line) use ($sock, $read): string {
        fwrite($sock, $line . "\r\n");
        return $read();
    };

    $expect($read(), '220', 'greeting');
    $ehlo = $cmd('EHLO ' . (gethostname() ?: 'localhost'));
    $expect($ehlo, '250', 'EHLO');

    if ($enc === 'tls') {
        $expect($cmd('STARTTLS'), '220', 'STARTTLS');
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP: TLS negotiation failed');
        }
        $ehlo = $cmd('EHLO ' . (gethostname() ?: 'localhost'));
        $expect($ehlo, '250', 'EHLO after STARTTLS');
    }

    if (stripos($ehlo, 'AUTH') !== false && stripos($ehlo, 'PLAIN') !== false) {
        $expect($cmd('AUTH PLAIN ' . base64_encode("\0{$user}\0{$pass}")), '235', 'AUTH');
    } else {
        $expect($cmd('AUTH LOGIN'), '334', 'AUTH LOGIN');
        $expect($cmd(base64_encode($user)), '334', 'AUTH username');
        $expect($cmd(base64_encode($pass)), '235', 'AUTH password');
    }

    $expect($cmd("MAIL FROM:<{$from}>"), '250', 'MAIL FROM');
    $expect($cmd("RCPT TO:<{$to}>"), '250,251', 'RCPT TO');
    $expect($cmd('DATA'), '354', 'DATA');

    $boundary = 'b' . bin2hex(random_bytes(12));
    $encodeHeader = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $fromHeader = $name !== '' ? $encodeHeader($name) . " <{$from}>" : $from;
    $messageId = '<' . bin2hex(random_bytes(16)) . '@' . substr(strrchr($from, '@') ?: '@localhost', 1) . '>';

    $headers = [
        'Date: ' . date('r'),
        "From: {$fromHeader}",
        "To: <{$to}>",
        'Subject: ' . $encodeHeader($subject),
        "Message-ID: {$messageId}",
        'MIME-Version: 1.0',
        'Auto-Submitted: auto-generated',
    ];

    if ($html === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($text));
    } else {
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--{$boundary}--\r\n";
    }

    // Dot-stuffing: a line consisting of "." would end the message early.
    $body = preg_replace('/^\./m', '..', $body) ?? $body;

    $expect($cmd(implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n."), '250', 'message accepted');
    $cmd('QUIT');
    fclose($sock);

    return $messageId;
}
