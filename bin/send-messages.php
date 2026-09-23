<?php

declare(strict_types=1);

/**
 * Deliver queued messages. Meant for cron, every minute or two:
 *
 *   * * * * * www-data php /var/www/sanatecdiving.com/bin/send-messages.php
 *
 * Sign-in codes are sent immediately and never wait for this; reminders and
 * anything scheduled do.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Messaging.php';

[$sent, $failed] = messages_deliver_pending();
if ($sent + $failed > 0 || in_array('-v', $argv, true)) {
    echo "sent={$sent} failed={$failed}\n";
}
