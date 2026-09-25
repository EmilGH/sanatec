-- The CENOTE Exploration Passport, public team pages, and exchange rates.

-- Payments in another currency convert at the rate typed; the MXN figure is
-- what the balance counts. A rate of 1 for MXN keeps the arithmetic honest.
ALTER TABLE payments
  ADD COLUMN fx_rate    DECIMAL(12,6) NULL AFTER currency,
  ADD COLUMN amount_mxn DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER fx_rate;
UPDATE payments SET amount_mxn = amount WHERE currency = 'MXN';

-- Dive sites: the cenotes themselves. A route is an ordered list of them.
CREATE TABLE dive_sites (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug            VARCHAR(80)  NOT NULL,
  name_en         VARCHAR(120) NOT NULL,
  name_es         VARCHAR(120) NOT NULL,
  description_en  TEXT NULL,
  description_es  TEXT NULL,
  max_depth_m     SMALLINT UNSIGNED NULL,
  cert_required   VARCHAR(24) NULL,                 -- a certifications.level_code
  latitude        DECIMAL(9,6) NULL,
  longitude       DECIMAL(9,6) NULL,
  is_published    TINYINT(1) NOT NULL DEFAULT 1,
  sort_order      INT NOT NULL DEFAULT 0,
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sites_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE excursion_sites (
  excursion_id  INT UNSIGNED NOT NULL,
  dive_site_id  INT UNSIGNED NOT NULL,
  sort_order    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (excursion_id, dive_site_id),
  CONSTRAINT fk_xs_excursion FOREIGN KEY (excursion_id) REFERENCES excursions (id) ON DELETE CASCADE,
  CONSTRAINT fk_xs_site      FOREIGN KEY (dive_site_id) REFERENCES dive_sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE event_sessions
  ADD COLUMN dive_site_id INT UNSIGNED NULL AFTER location,
  ADD CONSTRAINT fk_sessions_site FOREIGN KEY (dive_site_id) REFERENCES dive_sites (id) ON DELETE SET NULL;

-- A dive: one diver, one site, one date. Written automatically when a diver is
-- marked attended on an event whose sessions carry sites; editable after.
CREATE TABLE dives (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id     BIGINT UNSIGNED NOT NULL,
  participant_id  BIGINT UNSIGNED NULL,
  session_id      BIGINT UNSIGNED NULL,
  dive_site_id    INT UNSIGNED NOT NULL,
  dived_on        DATE NOT NULL,
  max_depth_m     DECIMAL(5,1) NULL,
  duration_min    SMALLINT UNSIGNED NULL,
  guide_team_id   BIGINT UNSIGNED NULL,
  notes           VARCHAR(500) NULL,               -- the diver's own
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dive_session (participant_id, session_id),
  KEY idx_dives_customer (customer_id, dived_on),
  CONSTRAINT fk_dives_customer    FOREIGN KEY (customer_id)    REFERENCES customers (id)          ON DELETE CASCADE,
  CONSTRAINT fk_dives_participant FOREIGN KEY (participant_id) REFERENCES event_participants (id) ON DELETE SET NULL,
  CONSTRAINT fk_dives_session     FOREIGN KEY (session_id)     REFERENCES event_sessions (id)     ON DELETE SET NULL,
  CONSTRAINT fk_dives_site        FOREIGN KEY (dive_site_id)   REFERENCES dive_sites (id)         ON DELETE RESTRICT,
  CONSTRAINT fk_dives_guide       FOREIGN KEY (guide_team_id)  REFERENCES team_members (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dive_photos (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dive_id       BIGINT UNSIGNED NOT NULL,
  path          VARCHAR(255) NOT NULL,
  caption       VARCHAR(200) NULL,
  is_public     TINYINT(1) NOT NULL DEFAULT 0,      -- shown on the diver's public passport
  uploaded_by   BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_photos_dive (dive_id),
  CONSTRAINT fk_photos_dive     FOREIGN KEY (dive_id)     REFERENCES dives (id)  ON DELETE CASCADE,
  CONSTRAINT fk_photos_uploader FOREIGN KEY (uploaded_by) REFERENCES people (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist (
  customer_id   BIGINT UNSIGNED NOT NULL,
  dive_site_id  INT UNSIGNED NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id, dive_site_id),
  CONSTRAINT fk_wish_customer FOREIGN KEY (customer_id)  REFERENCES customers (id)  ON DELETE CASCADE,
  CONSTRAINT fk_wish_site     FOREIGN KEY (dive_site_id) REFERENCES dive_sites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The passport is private until the diver switches it on; then it is at
-- /passport/<public_id>, showing stamps and counts, and only photos marked public.
ALTER TABLE customers ADD COLUMN passport_public TINYINT(1) NOT NULL DEFAULT 0 AFTER own_gear;

-- The sites the price list already names, one per distinct cenote, and each
-- route mapped to its sites in order.
INSERT IGNORE INTO dive_sites (slug, name_en, name_es, sort_order) VALUES
 ('angelita',  'Angelita',   'Angelita',   10),
 ('carwash',   'Carwash',    'Carwash',    20),
 ('casa',      'Casa Cenote','Casa Cenote',30),
 ('the-pit',   'The Pit',    'The Pit',    40),
 ('dos-ojos',  'Dos Ojos',   'Dos Ojos',   50),
 ('nic-te-ha', 'Nic Te-Ha',  'Nic Te-Ha',  60),
 ('dreamgate', 'Dreamgate',  'Dreamgate',  70),
 ('ponderosa', 'Ponderosa',  'Ponderosa',  80),
 ('chikin-ha', 'Chikin Ha',  'Chikin Ha',  90),
 ('yaa-kun',   'Yaa Kun',    'Yaa Kun',   100);

INSERT IGNORE INTO excursion_sites (excursion_id, dive_site_id, sort_order)
SELECT e.id, s.id, m.ord FROM excursions e
JOIN (
  SELECT 'angelita-carwash' AS xslug, 'angelita' AS sslug, 0 AS ord UNION ALL SELECT 'angelita-carwash', 'carwash', 1
  UNION ALL SELECT 'angelita-carwash-casa', 'angelita', 0 UNION ALL SELECT 'angelita-carwash-casa', 'carwash', 1 UNION ALL SELECT 'angelita-carwash-casa', 'casa', 2
  UNION ALL SELECT 'pit-dos-ojos', 'the-pit', 0 UNION ALL SELECT 'pit-dos-ojos', 'dos-ojos', 1
  UNION ALL SELECT 'pit-dos-ojos-nic-te-ha', 'the-pit', 0 UNION ALL SELECT 'pit-dos-ojos-nic-te-ha', 'dos-ojos', 1 UNION ALL SELECT 'pit-dos-ojos-nic-te-ha', 'nic-te-ha', 2
  UNION ALL SELECT 'dreamgate', 'dreamgate', 0
  UNION ALL SELECT 'dos-ojos', 'dos-ojos', 0
  UNION ALL SELECT 'ponderosa-chikin-ha', 'ponderosa', 0 UNION ALL SELECT 'ponderosa-chikin-ha', 'chikin-ha', 1
  UNION ALL SELECT 'chikin-ha', 'chikin-ha', 0
  UNION ALL SELECT 'yaa-kun', 'yaa-kun', 0
  UNION ALL SELECT 'casa-carwash', 'casa', 0 UNION ALL SELECT 'casa-carwash', 'carwash', 1
  UNION ALL SELECT 'dos-ojos-carwash', 'dos-ojos', 0 UNION ALL SELECT 'dos-ojos-carwash', 'carwash', 1
) m ON m.xslug = e.slug
JOIN dive_sites s ON s.slug = m.sslug;
