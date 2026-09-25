<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Inline CSS for the public page, which loads no external stylesheet.
 *
 * The component sheet is split on its own "---------- Name ----------"
 * section markers, so a page inlines only the sections it uses and the file
 * stays the single source of truth. The dark token block is always included.
 */
function ui_inline_css(array $sections, string $extra = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $tokens = (string) file_get_contents(SANATEC_ROOT . '/assets/css/tokens.css');
        // Dark theme only on the public page: drop the [data-theme="light"] block.
        $tokens = preg_replace('/\[data-theme="light"\]\{[^}]*\}/', '', $tokens) ?? $tokens;

        $components = (string) file_get_contents(SANATEC_ROOT . '/assets/css/components.css');
        $parts = preg_split('/\/\* -{6,} (.+?) -{6,}\s*\*\//', $components, -1, PREG_SPLIT_DELIM_CAPTURE);
        $blocks = ['_base' => preg_replace('#/\*.*?\*/#s', '', (string) array_shift($parts)) ?? ''];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $blocks[trim($parts[$i])] = $parts[$i + 1];
        }
        $cache = ['tokens' => trim($tokens), 'blocks' => $blocks];
    }

    $css = $cache['tokens'] . "\n" . $cache['blocks']['_base'];
    foreach ($sections as $name) {
        foreach ($cache['blocks'] as $key => $block) {
            if ($key !== '_base' && str_starts_with(strtolower($key), strtolower($name))) {
                $css .= "\n" . $block;
            }
        }
    }
    $css .= "\n" . $extra;

    // Tighten: strip comments and collapse whitespace.
    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css) ?? $css;

    return '<style>' . trim($css) . '</style>';
}
