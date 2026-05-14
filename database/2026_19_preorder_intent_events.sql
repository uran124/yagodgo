CREATE TABLE IF NOT EXISTS `preorder_intent_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `preorder_intent_id` bigint unsigned NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) DEFAULT NULL,
  `meta_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_preorder_intent_events_intent` (`preorder_intent_id`, `created_at`),
  KEY `idx_preorder_intent_events_event` (`event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
