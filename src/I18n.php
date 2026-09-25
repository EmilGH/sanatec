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

        'ask_for_pricing'  => ['en' => 'Ask us',                     'es' => 'Pregúntanos'],
        'hero_kicker'      => ['en' => 'Tulum · Cenotes · PADI & TDI', 'es' => 'Tulum · Cenotes · PADI y TDI'],
        'per_diver_mxn'    => ['en' => 'Per diver, in MXN. Each line is the total for that number of dives. Tap one to book it on WhatsApp.',
                               'es' => 'Por buzo, en MXN. Cada línea es el total por ese número de inmersiones. Toca una para reservar por WhatsApp.'],
        'courses_mxn'      => ['en' => 'Course prices in Mexican pesos (MXN). Tap a course to ask about dates.',
                               'es' => 'Precios de cursos en pesos mexicanos (MXN). Toca un curso para preguntar por fechas.'],
        'dive_n'           => ['en' => '{n} dive',                    'es' => '{n} inmersión'],
        'dives_n'          => ['en' => '{n} dives',                   'es' => '{n} inmersiones'],
        'special_price'    => ['en' => 'special price',               'es' => 'precio especial'],
        'wa_line_excursion' => ['en' => "Hi! I'd like {qty} at {name}. Dates: ", 'es' => '¡Hola! Quiero {qty} en {name}. Fechas: '],
        'wa_line_course'   => ['en' => "Hi! I'm interested in the {name} ({duration}). Dates: ", 'es' => '¡Hola! Me interesa el {name} ({duration}). Fechas: '],
        'contact_whatsapp' => ['en' => 'WhatsApp',                    'es' => 'WhatsApp'],
        'contact_fastest'  => ['en' => 'Fastest reply',               'es' => 'Respuesta más rápida'],
        'contact_sms'      => ['en' => 'Text message',                'es' => 'Mensaje de texto'],
        'contact_email'    => ['en' => 'Email',                       'es' => 'Correo'],
        'open_in_maps'     => ['en' => 'Open in Maps',                'es' => 'Abrir en Maps'],
        'privacy'          => ['en' => 'Privacy',                     'es' => 'Privacidad'],
        'share_intro'      => ['en' => 'Shared with you:',            'es' => 'Compartido contigo:'],
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

        'team_kicker'      => ['en' => 'The people',                 'es' => 'Las personas'],
        'team_title'       => ['en' => 'Meet the team',              'es' => 'Conoce al equipo'],
        'team_intro'       => ['en' => 'The guides and instructors who will be in the water with you.',
                               'es' => 'Los guías e instructores que estarán en el agua contigo.'],
        'team_link'        => ['en' => 'Meet the team',              'es' => 'Conoce al equipo'],
        'team_all'         => ['en' => 'The whole team',             'es' => 'Todo el equipo'],
        'team_speaks'      => ['en' => 'Speaks',                     'es' => 'Habla'],
        'team_message'     => ['en' => 'Message on WhatsApp',        'es' => 'Escribir por WhatsApp'],
        'team_wa_prefill'  => ['en' => 'Hi! I found you on the SanaTec Diving site. ',
                               'es' => '¡Hola! Te encontré en el sitio de SanaTec Diving. '],
        'team_none'        => ['en' => 'Team profiles are on their way.',
                               'es' => 'Los perfiles del equipo están en camino.'],
        'team_notfound'    => ['en' => 'That profile is not available.',
                               'es' => 'Ese perfil no está disponible.'],
        'role_instructor'  => ['en' => 'Instructor',                 'es' => 'Instructor'],
        'role_divemaster'  => ['en' => 'Divemaster',                 'es' => 'Divemaster'],
        'role_cave_guide'  => ['en' => 'Cave guide',                 'es' => 'Guía de cueva'],

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
