<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Customers.php';
require_once __DIR__ . '/../src/Team.php';

test('one agency list serves divers, staff credentials and validation', function (): void {
    $codes = array_keys(agencies());
    is_same(['PADI', 'SSI', 'NAUI', 'TDI', 'SDI'], array_slice($codes, 0, 5), 'the usual ones first');
    is_true(in_array('GUE', $codes, true) && in_array('NSS-CDS', $codes, true) && in_array('OTHER', $codes, true));
    is_same(CERT_AGENCIES, CREDENTIAL_AGENCIES, 'divers and staff draw on the same list');
    is_same('PADI', agency_code(' padi '));
    is_same(null, agency_code('ACME'));

    $c = make_customer('Agency Diver');
    throws(static fn () => certification_save((int) $c['id'], null, ['agency' => 'ACME', 'level_code' => 'ow']), 'an agency off the list');
    $id = certification_save((int) $c['id'], null, ['agency' => 'ssi', 'level_code' => 'aow']);
    $row = certifications((int) $c['id'])[0];
    is_same('SSI', $row['agency'], 'stored as the list code');
    is_same('Advanced Open Water', $row['level'], 'the level name comes from the list');

    $actor = ['id' => 0, 'team' => ['is_system_admin' => 1]];
    $tid = team_save(null, ['name' => 'Cred Staff'], $actor);
    team_credential_save($tid, null, ['kind' => 'technical', 'agency' => 'gue', 'title' => 'Cave 1', 'expires_on' => '2030-01-01']);
    is_same('GUE', team_credentials($tid)[0]['agency'], 'GUE was not an option for staff before; now any agency on the list is');
    throws(static fn () => team_credential_save($tid, null, ['kind' => 'technical', 'agency' => 'ACME', 'title' => 'x', 'expires_on' => '2030-01-01']));
});

test('one certification list with ranks, names in both languages, and kinds for grouping', function (): void {
    $all = certification_levels();
    foreach (['ow', 'aow', 'rescue', 'dm', 'instructor', 'sidemount', 'cavern', 'intro_cave', 'full_cave', 'other'] as $code) {
        is_true(isset($all[$code]), "{$code} still exists for stored records");
    }
    is_same(1, certification_rank('ow'));
    is_same(5, certification_rank('full_cave'));
    is_same(0, certification_rank('nitrox'), 'a specialty is not a level');
    is_same(0, certification_rank('nope'));
    is_same('Cueva completa', certification_name('full_cave', 'es'));
    is_same('Full Cave', certification_name('full_cave'));
    is_same(['Full Cave', 5], CERT_LEVELS['full_cave'], 'the old constant still reads the same shape');
    foreach ($all as $code => $c) {
        is_true(isset(CERTIFICATION_KINDS[$c[3]]), "{$code} has a known kind");
    }
});

test('one nationality list, validated on save, printed by name on the PDF', function (): void {
    is_same('MX', nationality_code('mx'));
    is_same(null, nationality_code('XX'));
    is_same('Alemania', nationality_name('DE', 'es'));
    is_same(['MX', 'CA', 'GB', 'US'], array_column(nationalities_ordered()[0], 0));
    $c = make_customer('Nation Diver');
    throws(static fn () => customer_save((int) $c['id'], ['name' => 'Nation Diver', 'nationality' => 'ZZ']), 'a code off the list');
    customer_save((int) $c['id'], ['name' => 'Nation Diver', 'nationality' => 'fr']);
    is_same('FR', customer_find((int) $c['id'])['nationality']);
});
