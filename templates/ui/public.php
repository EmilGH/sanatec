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

/** The same page in another language: /c/x becomes /es/c/x and back, query string kept. */
function ui_lang_path(string $code): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
    $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
    if ($path === '/es' || str_starts_with($path, '/es/')) {
        $path = substr($path, 3) ?: '/';
    }
    $path = preg_replace('#^/index\.php$#', '/', $path) ?? $path;
    $path = ($code === 'es' ? '/es' : '') . $path;
    if ($path === '/es') {
        $path = '/es/';
    }

    return $path . ($query !== '' ? '?' . $query : '');
}

function ui_public_header(string $lang): string
{
    $langs = '';
    foreach (LANGUAGES as $code => $meta) {
        $langs .= '<a href="' . e(ui_lang_path($code)) . '" hreflang="' . e($code) . '" lang="' . e($code) . '"'
            . ($code === $lang ? ' aria-current="true"' : '') . '>' . e(strtoupper($code)) . '</a>';
    }

    return '<header class="st-hdr st-wrap">'
        . '<a class="st-hdr__brand" href="' . e(LANGUAGES[$lang]['path']) . '"><img class="st-hdr__roundel" src="/assets/brand/roundel-512.png" width="512" height="512" alt="" fetchpriority="high">' . ui_wordmark('st-wm', setting('business_name')) . '</a>'
        . '<nav class="st-lang" aria-label="' . e(t('lang_label', $lang)) . '">' . $langs . '</nav>'
        . '</header>';
}

/** The band under the header for a diver who arrived through an affiliate's link. */
function ui_affiliate_strip(?array $affiliate, string $lang): string
{
    if ($affiliate === null) {
        return '';
    }
    $desc = trim((string) ($affiliate['description_' . $lang] ?? '')) ?: (string) ($affiliate['description_en'] ?? '');
    $logo = $affiliate['logo_path'] ? '<img class="st-affil__logo" src="/affiliate-logo.php?code=' . e($affiliate['code']) . '" alt="' . e($affiliate['name']) . '">' : '';

    return '<div class="st-affil st-wrap">' . $logo . '<div><strong>' . e(strtr(t('affiliate_strip', $lang), ['{name}' => $affiliate['name']])) . '</strong>'
        . ($desc !== '' ? '<span>' . e(mb_substr($desc, 0, 160)) . '</span>' : '') . '</div></div>';
}

/** The contact band at the end of every public page: heading, line of text, the contact list. */
function ui_contact_section(string $lang): string
{
    return '<section class="st-section st-wrap" id="contact" aria-labelledby="contact-title">'
        . '<div class="st-section__head"><h2 class="st-h1" id="contact-title">' . e(setting('contact_title', $lang)) . '</h2>'
        . '<p class="st-lede">' . e(setting('contact_text', $lang)) . '</p></div>'
        . ui_contact_list($lang) . '</section>';
}

function ui_public_footer(string $lang): string
{
    $privacy = $lang === 'es' ? '/es/privacy' : '/privacy';
    $other = $lang === 'es' ? '<a href="' . e(ui_lang_path('en')) . '" hreflang="en">EN</a>' : '<a href="' . e(ui_lang_path('es')) . '" hreflang="es">ES</a>';

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
    // [icon, href, title, subtitle, trailing icon, pill]
    $rows = [
        ['whatsapp', ui_wa_url(t('wa_prefill', $lang)), t('contact_whatsapp', $lang), setting('phone_display'), 'chevron', t('contact_fastest', $lang)],
        ['sms', ui_sms_url(), t('contact_sms', $lang), setting('phone_display'), 'chevron'],
    ];
    if (has_setting('contact_email')) {
        $rows[] = ['mail', 'mailto:' . setting('contact_email'), t('contact_email', $lang), setting('contact_email'), 'chevron'];
    }
    if (has_setting('addr_locality')) {
        $line = trim(setting('addr_street') . ' ' . setting('addr_postal') . ' ' . setting('addr_locality') . ', ' . setting('addr_region'));
        $href = has_setting('maps_url') ? setting('maps_url') : 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($line);
        $rows[] = ['pin', $href, $line, has_setting('opening_hours') ? setting('opening_hours') : t('open_in_maps', $lang), 'external'];
    }
    $teamCount = (int) db()->query('SELECT COUNT(*) FROM team_members WHERE profile_public = 1 AND is_active = 1 AND public_slug IS NOT NULL')->fetchColumn();
    if ($teamCount > 0) {
        $rows[] = ['users', ($lang === 'es' ? '/es' : '') . '/team/', t('team_link', $lang), t('team_intro', $lang), 'chevron'];
    }

    $html = '<div class="st-contact">';
    foreach ($rows as $row) {
        [$icon, $href, $title, $sub, $trail] = $row;
        $pill = isset($row[5]) ? ' <span class="st-pill st-pill--ok">' . e($row[5]) . '</span>' : '';
        $external = $trail === 'external';
        $html .= '<a href="' . e($href) . '"' . ($external ? ' target="_blank" rel="noopener" class="st-location"' : '') . '>'
            . ui_icon($icon) . '<span>' . e($title) . $pill . '<br><small>' . e($sub) . '</small></span>' . ui_icon($trail) . '</a>';
    }

    return $html . '</div>';
}

