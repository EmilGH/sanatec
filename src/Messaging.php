<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Smtp.php';

/**
 * What each template is for. The email relay is restricted by purpose in
 * config — the shop's mailbox is for sign-in and reminders, not marketing —
 * and a template with no purpose here cannot be sent by email at all.
 */
const MESSAGE_PURPOSES = [
    'login_code' => 'login',
    'login_link' => 'login',
    // reminder templates register here as they are written, e.g.
    // 'excursion_reminder' => 'reminder',
];

/** May this template leave through this transport? Returns null if yes, else the reason. */
function transport_refusal(string $transport, string $template): ?string
{
    if ($transport !== 'email') {
        return null;
    }
    $purpose = MESSAGE_PURPOSES[$template] ?? null;
    $allowed = (array) (cfg('mail', [])['purposes'] ?? ['login', 'reminder']);
    if ($purpose === null) {
        return "Template '{$template}' has no purpose and cannot be emailed.";
    }
    if (!in_array($purpose, $allowed, true)) {
        return "The mail relay is restricted to: " . implode(', ', $allowed) . " — not '{$purpose}'.";
    }

    return null;
}

/**
 * The outbox.
 *
 * message_queue() writes a row; message_deliver() hands it to a transport and
 * records what happened. Login codes call both back to back because someone
 * is waiting at a form. Everything else is queued and delivered by cron via
 * messages_deliver_pending().
 *
 * Transports:
 *   email     — the SMTP relay in config['mail']
 *   whatsapp  — a provider, once one is configured; until then reports failure
 *   sms       — likewise
 *   log       — writes to the PHP error log; development and tests only
 */

/** Which transport reaches a channel best, given what is configured. */
function transport_for_channel(array $channel): string
{
    if (cfg('mail_transport') === 'log') {
        return 'log';
    }

    if ($channel['kind'] === 'email') {
        return 'email';
    }

    if ((int) ($channel['whatsapp_capable'] ?? 0) === 1 && transport_configured('whatsapp')) {
        return 'whatsapp';
    }

    return transport_configured('sms') ? 'sms' : 'log';
}

function transport_configured(string $transport): bool
{
    return match ($transport) {
        'email'    => (cfg('mail_transport') === 'log') || !empty(cfg('mail', [])['host']),
        'whatsapp' => !empty(cfg('whatsapp', [])['provider']),
        'sms'      => !empty(cfg('sms', [])['provider']),
        'log'      => true,
        default    => false,
    };
}

/**
 * Queue a message. Returns the row id. Does not send.
 *
 * $rendered is ['subject' => ?string, 'text' => string, 'html' => ?string].
 */
function message_queue(string $transport, string $to, string $template, array $rendered, array $meta = []): int
{
    db()->prepare(
        'INSERT INTO messages
           (person_id, channel_id, transport, to_value, template, locale, subject, body_text, body_html, scheduled_at)
         VALUES (:p, :c, :t, :to, :tpl, :loc, :subj, :text, :html, :at)'
    )->execute([
        ':p'    => $meta['person_id'] ?? null,
        ':c'    => $meta['channel_id'] ?? null,
        ':t'    => $transport,
        ':to'   => $to,
        ':tpl'  => $template,
        ':loc'  => $meta['locale'] ?? 'en',
        ':subj' => $rendered['subject'] ?? null,
        ':text' => $rendered['text'],
        ':html' => $rendered['html'] ?? null,
        ':at'   => $meta['scheduled_at'] ?? null,
    ]);

    return (int) db()->lastInsertId();
}

