-- Part 1/3: schema-only changes (DDL)
SET NAMES utf8mb4;
SET @old_sql_mode := @@SESSION.sql_mode;

DELIMITER $$
DROP PROCEDURE IF EXISTS yg_add_column $$
CREATE PROCEDURE yg_add_column(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column
  ) THEN
    SET @yg_sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN ', p_definition);
    PREPARE yg_stmt FROM @yg_sql; EXECUTE yg_stmt; DEALLOCATE PREPARE yg_stmt;
  END IF;
END $$

DROP PROCEDURE IF EXISTS yg_add_index $$
CREATE PROCEDURE yg_add_index(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_definition TEXT)
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index
  ) THEN
    SET @yg_sql = CONCAT('ALTER TABLE `', p_table, '` ', p_definition);
    PREPARE yg_stmt FROM @yg_sql; EXECUTE yg_stmt; DEALLOCATE PREPARE yg_stmt;
  END IF;
END $$

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

CREATE TABLE IF NOT EXISTS `purchase_batches` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int UNSIGNED NOT NULL,
  `buyer_user_id` int UNSIGNED DEFAULT NULL,
  `purchased_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `arrived_at` datetime DEFAULT NULL,
  `box_size_snapshot` decimal(10,2) NOT NULL DEFAULT 0,
  `box_unit_snapshot` enum('кг','л') NOT NULL DEFAULT 'кг',
  `boxes_total` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_reserved` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_free` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_sold` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_discount` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_written_off` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_remaining` decimal(10,2) NOT NULL DEFAULT 0,
  `purchase_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `extra_cost_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `cost_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `preorder_margin_percent` decimal(5,2) NOT NULL DEFAULT 30.00,
  `instant_margin_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `discount_markup_fixed` decimal(10,2) NOT NULL DEFAULT 100.00,
  `preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `instant_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `discount_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `preorder_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  `instant_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  `discount_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  `status` enum('planned','purchased','arrived','active','sold_out','closed','cancelled') NOT NULL DEFAULT 'purchased',
  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_batches_product` (`product_id`),
  KEY `idx_purchase_batches_buyer` (`buyer_user_id`),
  KEY `idx_purchase_batches_status` (`status`),
  KEY `idx_purchase_batches_purchased_at` (`purchased_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `movement_type` enum('purchase','reserve','unreserve','sale','return_to_stock','move_to_discount','writeoff','correction') NOT NULL,
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
  KEY `fk_stock_movements_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `purchase_batch_photos` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_batch_photos_batch` (`purchase_batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL yg_add_column('products', 'current_purchase_batch_id', '`current_purchase_batch_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_column('addresses', 'street', '`street` varchar(255) NOT NULL DEFAULT ''''');
CALL yg_add_column('addresses', 'recipient_name', '`recipient_name` varchar(100) NOT NULL DEFAULT ''''');
CALL yg_add_column('addresses', 'recipient_phone', '`recipient_phone` varchar(20) NOT NULL DEFAULT ''''');
CALL yg_add_column('addresses', 'is_primary', '`is_primary` tinyint(1) NOT NULL DEFAULT 0');
CALL yg_add_column('users', 'address_id', '`address_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_index('users', 'idx_users_address_id', 'ADD KEY `idx_users_address_id` (`address_id`)');

CALL yg_add_column('cart_items', 'purchase_batch_id', '`purchase_batch_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_column('cart_items', 'stock_mode', '`stock_mode` enum(''preorder'',''instant'',''discount_stock'') NOT NULL DEFAULT ''instant''');
CALL yg_add_column('cart_items', 'boxes', '`boxes` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('cart_items', 'sale_price_per_box', '`sale_price_per_box` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_index('cart_items', 'idx_cart_items_purchase_batch', 'ADD KEY `idx_cart_items_purchase_batch` (`purchase_batch_id`)');
CALL yg_add_index('cart_items', 'idx_cart_items_stock_mode', 'ADD KEY `idx_cart_items_stock_mode` (`stock_mode`)');

CALL yg_add_column('orders', 'order_mode', '`order_mode` enum(''preorder'',''instant'',''discount_stock'') NOT NULL DEFAULT ''instant''');
CALL yg_add_column('orders', 'purchase_batch_id', '`purchase_batch_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_column('orders', 'reserved_at', '`reserved_at` datetime DEFAULT NULL');
CALL yg_add_column('orders', 'fulfilled_from_stock_at', '`fulfilled_from_stock_at` datetime DEFAULT NULL');
CALL yg_add_column('orders', 'bonuses_allowed', '`bonuses_allowed` tinyint(1) NOT NULL DEFAULT 1');
CALL yg_add_column('orders', 'coupons_allowed', '`coupons_allowed` tinyint(1) NOT NULL DEFAULT 1');

CALL yg_add_column('order_items', 'purchase_batch_id', '`purchase_batch_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_column('order_items', 'stock_mode', '`stock_mode` enum(''preorder'',''instant'',''discount_stock'') NOT NULL DEFAULT ''instant''');
CALL yg_add_column('order_items', 'cost_unit_price', '`cost_unit_price` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('order_items', 'cost_price_per_box', '`cost_price_per_box` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('order_items', 'sale_price_per_box', '`sale_price_per_box` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('order_items', 'margin_amount', '`margin_amount` decimal(10,2) NOT NULL DEFAULT 0');

CALL yg_add_column('products', 'free_stock_boxes', '`free_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'reserved_stock_boxes', '`reserved_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'discount_stock_boxes', '`discount_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'sold_stock_boxes', '`sold_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'written_off_stock_boxes', '`written_off_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'preorder_price_per_box', '`preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'instant_price_per_box', '`instant_price_per_box` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'discount_price_per_box', '`discount_price_per_box` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'preorder_unit_price', '`preorder_unit_price` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'instant_unit_price', '`instant_unit_price` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'discount_unit_price', '`discount_unit_price` decimal(10,2) NOT NULL DEFAULT 0');
CALL yg_add_column('products', 'stock_status', '`stock_status` enum(''in_stock'',''preorder'',''arriving_today'',''sold_out'',''hidden'') NOT NULL DEFAULT ''sold_out''');

SET SESSION sql_mode = @old_sql_mode;
