<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/People.php';
require_once __DIR__ . '/Uploads.php';

/**
 * Affiliates: hotels, concierges and operators who send divers.
 *
 * A referral link (/a/<code> or ?ref=<code>) sets a cookie for ninety days
 * and logs a visit. The first affiliate a diver arrives from is written on
 * their customer record; every booking they make carries it, together with
 * what the affiliate earns on that booking. Two arrangements:
 *
 *   commission — the diver pays retail; the affiliate earns share_pct of the
 *                room between what was charged and the floor.
 *   net        — the affiliate is charged retail minus that share, and
 *                collects from the guest themselves.
 */

const AFFILIATE_COOKIE = 'st_ref';
const AFFILIATE_COOKIE_DAYS = 90;
const AFFILIATE_MODES = ['commission' => 'Commission (guest pays retail)', 'net' => 'Net rate (affiliate is invoiced)'];

function affiliates_list(bool $activeOnly = false): array
{
    return db()->query(
        'SELECT a.*, p.name AS contact_name FROM affiliates a LEFT JOIN people p ON p.id = a.contact_person_id'
        . ($activeOnly ? ' WHERE a.is_active = 1' : '') . ' ORDER BY a.is_active DESC, a.name'
    )->fetchAll();
}

function affiliate_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT a.*, p.name AS contact_name FROM affiliates a LEFT JOIN people p ON p.id = a.contact_person_id WHERE a.id = :id');
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

function affiliate_find_by_code(string $code, bool $activeOnly = true): ?array
{
    $code = strtolower(trim($code));
    if ($code === '' || preg_match('/^[a-z0-9-]{2,40}$/', $code) !== 1) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM affiliates WHERE code = :c' . ($activeOnly ? ' AND is_active = 1' : ''));
    $stmt->execute([':c' => $code]);

    return $stmt->fetch() ?: null;
}

/** The affiliate a signed-in person represents, if any. */
function affiliate_for_person(int $personId): ?array
{
    $stmt = db()->prepare('SELECT * FROM affiliates WHERE contact_person_id = :p AND is_active = 1');
    $stmt->execute([':p' => $personId]);

    return $stmt->fetch() ?: null;
}

function affiliate_code_for(string $name, ?int $excludeId = null): string
{
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', ascii_fold($name)) ?? '', '-')) ?: 'partner';
    $base = substr($base, 0, 36);
    $stmt = db()->prepare('SELECT COUNT(*) FROM affiliates WHERE code = :c AND id <> :id');
    $code = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute([':c' => $code, ':id' => $excludeId ?? 0]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
        $code = "{$base}-{$n}";
    }
}

