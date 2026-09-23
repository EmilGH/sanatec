<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/Customers.php';
require_once __DIR__ . '/MedicalForm.php';

/**
 * Diver onboarding: the steps between "I'd like to dive" and "cleared to dive".
 *
 *   1. consent to the privacy notice (before any personal data is collected)
 *   2. the diver information form — profile, experience, emergency contact
 *   3. the medical questionnaire — which may end in "see a physician"
 *   4. the safe diving practices statement
 *   5. the liability release for the activity booked (training / excursion)
 *
 * Each signed form is a form_submission; the medical one also produces a
 * medical_evaluation. A minor's forms are signed by their guardian.
 */

const PRIVACY_CONSENT_PURPOSE = 'privacy_notice';

/** The customer profile for a signed-in person, created on first use. */
function customer_for_person(int $personId): array
{
    $c = customer_find_by_person($personId);
    if ($c !== null) {
        return $c;
    }
    db()->prepare('INSERT INTO customers (person_id) VALUES (:p)')->execute([':p' => $personId]);

    return customer_find((int) db()->lastInsertId());
}

/** Has this person consented to the current privacy notice version? */
function privacy_consent_current(int $personId): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM consents WHERE person_id = :p AND purpose = :purpose AND version = :v AND revoked_at IS NULL'
    );
    $stmt->execute([':p' => $personId, ':purpose' => PRIVACY_CONSENT_PURPOSE, ':v' => setting('privacy_notice_version')]);

    return (int) $stmt->fetchColumn() > 0;
}

function privacy_consent_record(int $personId, ?int $channelId = null): void
{
    if (privacy_consent_current($personId)) {
        return;
    }
    db()->prepare(
        'INSERT INTO consents (person_id, purpose, version, granted_at, via_channel_id, ip)
         VALUES (:p, :purpose, :v, NOW(), :c, :ip)'
    )->execute([
        ':p' => $personId, ':purpose' => PRIVACY_CONSENT_PURPOSE, ':v' => setting('privacy_notice_version'),
        ':c' => $channelId, ':ip' => client_ip_binary(),
    ]);
}

/** Is the diver information form substantially complete? */
function profile_complete(array $customer): bool
{
    $hasEmergency = emergency_contacts((int) $customer['id']) !== [];
    $hasChannel = person_channels((int) $customer['person_id']) !== [];

    return trim((string) $customer['name']) !== '' && $customer['date_of_birth'] !== null
        && $hasChannel && $hasEmergency;
}

/**
 * The onboarding checklist for a customer, for one activity kind.
 * Each step: ['key', 'title_en', 'title_es', 'done' => bool, 'status' => str, 'href' => str, 'template' => ?array].
 */
function onboarding_steps(array $customer, string $activity = 'all', string $lang = 'en'): array
{
    $steps = [];
    $steps[] = [
        'key' => 'consent', 'title' => $lang === 'es' ? 'Aviso de privacidad' : 'Privacy notice',
        'done' => privacy_consent_current((int) $customer['person_id']), 'status' => '', 'href' => '/my/consent.php', 'template' => null,
    ];
    $steps[] = [
        'key' => 'profile', 'title' => $lang === 'es' ? 'Información del buceador' : 'Diver information',
        'done' => profile_complete($customer), 'status' => '', 'href' => '/my/profile.php', 'template' => null,
    ];

    foreach (customer_document_status((int) $customer['id']) as $d) {
        $t = $d['template'];
        if ($t['code'] === 'diver_info') {
            continue;                                   // that is the profile step above
        }
        if ($t['applies_to'] !== 'all' && $activity !== 'all' && $t['applies_to'] !== $activity) {
            continue;
        }
        $steps[] = [
            'key' => $t['code'], 'title' => $t['title'], 'done' => $d['ok'],
            'status' => $d['status'] . ($d['outcome'] ? ' · ' . str_replace('_', ' ', $d['outcome']) : ''),
            'href' => '/my/form.php?code=' . rawurlencode($t['code']), 'template' => $t, 'outcome' => $d['outcome'],
        ];
    }

    return $steps;
}

