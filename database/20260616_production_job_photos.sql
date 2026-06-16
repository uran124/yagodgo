CREATE TABLE IF NOT EXISTS production_job_photos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  job_id INT UNSIGNED NOT NULL,
  order_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  photo_type ENUM('ready','packaging','handover') NOT NULL DEFAULT 'ready',
  review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by_user_id INT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  review_comment VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_production_job_photos_job_id (job_id),
  KEY idx_production_job_photos_order_id (order_id),
  KEY idx_production_job_photos_review_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
