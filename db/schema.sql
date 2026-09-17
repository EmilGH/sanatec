-- SanaTec Diving — catalog schema (MySQL 8.0)
--
-- Money: all prices are Mexican pesos (MXN), stored as DECIMAL(10,2).
-- A NULL price means "no price published for this option" and renders as an
-- em dash on the public site (or "Ask for pricing" where a course has none).
--
-- Bilingual: EN/ES are paired columns rather than a translations table. For a
-- catalogue of ~25 rows and two languages this keeps both the queries and the
-- admin form trivial (the owner edits both languages side by side). Adding a
-- third language later is an ALTER TABLE plus one more field per form row.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  skey        VARCHAR(64)  NOT NULL,
  val_en      TEXT         NULL,
  val_es      TEXT         NULL,
  updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name_en      VARCHAR(160) NOT NULL,
  name_es      VARCHAR(160) NOT NULL,
  price_mxn    DECIMAL(10,2) NULL,          -- NULL => "Ask for pricing"
  duration_en  VARCHAR(80)  NOT NULL DEFAULT '',
  duration_es  VARCHAR(80)  NOT NULL DEFAULT '',
  note_en      VARCHAR(255) NULL,
  note_es      VARCHAR(255) NULL,
  sort_order   INT          NOT NULL DEFAULT 0,
  is_published TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_courses_listing (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cenote adventure routes.
--
-- price_1_dive / price_2_dives / price_3_dives are TOTAL prices for a trip of
-- that many dives, not the incremental cost of each successive dive. This is
-- the reading the source guide supports: Dreamgate has no 1-dive price but a
-- 2-dive price of $3,500, which is only coherent as "a two-dive trip costs
-- $3,500 and this route is not sold as a single dive".
-- >>> Confirm with the shop before publishing. If it is wrong, the fix is the
-- >>> column headings in templates/public.php, not the data.
CREATE TABLE IF NOT EXISTS routes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name_en       VARCHAR(160) NOT NULL,
  name_es       VARCHAR(160) NOT NULL,
  price_1_dive  DECIMAL(10,2) NULL,
  price_2_dives DECIMAL(10,2) NULL,
  price_3_dives DECIMAL(10,2) NULL,
  cert_en       VARCHAR(120) NOT NULL DEFAULT '',
  cert_es       VARCHAR(120) NOT NULL DEFAULT '',
  is_special_price TINYINT(1) NOT NULL DEFAULT 0,   -- renders the * footnote marker
  sort_order    INT          NOT NULL DEFAULT 0,
  is_published  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_routes_listing (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username      VARCHAR(64)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(120) NOT NULL DEFAULT '',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME     NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed-login throttling. Rows are pruned on write; no cron needed.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip           VARBINARY(16) NOT NULL,
  username     VARCHAR(64)   NOT NULL DEFAULT '',
  succeeded    TINYINT(1)    NOT NULL DEFAULT 0,
  attempted_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Who changed which price, and when. Cheap to write, invaluable in an argument.
CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user VARCHAR(64)  NOT NULL DEFAULT '',
  action     VARCHAR(32)  NOT NULL,
  entity     VARCHAR(32)  NOT NULL,
  entity_id  VARCHAR(64)  NOT NULL DEFAULT '',
  summary    VARCHAR(500) NOT NULL DEFAULT '',
  ip         VARBINARY(16) NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
