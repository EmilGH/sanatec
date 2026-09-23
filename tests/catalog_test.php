<?php

declare(strict_types=1);

/** Helper: the current display order of a table, as a list of English names. */
function order_of(string $table): array
{
    return array_column(catalog_all($table), 'name_en');
}

test('a course saves and comes back', function (): void {
    $id = course_save([
        'name_en' => 'Test Course', 'name_es' => 'Curso de Prueba',
        'price_mxn' => '$7,500', 'duration_en' => '2 days', 'duration_es' => '2 días',
        'is_published' => true,
    ]);

    $row = catalog_find('courses', $id);
    is_same('Test Course', $row['name_en']);
    is_same('Curso de Prueba', $row['name_es']);
    is_same('7500.00', $row['price_mxn'], 'the typed "$7,500" is stored as a number');
    is_same(1, (int) $row['is_published']);

    catalog_delete('courses', $id);
    is_same(null, catalog_find('courses', $id));
});

test('a blank price stores NULL, so the page says "Ask for pricing"', function (): void {
    $id = course_save(['name_en' => 'No Price', 'name_es' => '', 'price_mxn' => '', 'is_published' => true]);
    is_same(null, catalog_find('courses', $id)['price_mxn']);
    catalog_delete('courses', $id);
});

test('unpublishing hides a row from the public page only', function (): void {
    $id = course_save(['name_en' => 'Hidden Course', 'name_es' => '', 'is_published' => true]);

    is_true(in_array('Hidden Course', array_column(catalog_published('courses'), 'name_en'), true));

    $live = catalog_toggle_published('courses', $id);
    is_false($live);
    is_false(in_array('Hidden Course', array_column(catalog_published('courses'), 'name_en'), true),
        'hidden rows must not reach the public page');
    is_true(in_array('Hidden Course', array_column(catalog_all('courses'), 'name_en'), true),
        'but the owner still sees them in the admin');

    catalog_delete('courses', $id);
});

test('moving a row up swaps it with its neighbour', function (): void {
    $before = order_of('routes');
    $second = catalog_all('routes')[1];

    catalog_move('routes', (int) $second['id'], -1);

    $after = order_of('routes');
    is_same($before[1], $after[0], 'the second row is now first');
    is_same($before[0], $after[1], 'and the first is now second');

    catalog_move('routes', (int) $second['id'], 1);
    is_same($before, order_of('routes'), 'moving back restores the original order');
});

test('moving the first row up does nothing', function (): void {
    $before = order_of('routes');
    catalog_move('routes', (int) catalog_all('routes')[0]['id'], -1);
    is_same($before, order_of('routes'));
});

test('moving the last row down does nothing', function (): void {
    $before = order_of('routes');
    $rows = catalog_all('routes');
    catalog_move('routes', (int) $rows[count($rows) - 1]['id'], 1);
    is_same($before, order_of('routes'));
});

test('rows sharing a sort_order can still be reordered', function (): void {
    // Two rows added in quick succession, then forced to collide.
    $a = route_save(['name_en' => 'Tie A', 'name_es' => '', 'is_published' => true]);
    $b = route_save(['name_en' => 'Tie B', 'name_es' => '', 'is_published' => true]);
    db()->exec("UPDATE routes SET sort_order = 9999 WHERE id IN ({$a}, {$b})");

    $before = order_of('routes');
    catalog_move('routes', $b, -1);
    $after = order_of('routes');

    is_false($before === $after, 'a tie must not make the arrows silently do nothing');

    catalog_delete('routes', $a);
    catalog_delete('routes', $b);
});

test('the table name can never come from user input', function (): void {
    throws(static fn () => catalog_all('users; DROP TABLE courses'));
    throws(static fn () => catalog_published('admin_users'),
        'only the two catalogue tables are reachable');
});

test('the special-price flag round-trips', function (): void {
    $yaakun = db()->query("SELECT * FROM routes WHERE name_en = 'Yaa Kun'")->fetch();
    is_same(1, (int) $yaakun['is_special_price'], 'Yaa Kun keeps the guide\'s asterisk');
});
