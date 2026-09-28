<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/autoload.php';
require_once __DIR__ . '/Uploads.php';
require_once __DIR__ . '/Onboarding.php';
require_once __DIR__ . '/Customers.php';
require_once __DIR__ . '/Events.php';

use setasign\Fpdi\Fpdi;

/** FPDF keeps the current page protected; overlaying an imported document needs to move between pages. */
final class SanatecPdf extends Fpdi
{
    public function goToPage(int $n): void
    {
        $this->page = $n;
        $this->FontFamily = '';   // so the next SetFont writes the font selection into this page's stream
    }

    /** FPDF writes out pages 1..$this->page, so the pointer must be on the last page when the document closes. */
    public function Output($dest = '', $name = '', $isUTF8 = false)
    {
        $this->page = count($this->pages);

        return parent::Output($dest, $name, $isUTF8);
    }
}

/**
 * Completed PDFs on the shop's own forms.
 *
 * Each template's source PDF (the file the online form was transcribed from)
 * is imported page by page and the answers, names, dates, the shop's name and
 * the drawn signature are written where the paper form has its blanks. The
 * coordinates below are PDF points from the top-left corner of the page, read
 * off the source files with pdftotext -bbox; they are per form version, so a
 * new PADI revision needs a new map.
 *
 * form_pdf_render() writes the file next to the other uploads and records it
 * on the submission. It runs at signing time and on demand from the admin.
 */

/** Where things go, per template code. */
function form_pdf_layout(string $code): array
{
    // 'text'  => [page, x, baseline y, value key, size]
    // 'sign'  => [page, x, bottom y, max width, max height]   participant line
    // 'guardian' => same, the parent/guardian line
    // 'date'  => [page, x, baseline y, size]                  next to each signature line
    // 'mark'  => answers: [key => [page, cx, cy]]             an X in a box when the answer is yes/no
    return match ($code) {
        'safe_diving' => [
            'text'     => [[1, 66, 208, 'participant', 9]],
            'sign'     => [1, 60, 697, 230, 19],
            'guardian' => [1, 60, 729, 230, 19],
            'date'     => [[1, 400, 697, 9], [1, 400, 729, 9]],
        ],
        'liability' => [
            'text' => [
                [1, 410, 112, 'store', 8], [1, 362, 194, 'store', 8],
                [1, 52, 291, 'participant', 8],
                [1, 40, 427, 'instructors', 8], [1, 40, 448, 'store', 8],
                [1, 328, 522, 'participant', 8], [1, 388, 543, 'instructors', 8], [1, 318, 563, 'store', 8],
            ],
            'sign'     => [1, 50, 708, 230, 17],
            'guardian' => [1, 50, 736, 230, 17],
            'date'     => [[1, 455, 709, 8], [1, 455, 737, 8]],
        ],
        'liability_excursion' => [
            'text' => [
                [1, 330, 175, 'store', 9], [1, 40, 324, 'participant', 9], [1, 190, 455, 'store', 9],
                [2, 40, 441, 'participant', 9], [2, 296, 617, 'dan_number', 9],
            ],
            'sign'     => [2, 40, 549, 230, 26],
            'guardian' => [2, 40, 583, 230, 26],
            'date'     => [[2, 460, 549, 9], [2, 460, 583, 9]],
            'mark'     => ['dan_yes' => [2, 190.3, 614], 'dan_no' => [2, 156, 614]],
        ],
        'medical' => [
            'text' => [
                [1, 395, 660, 'date', 9], [1, 60, 693, 'participant', 9], [1, 395, 693, 'birthdate', 9],
                [1, 60, 726, 'instructors', 9], [1, 395, 726, 'store', 9],
                [2, 110, 45, 'participant', 9], [2, 340, 45, 'birthdate', 9], [2, 445, 45, 'date', 9],
            ],
            'sign'     => [1, 60, 660, 230, 24],
            'guardian' => [1, 60, 660, 230, 24],   // one signature line: the guardian signs it for a minor
            'date'     => [],
            'mark'     => form_pdf_medical_marks(),
        ],
        default => [],
    };
}

