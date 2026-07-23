-- Follow-up for installations that already applied the initial Florix24 release.
ALTER TABLE integration_clients
  ADD COLUMN token_prefix VARCHAR(32) NOT NULL DEFAULT '' AFTER token_hash,
  ADD COLUMN ip_check_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER allowed_ips,
  ADD COLUMN trusted_proxy_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER ip_check_enabled,
  ADD COLUMN last_used_at DATETIME NULL,
  ADD COLUMN expires_at DATETIME NULL,
  ADD COLUMN revoked_at DATETIME NULL;
ALTER TABLE users ADD COLUMN integration_partner_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN external_updated_at DATETIME NULL;
ALTER TABLE points_transactions MODIFY COLUMN transaction_type ENUM('accrual','usage','payout','refund','partner_reward','partner_reward_reversal') NOT NULL;
CREATE TABLE IF NOT EXISTS integration_rate_limit_windows (
  integration_client_id BIGINT UNSIGNED NOT NULL,
  window_started_at DATETIME NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (integration_client_id, window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS catalog_feed_state (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  is_dirty TINYINT(1) NOT NULL DEFAULT 1,
  generated_at DATETIME NULL,
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO catalog_feed_state (id, is_dirty) VALUES (1, 1);
