<?php

declare(strict_types=1);

if (!defined('SANATEC')) {
    http_response_code(404);
    exit;
}

/**
 * ISO 3166-1 countries, in English and Spanish, for the nationality field.
 * The shop's usual visitors come first in the list; the rest follow sorted
 * by name in the language of the page.
 */

const COUNTRIES_FIRST = ['MX', 'CA', 'GB', 'US'];

const COUNTRIES_RAW = <<<'TXT'
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
function countries(): array
{
    static $list = null;
    if ($list === null) {
        $list = [];
        foreach (explode("\n", trim(COUNTRIES_RAW)) as $line) {
            [$code, $en, $es] = explode('|', $line);
            $list[$code] = [$en, $es];
        }
    }

    return $list;
}

function country_name(?string $code, string $lang = 'en'): string
{
    $c = countries()[strtoupper((string) $code)] ?? null;

    return $c === null ? (string) $code : $c[$lang === 'es' ? 1 : 0];
}

/** [['code','name'], ...] — the usual visitors first, then everyone sorted in the page's language. */
function countries_ordered(string $lang = 'en'): array
{
    $i = $lang === 'es' ? 1 : 0;
    $all = countries();
    $first = array_map(static fn (string $c): array => [$c, $all[$c][$i]], COUNTRIES_FIRST);
    $rest = [];
    foreach ($all as $code => $names) {
        if (!in_array($code, COUNTRIES_FIRST, true)) {
            $rest[] = [$code, $names[$i]];
        }
    }
    $collator = class_exists('Collator') ? new Collator($lang === 'es' ? 'es_ES' : 'en_US') : null;
    usort($rest, static fn (array $a, array $b): int => $collator ? $collator->compare($a[1], $b[1]) : strcmp(ascii_fold($a[1]), ascii_fold($b[1])));

    return [$first, $rest];
}
