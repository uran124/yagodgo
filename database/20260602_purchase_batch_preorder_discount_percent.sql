ALTER TABLE `purchase_batches`
  ADD COLUMN `preorder_discount_percent` DECIMAL(5,2) NOT NULL DEFAULT 10.00 AFTER `preorder_margin_percent`;
