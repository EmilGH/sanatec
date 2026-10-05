-- Where a guide takes tips: a small map of service => handle, shown as links
-- on their public profile. JSON so a service can be added without a migration.
ALTER TABLE team_members
  ADD COLUMN tip_handles JSON NULL AFTER languages;
