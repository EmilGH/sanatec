-- Customers, and the documents they sign.
--
-- A customer is a person with a diving history and paperwork. The paperwork
-- is the point of this migration: a signed form is a dated instance of a
-- specific version of a specific document, signed by a specific person (who
-- may be a guardian), and it can lapse. None of that fits in columns on a
-- customer row, so it does not go there.
--
-- Everything here is additive. Nothing the site reads today changes.

-- ---------------------------------------------------------------------------
-- customers — the diver profile on a person
-- ---------------------------------------------------------------------------
CREATE TABLE customers (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id           BIGINT UNSIGNED NOT NULL,

  -- A minor's forms are signed by this person. NULL for adults.
  guardian_person_id  BIGINT UNSIGNED NULL,

  -- From the Diver Information Form. "Latest declared": the dated snapshot
  -- lives in the form submission; this is what the shop sees at a glance.
  total_dives         INT UNSIGNED NULL,
  dives_last_year     INT UNSIGNED NULL,
  last_dive_on        DATE         NULL,

  -- Where they are staying this trip. Transient by nature; overwritten per visit.
  local_address       VARCHAR(255) NULL,

  -- Gear. Every shop asks; asking once is the point.
  wetsuit_size        VARCHAR(10)  NULL,
  bcd_size            VARCHAR(10)  NULL,
  fin_size            VARCHAR(10)  NULL,
  boot_size           VARCHAR(10)  NULL,
  height_cm           SMALLINT UNSIGNED NULL,
  weight_kg           SMALLINT UNSIGNED NULL,   -- for weighting, not vanity

  -- A customer-level discount, applied to list prices at booking time.
  discount_pct        DECIMAL(5,2) NULL,

  -- How they found the shop. Free text now; a lookup later if it earns one.
  source              VARCHAR(80)  NULL,

  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_person (person_id),
  KEY idx_customers_guardian (guardian_person_id),

  CONSTRAINT fk_customers_person   FOREIGN KEY (person_id)          REFERENCES people (id) ON DELETE CASCADE,
  CONSTRAINT fk_customers_guardian FOREIGN KEY (guardian_person_id) REFERENCES people (id) ON DELETE SET NULL,
  CONSTRAINT chk_customer_discount CHECK (discount_pct IS NULL OR (discount_pct >= 0 AND discount_pct <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- customer_notes — the CRM part
-- ---------------------------------------------------------------------------
-- Dated, attributed, append-only. "Prefers morning dives, left a regulator
-- here in March" is worth more than any field.
CREATE TABLE customer_notes (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id       BIGINT UNSIGNED NOT NULL,
  author_person_id  BIGINT UNSIGNED NULL,
  body              TEXT NOT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_notes_customer (customer_id, created_at),
  CONSTRAINT fk_notes_customer FOREIGN KEY (customer_id)      REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_author   FOREIGN KEY (author_person_id) REFERENCES people (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- emergency_contacts
-- ---------------------------------------------------------------------------
-- Not a person in the system: nobody logs in as an emergency contact. Held
-- as plain fields, phone in E.164 like everything else.
CREATE TABLE emergency_contacts (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id   BIGINT UNSIGNED NOT NULL,
  name          VARCHAR(200) NOT NULL,
  relationship  VARCHAR(60)  NULL,
  phone         VARCHAR(20)  NOT NULL,
  email         VARCHAR(255) NULL,
  is_primary    TINYINT(1)   NOT NULL DEFAULT 1,
  notes         VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_emergency_customer (customer_id),
  CONSTRAINT fk_emergency_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
  CONSTRAINT chk_emergency_phone CHECK (phone REGEXP '^\\+[1-9][0-9]{6,14}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- certifications — records, not a text field
-- ---------------------------------------------------------------------------
-- level_code is the machine-readable rank a route can require ("AOW" for
-- Angelita); level is what the card actually says. One person, many cards.
-- earned_event_id links a card to the training that produced it; the
-- foreign key is added by the events migration, which creates that table.
CREATE TABLE certifications (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id        BIGINT UNSIGNED NOT NULL,
  agency             VARCHAR(40)  NOT NULL,        -- PADI, TDI, SSI, NAUI, CMAS...
  level_code         VARCHAR(24)  NOT NULL,        -- ow, aow, rescue, dm, instructor, cavern, intro_cave, full_cave, sidemount, other
  level              VARCHAR(120) NOT NULL,        -- "Advanced Open Water Diver"
  number             VARCHAR(80)  NULL,
  issued_on          DATE         NULL,
  card_image_path    VARCHAR(255) NULL,
  verified_by        BIGINT UNSIGNED NULL,         -- team member who saw the card
  verified_at        DATETIME     NULL,
  earned_event_id    BIGINT UNSIGNED NULL,
  notes              VARCHAR(255) NULL,
  created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_certs_customer (customer_id),
  KEY idx_certs_level (level_code),
  CONSTRAINT fk_certs_customer FOREIGN KEY (customer_id) REFERENCES customers (id)    ON DELETE CASCADE,
  CONSTRAINT fk_certs_verifier FOREIGN KEY (verified_by) REFERENCES team_members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- form_templates — the documents, versioned
-- ---------------------------------------------------------------------------
-- PADI revises its forms (10060 Rev 06/15; the medical is dated 2022-02-01)
-- and the liability release has an EU/EFTA alternative. A signature is only
-- evidence of what was signed if the exact version is on record, so each
-- version is its own row and old ones are kept, deactivated.
--
-- definition is JSON describing the fields to collect and the statements to
-- acknowledge. source_document_path points at the original PDF, kept outside
-- the repository: the forms are PADI's, not ours.
CREATE TABLE form_templates (
  id                          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code                        VARCHAR(40)  NOT NULL,      -- diver_info, medical, safe_diving, liability
  version                     VARCHAR(32)  NOT NULL,      -- the publisher's version string
  jurisdiction                VARCHAR(8)   NULL,          -- NULL = default; 'EU' for the EU/EFTA variant
  language                    VARCHAR(8)   NOT NULL DEFAULT 'en',
  title                       VARCHAR(160) NOT NULL,
  publisher                   VARCHAR(80)  NULL,          -- PADI, DAN/WRSTC
  definition                  JSON         NOT NULL,
  source_document_path        VARCHAR(255) NULL,
  requires_guardian_if_minor  TINYINT(1)   NOT NULL DEFAULT 1,
  validity_days               INT UNSIGNED NULL,          -- NULL = does not lapse; medical = 365
  is_active                   TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order                  INT          NOT NULL DEFAULT 0,
  created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_template_version (code, version, jurisdiction, language),
  KEY idx_templates_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- form_submissions — a signed instance
-- ---------------------------------------------------------------------------
-- What was answered, by whom, when, from where. answers is the JSON the
-- template's definition describes. signed_by is the participant, or the
-- guardian when the participant is a minor. event_id ties a signature to the
-- booking it was collected for; its foreign key arrives with the events table.
CREATE TABLE form_submissions (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id            BIGINT UNSIGNED NOT NULL,
  template_id            BIGINT UNSIGNED NOT NULL,
  event_id               BIGINT UNSIGNED NULL,
  status                 ENUM('draft','signed','void') NOT NULL DEFAULT 'draft',
  answers                JSON         NOT NULL,
  signed_by_person_id    BIGINT UNSIGNED NULL,
  signer_role            ENUM('participant','guardian') NULL,
  signature_typed        VARCHAR(200) NULL,          -- the name as typed to sign
  signature_image_path   VARCHAR(255) NULL,          -- a drawn signature, if captured
  signed_at              DATETIME     NULL,
  signed_ip              VARBINARY(16) NULL,
  signed_user_agent      VARCHAR(255) NULL,
  expires_on             DATE         NULL,          -- signed_at + template.validity_days
  rendered_pdf_path      VARCHAR(255) NULL,          -- the filled document, for the file
  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_submissions_customer (customer_id, status, expires_on),
  KEY idx_submissions_template (template_id),
  CONSTRAINT fk_submissions_customer FOREIGN KEY (customer_id)         REFERENCES customers (id)      ON DELETE CASCADE,
  CONSTRAINT fk_submissions_template FOREIGN KEY (template_id)         REFERENCES form_templates (id) ON DELETE RESTRICT,
  CONSTRAINT fk_submissions_signer   FOREIGN KEY (signed_by_person_id) REFERENCES people (id)         ON DELETE SET NULL,
  CONSTRAINT chk_signed_has_signer CHECK (status <> 'signed' OR (signed_at IS NOT NULL AND signer_role IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- medical_evaluations — the outcome of a medical questionnaire
-- ---------------------------------------------------------------------------
-- The questionnaire is a form_submission. This is the decision it leads to:
-- cleared on answers alone, or a physician's evaluation is required, and if
-- so whether one came back and what it said. "physician_required" is a hard
-- stop for diving until it becomes "physician_cleared".
CREATE TABLE medical_evaluations (
  id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id            BIGINT UNSIGNED NOT NULL,
  customer_id              BIGINT UNSIGNED NOT NULL,
  outcome                  ENUM('cleared','physician_required','physician_cleared','physician_declined') NOT NULL,
  flagged_questions        JSON         NULL,          -- which answers triggered the referral
  physician_name           VARCHAR(160) NULL,
  physician_cleared_on     DATE         NULL,
  physician_document_path  VARCHAR(255) NULL,          -- the signed evaluation form
  reviewed_by              BIGINT UNSIGNED NULL,       -- team member who recorded it
  reviewed_at              DATETIME     NULL,
  notes                    VARCHAR(500) NULL,
  created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_medical_submission (submission_id),
  KEY idx_medical_customer (customer_id, outcome),
  CONSTRAINT fk_medical_submission FOREIGN KEY (submission_id) REFERENCES form_submissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_medical_customer   FOREIGN KEY (customer_id)   REFERENCES customers (id)        ON DELETE CASCADE,
  CONSTRAINT fk_medical_reviewer   FOREIGN KEY (reviewed_by)   REFERENCES team_members (id)     ON DELETE SET NULL,
  CONSTRAINT chk_physician_cleared CHECK (outcome <> 'physician_cleared' OR physician_cleared_on IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- The four documents in support/, as templates
-- ---------------------------------------------------------------------------
-- Metadata and field definitions only. The PDFs themselves belong at
-- /var/www/private/sanatecdiving/forms/ on the server, not in this repository.
INSERT INTO form_templates (code, version, jurisdiction, language, title, publisher, definition, source_document_path, requires_guardian_if_minor, validity_days, sort_order) VALUES
('diver_info', '2026-09', NULL, 'en', 'Diver Information Form', 'SanaTec Diving',
 JSON_OBJECT('sections', JSON_ARRAY(
   JSON_OBJECT('title','Contact','fields',JSON_ARRAY('name','date_of_birth','mobile','email','nationality','local_address')),
   JSON_OBJECT('title','Experience','fields',JSON_ARRAY('cert_agency','cert_level','cert_number','total_dives','dives_last_year','last_dive_on')),
   JSON_OBJECT('title','Emergency','fields',JSON_ARRAY('emergency_name','emergency_phone','dan_number','dan_expires_on')),
   JSON_OBJECT('title','Gear','fields',JSON_ARRAY('wetsuit_size','bcd_size','fin_size','boot_size','height_cm','weight_kg')))),
 'forms/0 - Diver Information Form, Blank.pdf', 1, NULL, 10),

('medical', '2022-02-01', NULL, 'en', 'Diver Medical — Participant Questionnaire', 'DAN / WRSTC / RSTC',
 JSON_OBJECT(
   'screening', JSON_ARRAY(
     JSON_OBJECT('id','q1','refer_to','A'), JSON_OBJECT('id','q2','refer_to','B'), JSON_OBJECT('id','q3','physician',true),
     JSON_OBJECT('id','q4','refer_to','C'), JSON_OBJECT('id','q5','physician',true), JSON_OBJECT('id','q6','refer_to','D'),
     JSON_OBJECT('id','q7','refer_to','E'), JSON_OBJECT('id','q8','refer_to','F'), JSON_OBJECT('id','q9','refer_to','G'),
     JSON_OBJECT('id','q10','physician',true)),
   'boxes', JSON_OBJECT('A',5,'B',4,'C',4,'D',5,'E',4,'F',5,'G',6),
   'rule', 'Any yes to q3, q5, q10, or to any box question, requires a physician evaluation before diving.'),
 'forms/3 - Medical Forms.pdf', 1, 365, 20),

('safe_diving', '2.01 (Rev 06/15)', NULL, 'en', 'Standard Safe Diving Practices Statement of Understanding', 'PADI',
 JSON_OBJECT('acknowledge', JSON_ARRAY('p1','p2','p3','p4','p5','p6','p7','p8','p9','p10'), 'product_no', '10060'),
 'forms/1 - Safe Diving Practices.pdf', 1, NULL, 30),

('liability', '2026-09', NULL, 'en', 'Non-Agency Disclosure and Liability Release', 'PADI',
 JSON_OBJECT('fills', JSON_ARRAY('store_name','instructor_names'), 'acknowledge', JSON_ARRAY('non_agency','release'),
   'note', 'EU/EFTA participants must use the alternative form; add it as jurisdiction = EU when obtained.'),
 'forms/2 - Non-Agency Disclosure.pdf', 1, NULL, 40);
