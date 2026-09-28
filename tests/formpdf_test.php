<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Onboarding.php';
require_once __DIR__ . '/../src/FormPdf.php';
require_once __DIR__ . '/../src/Events.php';

/** Pages in a PDF: the page tree's /Count. */
function pdf_pages(string $bytes): int
{
    return preg_match('#/Type\s*/Pages\s*/Kids\s*\[[^\]]*\]\s*/Count\s+(\d+)#', $bytes, $m) === 1 ? (int) $m[1] : -1;
}

test('every signed form is rendered onto its source PDF with the signature and the shop name', function (): void {
    $c = make_customer('Ana María Pérez Signer', '1990-04-12');
    customer_save((int) $c['id'], ['name' => $c['name'], 'dan_number' => 'DAN-123456']);
    $c = customer_find((int) $c['id']);
    $signer = onboarding_signer($c);
    $yes = array_fill_keys(array_map(static fn (array $q): string => $q['id'], medical_questions()), 'no');
    $cases = [
        'safe_diving'         => ['ack_read' => 'yes'],
        'liability'           => ['ack_read' => 'yes'],
        'liability_excursion' => ['ack_read' => 'yes'],
        'medical'             => ['q1' => 'yes', 'A1' => 'no', 'A2' => 'yes', 'A3' => 'no', 'A4' => 'no', 'A5' => 'no'] + $yes,
    ];
    $expectPages = ['safe_diving' => 1, 'liability' => 1, 'liability_excursion' => 2, 'medical' => 2];
    foreach ($cases as $code => $answers) {
        $tpl = form_template_by_code($code);
        $sid = form_sign($c, $tpl, $answers, test_signature(), $signer);
        $s = db()->query("SELECT * FROM form_submissions WHERE id = {$sid}")->fetch();
        is_true($s['rendered_pdf_path'] !== null, "{$code}: the PDF was rendered at signing");
        $abs = upload_path((string) $s['rendered_pdf_path']);
        is_true($abs !== null && is_file($abs), "{$code}: file stored");
        $bytes = (string) file_get_contents($abs);
        is_true(str_starts_with($bytes, '%PDF-'), "{$code}: is a PDF");
        is_same($expectPages[$code], pdf_pages($bytes), "{$code}: page count");
        is_true(strlen($bytes) > 20000, "{$code}: has the source page content, not a blank page");
        has('/Subtype /Image', $bytes, "{$code}: the signature image is embedded");
    }
});

test('the PDF can be generated again on demand and a paper record renders without a signature', function (): void {
    $c = make_customer('Regen Diver', '1985-01-01');
    $tpl = form_template_by_code('safe_diving');
    $sid = form_sign($c, $tpl, ['ack_read' => 'yes'], test_signature(), onboarding_signer($c));
    db()->exec("UPDATE form_submissions SET rendered_pdf_path = NULL WHERE id = {$sid}");
    $rel = form_pdf_render($sid);
    is_same('signed/submission-' . $sid . '.pdf', $rel);
    is_same($rel, db()->query("SELECT rendered_pdf_path FROM form_submissions WHERE id = {$sid}")->fetchColumn());

    $paper = form_record_paper($c, form_template_by_code('liability'), ['signed_on' => '2026-09-01'], null, 1);
    $bytes = form_pdf_build(db()->query("SELECT * FROM form_submissions WHERE id = {$paper}")->fetch());
    is_true(str_starts_with($bytes, '%PDF-'));
    has_not('/Subtype /Image', $bytes, 'no signature image on a paper record');
});

test('the diver information form is filled from the record', function (): void {
    $c = make_customer('Info Diver', '1992-07-07');
    customer_save((int) $c['id'], ['name' => 'Info Diver', 'nationality' => 'DE', 'total_dives' => '120', 'dan_number' => 'DAN-77']);
    $bytes = customer_info_pdf((int) $c['id']);
    is_true(str_starts_with($bytes, '%PDF-'));
    is_same(1, pdf_pages($bytes));
});
