-- Four more things a team member can be, and "Business info" as its own
-- permission instead of riding on "Catalog".

ALTER TABLE team_members
  ADD COLUMN is_cavern_guide   TINYINT(1) NOT NULL DEFAULT 0 AFTER is_cave_guide,
  ADD COLUMN is_shop_help      TINYINT(1) NOT NULL DEFAULT 0 AFTER is_driver,
  ADD COLUMN is_equipment_tech TINYINT(1) NOT NULL DEFAULT 0 AFTER is_shop_help,
  ADD COLUMN is_gas_tech       TINYINT(1) NOT NULL DEFAULT 0 AFTER is_equipment_tech,
  ADD COLUMN can_manage_business TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage_catalog;

-- Whoever could edit the catalogue could edit business info until now.
UPDATE team_members SET can_manage_business = can_manage_catalog;
