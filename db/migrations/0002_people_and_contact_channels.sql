-- People: everyone the business knows.
--
-- One identity, many roles. A person may be a team member, a customer, both,
-- or neither yet — an emergency contact, a minor's guardian. Team and customer
-- profiles hang off this table in later migrations. Login lives here too,
-- because both staff and divers sign in the same way: a code sent to a channel
-- they have proven they control.
--
-- Nothing here stores a password. Passwordless is the design, not a phase.

-- ---------------------------------------------------------------------------
-- people
-- ---------------------------------------------------------------------------
CREATE TABLE people (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Opaque identifier for anything a person sees in a URL: a form link, a
  -- Passport page. Sequential ids leak how many customers exist and invite
  -- guessing; this does neither.
  public_id          CHAR(36)     NOT NULL DEFAULT (UUID()),

  -- One field. "First/last" is an American assumption that mangles most of
  -- the world's names; a single string holds all of them.
  name               VARCHAR(200) NOT NULL,

  -- On the diver form, and needed to know who is a minor and so needs a
  -- guardian's signature. Nullable: team members need not supply it.
  date_of_birth      DATE         NULL,
  nationality        CHAR(2)      NULL,          -- ISO 3166-1 alpha-2

  -- Which language the site and messages address this person in.
  preferred_language VARCHAR(8)   NOT NULL DEFAULT 'en',

  -- The business runs on Cancun time (no DST). A person may live elsewhere;
  -- times shown to them are rendered in this zone. Stored values are UTC.
  timezone           VARCHAR(64)  NOT NULL DEFAULT 'America/Cancun',

  -- Soft delete. Right-to-erasure is a separate, explicit procedure that
  -- hard-deletes; ordinary "remove" just sets this.
  deleted_at         DATETIME     NULL,

  created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_people_public_id (public_id),
  KEY idx_people_name (name),
  KEY idx_people_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- contact_channels — phone numbers and email addresses as records
-- ---------------------------------------------------------------------------
-- A person has several. Each is verified independently, and login depends on
-- which are verified. "Reachable on WhatsApp" is earned by a successful
-- verification message, never ticked by hand: the API cannot be asked whether
-- a number is on WhatsApp, only shown by sending to it.
CREATE TABLE contact_channels (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id            BIGINT UNSIGNED NOT NULL,
  kind                 ENUM('email','mobile') NOT NULL,

  -- Normalised before storage: email lower-cased, mobile as E.164 with no
  -- spaces or punctuation (+529841063306). The CHECKs below hold the line
  -- even if application code slips.
  value                VARCHAR(255) NOT NULL,
  label                VARCHAR(40)  NULL,            -- "personal", "shop"

  is_primary           TINYINT(1)   NOT NULL DEFAULT 0,  -- one per kind per person; enforced in code
  verified_at          DATETIME     NULL,

  -- Mobile only. Set by delivery of a WhatsApp verification, cleared if a
  -- later send fails with "not a WhatsApp user".
  whatsapp_capable     TINYINT(1)   NOT NULL DEFAULT 0,
  whatsapp_checked_at  DATETIME     NULL,

  -- Transactional messages (codes, reminders about a booking they made) need
  -- no opt-in. Anything else does. NULL means no.
  marketing_opt_in_at  DATETIME     NULL,

  created_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),

  -- A number or address belongs to exactly one person. A couple sharing an
  -- email, or a parent's phone for a minor, would make "who is logging in"
  -- ambiguous — minors are reached through their guardian's account instead.
  UNIQUE KEY uq_channel_value (kind, value),
  KEY idx_channels_person (person_id),

  CONSTRAINT fk_channels_person
    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE CASCADE,

  CONSTRAINT chk_mobile_is_e164
    CHECK (kind <> 'mobile' OR value REGEXP '^\\+[1-9][0-9]{6,14}$'),
  CONSTRAINT chk_email_shape
    CHECK (kind <> 'email' OR value REGEXP '^[^@ ]+@[^@ ]+\\.[^@ ]+$'),
  CONSTRAINT chk_whatsapp_is_mobile_only
    CHECK (whatsapp_capable = 0 OR kind = 'mobile')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_tokens — one-time codes and magic links
-- ---------------------------------------------------------------------------
-- The code is sent to a channel and never stored: only its SHA-256 is. A token
-- is bound to the channel it went to, expires in minutes, is consumed once,
-- and gives up after a handful of wrong guesses.
CREATE TABLE login_tokens (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id     BIGINT UNSIGNED NOT NULL,
  channel_id    BIGINT UNSIGNED NOT NULL,
  purpose       ENUM('login','verify_channel') NOT NULL DEFAULT 'login',
  token_hash    CHAR(64)        NOT NULL,
  expires_at    DATETIME        NOT NULL,
  consumed_at   DATETIME        NULL,
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  requested_ip  VARBINARY(16)   NULL,
  requested_ua  VARCHAR(255)    NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_login_token_hash (token_hash),
  KEY idx_login_tokens_person_time (person_id, created_at),

  CONSTRAINT fk_login_tokens_person
    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE CASCADE,
  CONSTRAINT fk_login_tokens_channel
    FOREIGN KEY (channel_id) REFERENCES contact_channels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- consents — who agreed to what, which version, when, and from where
-- ---------------------------------------------------------------------------
-- Medical answers are "datos personales sensibles" under Mexico's LFPDPPP and
-- special-category data under GDPR for European divers. Both want a record
-- of consent that names the document version. A revoked consent stays as a
-- row with revoked_at set — deleting it would erase the evidence it existed.
CREATE TABLE consents (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id       BIGINT UNSIGNED NOT NULL,
  purpose         VARCHAR(64)     NOT NULL,   -- 'privacy_notice', 'medical_data', 'marketing_whatsapp'
  version         VARCHAR(32)     NOT NULL,   -- of the document consented to
  granted_at      DATETIME        NOT NULL,
  revoked_at      DATETIME        NULL,
  via_channel_id  BIGINT UNSIGNED NULL,       -- the channel the consent arrived through, if any
  ip              VARBINARY(16)   NULL,
  created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_consents_person_purpose (person_id, purpose),

  CONSTRAINT fk_consents_person
    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE CASCADE,
  CONSTRAINT fk_consents_channel
    FOREIGN KEY (via_channel_id) REFERENCES contact_channels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Adjustments to what already exists
-- ---------------------------------------------------------------------------

-- Not done here: renaming login_attempts.username to identifier. Auth.php
-- still writes that column, so the rename ships with the passwordless login
-- migration, alongside the code that needs it. Both of these files are
-- purely additive and cannot affect the running application.

-- The business's own zone, distinct from any person's.
INSERT IGNORE INTO settings (skey, val_en, val_es) VALUES ('timezone', 'America/Cancun', NULL);
