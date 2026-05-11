ALTER TABLE `orders`
  ADD COLUMN `order_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  ADD COLUMN `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `reserved_at` datetime DEFAULT NULL,
  ADD COLUMN `fulfilled_from_stock_at` datetime DEFAULT NULL,
  ADD COLUMN `bonuses_allowed` tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN `coupons_allowed` tinyint(1) NOT NULL DEFAULT 1,
  ADD KEY `idx_orders_order_mode` (`order_mode`),
  ADD KEY `idx_orders_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_orders_delivery_mode` (`delivery_date`, `order_mode`),
  ADD CONSTRAINT `fk_orders_purchase_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;
