<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Events.php';
require_once __DIR__ . '/../src/Passport.php';
require_once __DIR__ . '/../src/Team.php';

/** A small JPEG as $_FILES would carry it. */
function test_photo(int $w = 60, int $h = 40): array
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 20, 90, 120));
    $tmp = tempnam(sys_get_temp_dir(), 'ph');
    imagejpeg($img, $tmp);
    return ['name' => 'photo.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
}

test('the seeded routes know their cenotes in dive order', function (): void {
    $sites = dive_sites();
    is_true(count($sites) >= 10, 'ten cenotes seeded');
    $route = (int) db()->query("SELECT id FROM excursions WHERE name_en LIKE 'Angelita%Carwash%'")->fetchColumn();
    is_true($route > 0, 'the two-cenote route exists');
    is_same(['angelita', 'carwash'], array_column(excursion_sites($route), 'slug'));
});

test('an excursion built from a route puts a site on each dive session', function (): void {
    $route = (int) db()->query("SELECT id FROM excursions WHERE name_en LIKE 'Angelita%Carwash%'")->fetchColumn();
    $id = event_create('excursion', ['catalog_id' => $route, 'date' => '2031-01-10', 'meet_time' => '08:00', 'dives_count' => 2]);
    $sessions = event_sessions($id);
    is_same([null, 'angelita', 'carwash'], array_map(static function (array $s): ?string {
        return $s['dive_site_id'] ? dive_site_find((int) $s['dive_site_id'])['slug'] : null;
    }, $sessions), 'meet has no site; each dive has its cenote');
    is_same('Angelita', $sessions[1]['location']);
});

test('marking a diver attended writes their dives once, and stamps follow', function (): void {
    $route = (int) db()->query("SELECT id FROM excursions WHERE name_en LIKE 'Angelita%Carwash%'")->fetchColumn();
    $id = event_create('excursion', ['catalog_id' => $route, 'date' => '2031-01-11', 'dives_count' => 2]);
    $cid = customer_save(null, ['name' => 'Stamp Collector']);
    $row = event_participant_add($id, $cid);
    is_same([], customer_dives($cid));

    event_participant_set_status($id, $row, 'attended');
    $dives = customer_dives($cid);
    is_same(2, count($dives));
    is_same(['carwash', 'angelita'], array_column($dives, 'site_slug'), 'newest first');
    is_same('2031-01-11', $dives[0]['dived_on']);

    event_participant_set_status($id, $row, 'attended');
    is_same(2, count(customer_dives($cid)), 'attending twice does not double the log');

    $stamps = customer_stamps($cid);
    is_same(['angelita', 'carwash'], array_column($stamps, 'slug'));
    is_same('1', (string) $stamps[0]['dives']);

    // A second trip to Angelita: one more dive, still one stamp, count 2.
    $again = event_create('excursion', ['catalog_id' => excursion_id('Angelita + Carwash'), 'date' => '2031-02-01', 'dives_count' => 1]);
    event_participant_set_status($again, event_participant_add($again, $cid), 'attended');
    is_same(3, count(customer_dives($cid)));
    $stamps = customer_stamps($cid);
    is_same(2, count($stamps));
    is_same('2', (string) $stamps[0]['dives'], 'Angelita twice, one dive on the one-dive day');
});

test('a diver may annotate their own dives and nobody else\'s', function (): void {
    $id = event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2031-01-12', 'dives_count' => 2]);
    $mine = customer_save(null, ['name' => 'Note Taker']);
    $other = customer_save(null, ['name' => 'Someone Else']);
    event_participant_set_status($id, event_participant_add($id, $mine), 'attended');
    $dive = customer_dives($mine)[0];

    dive_update_notes($mine, (int) $dive['id'], ['notes' => 'Halocline!', 'max_depth_m' => '18.5', 'duration_min' => '47']);
    $d = customer_dive($mine, (int) $dive['id']);
    is_same('Halocline!', $d['notes']);
    is_same('18.5', $d['max_depth_m']);
    is_same(47, (int) $d['duration_min']);

    is_same(null, customer_dive($other, (int) $dive['id']), 'not theirs');
    dive_update_notes($other, (int) $dive['id'], ['notes' => 'hijack']);
    is_same('Halocline!', customer_dive($mine, (int) $dive['id'])['notes'], 'another diver cannot touch it');
});

