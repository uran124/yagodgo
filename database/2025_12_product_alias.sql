ALTER TABLE product_types
  ADD COLUMN alias VARCHAR(255) NOT NULL AFTER name,
  ADD UNIQUE KEY alias (alias);

ALTER TABLE products
  ADD COLUMN alias VARCHAR(255) NOT NULL AFTER product_type_id,
  ADD UNIQUE KEY alias (alias);
