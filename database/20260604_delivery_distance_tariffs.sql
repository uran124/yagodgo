CALL yg_add_column_if_missing('addresses', '`delivery_distance_km` decimal(8,3) DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_distance_m` int UNSIGNED DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_lat` decimal(10,7) DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_lng` decimal(10,7) DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_normalized_address` varchar(255) DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_distance_provider` varchar(50) DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_distance_calculated_at` datetime DEFAULT NULL');
CALL yg_add_column_if_missing('addresses', '`delivery_distance_error` text');

CALL yg_add_column_if_missing('orders', '`delivery_fee` int NOT NULL DEFAULT 0');
CALL yg_add_column_if_missing('orders', '`delivery_distance_km` decimal(8,3) DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`delivery_tariff_zone_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`delivery_pricing_source` varchar(50) DEFAULT NULL');

CREATE TABLE IF NOT EXISTS `delivery_tariff_zones` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `min_km` decimal(8,3) NOT NULL,
  `max_km` decimal(8,3) DEFAULT NULL,
  `price_rub` int UNSIGNED NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_delivery_tariff_zones_active_range` (`is_active`, `min_km`, `max_km`),
  KEY `idx_delivery_tariff_zones_sort` (`sort_order`, `min_km`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `delivery_tariff_zones` (`min_km`, `max_km`, `price_rub`, `sort_order`, `is_active`)
SELECT * FROM (
  SELECT 0.000 AS min_km, 4.000 AS max_km, 300 AS price_rub, 10 AS sort_order, 1 AS is_active
  UNION ALL SELECT 4.000, 6.000, 350, 20, 1
) AS defaults
WHERE NOT EXISTS (SELECT 1 FROM `delivery_tariff_zones` LIMIT 1);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('delivery_store_address', 'Самовывоз: 9 мая, 73'),
  ('delivery_default_fee', '300'),
  ('delivery_per_km_from_km', '6'),
  ('delivery_per_km_price', '50'),
  ('delivery_taxi_courier_enabled', '0'),
  ('delivery_taxi_courier_button_text', 'Вызову такси-курьера')
ON DUPLICATE KEY UPDATE `setting_value` = `setting_value`;
