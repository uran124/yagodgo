CREATE TABLE IF NOT EXISTS partner_profiles (
  user_id INT UNSIGNED NOT NULL,
  partner_type ENUM('internal_staff','production_partner','marketplace_seller','brand_partner') NOT NULL DEFAULT 'production_partner',
  status ENUM('draft','active','paused','blocked') NOT NULL DEFAULT 'draft',
  default_fulfillment_model ENUM('by_berrygo_on_site','by_berrygo_remote','by_partner_under_berrygo_brand','by_seller','by_berrygo_from_seller_stock') NOT NULL DEFAULT 'by_partner_under_berrygo_brand',
  monetization_model ENUM('salary','internal_bonus','fixed_payout','commission','subscription','commission_plus_subscription','fixed_fee_per_order') NOT NULL DEFAULT 'commission',
  client_visibility ENUM('berrygo_only','partner_visible','seller_visible') NOT NULL DEFAULT 'berrygo_only',
  commission_rate DECIMAL(5,2) NOT NULL DEFAULT 30.00,
  subscription_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  fixed_fee_per_order DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  default_bonus_percent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  max_active_jobs INT UNSIGNED NOT NULL DEFAULT 1,
  notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  KEY idx_partner_profiles_type_status (partner_type, status),
  KEY idx_partner_profiles_visibility (client_visibility)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
