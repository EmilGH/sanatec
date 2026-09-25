<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Uploads.php';

/**
 * The CENOTE Exploration Passport.
 *
 * Dive sites are the cenotes; a route is an ordered list of them; a dive is
 * one diver at one site on one date. Dives are written when a diver is marked
 * attended on an event, from the sessions that carry a site — the shop's own
 * records fill the log. Stamps are the distinct sites dived.
 */

function dive_sites(bool $publishedOnly = true): array
{
    return db()->query('SELECT * FROM dive_sites' . ($publishedOnly ? ' WHERE is_published = 1' : '') . ' ORDER BY sort_order, name_en')->fetchAll();
}

function dive_site_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dive_sites WHERE id = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

function dive_site_save(?int $id, array $in): int
{
    $name = trim((string) ($in['name_en'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('A site needs a name.');
    }
    $fields = [
        'name_en'        => $name,
        'name_es'        => trim((string) ($in['name_es'] ?? '')) ?: $name,
        'description_en' => trim((string) ($in['description_en'] ?? '')) ?: null,
        'description_es' => trim((string) ($in['description_es'] ?? '')) ?: null,
        'max_depth_m'    => ($in['max_depth_m'] ?? '') === '' ? null : max(0, (int) $in['max_depth_m']),
        'cert_required'  => isset(CERT_LEVELS[$in['cert_required'] ?? '']) ? $in['cert_required'] : null,
        'latitude'       => ($in['latitude'] ?? '') === '' ? null : (float) $in['latitude'],
        'longitude'      => ($in['longitude'] ?? '') === '' ? null : (float) $in['longitude'],
        'is_published'   => !empty($in['is_published']) ? 1 : 0,
        'sort_order'     => (int) ($in['sort_order'] ?? 0),
    ];
    if ($id === null) {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', ascii_fold($name)) ?? '', '-')) ?: 'site';
        $slug = $base;
        for ($n = 2; (int) db()->query('SELECT COUNT(*) FROM dive_sites WHERE slug = ' . db()->quote($slug))->fetchColumn() > 0; $n++) {
            $slug = "{$base}-{$n}";
        }
        $fields['slug'] = $slug;
        $cols = implode(', ', array_keys($fields));
        $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
        db()->prepare("INSERT INTO dive_sites ({$cols}) VALUES ({$params})")->execute($fields);

        return (int) db()->lastInsertId();
    }
    $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
    $fields['id'] = $id;
    db()->prepare("UPDATE dive_sites SET {$set} WHERE id = :id")->execute($fields);

    return $id;
}

/** A route's sites in order. */
function excursion_sites(int $excursionId): array
{
    $stmt = db()->prepare('SELECT s.* FROM excursion_sites x JOIN dive_sites s ON s.id = x.dive_site_id WHERE x.excursion_id = :e ORDER BY x.sort_order');
    $stmt->execute([':e' => $excursionId]);

    return $stmt->fetchAll();
}

/** Set a route's sites from an ordered list of site ids. */
function excursion_sites_set(int $excursionId, array $siteIds): void
{
    db()->prepare('DELETE FROM excursion_sites WHERE excursion_id = :e')->execute([':e' => $excursionId]);
    $ins = db()->prepare('INSERT IGNORE INTO excursion_sites (excursion_id, dive_site_id, sort_order) VALUES (:e, :s, :o)');
    foreach (array_values(array_unique(array_map('intval', $siteIds))) as $i => $sid) {
        if ($sid > 0) {
            $ins->execute([':e' => $excursionId, ':s' => $sid, ':o' => $i]);
        }
    }
}

/** Write dives for an attended participant from the event's sited sessions. Idempotent. */
function passport_record_attendance(int $eventId, int $participantId): int
{
    $p = db()->prepare('SELECT ep.*, e.kind FROM event_participants ep JOIN events e ON e.id = ep.event_id WHERE ep.id = :id AND ep.event_id = :e');
    $p->execute([':id' => $participantId, ':e' => $eventId]);
    $part = $p->fetch();
    if (!$part) {
        return 0;
    }
    $guide = db()->prepare('SELECT team_member_id FROM event_team WHERE event_id = :e AND status = "confirmed" ORDER BY FIELD(role, "lead", "instructor", "guide") LIMIT 1');
    $guide->execute([':e' => $eventId]);
    $guideId = $guide->fetchColumn() ?: null;

    $sessions = db()->prepare('SELECT * FROM event_sessions WHERE event_id = :e AND dive_site_id IS NOT NULL ORDER BY starts_at');
    $sessions->execute([':e' => $eventId]);
    $ins = db()->prepare(
        'INSERT IGNORE INTO dives (customer_id, participant_id, session_id, dive_site_id, dived_on, guide_team_id)
         VALUES (:c, :p, :s, :site, :on, :g)'
    );
    $n = 0;
    foreach ($sessions->fetchAll() as $s) {
        $ins->execute([':c' => $part['customer_id'], ':p' => $participantId, ':s' => $s['id'], ':site' => $s['dive_site_id'], ':on' => substr($s['starts_at'], 0, 10), ':g' => $guideId]);
        $n += $ins->rowCount();
    }

    return $n;
}

/** A diver's dives, newest first, with site and guide. */
function customer_dives(int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT d.*, s.name_en AS site_en, s.name_es AS site_es, s.slug AS site_slug, e.title_en AS event_title, e.kind,
                gp.name AS guide_name,
                (SELECT COUNT(*) FROM dive_photos ph WHERE ph.dive_id = d.id) AS photo_count
         FROM dives d
         JOIN dive_sites s ON s.id = d.dive_site_id
         LEFT JOIN event_participants ep ON ep.id = d.participant_id
         LEFT JOIN events e ON e.id = ep.event_id
         LEFT JOIN team_members gt ON gt.id = d.guide_team_id
         LEFT JOIN people gp ON gp.id = gt.person_id
         WHERE d.customer_id = :c ORDER BY d.dived_on DESC, d.id DESC'
    );
    $stmt->execute([':c' => $customerId]);

    return $stmt->fetchAll();
}

/** Stamps: each site dived, with how many times and when first. */
function customer_stamps(int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT s.*, COUNT(d.id) AS dives, MIN(d.dived_on) AS first_on, MAX(d.dived_on) AS last_on
         FROM dives d JOIN dive_sites s ON s.id = d.dive_site_id
         WHERE d.customer_id = :c GROUP BY s.id ORDER BY MIN(d.dived_on)'
    );
    $stmt->execute([':c' => $customerId]);

    return $stmt->fetchAll();
}

