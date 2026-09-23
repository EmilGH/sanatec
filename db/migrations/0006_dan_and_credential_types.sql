-- DAN membership for everyone, and a simpler shape for staff credentials.
--
-- DAN (Divers Alert Network) insurance is carried by divers and staff alike
-- and is asked for on the diver form, so it lives on people, not on a
-- profile. Number and expiry; the overview warns as it lapses.
ALTER TABLE people
  ADD COLUMN dan_number     VARCHAR(40) NULL AFTER nationality,
  ADD COLUMN dan_expires_on DATE        NULL AFTER dan_number;

-- Credentials are classified as recreational, technical or professional.
-- Technical and professional ratings renew and must carry an expiry;
-- recreational cards do not expire and must not pretend to. The rule is a
-- CHECK so it holds however the row gets written.
ALTER TABLE team_credentials
  MODIFY COLUMN kind ENUM('recreational','technical','professional') NOT NULL,
  MODIFY COLUMN agency VARCHAR(40) NOT NULL,
  MODIFY COLUMN title  VARCHAR(120) NOT NULL,
  ADD CONSTRAINT chk_credential_expiry
    CHECK ((kind = 'recreational' AND expires_on IS NULL) OR (kind <> 'recreational' AND expires_on IS NOT NULL));
