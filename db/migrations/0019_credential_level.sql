-- Staff credentials pick their title from the one certification list; the
-- code is kept so the row can be edited back into the same choice. Rows that
-- predate this keep their typed title and read as "Other" until re-saved.
ALTER TABLE team_credentials
  ADD COLUMN level VARCHAR(40) NULL AFTER kind;

UPDATE team_credentials SET level = CASE
  WHEN LOWER(title) IN ('open water', 'open water diver')                   THEN 'ow'
  WHEN LOWER(title) IN ('advanced open water', 'advanced open water diver') THEN 'aow'
  WHEN LOWER(title) IN ('rescue diver', 'rescue')                           THEN 'rescue'
  WHEN LOWER(title) IN ('enriched air', 'enriched air diver', 'nitrox')     THEN 'nitrox'
  WHEN LOWER(title) IN ('full cave', 'full cave diver')                     THEN 'full_cave'
  WHEN LOWER(title) IN ('divemaster')                                       THEN 'dm'
  WHEN LOWER(title) LIKE 'efr%'                                             THEN 'efr'
  ELSE NULL END
WHERE level IS NULL;
