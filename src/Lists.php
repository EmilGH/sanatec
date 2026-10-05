<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * The reference lists: one place for each, used by every form, validator
 * and report that needs them. Loaded with bootstrap, so always available.
 *
 *   agencies()         SCUBA certification agencies
 *   certification_levels()   certification levels, with rank and kind
 *   nationalities()    ISO 3166-1 countries, English and Spanish
 *
 * Codes are what is stored; names are what is shown. Add here, nowhere else.
 */

// ---------------------------------------------------------------------------
// Agencies
// ---------------------------------------------------------------------------

/** code => full name. The first five are the ones seen most in Tulum. */
function agencies(): array
{
    return [
        'PADI'    => 'Professional Association of Diving Instructors',
        'SSI'     => 'Scuba Schools International',
        'NAUI'    => 'National Association of Underwater Instructors',
        'TDI'     => 'Technical Diving International',
        'SDI'     => 'Scuba Diving International',
        'BSAC'    => 'British Sub-Aqua Club',
        'CMAS'    => 'Confédération Mondiale des Activités Subaquatiques',
        'DAN'     => 'Divers Alert Network (first aid and oxygen training)',
        'GUE'     => 'Global Underwater Explorers',
        'IANTD'   => 'International Association of Nitrox and Technical Divers',
        'NACD'    => 'National Association for Cave Diving',
        'NSS-CDS' => 'National Speleological Society – Cave Diving Section',
        'PSAI'    => 'Professional Scuba Association International',
        'RAID'    => 'Rebreather Association of International Divers',
        'OTHER'   => 'Other',
    ];
}

/** A valid agency code, upper-cased, or null. */
function agency_code(?string $raw): ?string
{
    $code = strtoupper(trim((string) $raw));

    return isset(agencies()[$code]) ? $code : null;
}

// ---------------------------------------------------------------------------
// Certifications
// ---------------------------------------------------------------------------

/**
 * code => [name_en, name_es, rank, kind]. Rank orders prerequisites: a
 * cenote asking for rank 2 is satisfied by any card of rank 2 or more of
 * the same ladder; specialties rank 0 and never satisfy a requirement.
 * Kind groups the list in menus: recreational, specialty, technical, professional.
 */
function certification_levels(): array
{
    return [
        'scuba_diver' => ['Scuba Diver',          'Scuba Diver',               1, 'recreational'],
        'ow'          => ['Open Water',           'Open Water',                1, 'recreational'],
        'aow'         => ['Advanced Open Water',  'Advanced Open Water',       2, 'recreational'],
        'rescue'      => ['Rescue Diver',         'Rescue Diver',              3, 'recreational'],
        'msd'         => ['Master Scuba Diver',   'Master Scuba Diver',        3, 'recreational'],
        'nitrox'      => ['Enriched Air (Nitrox)', 'Aire enriquecido (Nitrox)', 0, 'specialty'],
        'deep'        => ['Deep Diver',           'Buceo profundo',            0, 'specialty'],
        'night'       => ['Night Diver',          'Buceo nocturno',            0, 'specialty'],
        'wreck'       => ['Wreck Diver',          'Buceo en pecios',           0, 'specialty'],
        'drysuit'     => ['Dry Suit Diver',       'Traje seco',                0, 'specialty'],
        'sidemount'   => ['Sidemount',            'Sidemount',                 2, 'specialty'],
        'efr'         => ['EFR / CPR & First Aid', 'EFR / RCP y primeros auxilios', 0, 'safety'],
        'o2'          => ['Emergency Oxygen Provider', 'Proveedor de oxígeno de emergencia', 0, 'safety'],
        'cavern'      => ['Cavern',               'Caverna',                   3, 'technical'],
        'intro_cave'  => ['Intro to Cave',        'Introducción a cueva',      4, 'technical'],
        'full_cave'   => ['Full Cave',            'Cueva completa',            5, 'technical'],
        'tec_deco'    => ['Technical / Decompression', 'Técnico / descompresión', 4, 'technical'],
        'trimix'      => ['Trimix',               'Trimix',                    5, 'technical'],
        'ccr'         => ['Rebreather (CCR)',     'Rebreather (CCR)',          4, 'technical'],
        'dm'          => ['Divemaster',           'Divemaster',                4, 'professional'],
        'ai'          => ['Assistant Instructor', 'Instructor asistente',      4, 'professional'],
        'instructor'  => ['Instructor',           'Instructor',                5, 'professional'],
        'specialty_instructor' => ['Specialty Instructor', 'Instructor de especialidad', 5, 'professional'],
        'msdt'        => ['Master Scuba Diver Trainer', 'Master Scuba Diver Trainer', 5, 'professional'],
        'idc_staff'   => ['IDC Staff Instructor', 'IDC Staff Instructor',      5, 'professional'],
        'course_director' => ['Course Director',  'Course Director',           5, 'professional'],
        'cavern_instructor' => ['Cavern Instructor', 'Instructor de caverna',  5, 'professional'],
        'cave_instructor' => ['Cave Instructor',  'Instructor de cueva',       5, 'professional'],
        'tec_instructor' => ['Technical Instructor', 'Instructor técnico',     5, 'professional'],
        'efr_instructor' => ['EFR Instructor',    'Instructor EFR',            5, 'professional'],
        'other'       => ['Other',                'Otra',                      0, 'other'],
    ];
}

