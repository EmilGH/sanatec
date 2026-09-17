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

$ogImage = setting('og_image');
$ogImageUrl = $ogImage !== '' ? $baseUrl . '/' . ltrim($ogImage, '/') : '';

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
foreach ($routes as $route) {
    $lowest = array_filter([$route['price_1_dive'], $route['price_2_dives'], $route['price_3_dives']]);
    $offer = [
        '@type'       => 'Offer',
        'itemOffered' => ['@type' => 'Service', 'name' => $route['name_' . $lang] ?: $route['name_en']],
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
<meta name="theme-color" content="#061e27">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach (LANGUAGES as $code => $meta): ?>
<link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(lang_url($code, $baseUrl)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= e(lang_url('en', $baseUrl)) ?>">

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

<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23061e27'/%3E%3Ctext x='16' y='24' text-anchor='middle' font-family='Arial' font-size='25' fill='%233ed9df'%3ES%3C/text%3E%3C/svg%3E">
<script type="application/ld+json">
<?= json_encode($business, $jsonFlags) ?>
</script>
