-- Excursion assistants on a cenote day: a role of their own on the event team.
ALTER TABLE event_team
  MODIFY COLUMN role ENUM('lead','instructor','guide','assistant','driver','support') NOT NULL DEFAULT 'guide';