const CERTIFICATION_KINDS = [
    'recreational' => ['Recreational', 'Recreativo'],
    'specialty'    => ['Specialties',  'Especialidades'],
    'safety'       => ['First aid & safety', 'Primeros auxilios y seguridad'],
    'technical'    => ['Technical',    'Técnico'],
    'professional' => ['Professional', 'Profesional'],
    'other'        => ['Other',        'Otra'],
];

function certification_name(?string $code, string $lang = 'en'): string
{
    $c = certification_levels()[(string) $code] ?? null;

    return $c === null ? (string) $code : $c[$lang === 'es' ? 1 : 0];
}

function certification_rank(?string $code): int
{
    return (int) (certification_levels()[(string) $code][2] ?? 0);
}

// Older code reads these constants; they are views of the lists above.
define('CERT_AGENCIES', array_keys(agencies()));
define('CREDENTIAL_AGENCIES', array_keys(agencies()));
define('CERT_LEVELS', array_map(static fn (array $c): array => [$c[0], $c[2]], certification_levels()));

// ---------------------------------------------------------------------------
// Nationalities
// ---------------------------------------------------------------------------

const NATIONALITIES_FIRST = ['MX', 'CA', 'GB', 'US'];

const NATIONALITIES_RAW = <<<'TXT'
AF|Afghanistan|Afganistán
AL|Albania|Albania
DZ|Algeria|Argelia
AD|Andorra|Andorra
AO|Angola|Angola
AG|Antigua and Barbuda|Antigua y Barbuda
AR|Argentina|Argentina
AM|Armenia|Armenia
AU|Australia|Australia
AT|Austria|Austria
AZ|Azerbaijan|Azerbaiyán
BS|Bahamas|Bahamas
BH|Bahrain|Baréin
BD|Bangladesh|Bangladés
BB|Barbados|Barbados
BY|Belarus|Bielorrusia
BE|Belgium|Bélgica
BZ|Belize|Belice
BJ|Benin|Benín
BT|Bhutan|Bután
BO|Bolivia|Bolivia
BA|Bosnia and Herzegovina|Bosnia y Herzegovina
BW|Botswana|Botsuana
BR|Brazil|Brasil
BN|Brunei|Brunéi
BG|Bulgaria|Bulgaria
BF|Burkina Faso|Burkina Faso
BI|Burundi|Burundi
CV|Cabo Verde|Cabo Verde
KH|Cambodia|Camboya
CM|Cameroon|Camerún
CA|Canada|Canadá
CF|Central African Republic|República Centroafricana
TD|Chad|Chad
CL|Chile|Chile
CN|China|China
CO|Colombia|Colombia
KM|Comoros|Comoras
CG|Congo|Congo
CD|Congo (Democratic Republic)|Congo (República Democrática)
CR|Costa Rica|Costa Rica
CI|Côte d'Ivoire|Costa de Marfil
HR|Croatia|Croacia
CU|Cuba|Cuba
CY|Cyprus|Chipre
CZ|Czechia|Chequia
DK|Denmark|Dinamarca
DJ|Djibouti|Yibuti
DM|Dominica|Dominica
DO|Dominican Republic|República Dominicana
EC|Ecuador|Ecuador
EG|Egypt|Egipto
SV|El Salvador|El Salvador
GQ|Equatorial Guinea|Guinea Ecuatorial
ER|Eritrea|Eritrea
EE|Estonia|Estonia
SZ|Eswatini|Esuatini
ET|Ethiopia|Etiopía
FJ|Fiji|Fiyi
FI|Finland|Finlandia
FR|France|Francia
GA|Gabon|Gabón
GM|Gambia|Gambia
GE|Georgia|Georgia
DE|Germany|Alemania
GH|Ghana|Ghana
GR|Greece|Grecia
GD|Grenada|Granada
GT|Guatemala|Guatemala
GN|Guinea|Guinea
GW|Guinea-Bissau|Guinea-Bisáu
GY|Guyana|Guyana
HT|Haiti|Haití
HN|Honduras|Honduras
HK|Hong Kong|Hong Kong
HU|Hungary|Hungría
IS|Iceland|Islandia
IN|India|India
ID|Indonesia|Indonesia
IR|Iran|Irán
IQ|Iraq|Irak
IE|Ireland|Irlanda
IL|Israel|Israel
IT|Italy|Italia
JM|Jamaica|Jamaica
JP|Japan|Japón
JO|Jordan|Jordania
KZ|Kazakhstan|Kazajistán
KE|Kenya|Kenia
KI|Kiribati|Kiribati
KW|Kuwait|Kuwait
KG|Kyrgyzstan|Kirguistán
LA|Laos|Laos
LV|Latvia|Letonia
LB|Lebanon|Líbano
LS|Lesotho|Lesoto
LR|Liberia|Liberia
LY|Libya|Libia
LI|Liechtenstein|Liechtenstein
LT|Lithuania|Lituania
LU|Luxembourg|Luxemburgo
MG|Madagascar|Madagascar
MW|Malawi|Malaui
MY|Malaysia|Malasia
MV|Maldives|Maldivas
ML|Mali|Malí
MT|Malta|Malta
MH|Marshall Islands|Islas Marshall
MR|Mauritania|Mauritania
MU|Mauritius|Mauricio
MX|Mexico|México
FM|Micronesia|Micronesia
MD|Moldova|Moldavia
MC|Monaco|Mónaco
MN|Mongolia|Mongolia
ME|Montenegro|Montenegro
MA|Morocco|Marruecos
MZ|Mozambique|Mozambique
MM|Myanmar|Myanmar
NA|Namibia|Namibia
NR|Nauru|Nauru
NP|Nepal|Nepal
NL|Netherlands|Países Bajos
NZ|New Zealand|Nueva Zelanda
NI|Nicaragua|Nicaragua
NE|Niger|Níger
NG|Nigeria|Nigeria
KP|North Korea|Corea del Norte
MK|North Macedonia|Macedonia del Norte
NO|Norway|Noruega
OM|Oman|Omán
PK|Pakistan|Pakistán
PW|Palau|Palaos
PS|Palestine|Palestina
PA|Panama|Panamá
PG|Papua New Guinea|Papúa Nueva Guinea
PY|Paraguay|Paraguay
PE|Peru|Perú
PH|Philippines|Filipinas
PL|Poland|Polonia
PT|Portugal|Portugal
PR|Puerto Rico|Puerto Rico
QA|Qatar|Catar
RO|Romania|Rumanía
RU|Russia|Rusia
RW|Rwanda|Ruanda
KN|Saint Kitts and Nevis|San Cristóbal y Nieves
LC|Saint Lucia|Santa Lucía
VC|Saint Vincent and the Grenadines|San Vicente y las Granadinas
WS|Samoa|Samoa
SM|San Marino|San Marino
ST|Sao Tome and Principe|Santo Tomé y Príncipe
SA|Saudi Arabia|Arabia Saudita
SN|Senegal|Senegal
RS|Serbia|Serbia
SC|Seychelles|Seychelles
SL|Sierra Leone|Sierra Leona
SG|Singapore|Singapur
SK|Slovakia|Eslovaquia
SI|Slovenia|Eslovenia
SB|Solomon Islands|Islas Salomón
SO|Somalia|Somalia
ZA|South Africa|Sudáfrica
KR|South Korea|Corea del Sur
SS|South Sudan|Sudán del Sur
ES|Spain|España
LK|Sri Lanka|Sri Lanka
SD|Sudan|Sudán
SR|Suriname|Surinam
SE|Sweden|Suecia
CH|Switzerland|Suiza
SY|Syria|Siria
TW|Taiwan|Taiwán
TJ|Tajikistan|Tayikistán
TZ|Tanzania|Tanzania
TH|Thailand|Tailandia
TL|Timor-Leste|Timor Oriental
TG|Togo|Togo
TO|Tonga|Tonga
TT|Trinidad and Tobago|Trinidad y Tobago
TN|Tunisia|Túnez
TR|Türkiye|Turquía
TM|Turkmenistan|Turkmenistán
TV|Tuvalu|Tuvalu
UG|Uganda|Uganda
UA|Ukraine|Ucrania
AE|United Arab Emirates|Emiratos Árabes Unidos
GB|United Kingdom|Reino Unido
US|United States of America|Estados Unidos de América
UY|Uruguay|Uruguay
UZ|Uzbekistan|Uzbekistán
VU|Vanuatu|Vanuatu
VA|Vatican City|Ciudad del Vaticano
VE|Venezuela|Venezuela
VN|Vietnam|Vietnam
YE|Yemen|Yemen
ZM|Zambia|Zambia
ZW|Zimbabwe|Zimbabue
TXT;

