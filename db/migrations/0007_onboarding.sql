-- Diver onboarding: which documents apply to what, and the privacy notice.
--
-- The liability release comes in two PADI editions: 10072 for training
-- ("program", "instructional dives") and 10086 for diver activities
-- (certified divers on excursions; it also asks for an accident-insurance
-- policy number). A diver signs whichever their booking calls for.
-- The physician's evaluation form is page 3 of the 2020 medical PDF; the
-- 2022-02-01 questionnaire does not carry it, so the medical template points
-- at both documents.

ALTER TABLE form_templates
  ADD COLUMN applies_to ENUM('all','training','excursion') NOT NULL DEFAULT 'all' AFTER jurisdiction;

UPDATE form_templates SET
  version = '4.03 (Rev. 10/16)', applies_to = 'training',
  title = 'Liability Release and Non-Agency Acknowledgment — Training',
  definition = JSON_SET(definition, '$.product_no', '10072')
WHERE code = 'liability';

INSERT INTO form_templates (code, version, jurisdiction, applies_to, language, title, publisher, definition, source_document_path, requires_guardian_if_minor, validity_days, sort_order)
VALUES ('liability_excursion', '3.0 (Rev. 02/21)', NULL, 'excursion', 'en',
  'Release of Liability, Assumption of Risk and Non-Agency Acknowledgment — Diver Activities', 'PADI',
  JSON_OBJECT('product_no', '10086', 'fills', JSON_ARRAY('store_name'), 'asks', JSON_ARRAY('dan_number'),
              'acknowledge', JSON_ARRAY('non_agency', 'release')),
  'forms/2a - Non-Agency Disclosure.pdf', 1, NULL, 45);

UPDATE form_templates SET
  definition = JSON_SET(definition, '$.physician_form_path', 'forms/3a - Medical Forms.pdf', '$.physician_form_page', 3)
WHERE code = 'medical';

-- The privacy notice is site content, edited in Business info. It ships as a
-- DRAFT — the version string says so — and the consent a diver gives records
-- the version they saw. Replace the version when a lawyer has signed it off.
INSERT IGNORE INTO settings (skey, val_en, val_es) VALUES
 ('privacy_notice_version', '2026-09-DRAFT', NULL),
 ('privacy_notice', '', '');

-- A first draft of the notice, shaped to what the LFPDPPP requires of an
-- "aviso de privacidad integral": who is responsible, what is collected
-- (including sensitive health data), why, with whom it is shared, how ARCO
-- rights are exercised, and how changes are announced. Square brackets mark
-- what the shop must fill in. A lawyer must review it before the version
-- loses the word DRAFT.
UPDATE settings SET val_en = '# Who is responsible for your data
SanaTec Diving ("we"), with its address at [STREET ADDRESS], Tulum, Quintana Roo, Mexico, is responsible for the personal data you provide, in accordance with the Federal Law on the Protection of Personal Data Held by Private Parties (LFPDPPP) and, for residents of the European Union, the GDPR.

# What we collect
Identification and contact details: name, date of birth, nationality, email, mobile number, and where you are staying in Mexico. Diving experience: certifications, number of dives, date of last dive, and diver accident insurance. Emergency contact details. Equipment sizes.

Sensitive data: the answers you give on the Diver Medical Participant Questionnaire, and any physician''s evaluation you provide, are health data and are treated as sensitive personal data. We ask for your express consent to collect them.

# Why we collect it
To assess whether you can safely take part in the diving you have booked; to plan and run courses and excursions; to meet the training standards of the certifying agencies; to reach you or your emergency contact about a booking or in an emergency; to keep the records a dive operation is required to keep; and to issue invoices. With your separate consent, to tell you about future trips and offers.

# Who we share it with
Certifying agencies (PADI, TDI, NAUI) when you enrol in a course they certify; emergency and medical services, and your insurer, in an emergency; and the service providers that host our systems and deliver our messages ([HOSTING PROVIDER], [MESSAGING PROVIDER]), who process data only on our instructions. We do not sell personal data.

# How long we keep it
Signed forms and medical questionnaires for the period required by the certifying agencies and applicable law, and in any case no longer than [RETENTION PERIOD]. Contact details for as long as you remain a customer, and until you ask us to delete them.

# Your rights
You may access, rectify, cancel or object to the use of your personal data (ARCO rights), and withdraw consent, by writing to [PRIVACY EMAIL]. We will respond within the period the law sets. You may also limit the use of your data for offers at the same address.

# Changes
We may update this notice. The version in force is shown at sanatecdiving.com/privacy, and you will be asked to accept a new version before providing further data.

Version [DATE] — DRAFT pending legal review.',
val_es = '# Responsable de tus datos
SanaTec Diving ("nosotros"), con domicilio en [DIRECCIÓN], Tulum, Quintana Roo, México, es responsable de los datos personales que nos proporcionas, conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP) y, para residentes de la Unión Europea, al RGPD.

# Qué recabamos
Datos de identificación y contacto: nombre, fecha de nacimiento, nacionalidad, correo electrónico, número de móvil y lugar de hospedaje en México. Experiencia de buceo: certificaciones, número de buceos, fecha del último buceo y seguro de accidentes de buceo. Datos de contacto de emergencia. Tallas de equipo.

Datos sensibles: las respuestas del Cuestionario Médico para Buceadores y cualquier evaluación médica que aportes son datos de salud y se tratan como datos personales sensibles. Solicitamos tu consentimiento expreso para recabarlos.

# Para qué los usamos
Para valorar si puedes participar con seguridad en el buceo que reservaste; planear y operar cursos y salidas; cumplir los estándares de las agencias certificadoras; contactarte a ti o a tu contacto de emergencia sobre una reserva o en una emergencia; conservar los registros que un centro de buceo debe conservar; y emitir facturas. Con tu consentimiento por separado, para informarte de futuras salidas y promociones.

# Con quién los compartimos
Agencias certificadoras (PADI, TDI, NAUI) cuando te inscribes en un curso que certifican; servicios médicos y de emergencia, y tu aseguradora, en caso de emergencia; y los proveedores que alojan nuestros sistemas y entregan nuestros mensajes ([PROVEEDOR DE HOSPEDAJE], [PROVEEDOR DE MENSAJERÍA]), que tratan los datos solo bajo nuestras instrucciones. No vendemos datos personales.

# Cuánto tiempo los conservamos
Formularios firmados y cuestionarios médicos durante el plazo que exijan las agencias certificadoras y la ley aplicable, y en todo caso no más de [PLAZO DE CONSERVACIÓN]. Datos de contacto mientras seas cliente y hasta que nos pidas eliminarlos.

# Tus derechos
Puedes acceder, rectificar, cancelar u oponerte al tratamiento de tus datos (derechos ARCO) y revocar tu consentimiento escribiendo a [CORREO DE PRIVACIDAD]. Responderemos en el plazo que marca la ley. En esa misma dirección puedes limitar el uso de tus datos para promociones.

# Cambios
Podemos actualizar este aviso. La versión vigente se muestra en sanatecdiving.com/es/privacy y se te pedirá aceptar la nueva versión antes de proporcionar más datos.

Versión [FECHA] — BORRADOR pendiente de revisión legal.'
WHERE skey = 'privacy_notice' AND (val_en IS NULL OR val_en = '');
