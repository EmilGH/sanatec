<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Site content settings.
 *
 * The database stores values only. The labels, help text and field types below
 * define what the admin form looks like, so the wording of the admin lives in
 * version control (and can be reviewed in a diff) rather than in the database.
 *
 * 'localized' => false means the value has no translation: one field, stored in
 * val_en and returned for every language.
 */
function settings_schema(): array
{
    return [
        'contact' => [
            'title'  => 'Contact details',
            'intro'  => 'Used by every WhatsApp, SMS and telephone link on the site, in both languages.',
            'fields' => [
                'business_name'   => ['label' => 'Business name', 'localized' => false],
                'phone_display'   => ['label' => 'Phone number, as shown', 'localized' => false, 'help' => 'e.g. +52 984 106 3306'],
                'phone_e164'      => ['label' => 'Phone number for links', 'localized' => false, 'help' => 'International format, no spaces: +529841063306'],
                'whatsapp_number' => ['label' => 'WhatsApp number', 'localized' => false, 'help' => 'Digits only, with country code: 529841063306'],
                'contact_email'   => ['label' => 'Email address', 'localized' => false, 'help' => 'Optional. Shown only if filled in.'],
            ],
        ],
        'location' => [
            'title'  => 'Location',
            'intro'  => 'The site does not show a location block, and search engines get no address, until these are filled in. '
                      . 'Leave a field empty rather than guessing — a wrong address in structured data sends divers to the wrong place.',
            'fields' => [
                'addr_street'    => ['label' => 'Street address', 'localized' => false],
                'addr_locality'  => ['label' => 'Town or city', 'localized' => false, 'help' => 'e.g. Playa del Carmen'],
                'addr_region'    => ['label' => 'State', 'localized' => false],
                'addr_postal'    => ['label' => 'Postal code', 'localized' => false],
                'addr_country'   => ['label' => 'Country code', 'localized' => false, 'help' => 'Two letters: MX'],
                'geo_lat'        => ['label' => 'Latitude', 'localized' => false, 'help' => 'From Google Maps, e.g. 20.6296'],
                'geo_lng'        => ['label' => 'Longitude', 'localized' => false, 'help' => 'e.g. -87.0739'],
                'maps_url'       => ['label' => 'Google Maps link', 'localized' => false],
                'opening_hours'  => ['label' => 'Opening hours', 'localized' => false, 'help' => 'Schema format, e.g. Mo-Su 07:00-19:00'],
                'price_range'    => ['label' => 'Price range', 'localized' => false, 'help' => '$, $$ or $$$ — a rough signal for search engines'],
            ],
        ],
        'included' => [
            'title'  => 'What is included',
            'intro'  => 'One item per line. This section is the single most common source of disagreement at the cenote, '
                      . 'so it stays hidden until you switch it on below.',
            'fields' => [
                'included_publish' => ['label' => 'Show this section on the site', 'type' => 'checkbox', 'localized' => false],
                'included_title'   => ['label' => 'Heading'],
                'included_items'   => ['label' => 'Included — one per line', 'type' => 'textarea'],
                'excluded_title'   => ['label' => 'Second heading'],
                'excluded_items'   => ['label' => 'Not included — one per line', 'type' => 'textarea'],
                'included_note'    => ['label' => 'Note below the lists', 'type' => 'textarea'],
            ],
        ],
        'hero' => [
            'title'  => 'Top of the page',
            'fields' => [
                'hero_eyebrow' => ['label' => 'Small line above the headline'],
                'hero_title_a' => ['label' => 'Headline, first line'],
                'hero_title_b' => ['label' => 'Headline, second line', 'help' => 'Shown in the serif accent face'],
                'hero_intro'   => ['label' => 'Introduction', 'type' => 'textarea'],
            ],
        ],
        'training' => [
            'title'  => 'Dive training section',
            'fields' => [
                'training_eyebrow' => ['label' => 'Small line above the heading'],
                'training_title'   => ['label' => 'Heading'],
                'training_caption' => ['label' => 'Caption above the table'],
                'training_note'    => ['label' => 'Note below the table', 'type' => 'textarea'],
            ],
        ],
        'adventures' => [
            'title'  => 'Adventure dives section',
            'fields' => [
                'adventures_eyebrow'  => ['label' => 'Small line above the heading'],
                'adventures_title'    => ['label' => 'Heading'],
                'adventures_caption'  => ['label' => 'Caption above the table'],
                'adventures_legend'   => ['label' => 'Certification key below the table', 'type' => 'textarea'],
                'adventures_footnote' => ['label' => 'Footnote for special prices', 'type' => 'textarea', 'help' => 'Shown when any route is marked as a special price.'],
            ],
        ],
        'contact_block' => [
            'title'  => 'Contact section',
            'fields' => [
                'contact_eyebrow' => ['label' => 'Small line above the heading'],
                'contact_title'   => ['label' => 'Heading'],
                'contact_text'    => ['label' => 'Text', 'type' => 'textarea'],
                'footer_note'     => ['label' => 'Footer note', 'type' => 'textarea'],
            ],
        ],
        'privacy' => [
            'title'  => 'Privacy notice',
            'intro'  => 'Shown at /privacy and accepted by every diver before any personal data is collected. '
                      . 'It ships as a DRAFT: have a lawyer review it, then change the version to the sign-off date. '
                      . 'Divers who accepted an older version are asked again.',
            'fields' => [
                'privacy_notice_version' => ['label' => 'Version', 'localized' => false, 'help' => 'e.g. 2026-10-01. Leave "DRAFT" in it until reviewed; the page says so.'],
                'privacy_notice'         => ['label' => 'Notice text', 'type' => 'textarea', 'help' => 'Plain text. Blank lines separate paragraphs; a line starting with # is a heading.'],
            ],
        ],
        'meta' => [
            'title'  => 'Search engines and link previews',
            'intro'  => 'The title and description shown in Google results, and the preview card people see when the link is shared on WhatsApp.',
            'fields' => [
                'meta_title'       => ['label' => 'Page title', 'help' => 'Around 60 characters'],
                'meta_description' => ['label' => 'Description', 'type' => 'textarea', 'help' => 'Around 155 characters'],
                'og_image'         => ['label' => 'Link preview image', 'localized' => false, 'help' => 'Path relative to the site root, e.g. assets/og-image.jpg'],
            ],
        ],
    ];
}

