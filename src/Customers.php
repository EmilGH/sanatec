<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/People.php';

/**
 * Customers: divers, their history, their paperwork.
 *
 * A customer is a person with a diving profile. Everything a form collects
 * lands here as records — certifications, emergency contacts, signed
 * documents — so "is this diver cleared for Angelita tomorrow" is a query,
 * not a rummage through chat history.
 */

/** Certification levels a route can require, lowest first. */
const CERT_LEVELS = [
    'ow'         => ['Open Water',            1],
    'aow'        => ['Advanced Open Water',   2],
    'rescue'     => ['Rescue Diver',          3],
    'dm'         => ['Divemaster',            4],
    'instructor' => ['Instructor',            5],
    'sidemount'  => ['Sidemount',             2],
    'cavern'     => ['Cavern',                3],
    'intro_cave' => ['Intro to Cave',         4],
    'full_cave'  => ['Full Cave',             5],
    'other'      => ['Other',                 0],
];

const CERT_AGENCIES = ['PADI', 'TDI', 'NAUI', 'SSI', 'SDI', 'CMAS', 'GUE', 'IANTD', 'NSS-CDS', 'Other'];

const ADULT_AGE = 18;

function is_minor(?string $dateOfBirth): bool
{
    if ($dateOfBirth === null || $dateOfBirth === '') {
        return false;
    }
    $dob = new DateTimeImmutable($dateOfBirth);

    return $dob->diff(new DateTimeImmutable('today'))->y < ADULT_AGE;
}

/** Search by name, email or mobile. Empty query lists everyone, newest first. */
function customers_search(string $q = '', int $limit = 200): array
{
    $sql = 'SELECT c.id, c.person_id, c.total_dives, c.last_dive_on, c.discount_pct, p.name, p.date_of_birth, p.dan_expires_on,
                   (SELECT value FROM contact_channels WHERE person_id = p.id AND kind = "email"  ORDER BY is_primary DESC, id LIMIT 1) AS email,
                   (SELECT value FROM contact_channels WHERE person_id = p.id AND kind = "mobile" ORDER BY is_primary DESC, id LIMIT 1) AS mobile,
                   (SELECT level FROM certifications WHERE customer_id = c.id ORDER BY FIELD(level_code, "instructor","full_cave","dm","intro_cave","rescue","cavern","aow","sidemount","ow","other"), id LIMIT 1) AS top_cert,
                   (SELECT COUNT(*) FROM customer_notes WHERE customer_id = c.id) AS note_count
            FROM customers c JOIN people p ON p.id = c.person_id AND p.deleted_at IS NULL';
    $params = [];
    if (trim($q) !== '') {
        $sql .= ' WHERE p.name LIKE :q OR EXISTS (SELECT 1 FROM contact_channels ch WHERE ch.person_id = p.id AND ch.value LIKE :q2)';
        $params[':q'] = '%' . trim($q) . '%';
        $params[':q2'] = '%' . trim($q) . '%';
    }
    $sql .= ' ORDER BY p.name LIMIT ' . (int) $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function customer_find(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT c.*, p.name, p.public_id, p.date_of_birth, p.nationality, p.preferred_language, p.timezone,
                p.dan_number, p.dan_expires_on, g.name AS guardian_name
         FROM customers c
         JOIN people p ON p.id = c.person_id AND p.deleted_at IS NULL
         LEFT JOIN people g ON g.id = c.guardian_person_id
         WHERE c.id = :id'
    );
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

function customer_find_by_person(int $personId): ?array
{
    $stmt = db()->prepare('SELECT id FROM customers WHERE person_id = :p');
    $stmt->execute([':p' => $personId]);
    $id = $stmt->fetchColumn();

    return $id ? customer_find((int) $id) : null;
}

/** Create or update a customer and the person behind it. Returns the customer id. */
function customer_save(?int $id, array $in): int
{
    $existing = $id ? customer_find($id) : null;
    if ($id && $existing === null) {
        throw new RuntimeException('That customer no longer exists.');
    }

    $int = static fn (string $k): ?int => ($in[$k] ?? '') === '' ? null : max(0, (int) $in[$k]);
    $str = static fn (string $k, int $max): ?string => trim((string) ($in[$k] ?? '')) === '' ? null : mb_substr(trim((string) $in[$k]), 0, $max);
    $date = static fn (string $k): ?string => ($in[$k] ?? '') === '' ? null : (string) $in[$k];

    $discount = ($in['discount_pct'] ?? '') === '' ? null : round((float) $in['discount_pct'], 2);
    if ($discount !== null && ($discount < 0 || $discount > 100)) {
        throw new InvalidArgumentException('Discount must be between 0 and 100.');
    }

    $fields = [
        'total_dives'     => $int('total_dives'),
        'dives_last_year' => $int('dives_last_year'),
        'last_dive_on'    => $date('last_dive_on'),
        'local_address'   => $str('local_address', 255),
        'wetsuit_size'    => $str('wetsuit_size', 10),
        'bcd_size'        => $str('bcd_size', 10),
        'fin_size'        => $str('fin_size', 10),
        'boot_size'       => $str('boot_size', 10),
        'height_cm'       => $int('height_cm'),
        'weight_kg'       => $int('weight_kg'),
        'discount_pct'    => $discount,
        'source'          => $str('source', 80),
    ];

    $pdo = db();
    $own = !$pdo->inTransaction();       // join an outer transaction if there is one
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        if ($existing === null) {
            $personId = person_create((string) ($in['name'] ?? ''), $in);
            $fields['person_id'] = $personId;
            $cols = implode(', ', array_keys($fields));
            $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
            $pdo->prepare("INSERT INTO customers ({$cols}) VALUES ({$params})")->execute($fields);
            $id = (int) $pdo->lastInsertId();
        } else {
            person_update((int) $existing['person_id'], $in);
            $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
            $fields['id'] = $id;
            $pdo->prepare("UPDATE customers SET {$set} WHERE id = :id")->execute($fields);
        }
        if ($own) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($own) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return (int) $id;
}

/** Soft-delete: the customer disappears from lists; records stay for erasure to handle. */
function customer_archive(int $id): void
{
    $c = customer_find($id);
    if ($c !== null) {
        db()->prepare('UPDATE people SET deleted_at = NOW() WHERE id = :p')->execute([':p' => $c['person_id']]);
    }
}

/** Link a guardian by their email or mobile. They must already exist as a person. */
function customer_set_guardian(int $id, ?string $identifier): void
{
    $guardianId = null;
    if ($identifier !== null && trim($identifier) !== '') {
        $kind = str_contains($identifier, '@') ? 'email' : 'mobile';
        $g = person_find_by_channel($kind, $identifier);
        if ($g === null) {
            throw new RuntimeException('No person has that email or mobile. Add the guardian as a person first.');
        }
        $c = customer_find($id);
        if ((int) $g['id'] === (int) $c['person_id']) {
            throw new RuntimeException('A person cannot be their own guardian.');
        }
        $guardianId = (int) $g['id'];
    }
    db()->prepare('UPDATE customers SET guardian_person_id = :g WHERE id = :id')->execute([':g' => $guardianId, ':id' => $id]);
}

// ---------------------------------------------------------------------------
// Notes
// ---------------------------------------------------------------------------

function customer_notes(int $id): array
{
    $stmt = db()->prepare(
        'SELECT n.*, p.name AS author FROM customer_notes n LEFT JOIN people p ON p.id = n.author_person_id
         WHERE n.customer_id = :c ORDER BY n.id DESC'
    );
    $stmt->execute([':c' => $id]);

    return $stmt->fetchAll();
}

function customer_note_add(int $id, ?int $authorPersonId, string $body): int
{
    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('An empty note is not a note.');
    }
    db()->prepare('INSERT INTO customer_notes (customer_id, author_person_id, body) VALUES (:c, :a, :b)')
        ->execute([':c' => $id, ':a' => $authorPersonId, ':b' => $body]);

    return (int) db()->lastInsertId();
}

