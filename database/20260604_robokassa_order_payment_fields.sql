CALL yg_add_column_if_missing('orders', '`payment_status` varchar(32) NOT NULL DEFAULT ''pending''');
CALL yg_add_column_if_missing('orders', '`payment_provider` varchar(32) DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`payment_invoice_id` bigint UNSIGNED DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`payment_amount` decimal(10,2) DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`paid_at` datetime DEFAULT NULL');
CALL yg_add_column_if_missing('orders', '`payment_raw_response` text');
