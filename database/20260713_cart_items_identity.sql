-- PR 5: allow the same product in the cart in different selling modes/batches.
-- Before applying in production, check that the future unique key has no duplicates:
-- SELECT user_id, product_id, stock_mode, purchase_batch_id, COUNT(*) AS cnt
-- FROM cart_items
-- GROUP BY user_id, product_id, stock_mode, purchase_batch_id
-- HAVING cnt > 1;

ALTER TABLE cart_items
  DROP PRIMARY KEY,
  ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (id),
  ADD UNIQUE KEY uniq_cart_items_user_product_mode_batch (user_id, product_id, stock_mode, purchase_batch_id),
  ADD KEY idx_cart_items_user_id (user_id),
  ADD KEY idx_cart_items_product_id (product_id);
