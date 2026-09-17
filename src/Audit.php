<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * A short record of who changed what.
 *
 * Prices are the thing people argue about, so it is worth being able to answer
 * "when did this become $4,300, and who did it" without guessing.
 */
function audit(string $action, string $entity, string|int $entityId = '', string $summary = ''): void
{
    db()->prepare(
        'INSERT INTO audit_log (admin_user, action, entity, entity_id, summary, ip)
         VALUES (:u, :a, :e, :eid, :s, :ip)'
    )->execute([
        ':u'   => (string) ($_SESSION['username'] ?? 'system'),
        ':a'   => $action,
        ':e'   => $entity,
        ':eid' => (string) $entityId,
        ':s'   => mb_substr($summary, 0, 500),
        ':ip'  => client_ip_binary(),
    ]);
}

/** Most recent entries, for the dashboard. */
function audit_recent(int $limit = 12): array
{
    $stmt = db()->prepare('SELECT * FROM audit_log ORDER BY id DESC LIMIT :n');
    $stmt->bindValue(':n', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
