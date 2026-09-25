-- SanaTec Diving — initial catalogue and site content.
--
-- INSERT IGNORE throughout: re-running this file never overwrites an edit the
-- shop owner has made in the admin. It only fills in what is missing.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Courses (from assets/training.jpeg)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO courses (id, slug, name_en, name_es, price_mxn, duration_en, duration_es, sort_order) VALUES
 (1, 'open-water-course', 'Open Water Course',                        'Curso Open Water',                                10000.00, '3 days',           '3 días',            10),
 (2, 'advanced-open-water', 'Advanced Open Water',                       'Curso Advanced Open Water',                        9500.00, '2 days',           '2 días',            20),
 (3, 'rescue-efr', 'Rescue & EFR',                              'Curso Rescue y EFR',                              10200.00, '3 days',           '3 días',            30),
 (4, 'divemaster-padi', 'Divemaster (PADI)',                         'Divemaster (PADI)',                                   NULL, '2, 3 or 4 weeks',  '2, 3 o 4 semanas',  40),
 (5, 'sidemount-padi', 'Sidemount (PADI)',                          'Sidemount (PADI)',                                10000.00, '2 days',           '2 días',            50),
 (6, 'sidemount-tdi', 'Sidemount TDI',                             'Sidemount TDI',                                   15000.00, '3 days',           '3 días',            60),
 (7, 'tdi-sidemount-cavern', 'TDI Sidemount + Cavern',                    'TDI Sidemount + Caverna',                         30000.00, '5 days',           '5 días',            70),
 (8, 'tdi-sidemount-cavern-intro-to-cave', 'TDI Sidemount + Cavern + Intro to Cave',    'TDI Sidemount + Caverna + Introducción a Cueva',  50000.00, '10 days',          '10 días',           80),
 (9, 'tdi-cavern', 'TDI Cavern',                                'TDI Caverna',                                     15000.00, '3 days',           '3 días',            90),
 (10, 'tdi-intro-to-cave', 'TDI Intro to Cave',                         'TDI Introducción a Cueva',                        20000.00, '4 days',           '4 días',           100);

-- ---------------------------------------------------------------------------
-- Cenote excursions (from assets/adventures.jpeg)
--
-- The source guide stacks multi-cenote excursions on two lines with no separator
-- ("ANGELITA / CARWASH"). They are seeded here with "+", matching the guide's
-- own "PIT + 2 OJOS" style, because the price columns only make sense that way:
-- Angelita + Carwash is a two-dive day at $3,900, and adding Casa makes it a
-- three-dive day at $4,500. Reading the slash as "either/or" would leave the
-- three-dive column unexplained.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO excursions (id, slug, name_en, name_es, price_1_dive, price_2_dives, price_3_dives, cert_en, cert_es, is_special_price, sort_order) VALUES
 (1, 'angelita-carwash', 'Angelita + Carwash',            'Angelita + Carwash',            2400.00, 3900.00,    NULL, 'AOW',                          'AOW',                           0,  10),
 (2, 'angelita-carwash-casa', 'Angelita + Carwash + Casa',     'Angelita + Carwash + Casa',     2400.00, 3900.00, 4500.00, 'AOW',                          'AOW',                           0,  20),
 (3, 'pit-dos-ojos', 'Pit + Dos Ojos',                'Pit + Dos Ojos',                2400.00, 3900.00, 4300.00, 'AOW',                          'AOW',                           0,  30),
 (4, 'pit-dos-ojos-nic-te-ha', 'Pit + Dos Ojos + Nic Te-Ha',    'Pit + Dos Ojos + Nic Te-Ha',    2400.00, 3900.00, 4500.00, 'AOW',                          'AOW',                           0,  40),
 (5, 'dreamgate', 'Dreamgate',                     'Dreamgate',                        NULL, 3500.00,    NULL, 'Open Water + pre-dive check',  'Open Water + chequeo previo',   0,  50),
 (6, 'dos-ojos', 'Dos Ojos',                      'Dos Ojos',                         NULL, 3500.00,    NULL, 'OW',                           'OW',                            0,  60),
 (7, 'ponderosa-chikin-ha', 'Ponderosa + Chikin Ha',         'Ponderosa + Chikin Ha',            NULL, 4300.00,    NULL, 'OW',                           'OW',                            0,  70),
 (8, 'chikin-ha', 'Chikin Ha',                     'Chikin Ha',                        NULL, 4000.00,    NULL, 'OW',                           'OW',                            0,  80),
 (9, 'yaa-kun', 'Yaa Kun',                       'Yaa Kun',                          NULL, 4200.00,    NULL, 'AOW',                          'AOW',                           1,  90),
 (10, 'casa-carwash', 'Casa + Carwash',                'Casa + Carwash',                   NULL, 3900.00,    NULL, 'OW',                           'OW',                            0, 100),
 (11, 'dos-ojos-carwash', 'Dos Ojos + Carwash',            'Dos Ojos + Carwash',               NULL, 3900.00, 4300.00, 'OW',                           'OW',                            0, 110);

