<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Affiliates.php';
require_once __DIR__ . '/../src/Photos.php';
require_once __DIR__ . '/../src/Onboarding.php';

test('an affiliate gets a code from its name, and codes stay unique and well-formed', function (): void {
    $a = affiliate_save(null, ['name' => 'Casa Malca Concierge']);
    is_same('casa-malca-concierge', affiliate_find($a)['code']);
    $b = affiliate_save(null, ['name' => 'Casa Malca Concierge']);
    is_same('casa-malca-concierge-2', affiliate_find($b)['code']);
    throws(static fn () => affiliate_save(null, ['name' => 'X', 'code' => 'casa-malca-concierge']), 'a taken code');
    throws(static fn () => affiliate_save(null, ['name' => 'X', 'share_pct' => '150']), 'a share over 100');
    throws(static fn () => affiliate_save(null, ['name' => '']), 'a nameless affiliate');
    is_same(null, affiliate_find_by_code('nope'));
    is_same(null, affiliate_find_by_code('../etc'));
    affiliate_save($a, ['name' => 'Casa Malca Concierge', 'is_active' => 0]);
    is_same(null, affiliate_find_by_code('casa-malca-concierge'), 'inactive codes do not resolve');
});

test('the contact is a person who can sign in to the partner view', function (): void {
    $a = affiliate_save(null, ['name' => 'Hotel Sol', 'contact_name' => 'Lupita Reyes']);
    $row = affiliate_find($a);
    is_true($row['contact_person_id'] !== null);
    is_same('Lupita Reyes', $row['contact_name']);
    is_same('Hotel Sol', affiliate_for_person((int) $row['contact_person_id'])['name']);
    affiliate_save($a, ['name' => 'Hotel Sol', 'contact_name' => 'Lupita Reyes Ortiz']);
    is_same($row['contact_person_id'], affiliate_find($a)['contact_person_id'], 'renaming keeps the same person');
    is_same('Lupita Reyes Ortiz', person_find((int) $row['contact_person_id'])['name']);
});

test('first touch: a new diver is attributed to the affiliate in the cookie, and only once', function (): void {
    $a = affiliate_save(null, ['name' => 'Hotel Uno']);
    $b = affiliate_save(null, ['name' => 'Hotel Dos']);
    $_COOKIE[AFFILIATE_COOKIE] = 'hotel-uno';
    $pid = person_create('Referred Diver');
    $c = customer_for_person($pid);
    is_same($a, (int) $c['referred_by_affiliate_id']);
    affiliate_attach_customer((int) $c['id'], 'hotel-dos');
    is_same($a, (int) customer_find((int) $c['id'])['referred_by_affiliate_id'], 'the first affiliate stays');
    unset($_COOKIE[AFFILIATE_COOKIE]);
    $c2 = customer_for_person(person_create('Walk In'));
    is_same(null, $c2['referred_by_affiliate_id']);
});

test('a photo is cropped to 3:2 at 1500 by 1000 with a thumbnail, and removed with its files', function (): void {
    $img = imagecreatetruecolor(2400, 2400);
    imagefill($img, 0, 0, imagecolorallocate($img, 40, 100, 140));
    $tmp = tempnam(sys_get_temp_dir(), 'ph');
    imagejpeg($img, $tmp);
    $file = ['name' => 'p.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)];
    $ex = (int) db()->query("SELECT id FROM excursions WHERE slug = 'dos-ojos'")->fetchColumn();

    $id = catalog_photo_add('excursion', $ex, $file, [100, 200, 900, 300], 'Light beams', 'Rayos de luz');
    $ph = catalog_photo_find($id);
    [$w, $h] = getimagesize(upload_path($ph['path']));
    is_same([1500, 1000], [$w, $h], 'a wide crop box is squared to 3:2 and scaled up');
    [$tw, $th] = getimagesize(upload_path(preg_replace('/\\.jpg$/', '-s.jpg', $ph['path'])));
    is_same([600, 400], [$tw, $th]);
    is_same('Light beams', $ph['caption_en']);
    is_same($id, (int) catalog_photo_first('excursion', $ex)['id']);

    imagejpeg($img, $tmp);
    $second = catalog_photo_add('excursion', $ex, ['name' => 'p.jpg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]);
    is_same([$id, $second], array_map('intval', array_column(catalog_photos('excursion', $ex), 'id')));
    catalog_photo_move($second, -1);
    is_same([$second, $id], array_map('intval', array_column(catalog_photos('excursion', $ex), 'id')));

    $abs = upload_path($ph['path']);
    catalog_photo_delete($id);
    is_same(null, catalog_photo_find($id));
    is_false(is_file($abs));
    catalog_photo_delete($second);
    throws(static fn () => catalog_photo_add('poster', 1, $file), 'unknown target');
});
