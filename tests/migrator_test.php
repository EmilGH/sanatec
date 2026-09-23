<?php

declare(strict_types=1);

test('the schema was built by the migration runner', function (): void {
    $applied = migrator_applied();

    is_true($applied !== [], 'the test database is migrated, so something must be recorded');
    is_true(isset($applied['0001_initial_schema']), 'the initial schema is tracked');
    is_same([], migrator_pending(), 'nothing should be left pending after a run');
});

test('every migration on disk is recorded with its checksum', function (): void {
    foreach (migrator_available() as $version => $path) {
        $applied = migrator_applied();
        is_true(isset($applied[$version]), "{$version} was not recorded");
        is_same(migrator_checksum($path), $applied[$version]['checksum'], "{$version} checksum mismatch");
    }
});

test('editing an applied migration is detected', function (): void {
    is_same([], migrator_drifted(), 'nothing should have drifted in a fresh database');

    db()->prepare('UPDATE schema_migrations SET checksum = :c WHERE version = :v')
        ->execute([':c' => str_repeat('0', 64), ':v' => '0001_initial_schema']);

    is_same(['0001_initial_schema'], migrator_drifted(),
        'a changed file must be caught, not silently ignored');

    $path = migrator_available()['0001_initial_schema'];
    db()->prepare('UPDATE schema_migrations SET checksum = :c WHERE version = :v')
        ->execute([':c' => migrator_checksum($path), ':v' => '0001_initial_schema']);

    is_same([], migrator_drifted());
});

test('migrations are applied in filename order', function (): void {
    $versions = array_keys(migrator_available());
    $sorted = $versions;
    sort($sorted, SORT_STRING);

    is_same($sorted, $versions, 'glob order must be deterministic');
});

test('an existing database adopts migrations without re-creating tables', function (): void {
    // The baseline marker is what makes adoption on a live database safe.
    $path = migrator_available()['0001_initial_schema'];
    is_same('courses', migrator_baseline_table($path));
    is_true(migrator_table_exists('courses'));
    is_false(migrator_table_exists('table_that_does_not_exist'));
});
