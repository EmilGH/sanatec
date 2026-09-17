<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * Interface strings.
 *
 * These are the parts of the page the shop owner does not edit: column
 * headings, buttons, navigation. Everything he does edit lives in settings.
 *
 * Adding a language means adding a column to this table and to the catalogue
 * tables. The keys stay the same.
 */

const LANGUAGES = [
    'en' => ['label' => 'English', 'locale' => 'en_US', 'path' => '/'],
    'es' => ['label' => 'Español', 'locale' => 'es_MX', 'path' => '/es/'],
];

function ui_strings(): array
{
    return [
        'skip_to_content'  => ['en' => 'Skip to content',            'es' => 'Saltar al contenido'],
        'nav_training'     => ['en' => 'Training',                   'es' => 'Cursos'],
        'nav_adventures'   => ['en' => 'Adventures',                 'es' => 'Aventuras'],
        'nav_included'     => ['en' => 'What is included',           'es' => 'Qué incluye'],
        'nav_contact'      => ['en' => 'Contact',                    'es' => 'Contacto'],
        'nav_label'        => ['en' => 'Main navigation',            'es' => 'Navegación principal'],
        'lang_label'       => ['en' => 'Language',                   'es' => 'Idioma'],

        'cta_whatsapp_long' => ['en' => 'Chat on WhatsApp',          'es' => 'Escríbenos por WhatsApp'],
        'cta_whatsapp'      => ['en' => 'WhatsApp',                  'es' => 'WhatsApp'],
        'cta_sms_long'      => ['en' => 'Send an SMS',               'es' => 'Enviar un SMS'],
        'cta_sms'           => ['en' => 'SMS',                       'es' => 'SMS'],
        'cta_label'         => ['en' => 'Contact SanaTec Diving',    'es' => 'Contactar a SanaTec Diving'],
        'wa_prefill'        => ['en' => 'Hello! I would like to ask about diving with SanaTec.',
                                'es' => '¡Hola! Quisiera preguntar sobre bucear con SanaTec.'],

        'th_course'        => ['en' => 'Course',                     'es' => 'Curso'],
        'th_price_mxn'     => ['en' => 'Price (MXN)',                'es' => 'Precio (MXN)'],
        'th_duration'      => ['en' => 'Duration',                   'es' => 'Duración'],
        'th_route'         => ['en' => 'Cenote / route',             'es' => 'Cenote / ruta'],
        'th_1_dive'        => ['en' => '1 dive',                     'es' => '1 buceo'],
        'th_2_dives'       => ['en' => '2 dives',                    'es' => '2 buceos'],
        'th_3_dives'       => ['en' => '3 dives',                    'es' => '3 buceos'],
        'th_certification' => ['en' => 'Certification',              'es' => 'Certificación'],

        'ask_for_pricing'  => ['en' => 'Ask for pricing',            'es' => 'Consultar precio'],
        'scroll_hint'      => ['en' => 'Swipe the table to see all prices and certification requirements →',
                               'es' => 'Desliza la tabla para ver todos los precios y certificaciones →'],
        'table_region'     => ['en' => 'Cenote dive prices, scroll horizontally on small screens',
                               'es' => 'Precios de buceo en cenotes, desliza horizontalmente en pantallas pequeñas'],

        'source_training'  => ['en' => 'View original course guide', 'es' => 'Ver la guía original de cursos'],
        'source_adventure' => ['en' => 'View original dive guide',   'es' => 'Ver la guía original de buceos'],

        'visual_note_a'    => ['en' => 'Discover what is below.',    'es' => 'Descubre lo que hay abajo.'],
        'visual_note_b'    => ['en' => 'Dive with experts.',         'es' => 'Bucea con expertos.'],

        'find_us'          => ['en' => 'Find us',                    'es' => 'Dónde estamos'],
        'opening_hours'    => ['en' => 'Opening hours',              'es' => 'Horario'],
        'directions'       => ['en' => 'Get directions',             'es' => 'Cómo llegar'],

        'hero_image_alt'   => ['en' => 'SanaTec Diving — sunlight streaming into a blue cenote cavern',
                               'es' => 'SanaTec Diving — luz del sol entrando en una caverna azul de cenote'],
    ];
}

/** Translate an interface key. Falls back to English, then to the key itself. */
function t(string $key, string $lang = 'en'): string
{
    static $strings = null;
    $strings ??= ui_strings();

    return $strings[$key][$lang] ?? $strings[$key]['en'] ?? $key;
}

/** Normalise anything to a language we actually serve. */
function normalize_lang(?string $lang): string
{
    return isset(LANGUAGES[$lang]) ? $lang : 'en';
}

/** Absolute URL for a language's copy of the page, for canonical and hreflang. */
function lang_url(string $lang, string $baseUrl): string
{
    return rtrim($baseUrl, '/') . LANGUAGES[normalize_lang($lang)]['path'];
}