/** Create or update an affiliate and its contact person. Returns the affiliate id. */
function affiliate_save(?int $id, array $in): int
{
    $existing = $id ? affiliate_find($id) : null;
    if ($id && $existing === null) {
        throw new RuntimeException('That affiliate no longer exists.');
    }
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('An affiliate needs a business name.');
    }
    $code = strtolower(trim((string) ($in['code'] ?? '')));
    $code = $code !== '' ? preg_replace('/[^a-z0-9-]+/', '-', $code) : ($existing['code'] ?? affiliate_code_for($name, $id));
    if (preg_match('/^[a-z0-9-]{2,40}$/', (string) $code) !== 1) {
        throw new InvalidArgumentException('The referral code is letters, numbers and dashes, 2 to 40 characters.');
    }
    $clash = db()->prepare('SELECT id FROM affiliates WHERE code = :c AND id <> :id');
    $clash->execute([':c' => $code, ':id' => $id ?? 0]);
    if ($clash->fetchColumn()) {
        throw new InvalidArgumentException("The code '{$code}' is already used by another affiliate.");
    }
    $mode = isset(AFFILIATE_MODES[$in['pricing_mode'] ?? '']) ? $in['pricing_mode'] : 'commission';
    $share = round((float) ($in['share_pct'] ?? 50), 2);
    if ($share < 0 || $share > 100) {
        throw new InvalidArgumentException('The share is a percentage between 0 and 100.');
    }

    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        // The contact: a person, created or renamed here; channels are managed on the page.
        $contactId = $existing['contact_person_id'] ?? null;
        $contactName = trim((string) ($in['contact_name'] ?? ''));
        if ($contactName !== '') {
            if ($contactId) {
                person_update((int) $contactId, ['name' => $contactName]);
            } else {
                $contactId = person_create($contactName);
            }
        }

        $fields = [
            'code'              => $code,
            'name'              => $name,
            'description_en'    => trim((string) ($in['description_en'] ?? '')) ?: null,
            'description_es'    => trim((string) ($in['description_es'] ?? '')) ?: null,
            'contact_person_id' => $contactId,
            'pricing_mode'      => $mode,
            'share_pct'         => $share,
            'is_active'         => array_key_exists('is_active', $in) ? (!empty($in['is_active']) ? 1 : 0) : 1,
            'notes'             => trim((string) ($in['notes'] ?? '')) ?: null,
        ];
        if ($existing === null) {
            $cols = implode(', ', array_keys($fields));
            $params = implode(', ', array_map(static fn (string $k): string => ':' . $k, array_keys($fields)));
            $pdo->prepare("INSERT INTO affiliates ({$cols}) VALUES ({$params})")->execute($fields);
            $id = (int) $pdo->lastInsertId();
        } else {
            $set = implode(', ', array_map(static fn (string $k): string => "{$k} = :{$k}", array_keys($fields)));
            $fields['id'] = $id;
            $pdo->prepare("UPDATE affiliates SET {$set} WHERE id = :id")->execute($fields);
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

/** The logo: fitted inside 600×300, PNG with transparency kept. */
function affiliate_logo_set(int $id, ?array $file): void
{
    $a = affiliate_find($id);
    if ($a === null) {
        throw new RuntimeException('That affiliate no longer exists.');
    }
    $path = null;
    if ($file !== null) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The logo did not upload. Try again.');
        }
        $img = image_load_oriented((string) $file['tmp_name']);
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1.0, 600 / $w, 300 / $h);
        if ($scale < 1.0) {
            $img = imagescale($img, (int) round($w * $scale), (int) round($h * $scale), IMG_BICUBIC) ?: $img;
        }
        imagesavealpha($img, true);
        ob_start();
        imagepng($img, null, 7);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);
        $path = write_upload('affiliates', 'logo-' . $id . '-' . bin2hex(random_bytes(3)) . '.png', $bytes);
    }
    db()->prepare('UPDATE affiliates SET logo_path = :p WHERE id = :id')->execute([':p' => $path, ':id' => $id]);
    if ($a['logo_path']) {
        $old = upload_path((string) $a['logo_path']);
        if ($old !== null) {
            @unlink($old);
        }
    }
}

/** The referral link to hand an affiliate. */
function affiliate_link(array $a, string $lang = 'en'): string
{
    return rtrim((string) cfg('base_url', 'https://sanatecdiving.com'), '/') . ($lang === 'es' ? '/es' : '') . '/a/' . $a['code'];
}

// ---------------------------------------------------------------------------
// Tracking
// ---------------------------------------------------------------------------

/**
 * On a public page: a ?ref= in the URL sets the cookie and logs a visit;
 * otherwise the cookie stands. Returns the current affiliate or null.
 */
function affiliate_capture(): ?array
{
    $ref = (string) ($_GET['ref'] ?? '');
    // ?ref=none (or /a/none) forgets the affiliate — for testing, or a visitor who wants out.
    if (in_array(strtolower($ref), ['none', 'off', 'clear'], true)) {
        if (!headers_sent()) {
            setcookie(AFFILIATE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
        }
        unset($_COOKIE[AFFILIATE_COOKIE]);

        return null;
    }
    if ($ref !== '') {
        $a = affiliate_find_by_code($ref);
        if ($a !== null) {
            if (!headers_sent()) {
                setcookie(AFFILIATE_COOKIE, $a['code'], [
                    'expires' => time() + AFFILIATE_COOKIE_DAYS * 86400, 'path' => '/',
                    'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
                ]);
            }
            $_COOKIE[AFFILIATE_COOKIE] = $a['code'];
            affiliate_record_visit((int) $a['id']);

            return $a;
        }
    }
    $code = (string) ($_COOKIE[AFFILIATE_COOKIE] ?? '');

    return $code !== '' ? affiliate_find_by_code($code) : null;
}

function affiliate_record_visit(int $affiliateId): void
{
    db()->prepare('INSERT INTO affiliate_visits (affiliate_id, path, referrer, ip, user_agent) VALUES (:a, :p, :r, :ip, :ua)')->execute([
        ':a'  => $affiliateId,
        ':p'  => mb_substr((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), 0, 255),
        ':r'  => mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500) ?: null,
        ':ip' => client_ip_binary(),
        ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
    ]);
}

