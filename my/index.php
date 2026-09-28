<?php

declare(strict_types=1);

/**
 * /my/ — signed out, the sign-in page (so a shared link previews right here);
 * signed in, the checklist until onboarding is done, then excursions or the passport.
 */

define('DIVER_PUBLIC', true);
require __DIR__ . '/_init.php';

if ($currentUser === null) {
    require_once __DIR__ . '/../src/LoginPage.php';
    login_page('diver');
    exit;
}

redirect(diver_home());
