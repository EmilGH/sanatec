<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * The product catalogue: dive courses and cenote adventure excursions.
 *
 * Both tables share a shape (sort_order + is_published + paired EN/ES columns),
 * so ordering and publishing are handled generically; the field lists differ.
 */

const CATALOG_TABLES = ['courses', 'excursions'];

/** Guard against a table name ever reaching SQL from user input. */
function catalog_table(string $table): string
{
    if (!in_array($table, CATALOG_TABLES, true)) {
        throw new InvalidArgumentException('Unknown catalog table.');
    }

    return $table;
}

/** Published rows, in display order — what the public page renders. */
function catalog_published(string $table): array
{
    $t = catalog_table($table);

    return db()->query("SELECT * FROM {$t} WHERE is_published = 1 ORDER BY sort_order, id")->fetchAll();
}

/** Every row including unpublished ones, for the admin list. */
function catalog_all(string $table): array
{
    $t = catalog_table($table);

    return db()->query("SELECT * FROM {$t} ORDER BY sort_order, id")->fetchAll();
}

function catalog_find(string $table, int $id): ?array
{
    $t = catalog_table($table);
    $stmt = db()->prepare("SELECT * FROM {$t} WHERE id = :id");
    $stmt->execute([':id' => $id]);

    return $stmt->fetch() ?: null;
}