// ---------------------------------------------------------------------------
// Emergency contacts
// ---------------------------------------------------------------------------

function emergency_contacts(int $customerId): array
{
    $stmt = db()->prepare('SELECT * FROM emergency_contacts WHERE customer_id = :c ORDER BY is_primary DESC, id');
    $stmt->execute([':c' => $customerId]);

    return $stmt->fetchAll();
}

function emergency_contact_save(int $customerId, ?int $ecId, array $in): int
{
    $name = trim((string) ($in['name'] ?? ''));
    $phone = normalize_mobile($in['phone'] ?? null);
    if ($name === '') {
        throw new InvalidArgumentException('An emergency contact needs a name.');
    }
    if ($phone === null) {
        throw new InvalidArgumentException('Emergency phone must be +countrycode then digits.');
    }
    $email = trim((string) ($in['email'] ?? ''));
    if ($email !== '' && normalize_email($email) === null) {
        throw new InvalidArgumentException('That email address does not look right.');
    }

    $fields = [
        'name'         => $name,
        'relationship' => trim((string) ($in['relationship'] ?? '')) ?: null,
        'phone'        => $phone,
        'email'        => $email !== '' ? normalize_email($email) : null,
        'is_primary'   => !empty($in['is_primary']) ? 1 : 0,
        'notes'        => trim((string) ($in['notes'] ?? '')) ?: null,
    ];

    if ($ecId === null) {
        // The first contact is primary whether or not the box was ticked.
        if (emergency_contacts($customerId) === []) {
            $fields['is_primary'] = 1;
        }
        $fields['customer_id'] = $customerId;
        $cols = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO emergency_contacts ({$cols}) VALUES ({$params})")->execute($fields);
        $ecId = (int) db()->lastInsertId();
    } else {
        $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
        $fields['id'] = $ecId;
        $fields['c'] = $customerId;
        db()->prepare("UPDATE emergency_contacts SET {$set} WHERE id = :id AND customer_id = :c")->execute($fields);
    }

    if ($fields['is_primary']) {
        db()->prepare('UPDATE emergency_contacts SET is_primary = 0 WHERE customer_id = :c AND id <> :id')
            ->execute([':c' => $customerId, ':id' => $ecId]);
    }

    return $ecId;
}

