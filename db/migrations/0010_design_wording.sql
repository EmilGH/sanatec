-- Section wording as the design set it. Only where the seed text is still in
-- place; anything the shop has edited is left alone.
UPDATE settings SET val_en = 'Dive training',   val_es = 'Formación de buceo'   WHERE skey = 'training_eyebrow'   AND val_en = '01 / Build your skills';
UPDATE settings SET val_en = 'Build your skills', val_es = 'Desarrolla tus habilidades' WHERE skey = 'training_title' AND val_en = 'Dive training';
UPDATE settings SET val_en = 'Adventure dives',  val_es = 'Buceos recreativos'  WHERE skey = 'adventures_eyebrow' AND val_en = '02 / Explore the cenotes';
UPDATE settings SET val_en = 'The cenotes',      val_es = 'Los cenotes'         WHERE skey = 'adventures_title'   AND val_en = 'Adventure dives';
