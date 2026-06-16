CREATE TABLE IF NOT EXISTS production_executor_settings (
  user_id INT UNSIGNED NOT NULL,
  executor_type ENUM('internal_staff','production_partner','marketplace_seller','brand_partner') NOT NULL DEFAULT 'internal_staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  can_work_on_site TINYINT(1) NOT NULL DEFAULT 1,
  can_work_remote TINYINT(1) NOT NULL DEFAULT 0,
  current_mode ENUM('on_shift','remote_available','offline','paused') NOT NULL DEFAULT 'offline',
  default_fulfillment_model ENUM('by_berrygo_on_site','by_berrygo_remote','by_partner_under_berrygo_brand','by_seller','by_berrygo_from_seller_stock') NOT NULL DEFAULT 'by_berrygo_on_site',
  default_bonus_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  default_bonus_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  max_active_jobs INT UNSIGNED NOT NULL DEFAULT 1,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_production_executor_settings_available (executor_type, is_active, current_mode),
  KEY idx_production_executor_settings_mode (current_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
