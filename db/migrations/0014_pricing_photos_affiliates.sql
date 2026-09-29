-- Retail and floor prices, item pages with photos, and affiliates.

-- Floor: the least the shop will take. A diver's discount stops there; an
-- affiliate's share is a slice of the room between retail and floor.
ALTER TABLE courses
  ADD COLUMN floor_price_mxn DECIMAL(10,2) NULL AFTER price_mxn,
  ADD COLUMN intro_en  VARCHAR(300) NULL,
  ADD COLUMN intro_es  VARCHAR(300) NULL,
  ADD COLUMN body_en   TEXT NULL,
  ADD COLUMN body_es   TEXT NULL,
  ADD COLUMN prereq_en VARCHAR(300) NULL,
  ADD COLUMN prereq_es VARCHAR(300) NULL;

ALTER TABLE excursions
  ADD COLUMN floor_price_1_dive  DECIMAL(10,2) NULL AFTER price_3_dives,
  ADD COLUMN floor_price_2_dives DECIMAL(10,2) NULL AFTER floor_price_1_dive,
  ADD COLUMN floor_price_3_dives DECIMAL(10,2) NULL AFTER floor_price_2_dives,
  ADD COLUMN intro_en VARCHAR(300) NULL,
  ADD COLUMN intro_es VARCHAR(300) NULL,
  ADD COLUMN body_en  TEXT NULL,
  ADD COLUMN body_es  TEXT NULL;

-- Photos on catalogue items and cenotes: one aspect ratio (3:2), cropped on upload.
CREATE TABLE catalog_photos (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  item_type   ENUM('course','excursion','dive_site') NOT NULL,
  item_id     BIGINT UNSIGNED NOT NULL,
  path        VARCHAR(255) NOT NULL,
  caption_en  VARCHAR(200) NULL,
  caption_es  VARCHAR(200) NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_photos_item (item_type, item_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Affiliates: hotels, concierges, operators who send divers. The contact is a
-- person like everyone else, so they can sign in and see their numbers.
CREATE TABLE affiliates (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code              VARCHAR(40)  NOT NULL,                       -- the referral key in links
  name              VARCHAR(120) NOT NULL,
  description_en    TEXT NULL,
  description_es    TEXT NULL,
  logo_path         VARCHAR(255) NULL,
  contact_person_id BIGINT UNSIGNED NULL,
  pricing_mode      ENUM('commission','net') NOT NULL DEFAULT 'commission',
  share_pct         DECIMAL(5,2) NOT NULL DEFAULT 50.00,         -- the affiliate's share of retail minus floor
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  notes             TEXT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_affiliates_code (code),
  CONSTRAINT fk_affiliates_contact FOREIGN KEY (contact_person_id) REFERENCES people (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE affiliate_visits (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id BIGINT UNSIGNED NOT NULL,
  landed_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  path         VARCHAR(255) NULL,
  referrer     VARCHAR(500) NULL,
  ip           VARBINARY(16) NULL,
  user_agent   VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_visits_affiliate (affiliate_id, landed_at),
  CONSTRAINT fk_visits_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliates (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE customers
  ADD COLUMN referred_by_affiliate_id BIGINT UNSIGNED NULL AFTER onboarded_at,
  ADD COLUMN referred_at DATETIME NULL AFTER referred_by_affiliate_id,
  ADD CONSTRAINT fk_customers_affiliate FOREIGN KEY (referred_by_affiliate_id) REFERENCES affiliates (id) ON DELETE SET NULL;

ALTER TABLE events
  ADD COLUMN floor_mxn DECIMAL(10,2) NULL AFTER price_mxn;

ALTER TABLE event_participants
  ADD COLUMN floor_mxn            DECIMAL(10,2) NULL AFTER price_mxn,
  ADD COLUMN affiliate_id         BIGINT UNSIGNED NULL AFTER floor_mxn,
  ADD COLUMN affiliate_amount_mxn DECIMAL(10,2) NULL AFTER affiliate_id,
  ADD CONSTRAINT fk_participants_affiliate FOREIGN KEY (affiliate_id) REFERENCES affiliates (id) ON DELETE SET NULL;

ALTER TABLE team_members
  ADD COLUMN can_manage_affiliates TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage_team;
UPDATE team_members SET can_manage_affiliates = 1 WHERE is_system_admin = 1;