/** Deliver one queued message now. Returns true on success; the row records either way. */
function message_deliver(int $id): bool
{
    $stmt = db()->prepare('SELECT * FROM messages WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $m = $stmt->fetch();

    if (!$m || !in_array($m['status'], ['queued', 'failed'], true)) {
        return false;
    }

    if (($why = transport_refusal($m['transport'], $m['template'])) !== null) {
        db()->prepare('UPDATE messages SET status = "failed", error = :err WHERE id = :id')->execute([':err' => $why, ':id' => $id]);
        error_log("SanaTec message #{$id} refused: {$why}");

        return false;
    }

    db()->prepare('UPDATE messages SET status = "sending", attempts = attempts + 1 WHERE id = :id')
        ->execute([':id' => $id]);

    try {
        $ref = match ($m['transport']) {
            'email'    => smtp_send(cfg('mail', []), $m['to_value'], (string) $m['subject'], $m['body_text'], $m['body_html']),
            'log'      => transport_log($m),
            'whatsapp' => throw new RuntimeException('No WhatsApp provider is configured.'),
            'sms'      => throw new RuntimeException('No SMS provider is configured.'),
            default    => throw new RuntimeException('Unknown transport.'),
        };

        db()->prepare('UPDATE messages SET status = "sent", sent_at = NOW(), provider_ref = :ref, error = NULL WHERE id = :id')
            ->execute([':ref' => mb_substr($ref, 0, 128), ':id' => $id]);

        return true;
    } catch (Throwable $e) {
        error_log("SanaTec message #{$id} ({$m['transport']} to {$m['to_value']}) failed: " . $e->getMessage());
        db()->prepare('UPDATE messages SET status = "failed", error = :err WHERE id = :id')
            ->execute([':err' => mb_substr($e->getMessage(), 0, 500), ':id' => $id]);

        return false;
    }
}

/** The development transport: the message goes to the error log, nowhere else. */
function transport_log(array $m): string
{
    error_log(sprintf(
        "SanaTec [log transport] to=%s template=%s subject=%s\n%s",
        $m['to_value'], $m['template'], $m['subject'] ?? '-', $m['body_text']
    ));

    return 'log-' . $m['id'];
}

/** Cron entry point: deliver whatever is due. Returns [sent, failed]. */
function messages_deliver_pending(int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT id FROM messages
         WHERE status = "queued" AND (scheduled_at IS NULL OR scheduled_at <= NOW())
         ORDER BY id LIMIT :n'
    );
    $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $sent = $failed = 0;
    foreach ($stmt->fetchAll() as $row) {
        message_deliver((int) $row['id']) ? $sent++ : $failed++;
    }

    return [$sent, $failed];
}

/** Recent messages to a person, for the admin. */
function messages_for_person(int $personId, int $limit = 20): array
{
    $stmt = db()->prepare('SELECT * FROM messages WHERE person_id = :p ORDER BY id DESC LIMIT :n');
    $stmt->bindValue(':p', $personId, PDO::PARAM_INT);
    $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Templates. Small enough to live in code; move to the database when the shop
// wants to edit them.
// ---------------------------------------------------------------------------

function render_message(string $template, string $locale, array $vars): array
{
    $t = static fn (string $en, string $es): string => $locale === 'es' ? $es : $en;
    $business = setting('business_name') ?: 'SanaTec Diving';

    switch ($template) {
        case 'login_code':
            $code = $vars['code'];
            $minutes = (int) ($vars['minutes'] ?? 10);
            $subject = $t("Your {$business} sign-in code: {$code}", "Tu código de acceso a {$business}: {$code}");
            $text = $t(
                "Your sign-in code is {$code}\n\nIt expires in {$minutes} minutes. If you did not ask for it, ignore this message.",
                "Tu código de acceso es {$code}\n\nCaduca en {$minutes} minutos. Si no lo solicitaste, ignora este mensaje."
            );
            $html = '<p style="font:16px/1.5 sans-serif">' . $t('Your sign-in code is', 'Tu código de acceso es') . '</p>'
                . '<p style="font:32px/1 monospace;letter-spacing:6px;margin:12px 0">' . e($code) . '</p>'
                . '<p style="font:14px/1.5 sans-serif;color:#555">'
                . $t("It expires in {$minutes} minutes. If you did not ask for it, ignore this message.",
                     "Caduca en {$minutes} minutos. Si no lo solicitaste, ignora este mensaje.")
                . '</p>';
            return ['subject' => $subject, 'text' => $text, 'html' => $html];

        case 'login_link':
            $url = $vars['url'];
            $minutes = (int) ($vars['minutes'] ?? 15);
            return [
                'subject' => $t("Sign in to {$business}", "Entrar a {$business}"),
                'text'    => $t("Sign in with this link: {$url}\n\nIt works once and expires in {$minutes} minutes.",
                                "Entra con este enlace: {$url}\n\nFunciona una vez y caduca en {$minutes} minutos."),
                'html'    => '<p style="font:16px/1.5 sans-serif"><a href="' . e($url) . '">'
                             . $t('Sign in', 'Entrar') . '</a></p><p style="font:14px/1.5 sans-serif;color:#555">'
                             . $t("Works once, expires in {$minutes} minutes.", "Funciona una vez, caduca en {$minutes} minutos.")
                             . '</p>',
            ];
    }

    throw new InvalidArgumentException("Unknown message template: {$template}");
}
