<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * A very small migration runner.
 *
 * Migrations are plain .sql files in db/migrations, named NNNN_description.sql
 * and applied in filename order. Applied versions are recorded in
 * schema_migrations along with a checksum of the file, so editing a migration
 * that has already run is caught rather than silently ignored.
 *
 * MySQL does not roll DDL back, so there is no "down" direction. A migration
 * that fails half way leaves the database part-changed and says so loudly;
 * the fix is a new migration, not an edit to the old one. Keep each file to one
 * logical change so that "half way" stays a small place to be.
 */

const MIGRATIONS_DIR = SANATEC_ROOT . '/db/migrations';

function migrator_ensure_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            version    VARCHAR(128) NOT NULL,
            checksum   CHAR(64)     NOT NULL,
            applied_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/** Every migration on disk, as version => absolute path. */
function migrator_available(): array
{
    $files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    $out = [];
    foreach ($files as $path) {
        $out[basename($path, '.sql')] = $path;
    }

    return $out;
}

/** Applied versions, as version => ['checksum' => ..., 'applied_at' => ...]. */
function migrator_applied(): array
{
    migrator_ensure_table();

    $out = [];
    foreach (db()->query('SELECT version, checksum, applied_at FROM schema_migrations ORDER BY version') as $row) {
        $out[$row['version']] = $row;
    }

    return $out;
}

function migrator_checksum(string $path): string
{
    return hash('sha256', (string) file_get_contents($path));
}

/**
 * A migration may declare that a pre-existing table means it has effectively
 * already run. This is how a database created before migrations existed adopts
 * them without trying to re-create tables that are already there.
 */
function migrator_baseline_table(string $path): ?string
{
    $head = (string) file_get_contents($path, false, null, 0, 2048);

    return preg_match('/^--\s*baseline-if-table-exists:\s*(\w+)/mi', $head, $m) === 1 ? $m[1] : null;
}

function migrator_table_exists(string $table): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t'
    );
    $stmt->execute([':t' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

/** Migrations on disk that have not been recorded yet. */
function migrator_pending(): array
{
    $applied = migrator_applied();

    return array_diff_key(migrator_available(), $applied);
}

/**
 * Versions whose file has changed since it was applied. Nothing can be done
 * about them automatically; the point is to notice.
 */
function migrator_drifted(): array
{
    $applied = migrator_applied();
    $drifted = [];

    foreach (migrator_available() as $version => $path) {
        if (isset($applied[$version]) && $applied[$version]['checksum'] !== migrator_checksum($path)) {
            $drifted[] = $version;
        }
    }

    return $drifted;
}

function migrator_record(string $version, string $path): void
{
    db()->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (:v, :c)')
        ->execute([':v' => $version, ':c' => migrator_checksum($path)]);
}

/**
 * Apply everything pending. $log receives one line per migration.
 * Returns the versions applied. Throws on the first failure, leaving later
 * migrations unapplied.
 */
function migrator_run(?callable $log = null): array
{
    $log ??= static fn (string $line) => null;
    $done = [];

    foreach (migrator_pending() as $version => $path) {
        $baseline = migrator_baseline_table($path);

        if ($baseline !== null && migrator_table_exists($baseline)) {
            migrator_record($version, $path);
            $log("  baselined  {$version}  (table '{$baseline}' already present)");
            $done[] = $version;
            continue;
        }

        $sql = (string) file_get_contents($path);

        try {
            db()->exec($sql);
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Migration {$version} failed: " . $e->getMessage()
                . "\nThe database may be part-changed. Fix forward with a new migration.",
                0,
                $e
            );
        }

        migrator_record($version, $path);
        $log("  applied    {$version}");
        $done[] = $version;
    }

    return $done;
}
