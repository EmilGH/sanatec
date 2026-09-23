-- Team: the people who work here.
--
-- A team member is a person with a role and permissions. Two separate ideas
-- are kept apart on purpose:
--
--   what someone IS   — instructor, divemaster, cave guide, driver. Used by
--                       scheduling, and by the forms: the instructors on a
--                       course are printed onto each student's liability
--                       release automatically.
--   what someone MAY  — the five admin permissions. Used by the back end to
--                       decide which sections a person can open and change.
--
-- The current admin_users table is migrated into this structure but not
-- dropped. Password login keeps working until passwordless login ships; a
-- later migration removes admin_users once nothing reads it.

-- ---------------------------------------------------------------------------
-- team_members
-- ---------------------------------------------------------------------------
CREATE TABLE team_members (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  person_id              BIGINT UNSIGNED NOT NULL,

  is_active              TINYINT(1) NOT NULL DEFAULT 1,

  -- A system administrator can do everything regardless of the permission
  -- flags, and is the only role that can grant itself. There is no notion of
  -- an "owner": the business owner is a team member like anyone else.
  is_system_admin        TINYINT(1) NOT NULL DEFAULT 0,

  -- What someone is. These are declarations; the paperwork that backs them
  -- (and expires) lives in team_credentials below.
  is_instructor          TINYINT(1) NOT NULL DEFAULT 0,
  is_divemaster          TINYINT(1) NOT NULL DEFAULT 0,
  is_cave_guide          TINYINT(1) NOT NULL DEFAULT 0,   -- guiding in cenotes needs full-cave certification
  is_driver              TINYINT(1) NOT NULL DEFAULT 0,   -- cenote days start in a van, not a boat

  -- What someone may do in the admin.
  can_manage_customers   TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_excursions  TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_training    TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_catalog     TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_team        TINYINT(1) NOT NULL DEFAULT 0,

  -- Employment.
  job_title              VARCHAR(80)  NULL,
  started_on             DATE         NULL,
  ended_on               DATE         NULL,
  internal_notes         TEXT         NULL,   -- never shown publicly

  -- Public profile. Off by default; the person switches it on themselves.
  profile_public         TINYINT(1)   NOT NULL DEFAULT 0,
  public_slug            VARCHAR(80)  NULL,   -- /team/<slug>
  title_en               VARCHAR(80)  NULL,   -- "Cave Guide", "Open Water Instructor"
  title_es               VARCHAR(80)  NULL,
  bio_en                 TEXT         NULL,
  bio_es                 TEXT         NULL,
  photo_path             VARCHAR(255) NULL,
  languages              JSON         NULL,   -- ["es","en","de"] — ISO 639-1; a real filter for customers
  sort_order             INT          NOT NULL DEFAULT 0,

  -- Bridge back to the row this was migrated from. Dropped with admin_users.
  legacy_admin_user_id   INT UNSIGNED NULL,

  created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_team_person (person_id),
  UNIQUE KEY uq_team_slug (public_slug),
  KEY idx_team_active (is_active, sort_order),

  -- RESTRICT, not CASCADE: a person who was ever staff is soft-deleted, never
  -- silently removed along with their audit trail.
  CONSTRAINT fk_team_person
    FOREIGN KEY (person_id) REFERENCES people (id) ON DELETE RESTRICT,

  CONSTRAINT chk_team_dates
    CHECK (ended_on IS NULL OR started_on IS NULL OR ended_on >= started_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- team_credentials — the paperwork behind the flags, with expiry dates
-- ---------------------------------------------------------------------------
-- Instructor status, professional liability insurance and first-aid/oxygen
-- provider cards all renew on a cycle. An instructor whose insurance lapsed
-- last month cannot legally teach this month, and nobody notices until a
-- claim. expires_on is what the dashboard warns about.
CREATE TABLE team_credentials (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  team_member_id   BIGINT UNSIGNED NOT NULL,
  kind             ENUM('instructor','divemaster','cave','cavern','first_aid','oxygen',
                        'insurance','medical','other') NOT NULL,
  agency           VARCHAR(40)  NULL,     -- PADI, TDI, DAN, insurer
  title            VARCHAR(120) NULL,     -- "Open Water Scuba Instructor", "Full Cave Diver"
  number           VARCHAR(80)  NULL,
  issued_on        DATE         NULL,
  expires_on       DATE         NULL,     -- NULL = does not expire
  document_path    VARCHAR(255) NULL,     -- scan of the card or certificate
  verified_by      BIGINT UNSIGNED NULL,  -- team member who checked the original
  verified_at      DATETIME     NULL,
  notes            VARCHAR(255) NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_credentials_member (team_member_id),
  KEY idx_credentials_expiry (expires_on),

  CONSTRAINT fk_credentials_member
    FOREIGN KEY (team_member_id) REFERENCES team_members (id) ON DELETE CASCADE,
  CONSTRAINT fk_credentials_verifier
    FOREIGN KEY (verified_by) REFERENCES team_members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Migrate the existing admin accounts
-- ---------------------------------------------------------------------------
-- Each admin_users row becomes a person plus a system administrator. Names,
-- email addresses and phone numbers are deliberately NOT written here: this
-- file is in a repository that may be public. bin/sysadmin.php sets the
-- administrator's real identity and contact channels on the server.
-- admin_users itself is dropped by the migration that ships passwordless
-- login, together with the code that stops reading it.

ALTER TABLE people ADD COLUMN legacy_admin_user_id INT UNSIGNED NULL;

INSERT INTO people (name, legacy_admin_user_id, created_at)
SELECT COALESCE(NULLIF(TRIM(display_name), ''), username), id, created_at
FROM admin_users;

INSERT INTO team_members (
  person_id, is_system_admin, is_active,
  can_manage_customers, can_manage_excursions, can_manage_training,
  can_manage_catalog, can_manage_team,
  job_title, legacy_admin_user_id, created_at
)
SELECT p.id, 1, 1, 1, 1, 1, 1, 1, 'System Administrator', a.id, a.created_at
FROM admin_users a
JOIN people p ON p.legacy_admin_user_id = a.id;

ALTER TABLE people DROP COLUMN legacy_admin_user_id;
