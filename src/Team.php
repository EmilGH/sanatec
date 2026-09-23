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

const TEAM_ROLES = [
    'is_instructor' => 'Instructor',
    'is_divemaster' => 'Divemaster',
    'is_cave_guide' => 'Cave guide',
    'is_driver'     => 'Driver',
];

const TEAM_PERMISSIONS = [
    'can_manage_customers'  => 'Customers',
    'can_manage_excursions' => 'Excursions',
    'can_manage_training'   => 'Training',
    'can_manage_catalog'    => 'Catalog & business info',
    'can_manage_team'       => 'Team',
];

const CREDENTIAL_AGENCIES = ['NAUI', 'PADI', 'TDI'];

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
        'SELECT t.*, p.name, p.public_id, p.date_of_birth, p.nationality, p.preferred_language, p.timezone
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
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $ascii) ?? '', '-')) ?: 'team-member';

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
    $pdo->beginTransaction();
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

        $fields = $flags + [
            'is_system_admin' => $admin,
            'is_active'       => $active,
            'job_title'       => trim((string) ($in['job_title'] ?? '')) ?: null,
            'started_on'      => ($in['started_on'] ?? '') !== '' ? $in['started_on'] : null,
            'ended_on'        => ($in['ended_on'] ?? '') !== '' ? $in['ended_on'] : null,
            'internal_notes'  => trim((string) ($in['internal_notes'] ?? '')) ?: null,
            'profile_public'  => $profilePublic,
            'public_slug'     => $slug !== '' ? $slug : null,
            'title_en'        => trim((string) ($in['title_en'] ?? '')) ?: null,
            'title_es'        => trim((string) ($in['title_es'] ?? '')) ?: null,
            'bio_en'          => trim((string) ($in['bio_en'] ?? '')) ?: null,
            'bio_es'          => trim((string) ($in['bio_es'] ?? '')) ?: null,
            'languages'       => $languages,
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

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
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
        'name', 'date_of_birth', 'preferred_language', 'timezone',
        'profile_public', 'public_slug', 'title_en', 'title_es', 'bio_en', 'bio_es', 'languages',
    ]));
    // Carry everything else through unchanged.
    foreach (array_merge(array_keys(TEAM_ROLES), array_keys(TEAM_PERMISSIONS), ['is_system_admin', 'is_active', 'job_title', 'started_on', 'ended_on', 'internal_notes', 'sort_order']) as $k) {
        $safe[$k] = $team[$k];
    }
    team_save((int) $team['id'], $safe, $actor);
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
    $agency = strtoupper(trim((string) ($in['agency'] ?? '')));
    if (!in_array($agency, CREDENTIAL_AGENCIES, true)) {
        throw new InvalidArgumentException('Pick an agency.');
    }
    $title = trim((string) ($in['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Give the credential a title — what the card says.');
    }
    $expires = ($in['expires_on'] ?? '') !== '' ? (string) $in['expires_on'] : null;
    if ($kind === 'recreational') {
        $expires = null;                                   // recreational cards do not expire
    } elseif ($expires === null) {
        throw new InvalidArgumentException(CREDENTIAL_TYPES[$kind] . ' credentials need an expiration date.');
    }

    $fields = [
        'kind'          => $kind,
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
