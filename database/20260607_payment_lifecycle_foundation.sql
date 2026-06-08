CALL yg_add_column_if_missing('orders', '`payment_method` varchar(32) DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`refunded_at` datetime DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`refund_comment` text');

ALTER TABLE orders
  MODIFY payment_status varchar(32) NOT NULL DEFAULT 'unpaid';

UPDATE orders
   SET payment_status = CASE
       WHEN payment_status IS NULL OR payment_status = '' THEN 'unpaid'
       WHEN payment_status = 'returned_success' THEN 'pending'
       ELSE payment_status
   END;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('payment_method_online_robokassa_enabled', '1'),
  ('payment_method_cash_on_delivery_enabled', '1'),
  ('payment_method_cash_pickup_enabled', '1'),
  ('payment_method_card_on_delivery_enabled', '0'),
  ('payment_method_card_pickup_enabled', '0')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
