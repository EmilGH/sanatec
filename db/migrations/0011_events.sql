-- Events: excursions and training as dated things people are booked on.
--
-- One engine, two kinds. An excursion is an event with a day plan of one or
-- more dives; a course is an event with a session per day. Both carry a team
-- roster, a diver roster, a per-diver price snapshotted at booking, and a
-- payments ledger — cash in hand is still recorded, it just happened offline.

CREATE TABLE events (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind           ENUM('excursion','training') NOT NULL,
  slug           VARCHAR(120) NOT NULL,                 -- 2026-10-18-dos-ojos
  title_en       VARCHAR(160) NOT NULL,
  title_es       VARCHAR(160) NOT NULL,
  excursion_id   INT UNSIGNED NULL,                     -- the catalogue item it was made from
  course_id      INT UNSIGNED NULL,
  dives_count    TINYINT UNSIGNED NULL,                 -- excursions: 1, 2 or 3
  price_mxn      DECIMAL(10,2) NULL,                    -- list price per diver at creation
  capacity       TINYINT UNSIGNED NOT NULL DEFAULT 8,
  starts_on      DATE NOT NULL,                         -- first session's date, for listing
  status         ENUM('draft','open','done','cancelled') NOT NULL DEFAULT 'open',
  internal_notes TEXT NULL,
  created_by     BIGINT UNSIGNED NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_events_slug (slug),
  KEY idx_events_kind_date (kind, starts_on),
  CONSTRAINT fk_events_excursion FOREIGN KEY (excursion_id) REFERENCES excursions (id) ON DELETE SET NULL,
  CONSTRAINT fk_events_course    FOREIGN KEY (course_id)    REFERENCES courses (id)    ON DELETE SET NULL,
  CONSTRAINT fk_events_creator   FOREIGN KEY (created_by)   REFERENCES team_members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The day plan: "07:30 Meet at the shop", "08:30 Dive 1 — Dos Ojos"; for a
-- course, one row per day. Times are Cancun local, stored as DATETIME.
CREATE TABLE event_sessions (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    BIGINT UNSIGNED NOT NULL,
  starts_at   DATETIME NOT NULL,
  ends_at     DATETIME NULL,
  title_en    VARCHAR(120) NOT NULL,
  title_es    VARCHAR(120) NOT NULL,
  location    VARCHAR(120) NULL,                        -- cenote, "the shop", classroom
  sort_order  INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_sessions_event (event_id, sort_order),
  CONSTRAINT fk_sessions_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_team (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id        BIGINT UNSIGNED NOT NULL,
  team_member_id  BIGINT UNSIGNED NOT NULL,
  role            ENUM('lead','instructor','guide','driver','support') NOT NULL DEFAULT 'guide',
  status          ENUM('invited','confirmed','declined') NOT NULL DEFAULT 'confirmed',
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_member (event_id, team_member_id),
  CONSTRAINT fk_eteam_event  FOREIGN KEY (event_id)       REFERENCES events (id)       ON DELETE CASCADE,
  CONSTRAINT fk_eteam_member FOREIGN KEY (team_member_id) REFERENCES team_members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A diver on an event. price_mxn is what was agreed for this diver: the
-- event's list price less their discount, at the moment they were added.
CREATE TABLE event_participants (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id     BIGINT UNSIGNED NOT NULL,
  customer_id  BIGINT UNSIGNED NOT NULL,
  status       ENUM('invited','confirmed','attended','no_show','cancelled') NOT NULL DEFAULT 'confirmed',
  price_mxn    DECIMAL(10,2) NULL,
  notes        VARCHAR(255) NULL,
  added_by     BIGINT UNSIGNED NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_event_customer (event_id, customer_id),
  KEY idx_participants_customer (customer_id),
  CONSTRAINT fk_part_event    FOREIGN KEY (event_id)    REFERENCES events (id)       ON DELETE CASCADE,
  CONSTRAINT fk_part_customer FOREIGN KEY (customer_id) REFERENCES customers (id)    ON DELETE CASCADE,
  CONSTRAINT fk_part_adder    FOREIGN KEY (added_by)    REFERENCES team_members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money received, offline. A refund is a negative amount.
CREATE TABLE payments (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  participant_id  BIGINT UNSIGNED NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  currency        CHAR(3) NOT NULL DEFAULT 'MXN',
  method          ENUM('cash','transfer','card','other') NOT NULL DEFAULT 'cash',
  received_by     BIGINT UNSIGNED NULL,
  received_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note            VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_payments_participant (participant_id),
  CONSTRAINT fk_pay_participant FOREIGN KEY (participant_id) REFERENCES event_participants (id) ON DELETE CASCADE,
  CONSTRAINT fk_pay_receiver    FOREIGN KEY (received_by)    REFERENCES team_members (id)       ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The two foreign keys deferred from 0005, now that events exist.
ALTER TABLE form_submissions ADD CONSTRAINT fk_submissions_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL;
ALTER TABLE certifications  ADD CONSTRAINT fk_certs_event       FOREIGN KEY (earned_event_id) REFERENCES events (id) ON DELETE SET NULL;