function customer_wishlist(int $customerId): array
{
    $stmt = db()->prepare('SELECT dive_site_id FROM wishlist WHERE customer_id = :c');
    $stmt->execute([':c' => $customerId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function wishlist_toggle(int $customerId, int $siteId): bool
{
    $has = in_array($siteId, customer_wishlist($customerId), true);
    if ($has) {
        db()->prepare('DELETE FROM wishlist WHERE customer_id = :c AND dive_site_id = :s')->execute([':c' => $customerId, ':s' => $siteId]);
    } else {
        db()->prepare('INSERT IGNORE INTO wishlist (customer_id, dive_site_id) VALUES (:c, :s)')->execute([':c' => $customerId, ':s' => $siteId]);
    }

    return !$has;
}

/** A dive the diver may edit: theirs, by id. */
function customer_dive(int $customerId, int $diveId): ?array
{
    $stmt = db()->prepare('SELECT * FROM dives WHERE id = :id AND customer_id = :c');
    $stmt->execute([':id' => $diveId, ':c' => $customerId]);

    return $stmt->fetch() ?: null;
}

function dive_update_notes(int $customerId, int $diveId, array $in): void
{
    db()->prepare('UPDATE dives SET notes = :n, max_depth_m = :d, duration_min = :m WHERE id = :id AND customer_id = :c')->execute([
        ':n' => mb_substr(trim((string) ($in['notes'] ?? '')), 0, 500) ?: null,
        ':d' => ($in['max_depth_m'] ?? '') === '' ? null : round((float) $in['max_depth_m'], 1),
        ':m' => ($in['duration_min'] ?? '') === '' ? null : max(0, (int) $in['duration_min']),
        ':id' => $diveId, ':c' => $customerId,
    ]);
}

function dive_photos(int $diveId): array
{
    $stmt = db()->prepare('SELECT * FROM dive_photos WHERE dive_id = :d ORDER BY id');
    $stmt->execute([':d' => $diveId]);

    return $stmt->fetchAll();
}

function dive_photo_add(int $diveId, array $file, ?int $uploaderPersonId, string $caption = ''): int
{
    $path = store_image($file, 'dives', 'dive-' . $diveId . '-' . bin2hex(random_bytes(4)), 1600);
    db()->prepare('INSERT INTO dive_photos (dive_id, path, caption, uploaded_by) VALUES (:d, :p, :c, :u)')
        ->execute([':d' => $diveId, ':p' => $path, ':c' => mb_substr(trim($caption), 0, 200) ?: null, ':u' => $uploaderPersonId]);

    return (int) db()->lastInsertId();
}

function dive_photo_set_public(int $photoId, int $customerId, bool $public): void
{
    db()->prepare('UPDATE dive_photos ph JOIN dives d ON d.id = ph.dive_id SET ph.is_public = :p WHERE ph.id = :id AND d.customer_id = :c')
        ->execute([':p' => $public ? 1 : 0, ':id' => $photoId, ':c' => $customerId]);
}

function dive_photo_delete(int $photoId, int $customerId): void
{
    $stmt = db()->prepare('SELECT ph.path FROM dive_photos ph JOIN dives d ON d.id = ph.dive_id WHERE ph.id = :id AND d.customer_id = :c');
    $stmt->execute([':id' => $photoId, ':c' => $customerId]);
    $path = $stmt->fetchColumn();
    if ($path) {
        db()->prepare('DELETE FROM dive_photos WHERE id = :id')->execute([':id' => $photoId]);
        $abs = upload_path((string) $path);
        if ($abs !== null) {
            @unlink($abs);
        }
    }
}

/** What a public passport page shows: first name, counts, stamps, public photos. */
function passport_public(string $publicId): ?array
{
    $stmt = db()->prepare(
        'SELECT c.id, c.passport_public, p.name, p.public_id FROM customers c JOIN people p ON p.id = c.person_id
         WHERE p.public_id = :pid AND p.deleted_at IS NULL'
    );
    $stmt->execute([':pid' => $publicId]);
    $c = $stmt->fetch();
    if (!$c || (int) $c['passport_public'] !== 1) {
        return null;
    }
    $dives = customer_dives((int) $c['id']);
    $photos = db()->prepare('SELECT ph.*, d.dived_on, s.name_en AS site_en FROM dive_photos ph JOIN dives d ON d.id = ph.dive_id JOIN dive_sites s ON s.id = d.dive_site_id WHERE d.customer_id = :c AND ph.is_public = 1 ORDER BY d.dived_on DESC, ph.id');
    $photos->execute([':c' => $c['id']]);

    return [
        'name'   => explode(' ', trim($c['name']))[0],
        'dives'  => $dives,
        'stamps' => customer_stamps((int) $c['id']),
        'photos' => $photos->fetchAll(),
    ];
}
