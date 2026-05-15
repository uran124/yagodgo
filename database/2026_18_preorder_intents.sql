CREATE TABLE IF NOT EXISTS `preorder_intents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `product_id` bigint unsigned NOT NULL,
  `requested_boxes` decimal(10,2) NOT NULL,
  `status` enum('intent_created','offer_sent','confirmed','declined','expired','checkout_completed') NOT NULL DEFAULT 'intent_created',
  `offered_price_per_box` decimal(10,2) DEFAULT NULL,
  `offer_expires_at` datetime DEFAULT NULL,
  `checkout_token` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_preorder_intents_checkout_token` (`checkout_token`),
  KEY `idx_preorder_intents_product_status_created` (`product_id`, `status`, `created_at`),
  KEY `idx_preorder_intents_user_product_status` (`user_id`, `product_id`, `status`),
  KEY `idx_preorder_intents_expires_status` (`offer_expires_at`, `status`),
  CONSTRAINT `chk_preorder_intents_requested_boxes` CHECK (`requested_boxes` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
