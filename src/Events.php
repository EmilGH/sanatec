<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Customers.php';
require_once __DIR__ . '/Team.php';

/**
 * Events: excursions and training, dated.
 *
 * One model. What differs by kind is how the default day plan is built and
 * which liability release the diver must have signed.
 */

const EVENT_ROLES = ['lead' => 'Lead', 'instructor' => 'Instructor', 'guide' => 'Guide', 'driver' => 'Driver', 'support' => 'Support'];
const PARTICIPANT_STATUSES = ['invited' => 'Invited', 'confirmed' => 'Confirmed', 'attended' => 'Attended', 'no_show' => 'No-show', 'cancelled' => 'Cancelled'];
const PAYMENT_METHODS = ['cash' => 'Cash', 'transfer' => 'Transfer', 'card' => 'Card', 'other' => 'Other'];

function event_kind_label(string $kind, bool $plural = false): string
{
    return $kind === 'training' ? ($plural ? 'Training' : 'Course') : ($plural ? 'Excursions' : 'Excursion');
}

function events_list(string $kind, string $when = 'upcoming'): array
{
    $cmp = $when === 'past' ? '<' : '>=';
    $order = $when === 'past' ? 'DESC' : 'ASC';
    $stmt = db()->prepare(
        "SELECT e.*,
                (SELECT COUNT(*) FROM event_participants p WHERE p.event_id = e.id AND p.status IN ('invited','confirmed','attended')) AS taken,
                (SELECT COUNT(*) FROM event_team t WHERE t.event_id = e.id AND t.status = 'confirmed') AS team_count
         FROM events e WHERE e.kind = :k AND e.starts_on {$cmp} CURDATE() AND e.status <> 'cancelled'
         ORDER BY e.starts_on {$order}, e.id {$order} LIMIT 200"
    );
    $stmt->execute([':k' => $kind]);

    return $stmt->fetchAll();
}

function event_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

