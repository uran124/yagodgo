ALTER TABLE `order_items`
  ADD COLUMN `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `stock_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  ADD COLUMN `cost_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `cost_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `sale_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `margin_amount` decimal(10,2) NOT NULL DEFAULT 0,
  ADD KEY `idx_order_items_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_order_items_stock_mode` (`stock_mode`),
  ADD CONSTRAINT `fk_order_items_purchase_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;