-- ---------------------------------------------------------------------------
-- Site content. Keys are declared in src/Settings.php, which owns the labels,
-- help text and field types shown in the admin. This file only supplies values.
--
-- Location, hours and geo are deliberately seeded EMPTY. The public page hides
-- the location block and omits the corresponding structured-data fields until
-- they are filled in, so the site never publishes an address it was guessing at.
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO settings (skey, val_en, val_es) VALUES
 ('business_name',        'SanaTec Diving', NULL),
 ('phone_e164',           '+529841063306', NULL),
 ('phone_display',        '+52 984 106 3306', NULL),
 ('whatsapp_number',      '529841063306', NULL),
 ('contact_email',        '', NULL),

 -- Location — fill these in before the structured data can do any work.
 ('addr_street',          '', NULL),
 ('addr_locality',        '', NULL),
 ('addr_region',          'Quintana Roo', NULL),
 ('addr_postal',          '', NULL),
 ('addr_country',         'MX', NULL),
 ('geo_lat',              '', NULL),
 ('geo_lng',              '', NULL),
 ('maps_url',             '', NULL),
 ('opening_hours',        '', NULL),
 ('price_range',          '$$', NULL),

 -- Hero
 ('hero_eyebrow',         'Training · Cenotes · Adventure',  'Formación · Cenotes · Aventura'),
 ('hero_title_a',         'Your next dive.',                 'Tu próxima inmersión.'),
 ('hero_title_b',         'A new perspective.',              'Una nueva perspectiva.'),
 ('hero_intro',           'From your first Open Water course to technical training and cenote adventures. Find your next step with SanaTec Diving.',
                          'Desde tu primer curso Open Water hasta formación técnica y aventuras en cenotes. Encuentra tu siguiente paso con SanaTec Diving.'),

 -- Training section
 ('training_eyebrow',     '01 / Build your skills',          '01 / Desarrolla tus habilidades'),
 ('training_title',       'Dive training',                   'Formación de buceo'),
 ('training_caption',     'Course prices in Mexican pesos (MXN)', 'Precios de cursos en pesos mexicanos (MXN)'),
 ('training_note',        'Not sure where to start? Message us about course prerequisites and availability.',
                          '¿No sabes por dónde empezar? Escríbenos sobre requisitos y disponibilidad.'),

 -- Adventures section
 ('adventures_eyebrow',   '02 / Explore the cenotes',        '02 / Explora los cenotes'),
 ('adventures_title',     'Adventure dives',                 'Buceos recreativos'),
 ('adventures_caption',   'Prices in MXN · each column is the total price for that number of dives',
                          'Precios en MXN · cada columna es el precio total por ese número de inmersiones'),
 ('adventures_legend',    'OW = Open Water · AOW = Advanced Open Water · — = not offered as a trip of that length',
                          'OW = Open Water · AOW = Advanced Open Water · — = no se ofrece con ese número de inmersiones'),
 ('adventures_footnote',  '* Special price. Confirm your dive combination, the final price and what is included when you book.',
                          '* Precio especial. Confirma tu combinación de buceos, el precio final y qué incluye al reservar.'),

 -- What is included / not included.
 -- Seeded as a DRAFT and NOT published (included_publish = 0). It is based on
 -- ordinary Riviera Maya practice, not on anything the shop has confirmed.
 -- Review it in the admin, correct it, then switch it on.
 ('included_publish',     '0', NULL),
 ('included_title',       'What is included',                'Qué incluye'),
 ('included_items',       'Certified cave or cavern guide
Full scuba equipment: BCD, regulator, wetsuit, mask and fins
Tanks and weights
Dive lights for cenote dives',
                          'Guía certificado de caverna o cueva
Equipo completo de buceo: BCD, regulador, traje, visor y aletas
Tanques y plomos
Lámparas para los buceos en cenote'),
 ('excluded_title',       'Not included',                    'No incluye'),
 ('excluded_items',       'Cenote entrance fees
Transport to and from the cenote
Dive insurance
Gratuities',
                          'Entradas a los cenotes
Transporte hacia y desde el cenote
Seguro de buceo
Propinas'),
 ('included_note',        'Confirm exactly what your trip includes when you book — entrance fees vary by cenote.',
                          'Confirma qué incluye tu viaje al reservar: las entradas varían según el cenote.'),

 -- Contact block
 ('contact_eyebrow',      'Let us plan your dive',           'Planeemos tu buceo'),
 ('contact_title',        'Ready to get in the water?',      '¿Listo para entrar al agua?'),
 ('contact_text',         'Tell us your experience level and preferred dates.',
                          'Cuéntanos tu nivel de experiencia y las fechas que prefieres.'),

 -- Footer + metadata
 ('footer_note',          'All prices in MXN. Subject to change without prior notice.',
                          'Todos los precios en MXN. Sujetos a cambio sin previo aviso.'),
 ('meta_title',           'SanaTec Diving | Cenote Diving & Technical Training',
                          'SanaTec Diving | Buceo en cenotes y formación técnica'),
 ('meta_description',     'Cenote diving and PADI/TDI training on the Riviera Maya. Course and cenote prices in MXN. Message us on WhatsApp to plan your dive.',
                          'Buceo en cenotes y cursos PADI/TDI en la Riviera Maya. Precios de cursos y cenotes en MXN. Escríbenos por WhatsApp para planear tu buceo.'),
 ('og_image',             '', NULL);   -- empty: the generated preview card
