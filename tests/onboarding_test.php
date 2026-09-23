<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Onboarding.php';

function make_customer(string $name, ?string $dob = null): array
{
    $pid = person_create($name, $dob ? ['date_of_birth' => $dob] : []);
    channel_upsert($pid, 'email', strtolower(str_replace(' ', '.', ascii_fold($name))) . '@example.com', ['verified' => true, 'primary' => true]);
    $c = customer_for_person($pid);
    emergency_contact_save((int) $c['id'], null, ['name' => 'EC', 'phone' => '+12125550100']);
    return customer_find((int) $c['id']);
}

test('the medical rule: starred questions and box answers require a physician', function (): void {
    $no = array_fill_keys(array_map(static fn (array $q): string => $q['id'], medical_questions()), 'no');
    is_same('cleared', medical_outcome($no)['outcome']);

    is_same('physician_required', medical_outcome(['q3' => 'yes'] + $no)['outcome'], 'q3 is starred');
    is_same('physician_required', medical_outcome(['q10' => 'yes'] + $no)['outcome']);

    $q1yesBoxNo = ['q1' => 'yes', 'A1' => 'no', 'A2' => 'no', 'A3' => 'no', 'A4' => 'no', 'A5' => 'no'] + $no;
    is_same('cleared', medical_outcome($q1yesBoxNo)['outcome'], 'yes to q1 with a clean box A is fine');
    $r = medical_outcome(['A3' => 'yes'] + $q1yesBoxNo);
    is_same('physician_required', $r['outcome']);
    is_same(['A3'], $r['flagged']);

    is_true(in_array('A1', medical_required_ids(['q1' => 'yes']), true), 'a yes opens its box');
    is_false(in_array('A1', medical_required_ids(['q1' => 'no']), true));
});

test('consent is recorded against the notice version and re-asked when it changes', function (): void {
    $c = make_customer('Consent Test');
    is_false(privacy_consent_current((int) $c['person_id']));
    privacy_consent_record((int) $c['person_id']);
    is_true(privacy_consent_current((int) $c['person_id']));
    settings_save(['privacy_notice_version' => ['en' => '2030-01-01']]);
    is_false(privacy_consent_current((int) $c['person_id']), 'a new version needs a new consent');
    settings_save(['privacy_notice_version' => ['en' => '2026-09-DRAFT']]);
});

test('the checklist reflects consent, profile and each document', function (): void {
    $c = make_customer('Steps Test', '1990-05-05');
    $steps = onboarding_steps($c, 'all');
    $keys = array_column($steps, 'key');
    is_same(['consent', 'profile', 'medical', 'safe_diving', 'liability', 'liability_excursion'], $keys);
    is_false($steps[0]['done']);
    is_true($steps[1]['done'], 'name, DOB, a channel and an emergency contact make a complete profile');

    is_same(['consent', 'profile', 'medical', 'safe_diving', 'liability'], array_column(onboarding_steps($c, 'training'), 'key'), 'training shows the 10072 release only');
    is_same(['consent', 'profile', 'medical', 'safe_diving', 'liability_excursion'], array_column(onboarding_steps($c, 'excursion'), 'key'));
});

test('signing requires the signer to type their own name, then supersedes older copies', function (): void {
    $c = make_customer('Ana María Pérez', '1990-05-05');
    $tpl = form_template_by_code('safe_diving');
    $signer = onboarding_signer($c);
    is_same('participant', $signer['role']);

    throws(static fn () => form_sign($c, $tpl, ['ack_read' => 'yes'], 'Someone Else', $signer));
    $first = form_sign($c, $tpl, ['ack_read' => 'yes'], 'ana maria perez', $signer);   // accents and case forgiven
    $second = form_sign($c, $tpl, ['ack_read' => 'yes'], 'Ana María Pérez', $signer);
    is_same('void', db()->query("SELECT status FROM form_submissions WHERE id = {$first}")->fetchColumn(), 'the older copy is superseded, not deleted');
    is_same('signed', db()->query("SELECT status FROM form_submissions WHERE id = {$second}")->fetchColumn());
    is_true(array_values(array_filter(customer_document_status((int) $c['id']), static fn (array $d): bool => $d['template']['code'] === 'safe_diving'))[0]['ok']);
});

test('a signed medical produces an evaluation and an expiry a year out', function (): void {
    $c = make_customer('Med Test', '1990-05-05');
    $tpl = form_template_by_code('medical');
    $answers = array_fill_keys(array_map(static fn (array $q): string => $q['id'], medical_questions()), 'no');
    throws(static fn () => form_sign($c, $tpl, ['q1' => 'no'], 'Med Test', onboarding_signer($c)), 'every question must be answered');

    $sid = form_sign($c, $tpl, $answers, 'Med Test', onboarding_signer($c));
    $m = db()->query("SELECT * FROM medical_evaluations WHERE submission_id = {$sid}")->fetch();
    is_same('cleared', $m['outcome']);
    is_same(date('Y-m-d', strtotime('+365 days')), db()->query("SELECT expires_on FROM form_submissions WHERE id = {$sid}")->fetchColumn());
    is_true(array_values(array_filter(customer_document_status((int) $c['id']), static fn (array $d): bool => $d['template']['code'] === 'medical'))[0]['ok']);

    $sid2 = form_sign($c, $tpl, ['q5' => 'yes'] + $answers, 'Med Test', onboarding_signer($c));
    is_same('physician_required', db()->query("SELECT outcome FROM medical_evaluations WHERE submission_id = {$sid2}")->fetchColumn());
    is_false(customer_documents_complete((int) $c['id']), 'physician_required blocks completeness');
});

test('a minor signs through their guardian, or not at all', function (): void {
    $kid = make_customer('Kid Diver', date('Y-m-d', strtotime('-14 years')));
    is_same(null, onboarding_signer($kid), 'no guardian, no signer');
    $g = person_create('Guardian Person');
    channel_upsert($g, 'email', 'guardian.person@example.com');
    customer_set_guardian((int) $kid['id'], 'guardian.person@example.com');
    $kid = customer_find((int) $kid['id']);
    $signer = onboarding_signer($kid);
    is_same('guardian', $signer['role']);
    $tpl = form_template_by_code('liability');
    throws(static fn () => form_sign($kid, $tpl, ['ack_read' => 'yes'], 'Kid Diver', $signer), 'the child cannot sign for themselves');
    $sid = form_sign($kid, $tpl, ['ack_read' => 'yes'], 'Guardian Person', $signer);
    is_same('guardian', db()->query("SELECT signer_role FROM form_submissions WHERE id = {$sid}")->fetchColumn());
});

test('the privacy notice ships as a draft in both languages', function (): void {
    has('DRAFT', setting('privacy_notice_version'));
    has('# Your rights', setting('privacy_notice', 'en'));
    has('# Tus derechos', setting('privacy_notice', 'es'));
    has('[PRIVACY EMAIL]', setting('privacy_notice', 'en'), 'placeholders remain until the shop fills them');
});