function catalog_delete(string $table, int $id): void
{
    $t = catalog_table($table);
    $stmt = db()->prepare("DELETE FROM {$t} WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

/** Toggle a row's published flag and return the new state. */
function catalog_toggle_published(string $table, int $id): bool
{
    $t = catalog_table($table);
    db()->prepare("UPDATE {$t} SET is_published = 1 - is_published WHERE id = :id")->execute([':id' => $id]);

    return (bool) (catalog_find($t, $id)['is_published'] ?? false);
}

/**
 * Move a row one place up or down by swapping sort_order with its neighbour.
 *
 * Rows seeded ten apart can still collide once the owner has added a few, so
 * ties are broken by id and the two rows are swapped inside a transaction.
 */
function catalog_move(string $table, int $id, int $direction): void
{
    $t = catalog_table($table);
    $pdo = db();

    $row = catalog_find($t, $id);
    if ($row === null) {
        return;
    }

    $comparison = $direction < 0 ? '<' : '>';
    $order = $direction < 0 ? 'DESC' : 'ASC';

    $stmt = $pdo->prepare(
        "SELECT id, sort_order FROM {$t}
         WHERE (sort_order, id) {$comparison} (:so, :id)
         ORDER BY sort_order {$order}, id {$order}
         LIMIT 1"
    );
    $stmt->execute([':so' => $row['sort_order'], ':id' => $id]);
    $neighbour = $stmt->fetch();

    if ($neighbour === false) {
        return;
    }

    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE {$t} SET sort_order = :so WHERE id = :id");
        // Equal sort_order values would make the swap a no-op, so force them apart.
        if ((int) $neighbour['sort_order'] === (int) $row['sort_order']) {
            $update->execute([':so' => (int) $row['sort_order'] + $direction, ':id' => $id]);
        } else {
            $update->execute([':so' => $neighbour['sort_order'], ':id' => $id]);
            $update->execute([':so' => $row['sort_order'], ':id' => $neighbour['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** The sort_order to give a newly created row so it lands at the end. */
function catalog_next_sort_order(string $table): int
{
    $t = catalog_table($table);

    return (int) db()->query("SELECT COALESCE(MAX(sort_order), 0) + 10 FROM {$t}")->fetchColumn();
}

/**
 * Read a price typed by a human: "$10,000", "10 000", "10000" all mean the same
 * thing. An empty field means "no price published", which is NULL, not zero.
 */
function parse_money(?string $input): ?float
{
    $cleaned = preg_replace('/[^0-9.]/', '', (string) $input);

    if ($cleaned === '' || $cleaned === null) {
        return null;
    }

    return round((float) $cleaned, 2);
}

/** Insert or update a course. Returns the row id. */
function course_save(array $in, ?int $id = null): int
{
    $params = [
        ':name_en'     => trim((string) ($in['name_en'] ?? '')),
        ':name_es'     => trim((string) ($in['name_es'] ?? '')),
        ':price_mxn'   => parse_money($in['price_mxn'] ?? null),
        ':duration_en' => trim((string) ($in['duration_en'] ?? '')),
        ':duration_es' => trim((string) ($in['duration_es'] ?? '')),
        ':note_en'     => trim((string) ($in['note_en'] ?? '')) ?: null,
        ':note_es'     => trim((string) ($in['note_es'] ?? '')) ?: null,
        ':published'   => !empty($in['is_published']) ? 1 : 0,
    ];

    if ($id === null) {
        $params[':sort_order'] = catalog_next_sort_order('courses');
        $params[':slug'] = catalog_slug_for('courses', $params[':name_en']);
        db()->prepare(
            'INSERT INTO courses (slug, name_en, name_es, price_mxn, duration_en, duration_es, note_en, note_es, is_published, sort_order)
             VALUES (:slug, :name_en, :name_es, :price_mxn, :duration_en, :duration_es, :note_en, :note_es, :published, :sort_order)'
        )->execute($params);

        return (int) db()->lastInsertId();
    }

    $params[':id'] = $id;
    db()->prepare(
        'UPDATE courses SET name_en = :name_en, name_es = :name_es, price_mxn = :price_mxn,
                duration_en = :duration_en, duration_es = :duration_es,
                note_en = :note_en, note_es = :note_es, is_published = :published
         WHERE id = :id'
    )->execute($params);

    return $id;
}

/** Insert or update a cenote route. Returns the row id. */
function excursion_save(array $in, ?int $id = null): int
{
    $params = [
        ':name_en'       => trim((string) ($in['name_en'] ?? '')),
        ':name_es'       => trim((string) ($in['name_es'] ?? '')),
        ':price_1_dive'  => parse_money($in['price_1_dive'] ?? null),
        ':price_2_dives' => parse_money($in['price_2_dives'] ?? null),
        ':price_3_dives' => parse_money($in['price_3_dives'] ?? null),
        ':cert_en'       => trim((string) ($in['cert_en'] ?? '')),
        ':cert_es'       => trim((string) ($in['cert_es'] ?? '')),
        ':special'       => !empty($in['is_special_price']) ? 1 : 0,
        ':published'     => !empty($in['is_published']) ? 1 : 0,
    ];

    if ($id === null) {
        $params[':sort_order'] = catalog_next_sort_order('excursions');
        $params[':slug'] = catalog_slug_for('excursions', $params[':name_en']);
        db()->prepare(
            'INSERT INTO excursions (slug, name_en, name_es, price_1_dive, price_2_dives, price_3_dives, cert_en, cert_es, is_special_price, is_published, sort_order)
             VALUES (:slug, :name_en, :name_es, :price_1_dive, :price_2_dives, :price_3_dives, :cert_en, :cert_es, :special, :published, :sort_order)'
        )->execute($params);

        return (int) db()->lastInsertId();
    }

    $params[':id'] = $id;
    db()->prepare(
        'UPDATE excursions SET name_en = :name_en, name_es = :name_es,
                price_1_dive = :price_1_dive, price_2_dives = :price_2_dives, price_3_dives = :price_3_dives,
                cert_en = :cert_en, cert_es = :cert_es,
                is_special_price = :special, is_published = :published
         WHERE id = :id'
    )->execute($params);

    return $id;
}

/** A unique slug for a catalogue row, from its English name. */
function catalog_slug_for(string $table, string $name, ?int $excludeId = null): string
{
    $t = catalog_table($table);
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', ascii_fold($name)) ?? '', '-')) ?: 'item';
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$t} WHERE slug = :s AND id <> :id");
    $slug = $base;
    for ($n = 2; ; $n++) {
        $stmt->execute([':s' => $slug, ':id' => $excludeId ?? 0]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = "{$base}-{$n}";
    }
}

/** A published row by slug, or null. */
function catalog_find_by_slug(string $table, string $slug): ?array
{
    $t = catalog_table($table);
    $stmt = db()->prepare("SELECT * FROM {$t} WHERE slug = :s AND is_published = 1");
    $stmt->execute([':s' => $slug]);

    return $stmt->fetch() ?: null;
}

/** The most recent change to the catalogue, used for Last-Modified headers. */
function catalog_last_modified(): int
{
    $sql = 'SELECT UNIX_TIMESTAMP(GREATEST(
                (SELECT COALESCE(MAX(updated_at), FROM_UNIXTIME(86400)) FROM courses),
                (SELECT COALESCE(MAX(updated_at), FROM_UNIXTIME(86400)) FROM excursions),
                (SELECT COALESCE(MAX(updated_at), FROM_UNIXTIME(86400)) FROM settings)
            ))';

    $value = db()->query($sql)->fetchColumn();

    return $value ? (int) $value : time();
}
