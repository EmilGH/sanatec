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
    ];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($paths[$name] ?? '') . '</svg>';
}
