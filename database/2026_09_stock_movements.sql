CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `movement_type` enum(
    'purchase',
    'reserve',
    'unreserve',
    'sale',
    'return_to_stock',
    'move_to_discount',
    'writeoff',
    'correction'
  ) NOT NULL,
  `stock_mode` enum('preorder','instant','discount_stock','internal') NOT NULL DEFAULT 'internal',
  `boxes_delta` decimal(10,2) NOT NULL,
  `boxes_balance_after` decimal(10,2) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_batch` (`purchase_batch_id`),
  KEY `idx_stock_movements_product` (`product_id`),
  KEY `idx_stock_movements_order` (`order_id`),
  KEY `idx_stock_movements_type` (`movement_type`),
  CONSTRAINT `fk_stock_movements_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_movements_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_movements_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_movements_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