/** One course as a menu row with a single price line. */
/** A date the way the page's language says it: "Sat 18 Oct" / "sáb 18 oct". */
function ui_date_short(string $date, string $lang): string
{
    $t = strtotime($date);
    if ($lang === 'es') {
        $d = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'][(int) date('w', $t)];
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][(int) date('n', $t) - 1];

        return $d . ' ' . date('j', $t) . ' ' . $m;
    }

    return date('D j M', $t);
}

/**
 * Scheduled days as a list: date, title, places left, and a WhatsApp button
 * prefilled for that day. $linkTitles makes each title a link to its item page.
 */
function ui_upcoming(array $events, string $lang, bool $linkTitles = true): string
{
    if ($events === []) {
        return '';
    }
    $html = '<ul class="st-days">';
    foreach ($events as $ev) {
        $title = $lang === 'es' && $ev['title_es'] !== '' ? $ev['title_es'] : $ev['title_en'];
        $left = max(0, (int) $ev['capacity'] - (int) $ev['taken']);
        $when = ui_date_short($ev['starts_on'], $lang) . ($ev['first_at'] ? ' · ' . date('H:i', strtotime($ev['first_at'])) : '');
        $prefill = strtr(t('wa_day', $lang), ['{title}' => $title, '{date}' => ui_date_short($ev['starts_on'], $lang)]);
        $href = ($lang === 'es' ? '/es' : '') . '/c/' . rawurlencode((string) $ev['item_slug']);
        $t = strtotime($ev['starts_on']);
        $html .= '<li class="st-day' . ($left === 0 ? ' st-day--full' : '') . '">'
            . '<span class="st-day__date"><b>' . date('j', $t) . '</b><small>' . e($lang === 'es' ? ui_date_short($ev['starts_on'], 'es') : date('M', $t)) . '</small></span>'
            . '<span class="st-day__t">' . ($linkTitles ? '<a href="' . e($href) . '">' . e($title) . '</a>' : e($title))
            . '<span class="st-day__s">' . e($when) . ' · ' . ($left === 0 ? '<b>' . e(t('day_full', $lang)) . '</b>' : e($left === 1 ? t('place_left', $lang) : strtr(t('places_left', $lang), ['{n}' => (string) $left]))) . '</span></span>'
            . ($left > 0 ? '<a class="st-btn st-btn--secondary st-btn--sm" href="' . e(ui_wa_url($prefill)) . '">' . ui_icon('whatsapp') . e(t('book_day', $lang)) . '</a>' : '')
            . '</li>';
    }

    return $html . '</ul>';
}

/** The item's first photo as a small 3:2 thumbnail linking to its page, or nothing when it has none. */
function ui_row_thumb(string $type, int $itemId, string $slug, string $lang): string
{
    require_once __DIR__ . '/../../src/Photos.php';
    $ph = catalog_photo_first($type, $itemId);
    if ($ph === null) {
        return '';
    }
    $href = ($lang === 'es' ? '/es' : '') . '/c/' . rawurlencode($slug);

    return '<a class="st-row__thumb" href="' . e($href) . '" tabindex="-1" aria-hidden="true"><img src="' . e(photo_url($ph, true)) . '" alt="" width="84" height="56" loading="lazy"></a>';
}

/** The item's name as a link to its own page (its own URL and preview card), with a small link mark right after the text. */
function ui_row_title(string $name, string $slug, string $lang): string
{
    $href = ($lang === 'es' ? '/es' : '') . '/c/' . rawurlencode($slug);

    return '<h3 class="st-row__name"><a class="st-row__title" href="' . e($href) . '" title="' . e(t('share_row', $lang)) . '">' . e($name) . ui_icon('link') . '</a></h3>';
}

function ui_menu_course(array $c, string $lang): string
{
    $name = ui_name($c, $lang);
    $duration = ui_name($c, $lang, 'duration');
    $prefill = strtr(t('wa_line_course', $lang), ['{name}' => $name, '{duration}' => $duration]);
    $price = money($c['price_mxn']);

    return '<li class="st-row" id="' . e($c['slug']) . '">'
        . '<div class="st-row__head' . (($thumb = ui_row_thumb('course', (int) $c['id'], $c['slug'], $lang)) !== '' ? ' st-row__head--photo' : '') . '">' . $thumb . ui_row_title($name, $c['slug'], $lang) . '</div>'
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
        . '<div class="st-row__head' . (($thumb = ui_row_thumb('excursion', (int) $x['id'], $x['slug'], $lang)) !== '' ? ' st-row__head--photo' : '') . '">' . $thumb . ui_row_title($name, $x['slug'], $lang)
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
