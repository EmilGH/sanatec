<?php

declare(strict_types=1);

/**
 * Print a one-time sign-in link for a person.
 *
 *   php bin/login-link.php emil@rensing.com
 *   php bin/login-link.php +12123166800 --minutes 60
 *
 * The escape hatch: works with no mail or messaging provider at all, so a
 * delivery outage can never lock everyone out. The link is shown once, works
 * once, and expires. Treat it like a password while it lives.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

defined('SANATEC') || define('SANATEC', true);
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/Auth.php';

$identifier = trim((string) ($argv[1] ?? ''));
$minutes = LOGIN_LINK_MINUTES;
if (($i = array_search('--minutes', $argv, true)) !== false && isset($argv[$i + 1])) {
    $minutes = max(1, min(1440, (int) $argv[$i + 1]));
}

if ($identifier === '') {
    fwrite(STDERR, "Usage: php bin/login-link.php <email or +mobile> [--minutes N]\n");
    exit(1);
}

$found = login_identify($identifier);
if ($found === null) {
    fwrite(STDERR, "No person has that email or mobile.\n");
    exit(1);
}

$url = login_issue_link((int) $found['id'], (int) $found['channel_id'], $minutes);

echo "Sign-in link for {$found['name']} — works once, expires in {$minutes} minutes:\n\n  {$url}\n\n";