/** The Yes / No boxes of the medical questionnaire: answer key => [page, yes cx, cy] and [page, no cx, cy]. */
function form_pdf_medical_marks(): array
{
    $marks = [];
    $yes = [274.4, 302.4, 340.0, 359.8, 396.7, 416.5, 444.0, 474.7, 503.6, 541.1];
    $no  = [284.1, 312.2, 340.0, 368.5, 396.7, 425.4, 453.9, 482.4, 510.8, 541.1];
    foreach ($yes as $i => $y) {
        $marks['q' . ($i + 1) . ':yes'] = [1, 508, $y + 3.5];
        $marks['q' . ($i + 1) . ':no']  = [1, 549, $no[$i] + 3.5];
    }
    $boxes = [
        'A' => [127.3, 145.9, 167.5, 187.0, 200.3], 'B' => [237.1, 251.6, 266.2, 287.9], 'C' => [335.8, 350.2, 364.7, 378.1],
        'D' => [415.3, 429.7, 444.2, 458.6, 472.3], 'E' => [510.1, 529.0, 547.2, 561.8], 'F' => [599.4, 614.7, 627.6, 641.9, 656.1],
        'G' => [693.9, 709.2, 722.1, 737.0, 750.2, 764.9],
    ];
    foreach ($boxes as $letter => $rows) {
        foreach ($rows as $i => $y) {
            $marks[$letter . ($i + 1) . ':yes'] = [2, 520.5, $y + 3.4];
            $marks[$letter . ($i + 1) . ':no']  = [2, 553, $y + 3.4];
        }
    }

    return $marks;
}

/** Text the core fonts can draw: Latin-1 with the usual accents; anything else becomes its nearest ASCII. */
function pdf_latin(string $s): string
{
    $out = @iconv('UTF-8', 'windows-1252//TRANSLIT', $s);

    return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) ?? '' : $out;
}

function pdf_text(SanatecPdf $pdf, float $x, float $y, string $text, float $size = 9, string $style = ''): void
{
    if (trim($text) === '') {
        return;
    }
    $pdf->SetFont('Helvetica', $style, $size);
    $pdf->SetTextColor(11, 31, 51);
    $pdf->Text($x, $y, pdf_latin($text));
}

/** An X centred on a checkbox. */
function pdf_mark(SanatecPdf $pdf, float $cx, float $cy): void
{
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->SetTextColor(11, 31, 51);
    $pdf->SetXY($cx - 5, $cy - 5);
    $pdf->Cell(10, 10, 'X', 0, 0, 'C');
}

/**
 * The drawn signature, trimmed to its ink, scaled into a box and sat on its
 * line. The canvas is wide and the stroke usually small, so without the trim
 * a signature would shrink to a scribble.
 */
