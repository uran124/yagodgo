ALTER TABLE users
  MODIFY role enum('client','admin','courier','manager','partner','seller') NOT NULL DEFAULT 'client',
  ADD COLUMN company_name varchar(255) NULL AFTER name,
  ADD COLUMN pickup_address varchar(255) NULL AFTER company_name,
  ADD COLUMN delivery_cost DECIMAL(10,2) NULL DEFAULT 0 AFTER pickup_address;

ALTER TABLE products
  ADD COLUMN seller_id INT UNSIGNED NULL AFTER product_type_id,
  ADD CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE seller_payouts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  gross_amount DECIMAL(10,2) NOT NULL,
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 30.00,
  commission_amount DECIMAL(10,2) NOT NULL,
  payout_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending','scheduled','accrued','paid','cancelled') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  CONSTRAINT fk_sp_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
