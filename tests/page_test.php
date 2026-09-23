<?php

declare(strict_types=1);

/**
 * Render the real page the way a visitor gets it.
 *
 * These are the tests that catch a template breaking, because they exercise
 * index.php, head.php, styles.php and public.php together rather than a
 * function in isolation.
 */
function render_page(string $lang): string
{
    $_GET = ['lang' => $lang];
    $_SERVER['REQUEST_URI'] = $lang === 'es' ? '/es/' : '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    unset($_SERVER['HTTP_IF_NONE_MATCH']);

    settings_cache_clear();

    ob_start();
    require SANATEC_ROOT . '/index.php';

    return (string) ob_get_clean();
}

/** The JSON-LD block, decoded. */
function page_structured_data(string $html): array
{
    if (preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m) !== 1) {
        fail('no JSON-LD block in the page');
    }

    $data = json_decode(trim($m[1]), true);
    if (!is_array($data)) {
        fail('JSON-LD did not parse: ' . json_last_error_msg());
    }

    return $data;
}

test('the English page renders', function (): void {
    $html = render_page('en');

    has('<html lang="en">', $html);
    has('Dive training', $html);
    has('Adventure dives', $html);
    has('Open Water Course', $html);
    has('$10,000', $html, 'course prices come from the database');
    has('Ask for pricing', $html, 'Divemaster has no price');
    has('2 dives', $html, 'the cumulative-price column heading');
});

test('the Spanish page renders', function (): void {
    $html = render_page('es');

    has('<html lang="es">', $html);
    has('Formación de buceo', $html);
    has('Buceos recreativos', $html);
    has('2 buceos', $html);
    has('Introducción a Cueva', $html, 'accented Spanish survives intact');
    has_not('Dive training', $html, 'no English leaking into the Spanish page');
});

test('no PHP diagnostics reach the visitor', function (): void {
    foreach (['en', 'es'] as $lang) {
        $html = render_page($lang);
        foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error', 'Undefined'] as $bad) {
            has_not($bad, $html, "PHP '{$bad}' rendered into the {$lang} page");
        }
    }
});

test('a course name is escaped, not executed', function (): void {
    $id = course_save([
        'name_en' => '<script>alert("xss")</script>',
        'name_es' => '', 'price_mxn' => '100', 'is_published' => true,
    ]);

    $html = render_page('en');
    has_not('<script>alert("xss")</script>', $html, 'a name must never render as live markup');
    has('&lt;script&gt;', $html, 'it should appear escaped instead');

    catalog_delete('courses', $id);
});

test('an unpublished row never reaches the page', function (): void {
    $id = route_save(['name_en' => 'Secret Cenote', 'name_es' => '', 'is_published' => false]);

    has_not('Secret Cenote', render_page('en'));
    has_not('Secret Cenote', render_page('es'));

    catalog_delete('routes', $id);
});

test('structured data is valid and priced in pesos', function (): void {
    $data = page_structured_data(render_page('en'));

    is_same('SportsActivityLocation', $data['@type']);
    is_same('MXN', $data['currenciesAccepted']);

    $offers = $data['hasOfferCatalog']['itemListElement'];
    is_same(21, count($offers), 'ten seeded courses plus eleven seeded routes');

    foreach ($offers as $offer) {
        if (isset($offer['price'])) {
            is_same('MXN', $offer['priceCurrency'], 'every priced offer must state the currency');
        }
    }
});

test('an address that is not known is not invented', function (): void {
    $data = page_structured_data(render_page('en'));

    is_false(isset($data['geo']), 'no coordinates are seeded, so none must be published');
    is_false(isset($data['openingHours']), 'no hours are seeded, so none must be published');
    has_not('class="location"', render_page('en'), 'the location block stays hidden while empty');
});

test('an address that is known is published', function (): void {
    settings_save([
        'addr_locality' => ['en' => 'Tulum'],
        'opening_hours' => ['en' => 'Mo-Su 07:00-19:00'],
    ]);

    $html = render_page('en');
    $data = page_structured_data($html);

    is_same('Tulum', $data['address']['addressLocality']);
    is_same('Mo-Su 07:00-19:00', $data['openingHours']);
    has('class="location"', $html);

    settings_save(['addr_locality' => ['en' => ''], 'opening_hours' => ['en' => '']]);
});

test('both languages point at each other', function (): void {
    $en = render_page('en');
    has('<link rel="canonical" href="https://sanatecdiving.com/">', $en);
    has('hreflang="es" href="https://sanatecdiving.com/es/"', $en);
    has('hreflang="x-default"', $en);

    $es = render_page('es');
    has('<link rel="canonical" href="https://sanatecdiving.com/es/">', $es);
    has('hreflang="en" href="https://sanatecdiving.com/"', $es);
});

test('the link preview card is complete', function (): void {
    $html = render_page('en');

    has('property="og:image"', $html);
    has('og-image.jpg', $html);
    has('property="og:title"', $html);
    has('name="twitter:card" content="summary_large_image"', $html);
    has('property="og:locale" content="en_US"', $html);
});

test('the contact number comes from one place', function (): void {
    settings_save(['phone_e164' => ['en' => '+521111111111'], 'whatsapp_number' => ['en' => '521111111111']]);

    $html = render_page('en');
    has('wa.me/521111111111', $html);
    has('tel:+521111111111', $html);
    has_not('9841063306', $html, 'no hard-coded number may survive in a template');

    settings_save(['phone_e164' => ['en' => '+529841063306'], 'whatsapp_number' => ['en' => '529841063306']]);
});