test('photos are private until switched public, and the public passport is opt-in', function (): void {
    $id = event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2031-01-13', 'dives_count' => 2]);
    $cid = customer_save(null, ['name' => 'Photo Diver']);
    event_participant_set_status($id, event_participant_add($id, $cid), 'attended');
    $dive = customer_dives($cid)[0];
    $cust = customer_find($cid);
    $pid = (string) db()->query('SELECT public_id FROM people WHERE id = ' . (int) $cust['person_id'])->fetchColumn();

    $photoId = dive_photo_add((int) $dive['id'], test_photo(2000, 1000), (int) $cust['person_id']);
    $ph = dive_photos((int) $dive['id'])[0];
    is_same(0, (int) $ph['is_public']);
    $abs = upload_path($ph['path']);
    is_true($abs !== null && is_file($abs), 'stored');
    [$w] = getimagesize($abs);
    is_same(1600, $w, 'longest side capped');

    is_same(null, passport_public($pid), 'passport off by default');
    db()->exec('UPDATE customers SET passport_public = 1 WHERE id = ' . $cid);
    $pp = passport_public($pid);
    is_same('Photo', $pp['name'], 'first name only');
    is_same(2, count($pp['dives']), 'two dives at the same cenote');
    is_same([], $pp['photos'], 'private photo stays off the public page');

    dive_photo_set_public($photoId, $cid, true);
    is_same(1, count(passport_public($pid)['photos']));
    dive_photo_set_public($photoId, 999999, false);
    is_same(1, count(passport_public($pid)['photos']), 'only the owner can flip it');

    dive_photo_delete($photoId, $cid);
    is_same([], dive_photos((int) $dive['id']));
    is_false(is_file($abs), 'file removed with the row');
});

test('the wish list toggles and drops a site once it is stamped', function (): void {
    $cid = customer_save(null, ['name' => 'Wisher']);
    $pit = (int) db()->query("SELECT id FROM dive_sites WHERE slug = 'the-pit'")->fetchColumn();
    is_true(wishlist_toggle($cid, $pit));
    is_same([$pit], customer_wishlist($cid));
    is_false(wishlist_toggle($cid, $pit));
    is_same([], customer_wishlist($cid));
});

test('a dive site can be added, and the slug is made from the name', function (): void {
    $id = dive_site_save(null, ['name_en' => 'Cenote Tajma Ha', 'max_depth_m' => '15', 'cert_required' => 'ow', 'is_published' => 1]);
    $s = dive_site_find($id);
    is_same('cenote-tajma-ha', $s['slug']);
    is_same('Cenote Tajma Ha', $s['name_es'], 'Spanish falls back to English');
    $dup = dive_site_save(null, ['name_en' => 'Cenote Tajma Ha']);
    is_same('cenote-tajma-ha-2', dive_site_find($dup)['slug']);
    throws(static fn () => dive_site_save(null, ['name_en' => '']));
});

test('the public team page lists only active members who opted in, and hides numbers unless allowed', function (): void {
    $actor = ['id' => 0, 'team' => ['is_system_admin' => 1]];
    $tid = team_save(null, ['name' => 'Public Guide', 'is_cave_guide' => 1, 'profile_public' => 1, 'title_en' => 'Cave Guide', 'title_es' => 'Guía de cueva', 'languages' => 'es, en', 'bio_en' => 'Loves the dark.'], $actor);
    $t = team_find($tid);
    channel_upsert((int) $t['person_id'], 'mobile', '+529841234567', ['whatsapp' => true, 'primary' => true]);
    team_credential_save($tid, null, ['kind' => 'professional', 'agency' => 'TDI', 'title' => 'Full Cave Instructor', 'number' => '123', 'expires_on' => '2099-01-01']);
    team_credential_save($tid, null, ['kind' => 'recreational', 'agency' => 'PADI', 'title' => 'Open Water', 'number' => '1']);
    team_save(null, ['name' => 'Private Person', 'profile_public' => 0], $actor);

    $list = team_public_list();
    is_same(['Public Guide'], array_column($list, 'name'));
    is_same('public-guide', $list[0]['public_slug']);
    is_same(['es', 'en'], $list[0]['languages']);
    is_same(['TDI Full Cave Instructor'], $list[0]['credentials'], 'professional only, no numbers');
    is_same(null, $list[0]['whatsapp'], 'number hidden by default');

    team_save($tid, ['name' => 'Public Guide', 'is_cave_guide' => 1, 'profile_public' => 1, 'show_whatsapp_public' => 1], $actor);
    is_same('+529841234567', team_public_list('public-guide')[0]['whatsapp']);
    is_same([], team_public_list('nobody-here'));

    team_save($tid, ['name' => 'Public Guide', 'profile_public' => 1, 'is_active' => 0], $actor);
    is_same([], team_public_list(), 'inactive drops off');
});

test('a team photo is stored square-capped and removed cleanly', function (): void {
    $actor = ['id' => 0, 'team' => ['is_system_admin' => 1]];
    $tid = team_save(null, ['name' => 'Photo Face'], $actor);
    team_photo_set($tid, test_photo(1200, 900));
    $path = team_find($tid)['photo_path'];
    is_true($path !== null);
    $abs = upload_path($path);
    [$w] = getimagesize($abs);
    is_same(800, $w);
    team_photo_set($tid, null);
    is_same(null, team_find($tid)['photo_path']);
    is_false(is_file($abs));
});
