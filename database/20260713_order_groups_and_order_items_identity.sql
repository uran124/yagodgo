-- PR 1: linked order groups and order_items identity key.
-- The UNIQUE KEY below intentionally verifies there are no duplicate rows for
-- (order_id, product_id, purchase_batch_id, stock_mode): if duplicates exist,
-- MySQL will reject the migration and keep the transaction from being recorded.

CREATE TABLE `order_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `created_by_user_id` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comment` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_groups_user` (`user_id`),
  KEY `idx_order_groups_created_by` (`created_by_user_id`),
  CONSTRAINT `fk_order_groups_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_groups_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `orders`
  ADD COLUMN `order_group_id` BIGINT UNSIGNED DEFAULT NULL AFTER `id`,
  ADD KEY `idx_orders_order_group_id` (`order_group_id`),
  ADD CONSTRAINT `fk_orders_order_group` FOREIGN KEY (`order_group_id`) REFERENCES `order_groups` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_items`
  DROP PRIMARY KEY,
  ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_items_order_id` (`order_id`),
  ADD UNIQUE KEY `uniq_order_items_order_product_batch_mode` (`order_id`, `product_id`, `purchase_batch_id`, `stock_mode`);
