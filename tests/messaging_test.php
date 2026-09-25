<?php

declare(strict_types=1);

test('a queued message is recorded before anything is sent', function (): void {
    $id = message_queue('log', 'x@example.com', 'login_code', ['subject' => 'S', 'text' => 'T']);
    $m = db()->query("SELECT * FROM messages WHERE id = {$id}")->fetch();
    is_same('queued', $m['status']);
    is_same(0, (int) $m['attempts']);
});

test('delivery marks the row sent and keeps the provider reference', function (): void {
    $id = message_queue('log', 'x@example.com', 'login_code', ['subject' => 'S', 'text' => 'T']);
    is_true(message_deliver($id));
    $m = db()->query("SELECT * FROM messages WHERE id = {$id}")->fetch();
    is_same('sent', $m['status']);
    is_same(1, (int) $m['attempts']);
    is_true($m['sent_at'] !== null);
    has('log-', (string) $m['provider_ref']);
});

test('an unconfigured transport fails loudly and keeps the reason', function (): void {
    $id = message_queue('whatsapp', '+5215512345678', 'login_code', ['text' => 'T']);
    is_false(message_deliver($id));
    $m = db()->query("SELECT * FROM messages WHERE id = {$id}")->fetch();
    is_same('failed', $m['status']);
    has('No WhatsApp provider', (string) $m['error']);
});

test('a sent message is not sent again', function (): void {
    $id = message_queue('log', 'x@example.com', 'login_code', ['text' => 'T']);
    message_deliver($id);
    is_false(message_deliver($id));
    is_same(1, (int) db()->query("SELECT attempts FROM messages WHERE id = {$id}")->fetchColumn());
});

test('the cron sender only takes what is due', function (): void {
    db()->exec("UPDATE messages SET status = 'sent' WHERE status = 'queued'");
    $now = message_queue('log', 'a@example.com', 'login_code', ['text' => 'now']);
    $later = message_queue('log', 'b@example.com', 'login_code', ['text' => 'later'], ['scheduled_at' => date('Y-m-d H:i:s', time() + 3600)]);

    [$sent, $failed] = messages_deliver_pending();
    is_same(1, $sent);
    is_same(0, $failed);
    is_same('sent', db()->query("SELECT status FROM messages WHERE id = {$now}")->fetchColumn());
    is_same('queued', db()->query("SELECT status FROM messages WHERE id = {$later}")->fetchColumn());
});

test('templates render in both languages', function (): void {
    $en = render_message('login_code', 'en', ['code' => '123456']);
    $es = render_message('login_code', 'es', ['code' => '123456']);
    has('123456', $en['text']); has('123456', $es['text']);
    has('sign-in code', $en['subject']); has('código de acceso', $es['subject']);
    has('123456', $en['html']);
});

test('the email relay only carries sign-in and reminder mail', function (): void {
    is_same(null, transport_refusal('email', 'login_code'));
    is_true(is_string(transport_refusal('email', 'marketing_blast')), 'an unknown template cannot be emailed');

    $cfg = cfg_all(); $cfg['mail'] = ['host' => 'x', 'purposes' => ['reminder']]; cfg_all($cfg);
    is_true(is_string(transport_refusal('email', 'login_code')), 'purposes come from config');
    is_same(null, transport_refusal('log', 'anything'), 'other transports are not gated here');
    unset($cfg['mail']); cfg_all($cfg);

    $id = message_queue('email', 'x@example.com', 'marketing_blast', ['subject' => 'S', 'text' => 'T']);
    is_false(message_deliver($id));
    $m = db()->query("SELECT status, attempts, error FROM messages WHERE id = {$id}")->fetch();
    is_same('failed', $m['status']);
    is_same(0, (int) $m['attempts'], 'refused before any attempt');
    has('cannot be emailed', (string) $m['error']);
});
