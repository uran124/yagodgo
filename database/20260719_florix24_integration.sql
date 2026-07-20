CREATE TABLE IF NOT EXISTS florix24_order_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id INT UNSIGNED NOT NULL,
  external_order_id VARCHAR(128) NOT NULL,
  florix_order_id BIGINT UNSIGNED DEFAULT NULL,
  florix_order_number VARCHAR(128) DEFAULT NULL,
  sync_status ENUM('pending','processing','sent','error','conflict') NOT NULL DEFAULT 'pending',
  last_synced_at DATETIME DEFAULT NULL,
  last_error TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_florix24_order_links_order_id (order_id),
  UNIQUE KEY uq_florix24_order_links_external_order_id (external_order_id),
  KEY idx_florix24_order_links_sync_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS integration_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_code VARCHAR(64) NOT NULL,
  event_id VARCHAR(191) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  payload_json MEDIUMTEXT NOT NULL,
  status ENUM('pending','processing','sent','error','conflict') NOT NULL DEFAULT 'pending',
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME DEFAULT NULL,
  last_attempt_at DATETIME DEFAULT NULL,
  response_http_code SMALLINT UNSIGNED DEFAULT NULL,
  response_body MEDIUMTEXT DEFAULT NULL,
  last_error TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integration_outbox_event (integration_code, event_id),
  KEY idx_integration_outbox_queue (integration_code, status, next_attempt_at, id),
  KEY idx_integration_outbox_entity (integration_code, entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS integration_inbox_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  integration_code VARCHAR(64) NOT NULL,
  event_id VARCHAR(191) NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  payload_json MEDIUMTEXT NOT NULL,
  status ENUM('received','processed','error','conflict') NOT NULL DEFAULT 'received',
  error_message TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_integration_inbox_event (integration_code, event_id),
  KEY idx_integration_inbox_entity (integration_code, entity_type, entity_id),
  KEY idx_integration_inbox_created (integration_code, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('florix24_enabled', '0'),
  ('florix24_base_url', 'https://florix24.ru'),
  ('florix24_api_token', ''),
  ('florix24_webhook_secret', ''),
  ('florix24_send_orders', '1'),
  ('florix24_send_statuses', '1'),
  ('florix24_receive_statuses', '1'),
  ('florix24_auto_retry', '1'),
  ('florix24_enabled_at', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