function pdf_signature(SanatecPdf $pdf, string $absPng, float $x, float $bottom, float $maxW, float $maxH): void
{
    $img = @imagecreatefrompng($absPng);
    if ($img === false) {
        return;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $x0 = $w; $y0 = $h; $x1 = -1; $y1 = -1;
    for ($yy = 0; $yy < $h; $yy += 2) {
        for ($xx = 0; $xx < $w; $xx += 2) {
            $c = imagecolorsforindex($img, imagecolorat($img, $xx, $yy));
            if ($c['alpha'] < 120 && ($c['red'] + $c['green'] + $c['blue']) < 600) {
                $x0 = min($x0, $xx); $y0 = min($y0, $yy); $x1 = max($x1, $xx); $y1 = max($y1, $yy);
            }
        }
    }
    if ($x1 < 0) {
        imagedestroy($img);
        return;
    }
    $pad = 6;
    $x0 = max(0, $x0 - $pad); $y0 = max(0, $y0 - $pad); $x1 = min($w - 1, $x1 + $pad); $y1 = min($h - 1, $y1 + $pad);
    $crop = imagecrop($img, ['x' => $x0, 'y' => $y0, 'width' => $x1 - $x0 + 1, 'height' => $y1 - $y0 + 1]);
    imagedestroy($img);
    if ($crop === false) {
        return;
    }
    imagesavealpha($crop, true);
    $tmp = tempnam(sys_get_temp_dir(), 'sig') . '.png';
    imagepng($crop, $tmp);
    $cw = imagesx($crop);
    $ch = imagesy($crop);
    imagedestroy($crop);
    $scale = min($maxW / max(1, $cw), $maxH / max(1, $ch));
    $pdf->Image($tmp, $x, $bottom - $ch * $scale, $cw * $scale, $ch * $scale, 'PNG');
    @unlink($tmp);
}

/** Open the template's source PDF with every page imported; returns [pdf, page count]. */
function pdf_open_source(array $template): array
{
    $src = form_document_path($template);
    if ($src === null) {
        throw new RuntimeException("The source PDF for '{$template['code']}' is not on the server.");
    }
    $pdf = new SanatecPdf('P', 'pt', 'Letter');
    $pdf->SetAutoPageBreak(false);
    $pdf->SetMargins(0, 0, 0);
    $n = $pdf->setSourceFile($src);
    for ($i = 1; $i <= $n; $i++) {
        $tpl = $pdf->importPage($i);
        $size = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($tpl);
    }

    return [$pdf, $n];
}

/** Build the completed form for a submission. Returns the PDF bytes. */
function form_pdf_build(array $submission): string
{
    $template = db()->query('SELECT * FROM form_templates WHERE id = ' . (int) $submission['template_id'])->fetch();
    $customer = customer_find((int) $submission['customer_id']);
    if (!$template || $customer === null) {
        throw new RuntimeException('Submission, template or customer not found.');
    }
    $template['definition'] = json_decode((string) $template['definition'], true) ?: [];
    $layout = form_pdf_layout((string) $template['code']);
    if ($layout === []) {
        throw new RuntimeException("No PDF layout for '{$template['code']}'.");
    }
    $answers = json_decode((string) ($submission['answers'] ?? '[]'), true) ?: [];
    $signer = $submission['signed_by_person_id'] ? person_find((int) $submission['signed_by_person_id']) : null;
    $signedOn = strtotime((string) $submission['signed_at']) ?: time();

    $values = [
        'participant' => (string) $customer['name'],
        'birthdate'   => $customer['date_of_birth'] ? date('d/m/Y', strtotime($customer['date_of_birth'])) : '',
        'date'        => date('d/m/Y', $signedOn),
        'store'       => setting('business_name') ?: 'SANA TEC DIVING',
        'instructors' => $submission['event_id'] ? event_instructor_names((int) $submission['event_id']) : '',
        'dan_number'  => (string) ($customer['dan_number'] ?? ''),
    ];

    [$pdf, $pages] = pdf_open_source($template);

    foreach ($layout['text'] as [$page, $x, $y, $key, $size]) {
        if ($page <= $pages) {
            $pdf->goToPage($page);
            pdf_text($pdf, $x, $y, $values[$key] ?? '', $size);
        }
    }

    $marks = $layout['mark'] ?? [];
    if ($template['code'] === 'medical') {
        foreach ($answers as $k => $v) {
            if (isset($marks[$k . ':' . $v])) {
                [$page, $cx, $cy] = $marks[$k . ':' . $v];
                $pdf->goToPage($page);
                pdf_mark($pdf, $cx, $cy);
            }
        }
    } elseif ($template['code'] === 'liability_excursion') {
        [$page, $cx, $cy] = $marks[$values['dan_number'] !== '' ? 'dan_yes' : 'dan_no'];
        $pdf->goToPage($page);
        pdf_mark($pdf, $cx, $cy);
    }

    $sigLine = ($submission['signer_role'] ?? 'participant') === 'guardian' ? $layout['guardian'] : $layout['sign'];
    $sigPng = $submission['signature_image_path'] ? upload_path((string) $submission['signature_image_path']) : null;
    if ($sigPng !== null) {
        [$page, $x, $bottom, $maxW, $maxH] = $sigLine;
        $pdf->goToPage($page);
        pdf_signature($pdf, $sigPng, $x, $bottom, $maxW, $maxH);
    }
    // The date beside the line that was signed (the first date entry belongs to the participant line).
    $dates = $layout['date'];
    $dateSlot = ($submission['signer_role'] ?? 'participant') === 'guardian' ? ($dates[1] ?? $dates[0] ?? null) : ($dates[0] ?? null);
    if ($dateSlot !== null) {
        [$page, $x, $y, $size] = $dateSlot;
        $pdf->goToPage($page);
        pdf_text($pdf, $x, $y, $values['date'], $size);
    }

    // Provenance, small and grey, in the bottom margin of the first page.
    $pdf->goToPage(1);
    $ip = (string) ($submission['signed_ip'] ?? '');
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = (string) (@inet_ntop($ip) ?: '');
    }
    $how = ($submission['source'] ?? 'online') === 'online'
        ? sprintf('Signed electronically by %s on %s%s', $signer['name'] ?? $customer['name'], date('d M Y H:i', $signedOn), $ip !== '' ? ' from ' . $ip : '')
        : sprintf('Paper form recorded on %s', date('d M Y', $signedOn));
    $pdf->SetFont('Helvetica', '', 6.5);
    $pdf->SetTextColor(120, 130, 135);
    $pdf->Text(36, $pdf->GetPageHeight() - 10, pdf_latin($how . ' · ' . ($values['store']) . ' · record #' . (int) $submission['id']));

    return $pdf->Output('S');
}

