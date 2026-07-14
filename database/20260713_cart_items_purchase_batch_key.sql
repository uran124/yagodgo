-- PR 6: make mixed-mode cart uniqueness safe when legacy rows still have NULL purchase_batch_id.
-- MySQL treats NULL values in UNIQUE indexes as distinct, so use a generated key that maps NULL to 0.
-- Before applying in production, check that the new key has no duplicates:
-- SELECT user_id, product_id, stock_mode, COALESCE(purchase_batch_id, 0) AS purchase_batch_key, COUNT(*) AS cnt
-- FROM cart_items
-- GROUP BY user_id, product_id, stock_mode, COALESCE(purchase_batch_id, 0)
-- HAVING cnt > 1;

ALTER TABLE cart_items
  ADD COLUMN purchase_batch_key BIGINT UNSIGNED
    GENERATED ALWAYS AS (COALESCE(purchase_batch_id, 0)) STORED
    AFTER purchase_batch_id;

ALTER TABLE cart_items
  DROP INDEX uniq_cart_items_user_product_mode_batch,
  ADD UNIQUE KEY uniq_cart_items_user_product_mode_batch_key (user_id, product_id, stock_mode, purchase_batch_key);
