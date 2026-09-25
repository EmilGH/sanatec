-- Names the design settled, and the fields the mockups assume.
--
-- "routes" was the name the price poster suggested; the business calls them
-- excursions, and so does everything from here on. Courses and excursions get
-- a slug for share links (/c/dos-ojos) and generated link-preview images.

RENAME TABLE routes TO excursions;

ALTER TABLE courses    ADD COLUMN slug VARCHAR(80) NULL AFTER id;
ALTER TABLE excursions ADD COLUMN slug VARCHAR(80) NULL AFTER id;

-- Slug from the English name: lower-case, runs of anything non-alphanumeric
-- become one hyphen, trimmed. Collisions get the id appended.
UPDATE courses    SET slug = TRIM(BOTH '-' FROM LOWER(REGEXP_REPLACE(name_en, '[^A-Za-z0-9]+', '-')));
UPDATE excursions SET slug = TRIM(BOTH '-' FROM LOWER(REGEXP_REPLACE(name_en, '[^A-Za-z0-9]+', '-')));
UPDATE courses    c JOIN (SELECT slug FROM courses    GROUP BY slug HAVING COUNT(*) > 1) d ON d.slug = c.slug SET c.slug = CONCAT(c.slug, '-', c.id);
UPDATE excursions e JOIN (SELECT slug FROM excursions GROUP BY slug HAVING COUNT(*) > 1) d ON d.slug = e.slug SET e.slug = CONCAT(e.slug, '-', e.id);

ALTER TABLE courses    MODIFY slug VARCHAR(80) NOT NULL, ADD UNIQUE KEY uq_courses_slug (slug);
ALTER TABLE excursions MODIFY slug VARCHAR(80) NOT NULL, ADD UNIQUE KEY uq_excursions_slug (slug);

-- Customer page: an ID document on file, and gear the diver brings themselves.
ALTER TABLE customers
  ADD COLUMN id_document_kind    VARCHAR(40)  NULL AFTER source,     -- passport, INE, driver's licence
  ADD COLUMN id_document_on_file TINYINT(1)   NOT NULL DEFAULT 0 AFTER id_document_kind,
  ADD COLUMN own_gear            VARCHAR(255) NULL AFTER id_document_on_file;

-- Team page: an instructor may choose to show their WhatsApp number publicly.
ALTER TABLE team_members
  ADD COLUMN show_whatsapp_public TINYINT(1) NOT NULL DEFAULT 0 AFTER profile_public;

-- The name, and the address the public page shows. Both remain editable in
-- Business info; this only sets them where the seed value is still in place.
UPDATE settings SET val_en = 'SANA TEC DIVING' WHERE skey = 'business_name' AND val_en = 'SanaTec Diving';
UPDATE settings SET val_en = 'hola@sanatecdiving.com' WHERE skey = 'contact_email' AND (val_en IS NULL OR val_en = '');
UPDATE settings SET val_en = REPLACE(val_en, 'SanaTec Diving', 'SANA TEC DIVING'), val_es = REPLACE(val_es, 'SanaTec Diving', 'SANA TEC DIVING')
  WHERE skey IN ('meta_title', 'hero_intro', 'meta_description');
UPDATE settings SET val_en = '' WHERE skey = 'og_image' AND val_en = 'assets/og-image.jpg';   -- empty: use the generated preview
