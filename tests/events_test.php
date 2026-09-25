<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Events.php';
require_once __DIR__ . '/../src/Onboarding.php';

function excursion_id(string $name): int
{
    return (int) db()->query("SELECT id FROM excursions WHERE name_en = " . db()->quote($name))->fetchColumn();
}

test('an excursion is created from the catalogue with a day plan and a fixed price', function (): void {
    $id = event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2030-10-18', 'meet_time' => '07:30', 'dives_count' => 2, 'capacity' => 8]);
    $e = event_find($id);
    is_same('2030-10-18-dos-ojos', $e['slug']);
    is_same('Dos Ojos · 2 dives', $e['title_en']);
    is_same('Dos Ojos · 2 inmersiones', $e['title_es']);
    is_same('3500.00', $e['price_mxn'], 'the two-dive price from the catalogue');
    $sessions = event_sessions($id);
    is_same(['Meet at the shop', 'Dive 1', 'Dive 2'], array_column($sessions, 'title_en'));
    is_same('2030-10-18 07:30:00', $sessions[0]['starts_at']);
    throws(static fn () => event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2030-10-19', 'dives_count' => 1]),
        'Dos Ojos is not sold as one dive');
    $second = event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2030-10-18', 'dives_count' => 2]);
    is_same('2030-10-18-dos-ojos-2', event_find($second)['slug'], 'two on the same day stay distinct');
});

test('a course gets one session per day', function (): void {
    $course = (int) db()->query("SELECT id FROM courses WHERE name_en = 'Open Water Course'")->fetchColumn();
    $id = event_create('training', ['catalog_id' => $course, 'date' => '2030-11-03', 'meet_time' => '08:00']);
    $sessions = event_sessions($id);
    is_same(3, count($sessions), '"3 days" makes three sessions');
    is_same('2030-11-05 08:00:00', $sessions[2]['starts_at']);
    is_same('Open Water Course', event_find($id)['title_en']);
});

test('a diver is added at the list price less their discount, until the event is full', function (): void {
    $id = event_create('excursion', ['catalog_id' => excursion_id('Yaa Kun'), 'date' => '2030-10-20', 'dives_count' => 2, 'capacity' => 2]);
    $a = customer_save(null, ['name' => 'Full Price']);
    $b = customer_save(null, ['name' => 'Ten Off', 'discount_pct' => '10']);
    $c = customer_save(null, ['name' => 'No Room']);

    $pa = event_participant_add($id, $a);
    $pb = event_participant_add($id, $b);
    $rows = array_column(event_participants($id), 'price_mxn', 'id');
    is_same('4200.00', $rows[$pa]);
    is_same('3780.00', $rows[$pb], '10% off the agreed price, fixed now');
    throws(static fn () => event_participant_add($id, $c), 'capacity 2');
    throws(static fn () => event_participant_add($id, $a), 'not twice');

    // Changing the event price later does not touch prices already agreed.
    event_update($id, ['price_mxn' => '5000', 'capacity' => 5, 'status' => 'open']);
    is_same('4200.00', array_column(event_participants($id), 'price_mxn', 'id')[$pa]);
    $pc = event_participant_add($id, $c);
    is_same('5000.00', array_column(event_participants($id), 'price_mxn', 'id')[$pc]);
});

test('payments add up to paid, deposit or unpaid, and the event money summary', function (): void {
    $id = event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2030-10-21', 'dives_count' => 2]);
    $a = event_participant_add($id, customer_save(null, ['name' => 'Payer A']));
    $b = event_participant_add($id, customer_save(null, ['name' => 'Payer B']));
    $c = event_participant_add($id, customer_save(null, ['name' => 'Payer C']));

    payment_add($a, ['amount' => '3,500', 'method' => 'cash']);
    payment_add($b, ['amount' => '1000', 'method' => 'transfer']);
    throws(static fn () => payment_add($c, ['amount' => '']));

    $by = array_column(event_participants($id), null, 'id');
    is_same('paid', participant_payment_state($by[$a]));
    is_same('deposit', participant_payment_state($by[$b]));
    is_same('unpaid', participant_payment_state($by[$c]));

    $m = event_money($id);
    is_same(10500.0, $m['total']);
    is_same(4500.0, $m['paid']);
    is_same(6000.0, $m['due']);
    is_same([1, 1, 1], [$m['n_paid'], $m['n_deposit'], $m['n_unpaid']]);

    payment_add($a, ['amount' => '500', 'refund' => 1]);
    is_same('deposit', participant_payment_state(array_column(event_participants($id), null, 'id')[$a]), 'a refund reopens the balance');
});

test('the training release names the instructors of the next course, and the signature records the event', function (): void {
    $course = (int) db()->query("SELECT id FROM courses WHERE name_en = 'Advanced Open Water'")->fetchColumn();
    $instr = person_create('Ana Instructor');
    db()->prepare('INSERT INTO team_members (person_id, is_instructor, is_active) VALUES (:p, 1, 1)')->execute([':p' => $instr]);
    $tm = (int) db()->lastInsertId();
    $id = event_create('training', ['catalog_id' => $course, 'date' => '2030-12-01', 'lead_team_id' => $tm]);

    $cid = customer_save(null, ['name' => 'Student One', 'date_of_birth' => '1990-01-01']);
    $cust = customer_find($cid);
    is_same('', liability_fills($cust, form_template_by_code('liability'))['instructor_names'], 'not booked: no instructor');

    event_participant_add($id, $cid);
    $fills = liability_fills($cust, form_template_by_code('liability'));
    is_same('Ana Instructor', $fills['instructor_names']);
    is_same($id, $fills['event_id']);

    channel_upsert((int) $cust['person_id'], 'email', 'student.one@example.com');
    $sid = form_sign($cust, form_template_by_code('liability'), ['ack_read' => 'yes'], test_signature(), onboarding_signer($cust));
    is_same($id, (int) db()->query("SELECT event_id FROM form_submissions WHERE id = {$sid}")->fetchColumn());

    is_same(['consent', 'profile', 'medical', 'safe_diving', 'liability'], array_column(onboarding_steps($cust, 'auto'), 'key'),
        'booked on a course: the checklist asks for the training release only');
});

test('documents per diver on an event count only what that kind needs', function (): void {
    $id = event_create('excursion', ['catalog_id' => excursion_id('Dos Ojos'), 'date' => '2030-10-22', 'dives_count' => 2]);
    $cid = customer_save(null, ['name' => 'Docs Diver']);
    event_participant_add($id, $cid);
    $d = participant_documents($cid, 'excursion');
    is_same(4, $d['total'], 'diver info, medical, safe diving, the excursion release');
    is_same(0, $d['ok']);
    is_same(4, count($d['missing']));
});
