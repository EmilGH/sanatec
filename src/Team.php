<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/People.php';

/**
 * Team members: the people who work here, what they are, and what they may do.
 *
 * The guard rails live here rather than in the form so they hold no matter
 * which page — or which future API — is doing the editing:
 *
 *   - only a system administrator can make or unmake another one
 *   - nobody can deactivate themselves or take their own admin away
 *   - the last active system administrator cannot be deactivated or demoted
 */

/** Operating roles, in the order the form lists them (alphabetical). */
const TEAM_ROLES = [
    'is_cave_guide'     => 'Cave Guide',
    'is_cavern_guide'   => 'Cavern Guide',
    'is_divemaster'     => 'Divemaster',
    'is_driver'         => 'Driver',
    'is_equipment_tech' => 'Equipment Technician',
    'is_excursion_assistant' => 'Excursion Assistant',
    'is_gas_tech'       => 'Gas Prep & Tank Tech',
    'is_instructor'     => 'Instructor',
    'is_shop_help'      => 'Shop Help',
];

/** What someone may open in the admin (alphabetical). Business info is the settings page. */
const TEAM_PERMISSIONS = [
    'can_manage_affiliates' => 'Affiliates',
    'can_manage_business'   => 'Business Info',
    'can_manage_catalog'    => 'Catalog',
    'can_manage_customers'  => 'Customers',
    'can_manage_excursions' => 'Excursions',
    'can_manage_team'       => 'Team',
    'can_manage_training'   => 'Training',
];

/**
 * Services a guide can take tips through: label, link pattern (null when the
 * service has no public page and the handle itself is shown), and a hint.
 */
const TIP_SERVICES = [
    'zelle'       => ['Zelle',        null,                                   'Email or US mobile registered with Zelle'],
    'paypal'      => ['PayPal',       'https://paypal.me/%s',                 'Your PayPal.Me name'],
    'venmo'       => ['Venmo',        'https://venmo.com/u/%s',               'Venmo username, without the @'],
    'revolut'     => ['Revolut',      'https://revolut.me/%s',                'Your Revolut.Me name'],
    'wise'        => ['Wise',         'https://wise.com/pay/me/%s',           'Your Wise pay link name'],
    'mercadopago' => ['Mercado Pago', 'https://link.mercadopago.com.mx/%s',   'Your Mercado Pago link name'],
];

/** Clean a handle as typed: drop a pasted link, a leading @, and spaces. */
function tip_handle_clean(string $service, string $raw): ?string
{
    $h = trim($raw);
    if ($h === '') {
        return null;
    }
    if ($service !== 'zelle') {
        $h = rtrim($h, '/');
        if (str_contains($h, '/')) {
            $h = (string) substr($h, strrpos($h, '/') + 1);
        }
        $h = ltrim($h, '@');
        $h = preg_replace('/\s+/', '', $h) ?? '';
    }

    return $h !== '' && mb_strlen($h) <= 120 ? $h : null;
}

/** Tips from a form: only the services on the list, only what cleans up to something. */
function tip_handles_from_input(array $in): array
{
    $out = [];
    foreach (TIP_SERVICES as $code => $meta) {
        $h = tip_handle_clean($code, (string) ($in['tip_' . $code] ?? ''));
        if ($h !== null) {
            $out[$code] = $h;
        }
    }

    return $out;
}

/** [code => [label, handle, url|null]] for display, from the stored JSON. */
function team_tip_links(?string $json): array
{
    $stored = is_string($json) ? (json_decode($json, true) ?: []) : [];
    $out = [];
    foreach (TIP_SERVICES as $code => [$label, $pattern]) {
        $h = $stored[$code] ?? null;
        if (!is_string($h) || $h === '') {
            continue;
        }
        $out[$code] = [$label, $h, $pattern !== null ? sprintf($pattern, rawurlencode($h)) : null];
    }

    return $out;
}

