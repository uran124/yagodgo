-- BerryGo inbound Florix24 API and externally published product catalogue.
CREATE TABLE IF NOT EXISTS integration_clients (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  source VARCHAR(50) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  permissions JSON NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  allowed_ips TEXT NULL,
  rate_limit_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_integration_clients_source (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS integration_request_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  integration_client_id BIGINT UNSIGNED NULL,
  source VARCHAR(50) NOT NULL,
  endpoint VARCHAR(255) NOT NULL,
  request_payload JSON NULL,
  response_payload JSON NULL,
  http_status SMALLINT UNSIGNED NOT NULL,
  external_order_id VARCHAR(128) NULL,
  partner_user_id INT UNSIGNED NULL,
  points_used INT NOT NULL DEFAULT 0,
  error_code VARCHAR(64) NULL,
  correlation_id VARCHAR(64) NOT NULL,
  processing_ms INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_integration_request_logs_source_created (source, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
  ADD COLUMN integration_source VARCHAR(50) NULL,
  ADD COLUMN external_order_id VARCHAR(128) NULL,
  ADD COLUMN partner_user_id INT UNSIGNED NULL,
  ADD COLUMN partner_source VARCHAR(50) NULL,
  ADD COLUMN external_partner_id VARCHAR(128) NULL,
  ADD COLUMN external_partner_name VARCHAR(255) NULL,
  ADD COLUMN subtotal_before_points DECIMAL(10,2) NULL,
  ADD COLUMN points_discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN total_after_points DECIMAL(10,2) NULL,
  ADD UNIQUE KEY uq_orders_integration_external (integration_source, external_order_id);

ALTER TABLE points_transactions
  MODIFY COLUMN transaction_type ENUM('accrual','usage','payout','refund') NOT NULL,
  ADD COLUMN source VARCHAR(64) NULL,
  ADD COLUMN external_order_id VARCHAR(128) NULL,
  ADD COLUMN related_transaction_id INT UNSIGNED NULL;

ALTER TABLE products
  ADD COLUMN external_catalog_enabled TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN external_name VARCHAR(255) NULL,
  ADD COLUMN external_description TEXT NULL,
  ADD COLUMN external_sku VARCHAR(128) NULL,
  ADD COLUMN external_image_path VARCHAR(255) NULL;
