<?php

declare(strict_types=1);

/**
 * Send a test message through the configured relay.
 *
 *   php bin/mail-test.php you@example.com
 *
 * Goes through the outbox like everything else, as a login_link template
 * (so it passes the purpose gate), and reports the provider's reply.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Messaging.php';

$to = normalize_email($argv[1] ?? '') ?? null;
if ($to === null) {
    fwrite(STDERR, "Usage: php bin/mail-test.php you@example.com\n");
    exit(1);
}

$mail = cfg('mail', []);
printf("relay:    %s:%d (%s)\nusername: %s\nfrom:     %s <%s>\npurposes: %s\n\n",
    $mail['host'] ?? '(unset)', (int) ($mail['port'] ?? 0), $mail['encryption'] ?? '?',
    $mail['username'] ?? '(unset)', $mail['from_name'] ?? '', $mail['from_address'] ?? '(unset)',
    implode(', ', (array) ($mail['purposes'] ?? ['login', 'reminder'])));

if (($mail['password'] ?? '') === '' || str_contains((string) ($mail['password'] ?? ''), 'REPLACE')) {
    fwrite(STDERR, "The mail password is not set in the config file yet.\n");
    exit(1);
}

$id = message_queue('email', $to, 'login_link', render_message('login_link', 'en', [
    'url' => rtrim((string) cfg('base_url'), '/') . '/my/login.php', 'minutes' => 0,
]));
$ok = message_deliver($id);
$m = db()->query("SELECT status, provider_ref, error FROM messages WHERE id = {$id}")->fetch();

printf("message #%d: %s %s\n", $id, $m['status'], $ok ? '(' . $m['provider_ref'] . ')' : '— ' . $m['error']);
exit($ok ? 0 : 1);
