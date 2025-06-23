ALTER TABLE materials
  ADD COLUMN alias VARCHAR(255) NOT NULL AFTER category_id,
  ADD UNIQUE KEY alias (alias);
