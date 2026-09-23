<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Team.php';

/** An actor array shaped like current_user() returns it. */
function actor_for(int $teamId): array
{
    $t = team_find($teamId);
    $p = person_find((int) $t['person_id']);
    $p['team'] = $t;
    return $p;
}

function make_admin(string $name): int
{
    $pid = person_create($name);
    db()->prepare('INSERT INTO team_members (person_id, is_system_admin, is_active) VALUES (:p, 1, 1)')->execute([':p' => $pid]);
    return (int) db()->lastInsertId();
}

test('an administrator can create a team member with roles and permissions', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $id = team_save(null, [
        'name' => 'Ana Guía', 'job_title' => 'Cave guide', 'is_cave_guide' => 1, 'is_instructor' => 1,
        'can_manage_excursions' => 1, 'languages' => 'es, EN, english, x, fr',
    ], actor_for($admin));

    $t = team_find($id);
    is_same('Ana Guía', $t['name']);
    is_same(1, (int) $t['is_cave_guide']);
    is_same(1, (int) $t['can_manage_excursions']);
    is_same(0, (int) $t['can_manage_team']);
    is_same(0, (int) $t['is_system_admin']);
    is_same(['es', 'en', 'fr'], json_decode($t['languages'], true), 'codes are normalised, junk dropped');
});

test('only an administrator can grant administrator', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $manager = team_save(null, ['name' => 'Manager', 'can_manage_team' => 1], actor_for($admin));

    $new = team_save(null, ['name' => 'Wannabe', 'is_system_admin' => 1], actor_for($manager));
    is_same(0, (int) team_find($new)['is_system_admin'], 'a non-admin ticking the box changes nothing');

    $new2 = team_save(null, ['name' => 'Real', 'is_system_admin' => 1], actor_for($admin));
    is_same(1, (int) team_find($new2)['is_system_admin']);
});

test('nobody can deactivate themselves or drop their own admin', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $a = make_admin('Admin One'); make_admin('Admin Two');
    throws(static fn () => team_save($a, ['name' => 'Admin One', 'is_active' => 0, 'is_system_admin' => 1], actor_for($a)));
    throws(static fn () => team_save($a, ['name' => 'Admin One', 'is_active' => 1, 'is_system_admin' => 0], actor_for($a)));
});

test('the last active administrator cannot be removed', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $only = make_admin('Only Admin');
    $other = team_save(null, ['name' => 'Other', 'can_manage_team' => 1], actor_for($only));
    // Even acting as someone else with team rights, the last admin stays.
    throws(static fn () => team_save($only, ['name' => 'Only Admin', 'is_active' => 0, 'is_system_admin' => 1], actor_for($other)));
    is_same(1, team_active_admin_count());
});

test('a public profile gets a slug, and slugs stay unique', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $a = team_save(null, ['name' => 'José Pérez', 'profile_public' => 1], actor_for($admin));
    $b = team_save(null, ['name' => 'Jose Perez', 'profile_public' => 1], actor_for($admin));
    is_same('jose-perez', team_find($a)['public_slug'], 'accents folded');
    is_same('jose-perez-2', team_find($b)['public_slug']);
});

test('a member can edit their own profile but not their own access', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $m = team_save(null, ['name' => 'Staff', 'can_manage_customers' => 1], actor_for($admin));

    team_save_self(actor_for($m), ['name' => 'Staff Renamed', 'bio_en' => 'Hi', 'can_manage_team' => 1, 'is_system_admin' => 1, 'is_active' => 0]);
    $t = team_find($m);
    is_same('Staff Renamed', $t['name']);
    is_same('Hi', $t['bio_en']);
    is_same(0, (int) $t['can_manage_team'], 'permissions untouched');
    is_same(0, (int) $t['is_system_admin']);
    is_same(1, (int) $t['is_active']);
});

test('a team member keeps at least one channel', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $pid = (int) team_find($admin)['person_id'];
    $only = channel_upsert($pid, 'email', 'root@example.com');
    throws(static fn () => channel_delete($pid, $only), 'the last channel of a team member cannot be removed');
    $second = channel_upsert($pid, 'mobile', '+12125550123');
    channel_delete($pid, $only);
    is_same(1, count(person_channels($pid)));
});

test('credentials record expiry and who checked them', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $id = team_credential_save($admin, null, ['kind' => 'professional', 'agency' => 'padi', 'title' => 'Open Water Scuba Instructor', 'expires_on' => date('Y-m-d', strtotime('+30 days')), 'verified' => 1], $admin);
    $c = team_credentials($admin)[0];
    is_same($id, (int) $c['id']);
    is_same('PADI', $c['agency'], 'agency normalised to upper case');
    is_same(30, (int) $c['days_left']);
    is_same($admin, (int) $c['verified_by']);
    throws(static fn () => team_credential_save($admin, null, ['kind' => 'nonsense', 'agency' => 'PADI', 'title' => 'x'], $admin));
    throws(static fn () => team_credential_save($admin, null, ['kind' => 'technical', 'agency' => 'SSI', 'title' => 'x', 'expires_on' => '2030-01-01'], $admin), 'unknown agency');
});

test('technical and professional credentials must expire; recreational must not', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    throws(static fn () => team_credential_save($admin, null, ['kind' => 'technical', 'agency' => 'TDI', 'title' => 'Full Cave'], $admin),
        'a technical rating without an expiry is refused');
    $rec = team_credential_save($admin, null, ['kind' => 'recreational', 'agency' => 'NAUI', 'title' => 'Advanced', 'expires_on' => '2030-01-01'], $admin);
    is_same(null, team_credentials($admin)[0]['expires_on'], 'an expiry on a recreational card is dropped');
    // And the database agrees, whatever the code does.
    throws(static fn () => db()->exec("UPDATE team_credentials SET expires_on = '2030-01-01' WHERE id = {$rec}"));
});

test('DAN details save and read back on both the team page and the self profile', function (): void {
    db()->exec('DELETE FROM team_members'); db()->exec("DELETE FROM people");
    $admin = make_admin('Root Admin');
    $m = team_save(null, ['name' => 'Staff', 'dan_number' => 'DAN-123', 'dan_expires_on' => '2027-06-30'], actor_for($admin));
    is_same('DAN-123', team_find($m)['dan_number'], 'the team page must show what it saved');
    is_same('2027-06-30', team_find($m)['dan_expires_on']);

    team_save_self(actor_for($m), ['name' => 'Staff', 'dan_number' => 'DAN-456', 'dan_expires_on' => '2028-01-01']);
    is_same('DAN-456', team_find($m)['dan_number'], 'a member can update their own DAN');
    is_same('2028-01-01', team_find($m)['dan_expires_on']);

    team_save_self(actor_for($m), ['name' => 'Staff', 'dan_number' => '', 'dan_expires_on' => '']);
    is_same(null, team_find($m)['dan_number'], 'clearing the field clears the value');
});
