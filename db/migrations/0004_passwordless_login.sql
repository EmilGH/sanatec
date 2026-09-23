-- Passwordless login, and the outbox every message leaves through.
--
-- After this migration there is no password anywhere in the system. Signing
-- in means proving control of a verified channel: a code arrives by WhatsApp,
-- SMS or email, or a one-time link is issued from the server's command line.

-- ---------------------------------------------------------------------------
-- messages — the outbox
-- ---------------------------------------------------------------------------
-- Nothing sends inline. A row is queued, a transport delivers it, and the row
-- keeps the result. Login codes are sent immediately after queueing because
-- a person is waiting; reminders are sent by cron. Either way this table is
-- the record of what went out, to whom, and whether it arrived.
CREATE TABLE messages (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id     BIGINT UNSIGNED NULL,
  channel_id    BIGINT UNSIGNED NULL,
  transport     ENUM('email','sms','whatsapp','log') NOT NULL,
  to_value      VARCHAR(255)    NOT NULL,             -- the address or number, as sent
  template      VARCHAR(64)     NOT NULL,             -- 'login_code', 'excursion_reminder', ...
  locale        VARCHAR(8)      NOT NULL DEFAULT 'en',
  subject       VARCHAR(255)    NULL,                 -- email only
  body_text     TEXT            NOT NULL,
  body_html     MEDIUMTEXT      NULL,
  status        ENUM('queued','sending','sent','delivered','failed') NOT NULL DEFAULT 'queued',
  attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  provider_ref  VARCHAR(128)    NULL,                 -- the provider's message id
  error         VARCHAR(500)    NULL,
  scheduled_at  DATETIME        NULL,                 -- NULL = as soon as possible
  sent_at       DATETIME        NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_messages_pending (status, scheduled_at),
  KEY idx_messages_person (person_id, created_at),

  CONSTRAINT fk_messages_person  FOREIGN KEY (person_id)  REFERENCES people (id) ON DELETE SET NULL,
  CONSTRAINT fk_messages_channel FOREIGN KEY (channel_id) REFERENCES contact_channels (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_devices — "remember this device"
-- ---------------------------------------------------------------------------
-- A long random token in a cookie, its hash here. Presenting it restores a
-- session without a new code. Revocable per device; expires on its own.
CREATE TABLE login_devices (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id     BIGINT UNSIGNED NOT NULL,
  token_hash    CHAR(64)        NOT NULL,
  user_agent    VARCHAR(255)    NULL,
  created_ip    VARBINARY(16)   NULL,
  created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at  DATETIME        NULL,
  expires_at    DATETIME        NOT NULL,
  revoked_at    DATETIME        NULL,

  PRIMARY KEY (id),
  UNIQUE KEY uq_device_token (token_hash),
  KEY idx_devices_person (person_id),

  CONSTRAINT fk_devices_person FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_tokens: a per-token salt
-- ---------------------------------------------------------------------------
-- A six-digit code has a million possible values, so SHA-256 of the code
-- alone could be reversed from a table of a million rows. Salting each token
-- makes that table worthless. Links are 256-bit random and do not need it,
-- but get it anyway for uniformity.
ALTER TABLE login_tokens
  ADD COLUMN salt CHAR(32) NOT NULL DEFAULT '' AFTER token_hash,
  DROP INDEX uq_login_token_hash,
  ADD KEY idx_login_token_hash (token_hash);

-- ---------------------------------------------------------------------------
-- Throttling keyed on what was typed, not on a username
-- ---------------------------------------------------------------------------
ALTER TABLE login_attempts RENAME COLUMN username TO identifier;
ALTER TABLE login_attempts MODIFY identifier VARCHAR(255) NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------------
-- The audit log names people, not usernames
-- ---------------------------------------------------------------------------
ALTER TABLE audit_log
  ADD COLUMN person_id BIGINT UNSIGNED NULL AFTER id,
  ADD KEY idx_audit_person (person_id);

-- ---------------------------------------------------------------------------
-- And there are no more passwords
-- ---------------------------------------------------------------------------
-- The one row here was already migrated to people + team_members in 0003.
-- team_members.legacy_admin_user_id kept the bridge; with the table gone the
-- bridge has nothing to point at.
DROP TABLE admin_users;
ALTER TABLE team_members DROP COLUMN legacy_admin_user_id;