/** First touch: a customer with no referrer gets the one in the cookie. */
function affiliate_attach_customer(int $customerId, ?string $code = null): void
{
    $code ??= (string) ($_COOKIE[AFFILIATE_COOKIE] ?? '');
    if ($code === '') {
        return;
    }
    $a = affiliate_find_by_code($code);
    if ($a === null) {
        return;
    }
    db()->prepare('UPDATE customers SET referred_by_affiliate_id = :a, referred_at = NOW() WHERE id = :c AND referred_by_affiliate_id IS NULL')
        ->execute([':a' => $a['id'], ':c' => $customerId]);
}

/**
 * The money on one booking: what the diver is charged and what the
 * affiliate earns, from retail, floor, the diver's discount and the
 * affiliate's arrangement. Retail with no floor means no room to share.
 */
function affiliate_pricing(?float $retail, ?float $floor, float $discountPct, ?array $affiliate): array
{
    if ($retail === null) {
        return ['price' => null, 'floor' => $floor, 'affiliate_amount' => null];
    }
    $price = round($retail * (1 - $discountPct / 100), 2);
    if ($floor !== null) {
        $price = max($price, $floor);
    }
    $amount = null;
    if ($affiliate !== null && $floor !== null && (int) ($affiliate['is_active'] ?? 1) === 1) {
        $share = (float) $affiliate['share_pct'] / 100;
        if ($affiliate['pricing_mode'] === 'net') {
            $amount = round(($retail - $floor) * $share, 2);
            $price = round($retail - $amount, 2);                 // what the affiliate is invoiced
        } else {
            $amount = round(max(0, $price - $floor) * $share, 2); // what the affiliate is owed
        }
    }

    return ['price' => $price, 'floor' => $floor, 'affiliate_amount' => $amount];
}

// ---------------------------------------------------------------------------
// Reporting
// ---------------------------------------------------------------------------

/** Counts and money for one affiliate over a date range (inclusive, Y-m-d). */
function affiliate_stats(int $affiliateId, ?string $from = null, ?string $to = null): array
{
    $from ??= '2000-01-01';
    $to ??= '2100-01-01';
    $q = static function (string $sql, array $p = []) use ($affiliateId, $from, $to) {
        $stmt = db()->prepare($sql);
        $stmt->execute($p + [':a' => $affiliateId, ':from' => $from, ':to' => $to . ' 23:59:59']);
        return $stmt->fetch();
    };
    $visits = $q('SELECT COUNT(*) AS n FROM affiliate_visits WHERE affiliate_id = :a AND landed_at BETWEEN :from AND :to');
    $signups = $q('SELECT COUNT(*) AS n FROM customers WHERE referred_by_affiliate_id = :a AND referred_at BETWEEN :from AND :to');
    $book = $q(
        'SELECT COUNT(*) AS bookings, SUM(ep.status = "attended") AS attended,
                COALESCE(SUM(ep.price_mxn), 0) AS revenue, COALESCE(SUM(ep.affiliate_amount_mxn), 0) AS affiliate_amount
         FROM event_participants ep JOIN events e ON e.id = ep.event_id
         WHERE ep.affiliate_id = :a AND ep.status NOT IN ("cancelled", "no_show") AND e.starts_on BETWEEN :from AND :to'
    );

    return [
        'visits'           => (int) $visits['n'],
        'signups'          => (int) $signups['n'],
        'bookings'         => (int) $book['bookings'],
        'attended'         => (int) $book['attended'],
        'revenue'          => (float) $book['revenue'],
        'affiliate_amount' => (float) $book['affiliate_amount'],
    ];
}

/** Recent bookings that carry this affiliate, first names only. */
function affiliate_bookings(int $affiliateId, int $limit = 50): array
{
    $stmt = db()->prepare(
        'SELECT e.starts_on, e.title_en, e.title_es, e.kind, ep.status, ep.price_mxn, ep.affiliate_amount_mxn,
                SUBSTRING_INDEX(p.name, " ", 1) AS first_name
         FROM event_participants ep
         JOIN events e ON e.id = ep.event_id
         JOIN customers c ON c.id = ep.customer_id
         JOIN people p ON p.id = c.person_id
         WHERE ep.affiliate_id = :a ORDER BY e.starts_on DESC, ep.id DESC LIMIT ' . (int) $limit
    );
    $stmt->execute([':a' => $affiliateId]);

    return $stmt->fetchAll();
}
