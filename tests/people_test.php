<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/People.php';

test('mobile numbers are normalised to strict E.164', function (): void {
    is_same('+12123166800', normalize_mobile('+1 (212) 316-6800'));
    is_same('+529841063306', normalize_mobile('+52 984 106 3306'));
    is_same('+529841063306', normalize_mobile('0052 984 106 3306'), '00 prefix means +');
    is_same(null, normalize_mobile('984 106 3306'), 'no country code: refuse rather than guess');
    is_same(null, normalize_mobile('+0123'), 'country codes never start with 0');
    is_same(null, normalize_mobile(''));
    is_same(null, normalize_mobile('+1234567890123456'), 'too long');
});

test('email addresses are lower-cased and validated', function (): void {
    is_same('emil@example.com', normalize_email('  Emil@Example.COM '));
    is_same(null, normalize_email('not an email'));
    is_same(null, normalize_email(''));
});

test('a person can be created and reached by any of their channels', function (): void {
    $id = person_create('Ana María Pérez', ['preferred_language' => 'es']);
    channel_upsert($id, 'email', 'Ana@Example.com', ['verified' => true, 'primary' => true]);
    channel_upsert($id, 'mobile', '+52 998 123 4567', ['verified' => false, 'whatsapp' => true]);

    $byEmail = person_find_by_channel('email', 'ANA@example.com');
    is_same('Ana María Pérez', $byEmail['name'], 'lookup normalises the way storage does');
    is_true($byEmail['channel_verified_at'] !== null);

    $byMobile = person_find_by_channel('mobile', '+529981234567');
    is_same($id, (int) $byMobile['id']);
    is_same(1, (int) $byMobile['whatsapp_capable']);
    is_same(null, $byMobile['channel_verified_at']);
});

test('a channel belongs to exactly one person', function (): void {
    $a = person_create('Person A');
    $b = person_create('Person B');
    channel_upsert($a, 'mobile', '+12125550100');

    throws(static fn () => channel_upsert($b, 'mobile', '+1 212 555 0100'),
        'the same number, differently written, must be refused for a second person');
});

test('a malformed value is refused before it reaches the database', function (): void {
    $id = person_create('Person C');
    throws(static fn () => channel_upsert($id, 'mobile', '555 0100'));
    throws(static fn () => channel_upsert($id, 'email', 'nope'));
});

test('only one channel per kind is primary', function (): void {
    $id = person_create('Person D');
    $first = channel_upsert($id, 'email', 'd1@example.com', ['primary' => true]);
    $second = channel_upsert($id, 'email', 'd2@example.com', ['primary' => true]);

    $primaries = array_filter(person_channels($id), static fn (array $c): bool => (bool) $c['is_primary']);
    is_same(1, count($primaries));
    is_same($second, (int) array_values($primaries)[0]['id']);
    is_true($first !== $second);
});

test('whatsapp capability cannot be set on an email channel', function (): void {
    $id = person_create('Person E');
    $cid = channel_upsert($id, 'email', 'e@example.com', ['whatsapp' => true]);
    $row = db()->query("SELECT whatsapp_capable FROM contact_channels WHERE id = {$cid}")->fetch();
    is_same(0, (int) $row['whatsapp_capable'], 'the flag is ignored for email, and the CHECK would refuse it anyway');
});
