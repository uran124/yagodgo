ALTER TABLE orders
  ADD COLUMN created_by_user_id INT NULL AFTER delivery_date;
