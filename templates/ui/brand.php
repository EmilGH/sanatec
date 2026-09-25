<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Brand marks. The wordmark is inlined once per page as an SVG <symbol> and
 * then <use>d, so the header and footer share one copy. The roundel is an
 * <img>: at 44 KB of paths it is better cached than inlined.
 */

/** The <symbol> definitions for a page. Emit once, right after <body>. */
function ui_brand_defs(string $theme = 'dark'): string
{
    $file = SANATEC_ROOT . '/assets/brand/wordmark-' . ($theme === 'light' ? 'light' : 'dark') . '.svg';
    $svg = (string) @file_get_contents($file);
    if ($svg === '' || preg_match('/viewBox="([^"]+)"/', $svg, $vb) !== 1) {
        return '';
    }
    $inner = preg_replace('/^.*?<svg[^>]*>|<\/svg>\s*$/s', '', $svg) ?? '';

    return '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><symbol id="st-wm" viewBox="' . e($vb[1]) . '">'
        . $inner . '</symbol></svg>';
}

function ui_wordmark(string $class = 'st-wm', string $label = 'SANA TEC Diving'): string
{
    // The outer <svg> needs the viewBox too: without it a <use> of a symbol
    // has no intrinsic ratio and the browser gives it 300×150.
    return '<svg class="' . e($class) . '" viewBox="0 0 486.5 126.8" role="img" aria-label="' . e($label) . '"><use href="#st-wm"/></svg>';
}

/** The wave divider — the logo swoosh as a rule. */
function ui_wave(): string
{
    return '<svg class="st-wave" viewBox="0 0 1200 24" preserveAspectRatio="none" aria-hidden="true">'
        . '<path d="M0 14 C 150 2, 300 2, 450 14 S 750 26, 900 14 S 1150 2, 1200 14"/></svg>';
}

/** Small inline icons (Feather-style strokes), by name. */
function ui_icon(string $name, string $class = 'st-icon'): string
{
    $paths = [
        'whatsapp' => '<path d="M21 11.5a8.4 8.4 0 0 1-12.6 7.3L3 21l2.2-5.3A8.4 8.4 0 1 1 21 11.5z"/>',
        'sms'      => '<path d="M4 4h16v12H7l-3 3z"/>',
        'mail'     => '<path d="M3 5h18v14H3z"/><path d="m3 6 9 7 9-7"/>',
        'pin'      => '<path d="M12 22s7-7 7-12a7 7 0 0 0-14 0c0 5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
        'chevron'  => '<path d="m9 6 6 6-6 6"/>',
        'external' => '<path d="M14 4h6v6"/><path d="M20 4 10 14"/><path d="M18 13v7H4V6h7"/>',
        'check'    => '<path d="m4 12 5 5L20 6"/>',
        'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'back'     => '<path d="m15 6-6 6 6 6"/>',
        'home'     => '<path d="M3 11 12 3l9 8"/><path d="M5 10v10h14V10"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'wave'     => '<path d="M2 12c2.5-3 5-3 7.5 0s5 3 7.5 0 3-3 5 0"/><path d="M2 17c2.5-3 5-3 7.5 0s5 3 7.5 0 3-3 5 0"/>',
        'cap'      => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><circle cx="17" cy="9" r="2.5"/><path d="M22 19c0-2.5-2-4.5-5-4.5"/>',
        'badge'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M8 18c1-2 7-2 8 0"/>',
        'tag'      => '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'store'    => '<path d="M3 9 5 4h14l2 5"/><path d="M3 9h18v3a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 14v6h14v-6"/>',
        'more'     => '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
        'list'     => '<path d="M4 7h2M9 7h11M4 12h2M9 12h11M4 17h2M9 17h11"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/>',
        'logout'   => '<path d="M10 4H5v16h5"/><path d="m14 8 5 4-5 4M19 12H9"/>',
        'moon'     => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11.5 6.8"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'x'        => '<path d="M6 6l12 12M18 6 6 18"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/>',
        'file'     => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/>',
        'upload'   => '<path d="M12 16V5"/><path d="m7 10 5-5 5 5"/><path d="M4 19h16"/>',
        'pen'      => '<path d="m4 20 4-1L19 8l-3-3L5 16z"/><path d="m14 7 3 3"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'warn'     => '<path d="M12 3 2 20h20z"/><path d="M12 9v5M12 17.5v.5"/>',
    ];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}