function emergency_contact_delete(int $customerId, int $ecId): void
{
    db()->prepare('DELETE FROM emergency_contacts WHERE id = :id AND customer_id = :c')->execute([':id' => $ecId, ':c' => $customerId]);
}

// ---------------------------------------------------------------------------
// Certifications
// ---------------------------------------------------------------------------

function certifications(int $customerId): array
{
    $stmt = db()->prepare('SELECT * FROM certifications WHERE customer_id = :c ORDER BY issued_on DESC, id DESC');
    $stmt->execute([':c' => $customerId]);

    return $stmt->fetchAll();
}

function certification_save(int $customerId, ?int $certId, array $in, ?int $verifiedByTeamId = null): int
{
    $code = (string) ($in['level_code'] ?? '');
    if (!isset(CERT_LEVELS[$code])) {
        throw new InvalidArgumentException('Pick a certification level.');
    }
    $agency = trim((string) ($in['agency'] ?? ''));
    if ($agency === '') {
        throw new InvalidArgumentException('Which agency issued it?');
    }
    $fields = [
        'agency'     => mb_substr($agency, 0, 40),
        'level_code' => $code,
        'level'      => trim((string) ($in['level'] ?? '')) ?: CERT_LEVELS[$code][0],
        'number'     => trim((string) ($in['number'] ?? '')) ?: null,
        'issued_on'  => ($in['issued_on'] ?? '') !== '' ? $in['issued_on'] : null,
        'notes'      => trim((string) ($in['notes'] ?? '')) ?: null,
    ];
    if (!empty($in['verified']) && $verifiedByTeamId !== null) {
        $fields['verified_by'] = $verifiedByTeamId;
        $fields['verified_at'] = date('Y-m-d H:i:s');
    }

    if ($certId === null) {
        $fields['customer_id'] = $customerId;
        $cols = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO certifications ({$cols}) VALUES ({$params})")->execute($fields);

        return (int) db()->lastInsertId();
    }

    $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
    $fields['id'] = $certId;
    $fields['c'] = $customerId;
    db()->prepare("UPDATE certifications SET {$set} WHERE id = :id AND customer_id = :c")->execute($fields);

    return $certId;
}

function certification_delete(int $customerId, int $certId): void
{
    db()->prepare('DELETE FROM certifications WHERE id = :id AND customer_id = :c')->execute([':id' => $certId, ':c' => $customerId]);
}

/** Does this customer hold at least the given level? Uses the rank in CERT_LEVELS. */
function customer_holds_level(int $customerId, string $requiredCode): bool
{
    $need = CERT_LEVELS[$requiredCode][1] ?? 0;
    foreach (certifications($customerId) as $c) {
        if ((CERT_LEVELS[$c['level_code']][1] ?? 0) >= $need && $need > 0) {
            return true;
        }
    }

    return false;
}

// ---------------------------------------------------------------------------
// Documents
// ---------------------------------------------------------------------------

/**
 * Where each active document stands for a customer.
 *
 * One row per active template: the latest signed submission if any, and a
 * status of signed / expired / draft / missing. The medical row also carries
 * the evaluation outcome, because "signed" is not the same as "cleared".
 */
function customer_document_status(int $customerId): array
{
    $templates = db()->query('SELECT * FROM form_templates WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    $out = [];

    foreach ($templates as $t) {
        $stmt = db()->prepare(
            'SELECT s.*, m.outcome, m.physician_cleared_on
             FROM form_submissions s
             LEFT JOIN medical_evaluations m ON m.submission_id = s.id
             WHERE s.customer_id = :c AND s.template_id = :t AND s.status <> "void"
             ORDER BY s.status = "signed" DESC, s.signed_at DESC, s.id DESC LIMIT 1'
        );
        $stmt->execute([':c' => $customerId, ':t' => $t['id']]);
        $s = $stmt->fetch() ?: null;

        $status = 'missing';
        if ($s !== null) {
            if ($s['status'] === 'draft') {
                $status = 'draft';
            } elseif ($s['expires_on'] !== null && $s['expires_on'] < date('Y-m-d')) {
                $status = 'expired';
            } else {
                $status = 'signed';
            }
        }

        $out[] = [
            'template'   => $t,
            'submission' => $s,
            'status'     => $status,
            'outcome'    => $s['outcome'] ?? null,
            'ok'         => $status === 'signed' && ($t['code'] !== 'medical' || in_array($s['outcome'] ?? '', ['cleared', 'physician_cleared'], true)),
        ];
    }

    return $out;
}

/** True when every active document is signed, current, and (for medical) cleared. */
function customer_documents_complete(int $customerId): bool
{
    foreach (customer_document_status($customerId) as $row) {
        if (!$row['ok']) {
            return false;
        }
    }

    return true;
}