// Agencies: see src/Lists.php (agencies()); CREDENTIAL_AGENCIES is defined there.

/** Technical and professional ratings renew; recreational cards do not. */
const CREDENTIAL_TYPES = [
    'recreational' => 'Recreational',
    'technical'    => 'Technical',
    'professional' => 'Professional',
];

/** Everyone on the team, with their primary channels, active first. */
function team_list(): array
{
    return db()->query(
        'SELECT t.*, p.name, p.public_id, p.preferred_language,
                (SELECT value FROM contact_channels WHERE person_id = p.id AND kind = "email"  ORDER BY is_primary DESC, id LIMIT 1) AS email,
                (SELECT value FROM contact_channels WHERE person_id = p.id AND kind = "mobile" ORDER BY is_primary DESC, id LIMIT 1) AS mobile,
                (SELECT MIN(expires_on) FROM team_credentials WHERE team_member_id = t.id AND expires_on IS NOT NULL) AS next_expiry
         FROM team_members t
         JOIN people p ON p.id = t.person_id AND p.deleted_at IS NULL
         ORDER BY t.is_active DESC, t.sort_order, p.name'
    )->fetchAll();
}

/** One team member with person fields merged in, or null. */
function team_find(int $teamId): ?array
{
    $stmt = db()->prepare(
        'SELECT t.*, p.name, p.public_id, p.date_of_birth, p.nationality, p.preferred_language, p.timezone,
                p.dan_number, p.dan_expires_on
         FROM team_members t JOIN people p ON p.id = t.person_id AND p.deleted_at IS NULL
         WHERE t.id = :id'
    );
    $stmt->execute([':id' => $teamId]);

    return $stmt->fetch() ?: null;
}

function team_find_by_person(int $personId): ?array
{
    $stmt = db()->prepare('SELECT id FROM team_members WHERE person_id = :p');
    $stmt->execute([':p' => $personId]);
    $id = $stmt->fetchColumn();

    return $id ? team_find((int) $id) : null;
}

function team_active_admin_count(): int
{
    return (int) db()->query(
        'SELECT COUNT(*) FROM team_members t JOIN people p ON p.id = t.person_id
         WHERE t.is_system_admin = 1 AND t.is_active = 1 AND p.deleted_at IS NULL'
    )->fetchColumn();
}

/** A URL-safe slug from a name, made unique against existing ones. */
function team_slug_for(string $name, ?int $excludeTeamId = null): string
{
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', ascii_fold($name)) ?? '', '-')) ?: 'team-member';

    $stmt = db()->prepare('SELECT COUNT(*) FROM team_members WHERE public_slug = :s AND id <> :id');
    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute([':s' => $slug, ':id' => $excludeTeamId ?? 0]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = "{$base}-{$n}";
    }
}

/** "es, en" → ["es","en"]; anything that is not a two-letter code is dropped. */
function team_parse_languages(string $raw): array
{
    $codes = array_filter(array_map(
        static fn (string $c): string => strtolower(trim($c)),
        preg_split('/[,\s]+/', $raw) ?: []
    ), static fn (string $c): bool => preg_match('/^[a-z]{2}$/', $c) === 1);

    return array_values(array_unique($codes));
}

/**
 * Create or update a team member.
 *
 * $in carries person fields (name, date_of_birth, preferred_language, timezone),
 * team fields (job_title, started_on, ended_on, internal_notes, is_active,
 * profile_public, title_*, bio_*, languages, sort_order), and the role and
 * permission flags. $actor is the signed-in user, for the guard rails.
 * Returns the team id.
 */
