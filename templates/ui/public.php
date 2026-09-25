<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/brand.php';

/**
 * The public page's pieces. Header, footer and CTA bar are defined once here;
 * every public route composes them, so a change lands everywhere at once.
 */

function ui_wa_url(string $text = ''): string
{
    $url = 'https://wa.me/' . rawurlencode(setting('whatsapp_number'));

    return $text === '' ? $url : $url . '?text=' . rawurlencode($text);
}

function ui_sms_url(): string
{
    return 'sms:' . setting('phone_e164');
}

/** Name in the current language, falling back to English. */
function ui_name(array $row, string $lang, string $field = 'name'): string
{
    return trim((string) ($row[$field . '_' . $lang] ?? '')) ?: (string) $row[$field . '_en'];
}

function ui_public_header(string $lang): string
{
    $langs = '';
    foreach (LANGUAGES as $code => $meta) {
        $langs .= '<a href="' . e($meta['path']) . '" hreflang="' . e($code) . '" lang="' . e($code) . '"'
            . ($code === $lang ? ' aria-current="true"' : '') . '>' . e(strtoupper($code)) . '</a>';
    }

    return '<header class="st-hdr st-wrap">'
        . '<a class="st-hdr__brand" href="' . e(LANGUAGES[$lang]['path']) . '">' . ui_wordmark('st-wm', setting('business_name')) . '</a>'
        . '<nav class="st-lang" aria-label="' . e(t('lang_label', $lang)) . '">' . $langs . '</nav>'
        . '</header>';
}

function ui_public_footer(string $lang): string
{
    $privacy = $lang === 'es' ? '/es/privacy' : '/privacy';
    $other = $lang === 'es' ? '<a href="/">EN</a>' : '<a href="/es/">ES</a>';

    return '<footer class="st-foot st-wrap">'
        . ui_wordmark('st-wm st-wm--small', setting('business_name'))
        . '<p>' . e(setting('footer_note', $lang)) . '</p>'
        . '<p><a href="' . e($privacy) . '">' . e(t('privacy', $lang)) . '</a> · ' . $other . '</p>'
        . '</footer>';
}

function ui_ctabar(string $lang): string
{
    return '<div class="st-ctabar" aria-label="' . e(t('cta_label', $lang)) . '">'
        . '<a class="st-btn st-btn--primary" href="' . e(ui_wa_url(t('wa_prefill', $lang))) . '">' . ui_icon('whatsapp') . e(t('cta_whatsapp', $lang)) . '</a>'
        . '<a class="st-btn st-btn--secondary" href="' . e(ui_sms_url()) . '">' . e(t('cta_sms', $lang)) . '</a>'
        . '</div>';
}

/** WhatsApp, SMS, email, address — the contact list at the end of the page. */
function ui_contact_list(string $lang): string
{
    $rows = [
        ['whatsapp', ui_wa_url(t('wa_prefill', $lang)), t('contact_whatsapp', $lang), setting('phone_display') . ' · ' . t('contact_fastest', $lang), 'chevron'],
        ['sms', ui_sms_url(), t('contact_sms', $lang), setting('phone_display'), 'chevron'],
    ];
    if (has_setting('contact_email')) {
        $rows[] = ['mail', 'mailto:' . setting('contact_email'), t('contact_email', $lang), setting('contact_email'), 'chevron'];
    }
    if (has_setting('addr_locality')) {
        $line = trim(setting('addr_street') . ' ' . setting('addr_postal') . ' ' . setting('addr_locality') . ', ' . setting('addr_region'));
        $href = has_setting('maps_url') ? setting('maps_url') : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($line);
        $rows[] = ['pin', $href, $line, has_setting('opening_hours') ? setting('opening_hours') : t('open_in_maps', $lang), 'external', 'st-location'];
    }

    $html = '<div class="st-contact">';
    foreach ($rows as [$icon, $href, $title, $sub, $trail]) {
        $cls = $rows[count($rows) - 1][5] ?? '';
        $external = $trail === 'external';
        $html .= '<a href="' . e($href) . '"' . ($external ? ' target="_blank" rel="noopener" class="st-location"' : '') . '>'
            . ui_icon($icon) . '<span>' . e($title) . '<br><small>' . e($sub) . '</small></span>' . ui_icon($trail) . '</a>';
    }

    return $html . '</div>';
}

/** One course as a menu row with a single price line. */
function ui_menu_course(array $c, string $lang): string
{
    $name = ui_name($c, $lang);
    $duration = ui_name($c, $lang, 'duration');
    $prefill = strtr(t('wa_line_course', $lang), ['{name}' => $name, '{duration}' => $duration]);
    $price = money($c['price_mxn']);

    return '<li class="st-row" id="' . e($c['slug']) . '">'
        . '<div class="st-row__head"><h3 class="st-row__name">' . e($name) . '</h3></div>'
        . ($c['note_' . $lang] ?? $c['note_en'] ? '<p class="st-row__note">' . e(ui_name($c, $lang, 'note')) . '</p>' : '')
        . '<a class="st-line" href="' . e(ui_wa_url($prefill)) . '"><span>' . e($duration) . '</span><span class="st-line__lead"></span>'
        . ($price !== null ? '<span class="st-line__price">' . e($price) . '</span>' : '<span class="st-line__ask">' . e(t('ask_for_pricing', $lang)) . '</span>')
        . '</a></li>';
}

/** One excursion as a menu row with a line per dive count that is offered. */
function ui_menu_excursion(array $x, string $lang): string
{
    $name = ui_name($x, $lang);
    $cert = ui_name($x, $lang, 'cert');
    // A long certification ("Open Water + pre-dive check") becomes the code plus a note.
    $code = $cert;
    $note = '';
    if (preg_match('/^(open water|ow)\b(.*)$/i', $cert, $m)) {
        $code = 'OW';
        $note = trim($m[2], " +·-");
        $note = $note !== '' ? ($lang === 'es' ? 'Open Water más ' : 'Open Water plus ') . $note : '';
    } elseif (preg_match('/^(advanced open water|aow)\b/i', $cert)) {
        $code = 'AOW';
    }

    $html = '<li class="st-row" id="' . e($x['slug']) . '">'
        . '<div class="st-row__head"><h3 class="st-row__name">' . e($name) . '</h3>'
        . '<span class="st-cert' . ($code === 'AOW' ? ' st-cert--aow' : '') . '">' . e($code) . '</span></div>'
        . ($note !== '' ? '<p class="st-row__note">' . e($note) . '</p>' : '');

    $marker = (bool) $x['is_special_price'];
    foreach ([1 => 'price_1_dive', 2 => 'price_2_dives', 3 => 'price_3_dives'] as $n => $col) {
        if ($x[$col] === null) {
            continue;
        }
        $qty = strtr(t($n === 1 ? 'dive_n' : 'dives_n', $lang), ['{n}' => (string) $n]);
        $prefill = strtr(t('wa_line_excursion', $lang), ['{qty}' => $qty, '{name}' => $name]);
        $html .= '<a class="st-line" href="' . e(ui_wa_url($prefill)) . '"><span>' . e($qty) . '</span><span class="st-line__lead"></span>'
            . ($marker ? '<span class="st-line__tag">' . e(t('special_price', $lang)) . '</span>' : '')
            . '<span class="st-line__price">' . e((string) money($x[$col])) . '</span></a>';
        $marker = false;
    }

    return $html . '</li>';
}
