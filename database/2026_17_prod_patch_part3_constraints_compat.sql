-- Part 3/3: constraints, checks, compatibility triggers and control queries
SET NAMES utf8mb4;
SET @old_sql_mode := @@SESSION.sql_mode;

DELIMITER $$
DROP PROCEDURE IF EXISTS yg_add_fk $$
CREATE PROCEDURE yg_add_fk(IN p_table VARCHAR(64), IN p_constraint VARCHAR(64), IN p_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = p_table
      AND CONSTRAINT_NAME = p_constraint AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    SET @yg_sql = CONCAT('ALTER TABLE `', p_table, '` ', p_definition);
    PREPARE yg_stmt FROM @yg_sql; EXECUTE yg_stmt; DEALLOCATE PREPARE yg_stmt;
  END IF;
END $$
DELIMITER ;

UPDATE `cart_items` ci LEFT JOIN `purchase_batches` pb ON pb.id = ci.purchase_batch_id
SET ci.purchase_batch_id = NULL
WHERE ci.purchase_batch_id IS NOT NULL AND pb.id IS NULL;

CALL yg_add_fk('cart_items', 'fk_cart_items_purchase_batch',
  'ADD CONSTRAINT `fk_cart_items_purchase_batch` FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL');

CALL yg_add_fk('purchase_batches', 'fk_purchase_batches_product',
  'ADD CONSTRAINT `fk_purchase_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT');
CALL yg_add_fk('purchase_batches', 'fk_purchase_batches_buyer',
  'ADD CONSTRAINT `fk_purchase_batches_buyer` FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('pricing_preorder_margin_percent', '30'),
  ('pricing_instant_margin_percent', '50'),
  ('pricing_discount_stock_markup_fixed', '100'),
  ('pricing_rounding_step', '10'),
  ('pricing_free_boxes_default', '10'),
  ('pricing_discount_stock_bonuses_allowed', '0'),
  ('pricing_discount_stock_coupons_allowed', '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

DELIMITER $$
DROP TRIGGER IF EXISTS trg_users_bi_defaults $$
CREATE TRIGGER trg_users_bi_defaults
BEFORE INSERT ON `users`
FOR EACH ROW
BEGIN
  IF NEW.password_hash IS NULL OR NEW.password_hash = '' THEN
    SET NEW.password_hash = CONCAT('bot-only:', SHA2(UUID(), 256));
  END IF;
END $$
DELIMITER ;

SELECT 'cart_items' AS table_name, COUNT(*) AS rows_count,
       SUM(CASE WHEN boxes IS NULL OR sale_price_per_box IS NULL OR stock_mode IS NULL THEN 1 ELSE 0 END) AS bad_rows
FROM cart_items;

DROP PROCEDURE IF EXISTS yg_add_fk;
SET SESSION sql_mode = @old_sql_mode;
