<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Document head: metadata, link-preview cards and structured data.
 *
 * @var string $lang     Current language code.
 * @var string $baseUrl  Absolute site root, no trailing slash.
 */

$title       = setting('meta_title', $lang) ?: setting('business_name');
$description = setting('meta_description', $lang);
$canonical   = lang_url($lang, $baseUrl);
$share       = $share ?? null;

// Link preview: the shop's own override if set, else the generated image —
// the home card, or the shared item's card.
$ogImage = setting('og_image');
$ogImageUrl = $ogImage !== '' ? $baseUrl . '/' . ltrim($ogImage, '/') : $baseUrl . '/og/home-' . $lang . '.png';
if ($share !== null) {
    $r = $share['row'];
    $name = trim((string) ($r['name_' . $lang] ?? '')) ?: (string) $r['name_en'];
    $title = $name . ' · ' . setting('business_name');
    $description = $share['type'] === 'course'
        ? ($lang === 'es' ? 'Curso de buceo' : 'Dive course') . ' · ' . (trim((string) ($r['duration_' . $lang] ?? '')) ?: $r['duration_en'])
            . ($r['price_mxn'] !== null ? ' · ' . money($r['price_mxn']) . ' MXN' : '')
        : ($lang === 'es' ? 'Buceo en cenote' : 'Cenote dive') . ' · ' . (trim((string) ($r['cert_' . $lang] ?? '')) ?: $r['cert_en'])
            . ($r['price_2_dives'] !== null ? ' · ' . money($r['price_2_dives']) . ' MXN / 2' : '');
    $canonical = rtrim($baseUrl, '/') . ($lang === 'es' ? '/es' : '') . '/c/' . $r['slug'];
    $ogImageUrl = $baseUrl . '/og/' . $share['type'] . '-' . $r['slug'] . '-' . $lang . '.png';
}

// Structured data. Every field is omitted rather than guessed: an invented
// address or opening hours would send divers to the wrong place, and search
// engines penalise structured data that disagrees with the page.
$business = [
    '@context' => 'https://schema.org',
    '@type'    => 'SportsActivityLocation',
    'name'     => setting('business_name'),
    'url'      => $canonical,
];

if ($description !== '') {
    $business['description'] = $description;
}
if ($ogImageUrl !== '') {
    $business['image'] = $ogImageUrl;
}
if (has_setting('phone_e164')) {
    $business['telephone'] = setting('phone_e164');
}
if (has_setting('contact_email')) {
    $business['email'] = setting('contact_email');
}
if (has_setting('price_range')) {
    $business['priceRange'] = setting('price_range');
}
if (has_setting('opening_hours')) {
    $business['openingHours'] = setting('opening_hours');
}
if (has_setting('maps_url')) {
    $business['hasMap'] = setting('maps_url');
}

$address = array_filter([
    'streetAddress'   => setting('addr_street'),
    'addressLocality' => setting('addr_locality'),
    'addressRegion'   => setting('addr_region'),
    'postalCode'      => setting('addr_postal'),
    'addressCountry'  => setting('addr_country'),
], static fn (string $v): bool => trim($v) !== '');

if (isset($address['addressLocality'])) {
    $business['address'] = ['@type' => 'PostalAddress'] + $address;
}

if (has_setting('geo_lat') && has_setting('geo_lng')) {
    $business['geo'] = [
        '@type'     => 'GeoCoordinates',
        'latitude'  => (float) setting('geo_lat'),
        'longitude' => (float) setting('geo_lng'),
    ];
}

$business['currenciesAccepted'] = 'MXN';
$business['availableLanguage']  = array_values(array_map(
    static fn (array $l): string => $l['label'],
    LANGUAGES
));

// The catalogue itself, priced in pesos.
$offers = [];
foreach ($courses as $course) {
    $offer = [
        '@type'       => 'Offer',
        'itemOffered' => ['@type' => 'Service', 'name' => $course['name_' . $lang] ?: $course['name_en']],
    ];
    if ($course['price_mxn'] !== null) {
        $offer['price']         = number_format((float) $course['price_mxn'], 2, '.', '');
        $offer['priceCurrency'] = 'MXN';
    }
    $offers[] = $offer;
}
foreach ($excursions as $excursion) {
    $lowest = array_filter([$excursion['price_1_dive'], $excursion['price_2_dives'], $excursion['price_3_dives']]);
    $offer = [
        '@type'       => 'Offer',
        'itemOffered' => ['@type' => 'Service', 'name' => $excursion['name_' . $lang] ?: $excursion['name_en']],
    ];
    if ($lowest !== []) {
        $offer['price']         = number_format((float) min($lowest), 2, '.', '');
        $offer['priceCurrency'] = 'MXN';
    }
    $offers[] = $offer;
}

if ($offers !== []) {
    $business['hasOfferCatalog'] = [
        '@type'          => 'OfferCatalog',
        'name'           => setting('business_name') . ' — ' . setting('meta_title', $lang),
        'itemListElement' => $offers,
    ];
}

$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#04263a">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php $altPath = $share !== null ? '/c/' . $share['row']['slug'] : ''; foreach (LANGUAGES as $code => $meta): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(rtrim($baseUrl, '/') . ($code === 'es' ? '/es' : '') . ($altPath ?: ($code === 'es' ? '/' : '/'))) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(rtrim($baseUrl, '/') . ($altPath ?: '/')) ?>">

<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e(setting('business_name')) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:locale" content="<?= e(LANGUAGES[$lang]['locale']) ?>">
<?php foreach (LANGUAGES as $code => $meta): if ($code !== $lang): ?>
<meta property="og:locale:alternate" content="<?= e($meta['locale']) ?>">
<?php endif; endforeach; ?>
<?php if ($ogImageUrl !== ''): ?>
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= e(setting('business_name') . ' — ' . t('visual_note_b', $lang)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($title) ?>">
<meta name="twitter:description" content="<?= e($description) ?>">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="64x64" href="/assets/brand/favicon-64.png">
<link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">
<script type="application/ld+json">
<?= json_encode($business, $jsonFlags) ?>
</script>
