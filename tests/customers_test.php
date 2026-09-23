<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Customers.php';

test('a customer is created with a person behind it', function (): void {
    $id = customer_save(null, ['name' => 'Lena Fischer', 'nationality' => 'de', 'total_dives' => '48', 'discount_pct' => '10', 'wetsuit_size' => 'M']);
    $c = customer_find($id);
    is_same('Lena Fischer', $c['name']);
    is_same('DE', $c['nationality'], 'country code upper-cased');
    is_same(48, (int) $c['total_dives']);
    is_same('10.00', $c['discount_pct']);
    throws(static fn () => customer_save($id, ['name' => 'Lena Fischer', 'discount_pct' => '150']));
});

test('minors are detected from the date of birth', function (): void {
    is_true(is_minor(date('Y-m-d', strtotime('-15 years'))));
    is_false(is_minor(date('Y-m-d', strtotime('-19 years'))));
    is_false(is_minor(null), 'unknown age is not assumed to be a minor');
});

test('a guardian must exist and cannot be the diver', function (): void {
    $kid = customer_save(null, ['name' => 'Kid', 'date_of_birth' => date('Y-m-d', strtotime('-12 years'))]);
    $parentId = person_create('Parent');
    channel_upsert($parentId, 'email', 'parent@example.com');
    throws(static fn () => customer_set_guardian($kid, 'nobody@example.com'));
    customer_set_guardian($kid, 'PARENT@example.com');
    is_same('Parent', customer_find($kid)['guardian_name']);
    $kidPerson = (int) customer_find($kid)['person_id'];
    channel_upsert($kidPerson, 'email', 'kid@example.com');
    throws(static fn () => customer_set_guardian($kid, 'kid@example.com'), 'not their own guardian');
    customer_set_guardian($kid, '');
    is_same(null, customer_find($kid)['guardian_person_id']);
});

test('emergency contacts need an E.164 phone and the first is primary', function (): void {
    $id = customer_save(null, ['name' => 'Omar']);
    throws(static fn () => emergency_contact_save($id, null, ['name' => 'Ana', 'phone' => '984 106 3306']));
    emergency_contact_save($id, null, ['name' => 'Ana', 'phone' => '+52 984 106 3306']);
    $second = emergency_contact_save($id, null, ['name' => 'Bo', 'phone' => '+12125550100', 'is_primary' => 1]);
    $ecs = emergency_contacts($id);
    is_same(2, count($ecs));
    is_same('Bo', $ecs[0]['name'], 'the newest primary wins');
    is_same(1, count(array_filter($ecs, static fn (array $e): bool => (bool) $e['is_primary'])));
});

test('certifications rank, and gate by level', function (): void {
    $id = customer_save(null, ['name' => 'Rank Test']);
    certification_save($id, null, ['agency' => 'PADI', 'level_code' => 'ow']);
    is_true(customer_holds_level($id, 'ow'));
    is_false(customer_holds_level($id, 'aow'), 'Angelita needs AOW');
    certification_save($id, null, ['agency' => 'PADI', 'level_code' => 'aow', 'level' => 'Advanced Open Water Diver']);
    is_true(customer_holds_level($id, 'aow'));
    is_same('Advanced Open Water Diver', customers_search('Rank Test')[0]['top_cert']);
    throws(static fn () => certification_save($id, null, ['agency' => 'PADI', 'level_code' => 'black-belt']));
});

test('document status reads missing, signed, expired, and medical outcome', function (): void {
    $id = customer_save(null, ['name' => 'Docs Test']);
    $status = customer_document_status($id);
    is_same(5, count($status), 'five active templates: info, medical, safe diving, two liability editions');
    is_true(array_reduce($status, static fn (bool $c, array $d): bool => $c && $d['status'] === 'missing', true));
    is_false(customer_documents_complete($id));

    $tpl = db()->query("SELECT id FROM form_templates WHERE code = 'medical'")->fetchColumn();
    db()->prepare('INSERT INTO form_submissions (customer_id, template_id, status, answers, signer_role, signed_at, expires_on)
                   VALUES (:c, :t, "signed", "{}", "participant", NOW(), :exp)')
        ->execute([':c' => $id, ':t' => $tpl, ':exp' => date('Y-m-d', strtotime('+300 days'))]);
    $sid = (int) db()->lastInsertId();
    $med = array_values(array_filter(customer_document_status($id), static fn (array $d): bool => $d['template']['code'] === 'medical'))[0];
    is_same('signed', $med['status']);
    is_false($med['ok'], 'signed but no evaluation is not cleared');

    db()->prepare('INSERT INTO medical_evaluations (submission_id, customer_id, outcome) VALUES (:s, :c, "physician_required")')->execute([':s' => $sid, ':c' => $id]);
    is_false(array_values(array_filter(customer_document_status($id), static fn (array $d): bool => $d['template']['code'] === 'medical'))[0]['ok']);
    db()->exec("UPDATE medical_evaluations SET outcome = 'cleared' WHERE submission_id = {$sid}");
    is_true(array_values(array_filter(customer_document_status($id), static fn (array $d): bool => $d['template']['code'] === 'medical'))[0]['ok']);

    db()->exec("UPDATE form_submissions SET expires_on = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id = {$sid}");
    is_same('expired', array_values(array_filter(customer_document_status($id), static fn (array $d): bool => $d['template']['code'] === 'medical'))[0]['status']);
});

test('notes are attributed and append-only; archiving hides a customer', function (): void {
    $id = customer_save(null, ['name' => 'Notes Test']);
    $author = person_create('Staff Member');
    throws(static fn () => customer_note_add($id, $author, '   '));
    customer_note_add($id, $author, 'Prefers morning dives.');
    is_same('Staff Member', customer_notes($id)[0]['author']);
    is_same(1, count(customers_search('Notes Test')));
    customer_archive($id);
    is_same(0, count(customers_search('Notes Test')));
    is_same(null, customer_find($id));
});
