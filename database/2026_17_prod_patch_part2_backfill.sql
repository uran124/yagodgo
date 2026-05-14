-- Part 2/3: data backfill and normalization
SET NAMES utf8mb4;

ALTER TABLE `users`
  MODIFY `password_hash` varchar(255) NOT NULL DEFAULT '',
  MODIFY `referral_code` varchar(20) NULL DEFAULT NULL;

UPDATE `users`
SET password_hash = CONCAT('bot-only:', SHA2(UUID(), 256))
WHERE password_hash IS NULL OR password_hash = '';

UPDATE `users`
SET referral_code = UPPER(SUBSTRING(REPLACE(UUID(), '-', ''), 1, 8))
WHERE referral_code IS NULL OR referral_code = '';

UPDATE `cart_items` ci
LEFT JOIN `products` p ON p.id = ci.product_id
SET ci.boxes = CASE WHEN ci.boxes IS NULL OR ci.boxes = 0 THEN ci.quantity ELSE ci.boxes END,
    ci.sale_price_per_box = CASE WHEN ci.sale_price_per_box IS NULL OR ci.sale_price_per_box = 0 THEN ci.unit_price ELSE ci.sale_price_per_box END,
    ci.stock_mode = CASE WHEN ci.stock_mode IS NULL OR ci.stock_mode = '' THEN 'instant' ELSE ci.stock_mode END,
    ci.purchase_batch_id = CASE WHEN ci.purchase_batch_id IS NULL THEN p.current_purchase_batch_id ELSE ci.purchase_batch_id END;

UPDATE `order_items` oi
JOIN `products` p ON p.id = oi.product_id
SET oi.boxes = CASE
      WHEN (oi.boxes IS NULL OR oi.boxes = 0) AND p.box_size > 0 THEN ROUND(oi.quantity / p.box_size, 2)
      WHEN (oi.boxes IS NULL OR oi.boxes = 0) THEN oi.quantity
      ELSE oi.boxes
    END,
    oi.sale_price_per_box = CASE
      WHEN (oi.sale_price_per_box IS NULL OR oi.sale_price_per_box = 0) AND p.box_size > 0 THEN ROUND(oi.unit_price * p.box_size, 2)
      WHEN (oi.sale_price_per_box IS NULL OR oi.sale_price_per_box = 0) THEN oi.unit_price
      ELSE oi.sale_price_per_box
    END,
    oi.stock_mode = CASE WHEN oi.stock_mode IS NULL OR oi.stock_mode = '' THEN 'instant' ELSE oi.stock_mode END;

UPDATE `products`
SET instant_unit_price = CASE WHEN instant_unit_price = 0 THEN IF(sale_price > 0, sale_price, price) ELSE instant_unit_price END,
    preorder_unit_price = CASE WHEN preorder_unit_price = 0 THEN IF(sale_price > 0, sale_price, price) ELSE preorder_unit_price END,
    discount_unit_price = CASE WHEN discount_unit_price = 0 THEN IF(sale_price > 0, sale_price, price) ELSE discount_unit_price END,
    instant_price_per_box = CASE WHEN instant_price_per_box = 0 AND box_size > 0 THEN ROUND(IF(sale_price > 0, sale_price, price) * box_size, 2) ELSE instant_price_per_box END,
    preorder_price_per_box = CASE WHEN preorder_price_per_box = 0 AND box_size > 0 THEN ROUND(IF(sale_price > 0, sale_price, price) * box_size, 2) ELSE preorder_price_per_box END,
    discount_price_per_box = CASE WHEN discount_price_per_box = 0 AND box_size > 0 THEN ROUND(IF(sale_price > 0, sale_price, price) * box_size, 2) ELSE discount_price_per_box END;
