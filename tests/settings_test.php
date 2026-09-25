<?php

declare(strict_types=1);

test('Spanish falls back to English per field, not per page', function (): void {
    settings_save(['test_key' => ['en' => 'English text', 'es' => '']]);

    $row = db()->query("SELECT val_en, val_es FROM settings WHERE skey = 'test_key'")->fetch();
    is_same('English text', $row['val_en']);
    is_same('', $row['val_es'], 'an empty translation is stored, not silently copied');
});

test('a blank Spanish value renders the English one', function (): void {
    // setting() is the function the templates call.
    db()->exec("INSERT INTO settings (skey, val_en, val_es) VALUES ('fallback_probe', 'Dive with experts', '')
                ON DUPLICATE KEY UPDATE val_en = VALUES(val_en), val_es = VALUES(val_es)");
    settings_cache_clear();

    is_same('Dive with experts', setting('fallback_probe', 'es'), 'blank Spanish must not render blank');
    is_same('Dive with experts', setting('fallback_probe', 'en'));
});

test('a real Spanish value wins over the English one', function (): void {
    db()->exec("INSERT INTO settings (skey, val_en, val_es) VALUES ('probe2', 'Adventure dives', 'Buceos recreativos')
                ON DUPLICATE KEY UPDATE val_en = VALUES(val_en), val_es = VALUES(val_es)");
    settings_cache_clear();

    is_same('Buceos recreativos', setting('probe2', 'es'));
    is_same('Adventure dives', setting('probe2', 'en'));
});

test('a missing key is empty, never a PHP warning', function (): void {
    is_same('', setting('definitely_not_a_key', 'en'));
    is_false(has_setting('definitely_not_a_key', 'en'));
});

test('one-per-line settings split cleanly', function (): void {
    db()->exec("INSERT INTO settings (skey, val_en) VALUES ('lines_probe', 'Tanks and weights\n\n  Dive lights  \nGuide\n')
                ON DUPLICATE KEY UPDATE val_en = VALUES(val_en)");
    settings_cache_clear();

    is_same(['Tanks and weights', 'Dive lights', 'Guide'], setting_lines('lines_probe', 'en'),
        'blank lines dropped and whitespace trimmed');
});

test('the seeded catalogue matches the printed guides', function (): void {
    // These are the seed's counts, not production's. The test database is built
    // from db/seed.sql, so rows the shop adds through the admin never appear here.
    is_same(10, (int) db()->query('SELECT COUNT(*) FROM courses')->fetchColumn(),
        'the ten courses printed in the training guide');
    is_same(11, (int) db()->query('SELECT COUNT(*) FROM excursions')->fetchColumn());

    $dreamgate = db()->query("SELECT * FROM excursions WHERE name_en = 'Dreamgate'")->fetch();
    is_same(null, $dreamgate['price_1_dive'], 'Dreamgate is not sold as a single dive');
    is_same('3500.00', $dreamgate['price_2_dives']);
});

test('location ships empty so no address is invented', function (): void {
    foreach (['addr_street', 'geo_lat', 'geo_lng', 'opening_hours'] as $key) {
        is_same('', setting($key, 'en'), "{$key} must seed empty");
    }
});
