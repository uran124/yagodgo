ALTER TABLE users
  ADD COLUMN work_mode ENUM('berrygo_store','own_store','warehouse_delivery') NOT NULL DEFAULT 'berrygo_store' AFTER delivery_cost;
