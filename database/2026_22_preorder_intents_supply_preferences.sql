ALTER TABLE `preorder_intents`
  ADD COLUMN `desired_delivery_date` date DEFAULT NULL AFTER `requested_boxes`,
  ADD COLUMN `expected_price_per_box` decimal(10,2) DEFAULT NULL AFTER `offered_price_per_box`,
  ADD COLUMN `discount_percent_snapshot` decimal(5,2) DEFAULT NULL AFTER `expected_price_per_box`;
