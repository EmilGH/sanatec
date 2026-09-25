-- Signatures are drawn, never typed, and staff can record paper forms.
--
-- A drawn signature is an image kept outside the document root; the row
-- records where the signer came from — network address, referring page, the
-- UTM tags they first arrived with — beside when and as whom they signed.
-- A paper form is the same row with source = 'paper', a scan attached, and
-- the team member who recorded it.

ALTER TABLE form_submissions
  ADD COLUMN source          ENUM('online','paper') NOT NULL DEFAULT 'online' AFTER status,
  ADD COLUMN signed_referrer VARCHAR(500)   NULL AFTER signed_user_agent,
  ADD COLUMN signed_utm      JSON           NULL AFTER signed_referrer,
  ADD COLUMN scan_path       VARCHAR(255)   NULL AFTER rendered_pdf_path,
  ADD COLUMN recorded_by     BIGINT UNSIGNED NULL AFTER scan_path,
  ADD CONSTRAINT fk_submissions_recorder FOREIGN KEY (recorded_by) REFERENCES team_members (id) ON DELETE SET NULL;
