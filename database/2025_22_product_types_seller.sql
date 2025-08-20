ALTER TABLE product_types
  ADD COLUMN seller_id INT UNSIGNED NULL AFTER text,
  ADD CONSTRAINT fk_product_types_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL;