function team_save(?int $teamId, array $in, array $actor): int
{
    $actorIsAdmin = (int) ($actor['team']['is_system_admin'] ?? 0) === 1;
    $existing = $teamId ? team_find($teamId) : null;
    if ($teamId && $existing === null) {
        throw new RuntimeException('That team member no longer exists.');
    }

    $flags = [];
    foreach (array_merge(array_keys(TEAM_ROLES), array_keys(TEAM_PERMISSIONS)) as $flag) {
        $flags[$flag] = !empty($in[$flag]) ? 1 : 0;
    }
    $active = array_key_exists('is_active', $in) ? (!empty($in['is_active']) ? 1 : 0) : 1;

    // Only an administrator may change who is an administrator.
    $wantAdmin = !empty($in['is_system_admin']) ? 1 : 0;
    $admin = $actorIsAdmin ? $wantAdmin : (int) ($existing['is_system_admin'] ?? 0);

    if ($existing !== null) {
        $isSelf = (int) $existing['person_id'] === (int) $actor['id'];
        if ($isSelf && ($active === 0 || ((int) $existing['is_system_admin'] === 1 && $admin === 0))) {
            throw new RuntimeException('You cannot deactivate yourself or remove your own administrator access.');
        }
        $wasLastAdmin = (int) $existing['is_system_admin'] === 1 && (int) $existing['is_active'] === 1
            && team_active_admin_count() === 1;
        if ($wasLastAdmin && ($active === 0 || $admin === 0)) {
            throw new RuntimeException('There must always be at least one active system administrator.');
        }
    }

    $pdo = db();
    $own = !$pdo->inTransaction();       // join an outer transaction if there is one
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        if ($existing === null) {
            $personId = person_create((string) ($in['name'] ?? ''), $in);
        } else {
            $personId = (int) $existing['person_id'];
            person_update($personId, $in);
        }

        $profilePublic = !empty($in['profile_public']) ? 1 : 0;
        $slug = trim((string) ($in['public_slug'] ?? ''));
        $slug = $slug !== '' ? strtolower(preg_replace('/[^a-z0-9-]+/i', '-', $slug) ?? '') : ($existing['public_slug'] ?? '');
        if ($profilePublic && $slug === '') {
            $slug = team_slug_for((string) ($in['name'] ?? $existing['name'] ?? ''), $teamId);
        }

        $languages = isset($in['languages'])
            ? json_encode(team_parse_languages((string) $in['languages']))
            : ($existing['languages'] ?? null);
        $tipsGiven = array_intersect_key($in, array_flip(array_map(static fn (string $c): string => 'tip_' . $c, array_keys(TIP_SERVICES)))) !== [];
        $tips = $tipsGiven
            ? (($t = tip_handles_from_input($in)) !== [] ? json_encode($t) : null)
            : ($existing['tip_handles'] ?? null);

        $fields = $flags + [
            'is_system_admin' => $admin,
            'is_active'       => $active,
            'job_title'       => trim((string) ($in['job_title'] ?? '')) ?: null,
            'started_on'      => parse_date_input(isset($in['started_on']) ? (string) $in['started_on'] : null),
            'ended_on'        => parse_date_input(isset($in['ended_on']) ? (string) $in['ended_on'] : null),
            'internal_notes'  => trim((string) ($in['internal_notes'] ?? '')) ?: null,
            'profile_public'  => $profilePublic,
            'show_whatsapp_public' => !empty($in['show_whatsapp_public']) ? 1 : 0,
            'public_slug'     => $slug !== '' ? $slug : null,
            'title_en'        => trim((string) ($in['title_en'] ?? '')) ?: null,
            'title_es'        => trim((string) ($in['title_es'] ?? '')) ?: null,
            'bio_en'          => trim((string) ($in['bio_en'] ?? '')) ?: null,
            'bio_es'          => trim((string) ($in['bio_es'] ?? '')) ?: null,
            'languages'       => $languages,
            'tip_handles'     => $tips,
            'sort_order'      => (int) ($in['sort_order'] ?? ($existing['sort_order'] ?? 0)),
        ];

        if ($existing === null) {
            $fields['person_id'] = $personId;
            $cols = implode(', ', array_keys($fields));
            $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
            $pdo->prepare("INSERT INTO team_members ({$cols}) VALUES ({$params})")->execute($fields);
            $teamId = (int) $pdo->lastInsertId();
        } else {
            $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
            $fields['id'] = $teamId;
            $pdo->prepare("UPDATE team_members SET {$set} WHERE id = :id")->execute($fields);
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

    return (int) $teamId;
}

/**
 * The fields a team member may change on their own record: identity and
 * public profile, never roles, permissions or active status.
 */
function team_save_self(array $actor, array $in): void
{
    $team = $actor['team'];
    $safe = array_intersect_key($in, array_flip([
        'name', 'date_of_birth', 'nationality', 'preferred_language', 'timezone', 'dan_number', 'dan_expires_on',
        'profile_public', 'show_whatsapp_public', 'public_slug', 'title_en', 'title_es', 'bio_en', 'bio_es', 'languages',
        'tip_zelle', 'tip_paypal', 'tip_venmo', 'tip_revolut', 'tip_wise', 'tip_mercadopago',
    ]));
    // Carry everything else through unchanged.
    foreach (array_merge(array_keys(TEAM_ROLES), array_keys(TEAM_PERMISSIONS), ['is_system_admin', 'is_active', 'job_title', 'started_on', 'ended_on', 'internal_notes', 'sort_order']) as $k) {
        $safe[$k] = $team[$k];
    }
    team_save((int) $team['id'], $safe, $actor);
}

// ---------------------------------------------------------------------------
// Photo and the public team page
// ---------------------------------------------------------------------------

/** Store a profile photo (square-ish, 800px) and drop the old one. */
function team_photo_set(int $teamId, ?array $file): void
{
    require_once __DIR__ . '/Uploads.php';
    $row = team_find($teamId);
    if ($row === null) {
        throw new RuntimeException('That team member no longer exists.');
    }
    $path = $file !== null ? store_image($file, 'team', 'team-' . $teamId . '-' . bin2hex(random_bytes(3)), 800) : null;
    db()->prepare('UPDATE team_members SET photo_path = :p WHERE id = :id')->execute([':p' => $path, ':id' => $teamId]);
    if ($row['photo_path']) {
        $old = upload_path((string) $row['photo_path']);
        if ($old !== null) {
            @unlink($old);
        }
    }
}

/**
 * Team members shown on the public site: active, opted in. Carries the
 * public WhatsApp number only when the member allowed it, and every
 * current credential (agency + title, never numbers), professional first.
 */
function team_public_list(?string $slug = null): array
{
    $sql = 'SELECT t.id, t.public_slug, t.title_en, t.title_es, t.bio_en, t.bio_es, t.languages, t.tip_handles, t.photo_path,
                   t.is_instructor, t.is_divemaster, t.is_cave_guide, t.is_cavern_guide, t.show_whatsapp_public, p.name,
                   (SELECT cc.value FROM contact_channels cc WHERE cc.person_id = p.id AND cc.kind = "mobile" AND cc.whatsapp_capable = 1
                     ORDER BY cc.is_primary DESC, cc.id LIMIT 1) AS whatsapp
            FROM team_members t JOIN people p ON p.id = t.person_id
            WHERE t.profile_public = 1 AND t.is_active = 1 AND t.public_slug IS NOT NULL AND p.deleted_at IS NULL'
        . ($slug !== null ? ' AND t.public_slug = :slug' : '') . ' ORDER BY t.sort_order, p.name';
    $stmt = db()->prepare($sql);
    $stmt->execute($slug !== null ? [':slug' => $slug] : []);
    $rows = $stmt->fetchAll();
    $creds = db()->prepare('SELECT agency, title FROM team_credentials WHERE team_member_id = :t AND (expires_on IS NULL OR expires_on >= CURDATE()) ORDER BY FIELD(kind, "professional", "technical", "recreational"), id');
    foreach ($rows as &$r) {
        if (!(int) $r['show_whatsapp_public']) {
            $r['whatsapp'] = null;
        }
        $r['languages'] = is_string($r['languages']) ? (json_decode($r['languages'], true) ?: []) : [];
        $r['tips'] = team_tip_links($r['tip_handles']);
        $creds->execute([':t' => $r['id']]);
        $r['credentials'] = array_values(array_unique(array_map(static fn (array $c): string => trim($c['agency'] . ' ' . $c['title']), $creds->fetchAll())));
    }

    return $rows;
}

// ---------------------------------------------------------------------------
// Credentials
// ---------------------------------------------------------------------------

function team_credentials(int $teamId): array
{
    $stmt = db()->prepare(
        'SELECT c.*, DATEDIFF(c.expires_on, CURDATE()) AS days_left
         FROM team_credentials c WHERE team_member_id = :t ORDER BY COALESCE(expires_on, "9999-12-31"), id'
    );
    $stmt->execute([':t' => $teamId]);

    return $stmt->fetchAll();
}

function team_credential_save(int $teamId, ?int $credId, array $in, ?int $verifiedByTeamId = null): int
{
    $kind = (string) ($in['kind'] ?? '');
    if (!isset(CREDENTIAL_TYPES[$kind])) {
        throw new InvalidArgumentException('Pick a credential type.');
    }
    $agency = agency_code($in['agency'] ?? null);
    if ($agency === null) {
        throw new InvalidArgumentException('Pick an agency from the list.');
    }
    // The title comes from the certification list; "other" (or no level at
    // all, for callers that still pass a bare title) means the typed text.
    $level = (string) ($in['level'] ?? '');
    if ($level !== '' && !isset(certification_levels()[$level])) {
        throw new InvalidArgumentException('Pick a certification from the list.');
    }
    $title = $level !== '' && $level !== 'other' ? certification_name($level) : trim((string) ($in['title'] ?? ''));
    if ($title === '' && $level === 'other') {
        $title = certification_name('other');
    }
    if ($title === '') {
        throw new InvalidArgumentException('Give the credential a title — what the card says.');
    }
    $expires = parse_date_input(isset($in['expires_on']) ? (string) $in['expires_on'] : null);
    if ($kind === 'recreational') {
        $expires = null;                                   // recreational cards do not expire
    } elseif ($expires === null) {
        throw new InvalidArgumentException(CREDENTIAL_TYPES[$kind] . ' credentials need an expiration date.');
    }

    $fields = [
        'kind'          => $kind,
        'level'         => $level !== '' ? $level : null,
        'agency'        => $agency,
        'title'         => $title,
        'number'        => trim((string) ($in['number'] ?? '')) ?: null,
        'issued_on'     => ($in['issued_on'] ?? '') !== '' ? $in['issued_on'] : null,
        'expires_on'    => $expires,
        'notes'         => trim((string) ($in['notes'] ?? '')) ?: null,
    ];
    if (!empty($in['verified']) && $verifiedByTeamId !== null) {
        $fields['verified_by'] = $verifiedByTeamId;
        $fields['verified_at'] = date('Y-m-d H:i:s');
    } elseif ($credId !== null && empty($in['verified'])) {
        $fields['verified_by'] = null;                     // an edit that unticks "seen" withdraws it
        $fields['verified_at'] = null;
    }

    if ($credId === null) {
        $fields['team_member_id'] = $teamId;
        $cols = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO team_credentials ({$cols}) VALUES ({$params})")->execute($fields);

        return (int) db()->lastInsertId();
    }

    $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
    $fields['id'] = $credId;
    $fields['t'] = $teamId;
    db()->prepare("UPDATE team_credentials SET {$set} WHERE id = :id AND team_member_id = :t")->execute($fields);

    return $credId;
}

function team_credential_delete(int $teamId, int $credId): void
{
    db()->prepare('DELETE FROM team_credentials WHERE id = :id AND team_member_id = :t')
        ->execute([':id' => $credId, ':t' => $teamId]);
}