/** Who signs for this customer: the guardian if a minor, else themselves. Null if a minor has no guardian yet. */
function onboarding_signer(array $customer): ?array
{
    if (is_minor($customer['date_of_birth'])) {
        return $customer['guardian_person_id'] ? ['person_id' => (int) $customer['guardian_person_id'], 'role' => 'guardian'] : null;
    }

    return ['person_id' => (int) $customer['person_id'], 'role' => 'participant'];
}

function form_template_by_code(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM form_templates WHERE code = :c AND is_active = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute([':c' => $code]);
    $t = $stmt->fetch() ?: null;
    if ($t !== null) {
        $t['definition'] = json_decode((string) $t['definition'], true) ?: [];
    }

    return $t;
}

/**
 * Sign a form. Creates the submission (and, for the medical questionnaire,
 * the evaluation). Returns the submission id.
 *
 * $typedName is the signature: the signer's name as they typed it, which must
 * match the signer's record closely enough to be a signature and not a typo.
 */
function form_sign(array $customer, array $template, array $answers, string $typedName, array $signer): int
{
    $signerPerson = person_find($signer['person_id']);
    if ($signerPerson === null) {
        throw new RuntimeException('Signer not found.');
    }
    if (!names_match($typedName, $signerPerson['name'])) {
        throw new InvalidArgumentException('Please type your name exactly as it appears on your profile: ' . $signerPerson['name']);
    }
    if ($template['code'] === 'medical') {
        $missing = array_diff(medical_required_ids($answers), array_keys($answers));
        if ($missing !== []) {
            throw new InvalidArgumentException('Please answer every question.');
        }
    }

    $expires = $template['validity_days'] ? date('Y-m-d', strtotime('+' . (int) $template['validity_days'] . ' days')) : null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(
            'INSERT INTO form_submissions
               (customer_id, template_id, status, answers, signed_by_person_id, signer_role, signature_typed,
                signed_at, signed_ip, signed_user_agent, expires_on)
             VALUES (:c, :t, "signed", :a, :s, :role, :sig, NOW(), :ip, :ua, :exp)'
        )->execute([
            ':c' => $customer['id'], ':t' => $template['id'], ':a' => json_encode($answers, JSON_UNESCAPED_UNICODE),
            ':s' => $signer['person_id'], ':role' => $signer['role'], ':sig' => mb_substr(trim($typedName), 0, 200),
            ':ip' => client_ip_binary(), ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), ':exp' => $expires,
        ]);
        $submissionId = (int) $pdo->lastInsertId();

        // Earlier signed copies of the same document are superseded, not deleted.
        $pdo->prepare('UPDATE form_submissions SET status = "void" WHERE customer_id = :c AND template_id = :t AND id <> :id AND status = "signed"')
            ->execute([':c' => $customer['id'], ':t' => $template['id'], ':id' => $submissionId]);

        if ($template['code'] === 'medical') {
            $result = medical_outcome($answers);
            $pdo->prepare('INSERT INTO medical_evaluations (submission_id, customer_id, outcome, flagged_questions) VALUES (:s, :c, :o, :f)')
                ->execute([':s' => $submissionId, ':c' => $customer['id'], ':o' => $result['outcome'], ':f' => json_encode($result['flagged'])]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $submissionId;
}

/** A typed signature must be the person's name; accents, case and spacing forgiven. */
function names_match(string $typed, string $recorded): bool
{
    $fold = static fn (string $s): string => strtolower(preg_replace('/[^a-z]+/i', '', ascii_fold($s)) ?? '');

    return $fold($typed) !== '' && $fold($typed) === $fold($recorded);
}

/** The instructors on a training event, for the liability form. No events yet: none. */
function liability_fills(array $customer, array $template): array
{
    return [
        'store_name'       => setting('business_name') ?: 'SanaTec Diving',
        'instructor_names' => '',
    ];
}

/** Path of a template's PDF under the private directory, if it exists. */
function form_document_path(array $template, bool $physician = false): ?string
{
    $rel = $physician ? ($template['definition']['physician_form_path'] ?? null) : ($template['source_document_path'] ?? null);
    if ($rel === null) {
        return null;
    }
    $base = (string) cfg('private_dir', '/var/www/private/sanatecdiving');
    $path = $base . '/' . ltrim((string) $rel, '/');

    return is_file($path) ? $path : null;
}