/** code => [en, es] */
function nationalities(): array
{
    static $list = null;
    if ($list === null) {
        $list = [];
        foreach (explode("\n", trim(NATIONALITIES_RAW)) as $line) {
            [$code, $en, $es] = explode('|', $line);
            $list[$code] = [$en, $es];
        }
    }

    return $list;
}

function nationality_name(?string $code, string $lang = 'en'): string
{
    $c = nationalities()[strtoupper((string) $code)] ?? null;

    return $c === null ? (string) $code : $c[$lang === 'es' ? 1 : 0];
}

/** A valid two-letter code, upper-cased, or null. */
function nationality_code(?string $raw): ?string
{
    $code = strtoupper(trim((string) $raw));

    return isset(nationalities()[$code]) ? $code : null;
}

/** [[code, name], ...] twice: the usual visitors first, then everyone sorted in the page's language. */
function nationalities_ordered(string $lang = 'en'): array
{
    $i = $lang === 'es' ? 1 : 0;
    $all = nationalities();
    $first = array_map(static fn (string $c): array => [$c, $all[$c][$i]], NATIONALITIES_FIRST);
    $rest = [];
    foreach ($all as $code => $names) {
        if (!in_array($code, NATIONALITIES_FIRST, true)) {
            $rest[] = [$code, $names[$i]];
        }
    }
    $collator = class_exists('Collator') ? new Collator($lang === 'es' ? 'es_ES' : 'en_US') : null;
    usort($rest, static fn (array $a, array $b): int => $collator ? $collator->compare($a[1], $b[1]) : strcmp(ascii_fold($a[1]), ascii_fold($b[1])));

    return [$first, $rest];
}

// The names used while the list lived in Countries.php.
function countries(): array { return nationalities(); }
function country_name(?string $code, string $lang = 'en'): string { return nationality_name($code, $lang); }
function countries_ordered(string $lang = 'en'): array { return nationalities_ordered($lang); }