/** Render, store and record the completed PDF for a submission. Returns the relative path. */
function form_pdf_render(int $submissionId): string
{
    $stmt = db()->prepare('SELECT * FROM form_submissions WHERE id = :id');
    $stmt->execute([':id' => $submissionId]);
    $s = $stmt->fetch();
    if (!$s) {
        throw new RuntimeException('Submission not found.');
    }
    $bytes = form_pdf_build($s);
    $rel = write_upload('signed', 'submission-' . $submissionId . '.pdf', $bytes);
    db()->prepare('UPDATE form_submissions SET rendered_pdf_path = :p WHERE id = :id')->execute([':p' => $rel, ':id' => $submissionId]);

    return $rel;
}

/** The shop's Diver Information Form, filled from the customer record. Returns the PDF bytes. */
function customer_info_pdf(int $customerId): string
{
    $c = customer_find($customerId);
    $template = form_template_by_code('diver_info');
    if ($c === null || $template === null) {
        throw new RuntimeException('Customer or template not found.');
    }
    $channels = person_channels((int) $c['person_id']);
    $pick = static function (string $kind) use ($channels): string {
        foreach ($channels as $ch) {
            if ($ch['kind'] === $kind) {
                return (string) $ch['value'];
            }
        }
        return '';
    };
    $ec = emergency_contacts($customerId)[0] ?? null;
    $certs = certifications($customerId);
    $top = $certs[0] ?? null;
    $fmt = static fn (?string $d): string => $d ? date('d/m/Y', strtotime($d)) : '';

    $rows = [
        [239, $c['name']], [266, $fmt($c['date_of_birth'])], [293, $pick('mobile')], [320, $pick('email')],
        [347, (string) ($c['nationality'] ?? '')], [374, (string) ($c['local_address'] ?? '')],
        [433, (string) ($top['agency'] ?? '')], [465, (string) ($top['level'] ?? '')], [497, (string) ($top['number'] ?? '')],
        [529, $c['total_dives'] !== null ? (string) $c['total_dives'] : ''], [561, $c['dives_last_year'] !== null ? (string) $c['dives_last_year'] : ''],
        [593, $fmt($c['last_dive_on'] ?? null)],
        [652, (string) ($ec['name'] ?? '')], [684, (string) ($ec['phone'] ?? '')],
        [716, trim((string) ($c['dan_number'] ?? '') . ($c['dan_expires_on'] ? '  (to ' . $fmt($c['dan_expires_on']) . ')' : ''))],
    ];
    [$pdf] = pdf_open_source($template);
    $pdf->goToPage(1);
    foreach ($rows as [$y, $text]) {
        pdf_text($pdf, 220, $y, (string) $text, 10);
    }
    $pdf->SetFont('Helvetica', '', 6.5);
    $pdf->SetTextColor(120, 130, 135);
    $pdf->Text(36, $pdf->GetPageHeight() - 10, pdf_latin('From the diver\'s record at ' . (setting('business_name') ?: 'SANA TEC DIVING') . ' · ' . date('d M Y')));

    return $pdf->Output('S');
}
