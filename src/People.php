<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * People and their contact channels.
 *
 * A person is an identity; a channel is a verified way to reach them. Login,
 * notifications and consent all hang off channels, so their values are
 * normalised here, once, before anything stores or compares them.
 */

/**
 * A mobile number as E.164: +, country code, digits, nothing else.
 * Returns null for anything that cannot be read as one.
 */
function normalize_mobile(?string $raw): ?string
{
    $digits = preg_replace('/[^0-9+]/', '', trim((string) $raw)) ?? '';

    // "00" international prefix → "+"
    if (str_starts_with($digits, '00')) {
        $digits = '+' . substr($digits, 2);
    }

    if ($digits === '' || $digits[0] !== '+') {
        return null;                                   // no country code: refuse to guess one
    }

    $digits = '+' . str_replace('+', '', $digits);     // a stray + in the middle

    return preg_match('/^\+[1-9][0-9]{6,14}$/', $digits) === 1 ? $digits : null;
}

/** An email address, lower-cased and trimmed, or null if it is not one. */
function normalize_email(?string $raw): ?string
{
    $email = mb_strtolower(trim((string) $raw));

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
}

/** Normalise a channel value according to its kind. Null means invalid. */
function normalize_channel(string $kind, ?string $raw): ?string
{
    return match ($kind) {
        'email'  => normalize_email($raw),
        'mobile' => normalize_mobile($raw),
        default  => null,
    };
}

function person_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM people WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

/** The person a channel value belongs to, if anyone. Value is normalised first. */
function person_find_by_channel(string $kind, ?string $raw): ?array
{
    $value = normalize_channel($kind, $raw);
    if ($value === null) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT p.*, c.id AS channel_id, c.verified_at AS channel_verified_at, c.whatsapp_capable
         FROM contact_channels c
         JOIN people p ON p.id = c.person_id AND p.deleted_at IS NULL
         WHERE c.kind = :k AND c.value = :v'
    );
    $stmt->execute([':k' => $kind, ':v' => $value]);

    return $stmt->fetch() ?: null;
}

function person_create(string $name, array $extra = []): int
{
    $fields = ['name' => trim($name)] + array_intersect_key($extra, array_flip([
        'date_of_birth', 'nationality', 'preferred_language', 'timezone',
    ]));

    $cols = implode(', ', array_keys($fields));
    $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));

    db()->prepare("INSERT INTO people ({$cols}) VALUES ({$params})")->execute($fields);

    return (int) db()->lastInsertId();
}

/** Channels for a person, primary first. */
function person_channels(int $personId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM contact_channels WHERE person_id = :p ORDER BY kind, is_primary DESC, id'
    );
    $stmt->execute([':p' => $personId]);

    return $stmt->fetchAll();
}

/**
 * Add or update a channel on a person. Returns the channel id.
 *
 * Throws if the value is malformed or already belongs to someone else — a
 * number or address is one person's, and this is where that is decided.
 */
function channel_upsert(int $personId, string $kind, string $raw, array $opts = []): int
{
    $value = normalize_channel($kind, $raw);
    if ($value === null) {
        throw new InvalidArgumentException("Not a valid {$kind}: {$raw}");
    }

    $existing = db()->prepare('SELECT id, person_id FROM contact_channels WHERE kind = :k AND value = :v');
    $existing->execute([':k' => $kind, ':v' => $value]);
    $row = $existing->fetch();

    if ($row && (int) $row['person_id'] !== $personId) {
        throw new RuntimeException("That {$kind} already belongs to another person.");
    }

    $verified = !empty($opts['verified']);
    $whatsapp = $kind === 'mobile' && !empty($opts['whatsapp']);

    if ($row) {
        db()->prepare(
            'UPDATE contact_channels
             SET label = COALESCE(:label, label),
                 is_primary = :primary,
                 verified_at = COALESCE(verified_at, :verified_at),
                 whatsapp_capable = :wa,
                 whatsapp_checked_at = CASE WHEN :wa2 = 1 THEN NOW() ELSE whatsapp_checked_at END
             WHERE id = :id'
        )->execute([
            ':label' => $opts['label'] ?? null,
            ':primary' => !empty($opts['primary']) ? 1 : 0,
            ':verified_at' => $verified ? date('Y-m-d H:i:s') : null,
            ':wa' => $whatsapp ? 1 : 0,
            ':wa2' => $whatsapp ? 1 : 0,
            ':id' => $row['id'],
        ]);
        $id = (int) $row['id'];
    } else {
        db()->prepare(
            'INSERT INTO contact_channels
               (person_id, kind, value, label, is_primary, verified_at, whatsapp_capable, whatsapp_checked_at)
             VALUES (:p, :k, :v, :label, :primary, :verified_at, :wa, :wa_at)'
        )->execute([
            ':p' => $personId,
            ':k' => $kind,
            ':v' => $value,
            ':label' => $opts['label'] ?? null,
            ':primary' => !empty($opts['primary']) ? 1 : 0,
            ':verified_at' => $verified ? date('Y-m-d H:i:s') : null,
            ':wa' => $whatsapp ? 1 : 0,
            ':wa_at' => $whatsapp ? date('Y-m-d H:i:s') : null,
        ]);
        $id = (int) db()->lastInsertId();
    }

    // One primary per kind per person.
    if (!empty($opts['primary'])) {
        db()->prepare(
            'UPDATE contact_channels SET is_primary = 0 WHERE person_id = :p AND kind = :k AND id <> :id'
        )->execute([':p' => $personId, ':k' => $kind, ':id' => $id]);
    }

    return $id;
}
