-- One more operating role: helps on cenote days without leading the dive.
ALTER TABLE team_members
  ADD COLUMN is_excursion_assistant TINYINT(1) NOT NULL DEFAULT 0 AFTER is_equipment_tech;