function event_slug_for(string $date, string $name): string
{
    $base = $date . '-' . (strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', ascii_fold($name)) ?? '', '-')) ?: 'event');
    $stmt = db()->prepare('SELECT COUNT(*) FROM events WHERE slug = :s');
    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute([':s' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = "{$base}-{$n}";
    }
}

/** The number of days a course runs, read from "3 days" / "2, 3 or 4 weeks" (first number, days assumed). */
function course_days(array $course): int
{
    if (preg_match('/(\d+)/', (string) $course['duration_en'], $m) !== 1) {
        return 1;
    }
    $n = (int) $m[1];

    return stripos((string) $course['duration_en'], 'week') !== false ? min($n * 7, 30) : max(1, min($n, 30));
}

/**
 * Create an event from a catalogue item on a date. $in: catalog_id, date,
 * meet_time (HH:MM), dives_count (excursions), capacity, lead_team_id.
 * Returns the event id.
 */
function event_create(string $kind, array $in, ?int $createdByTeamId = null): int
{
    $date = (string) ($in['date'] ?? '');
    if ($date === '' || strtotime($date) === false) {
        throw new InvalidArgumentException('Pick a date.');
    }
    $meet = preg_match('/^\d{2}:\d{2}$/', (string) ($in['meet_time'] ?? '')) ? $in['meet_time'] : '07:30';
    $capacity = max(1, min(60, (int) ($in['capacity'] ?? 8)));
    $item = catalog_find($kind === 'training' ? 'courses' : 'excursions', (int) ($in['catalog_id'] ?? 0));
    if ($item === null) {
        throw new InvalidArgumentException('Pick a ' . ($kind === 'training' ? 'course' : 'cenote route') . ' from the catalogue.');
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        if ($kind === 'training') {
            $dives = null;
            $price = $item['price_mxn'];
            $title_en = $item['name_en'];
            $title_es = $item['name_es'] ?: $item['name_en'];
        } else {
            $dives = max(1, min(3, (int) ($in['dives_count'] ?? 2)));
            $col = ['price_1_dive', 'price_2_dives', 'price_3_dives'][$dives - 1];
            if ($item[$col] === null) {
                throw new InvalidArgumentException('That route is not sold with ' . $dives . ' dive' . ($dives > 1 ? 's' : '') . '.');
            }
            $price = $item[$col];
            $title_en = $item['name_en'] . ' · ' . $dives . ' dive' . ($dives > 1 ? 's' : '');
            $title_es = ($item['name_es'] ?: $item['name_en']) . ' · ' . $dives . ' inmersi' . ($dives > 1 ? 'ones' : 'ón');
        }

        $pdo->prepare(
            'INSERT INTO events (kind, slug, title_en, title_es, excursion_id, course_id, dives_count, price_mxn, capacity, starts_on, created_by)
             VALUES (:k, :slug, :te, :ts, :x, :c, :d, :p, :cap, :on, :by)'
        )->execute([
            ':k' => $kind, ':slug' => event_slug_for($date, $item['name_en']), ':te' => $title_en, ':ts' => $title_es,
            ':x' => $kind === 'excursion' ? $item['id'] : null, ':c' => $kind === 'training' ? $item['id'] : null,
            ':d' => $dives, ':p' => $price, ':cap' => $capacity, ':on' => $date, ':by' => $createdByTeamId,
        ]);
        $eventId = (int) $pdo->lastInsertId();

        // Default day plan.
        $ins = $pdo->prepare('INSERT INTO event_sessions (event_id, starts_at, title_en, title_es, location, sort_order) VALUES (:e, :at, :te, :ts, :loc, :o)');
        if ($kind === 'excursion') {
            $ins->execute([':e' => $eventId, ':at' => "{$date} {$meet}:00", ':te' => 'Meet at the shop', ':ts' => 'Encuentro en el centro', ':loc' => setting('business_name'), ':o' => 0]);
            $t = strtotime("{$date} {$meet}:00") + 3600;
            for ($i = 1; $i <= $dives; $i++) {
                $ins->execute([':e' => $eventId, ':at' => date('Y-m-d H:i:s', $t), ':te' => "Dive {$i}", ':ts' => "Inmersión {$i}", ':loc' => $item['name_en'], ':o' => $i]);
                $t += 2 * 3600 + 15 * 60;
            }
        } else {
            $days = course_days($item);
            for ($i = 0; $i < $days; $i++) {
                $d = date('Y-m-d', strtotime("{$date} +{$i} days"));
                $ins->execute([':e' => $eventId, ':at' => "{$d} {$meet}:00", ':te' => 'Day ' . ($i + 1), ':ts' => 'Día ' . ($i + 1), ':loc' => setting('business_name'), ':o' => $i]);
            }
        }

        if (!empty($in['lead_team_id'])) {
            event_team_add($eventId, (int) $in['lead_team_id'], $kind === 'training' ? 'instructor' : 'lead');
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

    return $eventId;
}

function event_update(int $id, array $in): void
{
    $fields = [
        'capacity'       => max(1, min(60, (int) ($in['capacity'] ?? 8))),
        'status'         => in_array($in['status'] ?? '', ['draft', 'open', 'done', 'cancelled'], true) ? $in['status'] : 'open',
        'internal_notes' => trim((string) ($in['internal_notes'] ?? '')) ?: null,
        'price_mxn'      => ($in['price_mxn'] ?? '') === '' ? null : parse_money((string) $in['price_mxn']),
    ];
    $fields['id'] = $id;
    db()->prepare('UPDATE events SET capacity = :capacity, status = :status, internal_notes = :internal_notes, price_mxn = :price_mxn WHERE id = :id')->execute($fields);
}

// ---------------------------------------------------------------------------
// Sessions
// ---------------------------------------------------------------------------

function event_sessions(int $eventId): array
{
    $stmt = db()->prepare('SELECT * FROM event_sessions WHERE event_id = :e ORDER BY starts_at, sort_order, id');
    $stmt->execute([':e' => $eventId]);

    return $stmt->fetchAll();
}

function event_session_save(int $eventId, ?int $sessionId, array $in): int
{
    $at = (string) ($in['starts_at'] ?? '');
    if ($at === '' || strtotime($at) === false) {
        throw new InvalidArgumentException('When does it start?');
    }
    $title = trim((string) ($in['title_en'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Give the session a title.');
    }
    $fields = [
        'starts_at' => date('Y-m-d H:i:s', strtotime($at)),
        'ends_at'   => ($in['ends_at'] ?? '') !== '' && strtotime((string) $in['ends_at']) !== false ? date('Y-m-d H:i:s', strtotime((string) $in['ends_at'])) : null,
        'title_en'  => $title,
        'title_es'  => trim((string) ($in['title_es'] ?? '')) ?: $title,
        'location'  => trim((string) ($in['location'] ?? '')) ?: null,
    ];
    if ($sessionId === null) {
        $fields['event_id'] = $eventId;
        $fields['sort_order'] = (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM event_sessions WHERE event_id = {$eventId}")->fetchColumn();
        db()->prepare('INSERT INTO event_sessions (event_id, starts_at, ends_at, title_en, title_es, location, sort_order) VALUES (:event_id, :starts_at, :ends_at, :title_en, :title_es, :location, :sort_order)')->execute($fields);
        $sessionId = (int) db()->lastInsertId();
    } else {
        $fields['id'] = $sessionId;
        $fields['e'] = $eventId;
        db()->prepare('UPDATE event_sessions SET starts_at = :starts_at, ends_at = :ends_at, title_en = :title_en, title_es = :title_es, location = :location WHERE id = :id AND event_id = :e')->execute($fields);
    }
    db()->prepare('UPDATE events SET starts_on = (SELECT MIN(DATE(starts_at)) FROM event_sessions WHERE event_id = :e) WHERE id = :e2')->execute([':e' => $eventId, ':e2' => $eventId]);

    return $sessionId;
}

function event_session_delete(int $eventId, int $sessionId): void
{
    db()->prepare('DELETE FROM event_sessions WHERE id = :id AND event_id = :e')->execute([':id' => $sessionId, ':e' => $eventId]);
}

// ---------------------------------------------------------------------------
// Team
// ---------------------------------------------------------------------------

function event_team(int $eventId): array
{
    $stmt = db()->prepare(
        'SELECT et.*, p.name, t.job_title, t.is_instructor, t.is_cave_guide
         FROM event_team et JOIN team_members t ON t.id = et.team_member_id JOIN people p ON p.id = t.person_id
         WHERE et.event_id = :e ORDER BY FIELD(et.role, "lead", "instructor", "guide", "driver", "support"), p.name'
    );
    $stmt->execute([':e' => $eventId]);

    return $stmt->fetchAll();
}

function event_team_add(int $eventId, int $teamMemberId, string $role = 'guide'): void
{
    if (!isset(EVENT_ROLES[$role])) {
        throw new InvalidArgumentException('Unknown role.');
    }
    db()->prepare('INSERT INTO event_team (event_id, team_member_id, role) VALUES (:e, :t, :r) ON DUPLICATE KEY UPDATE role = VALUES(role)')
        ->execute([':e' => $eventId, ':t' => $teamMemberId, ':r' => $role]);
}

function event_team_set_status(int $eventId, int $rowId, string $status): void
{
    if (!in_array($status, ['invited', 'confirmed', 'declined'], true)) {
        return;
    }
    db()->prepare('UPDATE event_team SET status = :s WHERE id = :id AND event_id = :e')->execute([':s' => $status, ':id' => $rowId, ':e' => $eventId]);
}

function event_team_remove(int $eventId, int $rowId): void
{
    db()->prepare('DELETE FROM event_team WHERE id = :id AND event_id = :e')->execute([':id' => $rowId, ':e' => $eventId]);
}

/** Instructor names on a training event, for the liability release. */
function event_instructor_names(int $eventId): string
{
    $names = [];
    foreach (event_team($eventId) as $m) {
        if (in_array($m['role'], ['lead', 'instructor'], true) && $m['status'] !== 'declined') {
            $names[] = $m['name'];
        }
    }

    return implode(', ', $names);
}

// ---------------------------------------------------------------------------
// Participants
// ---------------------------------------------------------------------------

function event_participants(int $eventId): array
{
    $stmt = db()->prepare(
        'SELECT ep.*, p.name, p.date_of_birth, c.wetsuit_size, c.fin_size, c.person_id,
                (SELECT value FROM contact_channels WHERE person_id = p.id AND kind = "mobile" ORDER BY is_primary DESC, id LIMIT 1) AS mobile,
                (SELECT whatsapp_capable FROM contact_channels WHERE person_id = p.id AND kind = "mobile" ORDER BY is_primary DESC, id LIMIT 1) AS whatsapp,
                (SELECT level FROM certifications WHERE customer_id = c.id ORDER BY FIELD(level_code, "instructor","full_cave","dm","intro_cave","rescue","cavern","aow","sidemount","ow","other"), id LIMIT 1) AS top_cert,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE participant_id = ep.id AND currency = "MXN") AS paid_mxn
         FROM event_participants ep
         JOIN customers c ON c.id = ep.customer_id
         JOIN people p ON p.id = c.person_id
         WHERE ep.event_id = :e
         ORDER BY FIELD(ep.status, "confirmed", "invited", "attended", "no_show", "cancelled"), p.name'
    );
    $stmt->execute([':e' => $eventId]);

    return $stmt->fetchAll();
}

function event_places_taken(int $eventId): int
{
    return (int) db()->query("SELECT COUNT(*) FROM event_participants WHERE event_id = {$eventId} AND status IN ('invited','confirmed','attended')")->fetchColumn();
}

/** Add a diver. The price is the event's list price less the customer's discount, fixed now. */
function event_participant_add(int $eventId, int $customerId, ?int $addedByTeamId = null): int
{
    $event = event_find($eventId);
    $customer = customer_find($customerId);
    if ($event === null || $customer === null) {
        throw new RuntimeException('Event or customer not found.');
    }
    if (event_places_taken($eventId) >= (int) $event['capacity']) {
        throw new RuntimeException('This ' . strtolower(event_kind_label($event['kind'])) . ' is full (' . $event['capacity'] . ' places).');
    }
    $price = $event['price_mxn'] !== null
        ? round((float) $event['price_mxn'] * (1 - ((float) ($customer['discount_pct'] ?? 0)) / 100), 2)
        : null;

    try {
        db()->prepare('INSERT INTO event_participants (event_id, customer_id, price_mxn, added_by) VALUES (:e, :c, :p, :by)')
            ->execute([':e' => $eventId, ':c' => $customerId, ':p' => $price, ':by' => $addedByTeamId]);
    } catch (PDOException $ex) {
        throw new RuntimeException($customer['name'] . ' is already on this event.');
    }

    return (int) db()->lastInsertId();
}

function event_participant_set_status(int $eventId, int $rowId, string $status): void
{
    if (!isset(PARTICIPANT_STATUSES[$status])) {
        return;
    }
    db()->prepare('UPDATE event_participants SET status = :s WHERE id = :id AND event_id = :e')->execute([':s' => $status, ':id' => $rowId, ':e' => $eventId]);
}

function event_participant_remove(int $eventId, int $rowId): void
{
    db()->prepare('DELETE FROM event_participants WHERE id = :id AND event_id = :e')->execute([':id' => $rowId, ':e' => $eventId]);
}

/** Events a customer is or was on, newest first. */
function customer_events(int $customerId): array
{
    $stmt = db()->prepare(
        'SELECT e.*, ep.id AS participant_id, ep.status AS participation, ep.price_mxn AS agreed_price,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE participant_id = ep.id AND currency = "MXN") AS paid_mxn
         FROM event_participants ep JOIN events e ON e.id = ep.event_id
         WHERE ep.customer_id = :c ORDER BY e.starts_on DESC'
    );
    $stmt->execute([':c' => $customerId]);

    return $stmt->fetchAll();
}

/** The customer's next event of a kind, if any — the training release names its instructors. */
function customer_next_event(int $customerId, ?string $kind = null): ?array
{
    $sql = 'SELECT e.* FROM event_participants ep JOIN events e ON e.id = ep.event_id
            WHERE ep.customer_id = :c AND ep.status IN ("invited","confirmed") AND e.status = "open" AND e.starts_on >= CURDATE()'
        . ($kind !== null ? ' AND e.kind = :k' : '') . ' ORDER BY e.starts_on LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($kind !== null ? [':c' => $customerId, ':k' => $kind] : [':c' => $customerId]);

    return $stmt->fetch() ?: null;
}

// ---------------------------------------------------------------------------
// Payments
// ---------------------------------------------------------------------------

function payment_add(int $participantId, array $in, ?int $receivedByTeamId = null): int
{
    $amount = parse_money((string) ($in['amount'] ?? ''));
    if ($amount === null || $amount == 0.0) {
        throw new InvalidArgumentException('How much?');
    }
    if (!empty($in['refund'])) {
        $amount = -abs($amount);
    }
    $method = isset(PAYMENT_METHODS[$in['method'] ?? '']) ? $in['method'] : 'cash';
    $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($in['currency'] ?? 'MXN')) ?: 'MXN', 0, 3));
    db()->prepare('INSERT INTO payments (participant_id, amount, currency, method, received_by, received_at, note) VALUES (:p, :a, :c, :m, :by, :at, :n)')
        ->execute([
            ':p' => $participantId, ':a' => $amount, ':c' => $currency, ':m' => $method, ':by' => $receivedByTeamId,
            ':at' => ($in['received_at'] ?? '') !== '' && strtotime((string) $in['received_at']) !== false ? date('Y-m-d H:i:s', strtotime((string) $in['received_at'])) : date('Y-m-d H:i:s'),
            ':n' => trim((string) ($in['note'] ?? '')) ?: null,
        ]);

    return (int) db()->lastInsertId();
}

function participant_payments(int $participantId): array
{
    $stmt = db()->prepare('SELECT pm.*, p.name AS received_by_name FROM payments pm LEFT JOIN team_members t ON t.id = pm.received_by LEFT JOIN people p ON p.id = t.person_id WHERE pm.participant_id = :p ORDER BY pm.received_at DESC, pm.id DESC');
    $stmt->execute([':p' => $participantId]);

    return $stmt->fetchAll();
}

/** A diver's money position on an event: paid / deposit / unpaid / n.a. */
function participant_payment_state(array $participant): string
{
    if ($participant['price_mxn'] === null) {
        return 'n/a';
    }
    $paid = (float) ($participant['paid_mxn'] ?? 0);
    $price = (float) $participant['price_mxn'];
    if ($paid >= $price - 0.005) {
        return 'paid';
    }

    return $paid > 0 ? 'deposit' : 'unpaid';
}

/** Money summary for an event, MXN only. */
function event_money(int $eventId): array
{
    $total = $paid = 0.0;
    $counts = ['n_paid' => 0, 'n_deposit' => 0, 'n_unpaid' => 0];
    foreach (event_participants($eventId) as $p) {
        if (!in_array($p['status'], ['invited', 'confirmed', 'attended'], true) || $p['price_mxn'] === null) {
            continue;
        }
        $total += (float) $p['price_mxn'];
        $paid += (float) $p['paid_mxn'];
        $counts['n_' . participant_payment_state($p)]++;
    }

    return ['total' => $total, 'paid' => $paid, 'due' => max(0.0, $total - $paid)] + $counts;
}

/** Documents position for a diver on an event: [ok, total, missing titles]. */
function participant_documents(int $customerId, string $kind): array
{
    $ok = 0;
    $total = 0;
    $missing = [];
    foreach (customer_document_status($customerId) as $d) {
        $t = $d['template'];
        if ($t['applies_to'] !== 'all' && $t['applies_to'] !== $kind) {
            continue;
        }
        $total++;
        if ($d['ok']) {
            $ok++;
        } else {
            $missing[] = $t['title'];
        }
    }

    return ['ok' => $ok, 'total' => $total, 'missing' => $missing];
}
