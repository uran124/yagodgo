INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
  ('pricing_preorder_margin_percent', '30'),
  ('pricing_instant_margin_percent', '50'),
  ('pricing_discount_stock_markup_fixed', '100'),
  ('pricing_rounding_step', '10'),
  ('pricing_free_boxes_default', '10'),
  ('pricing_discount_stock_bonuses_allowed', '0'),
  ('pricing_discount_stock_coupons_allowed', '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
