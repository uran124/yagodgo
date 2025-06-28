ALTER TABLE products
  ADD COLUMN meta_title VARCHAR(255) DEFAULT NULL AFTER composition,
  ADD COLUMN meta_description VARCHAR(255) DEFAULT NULL AFTER meta_title,
  ADD COLUMN meta_keywords VARCHAR(255) DEFAULT NULL AFTER meta_description;
