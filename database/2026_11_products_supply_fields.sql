ALTER TABLE `products`
  ADD COLUMN `current_purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `free_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `reserved_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `sold_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `written_off_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `instant_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `preorder_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `instant_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `stock_status` enum('in_stock','preorder','arriving_today','sold_out','hidden') NOT NULL DEFAULT 'sold_out',
  ADD KEY `idx_products_current_batch` (`current_purchase_batch_id`),
  ADD KEY `idx_products_stock_status` (`stock_status`),
  ADD CONSTRAINT `fk_products_current_batch`
    FOREIGN KEY (`current_purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;
