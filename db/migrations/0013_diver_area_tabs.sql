-- The diver area gets tabs once onboarding is complete, and every form
-- expires after twelve months.

ALTER TABLE customers
  ADD COLUMN onboarded_at DATETIME NULL AFTER discount_pct;

UPDATE form_templates SET validity_days = 365 WHERE code <> 'diver_info';

UPDATE form_submissions s
  JOIN form_templates t ON t.id = s.template_id
  SET s.expires_on = DATE_ADD(DATE(s.signed_at), INTERVAL 365 DAY)
  WHERE s.expires_on IS NULL AND s.signed_at IS NOT NULL AND t.code <> 'diver_info';