/**
 * Every setting, as skey => ['en' => ..., 'es' => ...]. Cached per request.
 *
 * Passing true drops the cache. settings_save() does that, so a read after a
 * write in the same request sees the new value rather than the old one.
 */
function settings_all(bool $reload = false): array
{
    static $cache = null;

    if ($cache !== null && !$reload) {
        return $cache;
    }

    $cache = [];
    foreach (db()->query('SELECT skey, val_en, val_es FROM settings') as $row) {
        $cache[$row['skey']] = ['en' => $row['val_en'], 'es' => $row['val_es']];
    }

    return $cache;
}

/**
 * One setting in one language.
 *
 * Spanish falls back to English when a translation has not been written yet, so
 * a half-translated site degrades to readable rather than to blank.
 */
function setting(string $key, string $lang = 'en'): string
{
    $all = settings_all();

    if (!isset($all[$key])) {
        return '';
    }

    if ($lang !== 'en') {
        $translated = $all[$key][$lang] ?? null;
        if ($translated !== null && trim($translated) !== '') {
            return $translated;
        }
    }

    return (string) ($all[$key]['en'] ?? '');
}

/** True when a setting has a non-empty value in the given language. */
function has_setting(string $key, string $lang = 'en'): bool
{
    return trim(setting($key, $lang)) !== '';
}

/** Split a one-per-line textarea setting into a clean list. */
function setting_lines(string $key, string $lang = 'en'): array
{
    $raw = preg_split('/\R/', setting($key, $lang)) ?: [];

    return array_values(array_filter(array_map('trim', $raw), static fn (string $l): bool => $l !== ''));
}

/**
 * Write settings. $values is skey => ['en' => ..., 'es' => ...]; a missing 'es'
 * leaves the existing translation untouched rather than wiping it.
 */
/** Drop the per-request settings cache. */
function settings_cache_clear(): void
{
    settings_all(true);
}

function settings_save(array $values): int
{
    $stmt = db()->prepare(
        'INSERT INTO settings (skey, val_en, val_es) VALUES (:k, :en, :es)
         ON DUPLICATE KEY UPDATE val_en = VALUES(val_en), val_es = VALUES(val_es)'
    );

    $existing = settings_all();
    $changed = 0;

    foreach ($values as $key => $pair) {
        $en = $pair['en'] ?? ($existing[$key]['en'] ?? '');
        $es = array_key_exists('es', $pair) ? $pair['es'] : ($existing[$key]['es'] ?? null);

        if (($existing[$key]['en'] ?? null) === $en && ($existing[$key]['es'] ?? null) === $es) {
            continue;
        }

        $stmt->execute([':k' => $key, ':en' => $en, ':es' => $es]);
        $changed++;
    }

    if ($changed > 0) {
        settings_cache_clear();
    }

    return $changed;
}
