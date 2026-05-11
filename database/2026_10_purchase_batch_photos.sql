CREATE TABLE IF NOT EXISTS `purchase_batch_photos` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase_batch_photos_batch` (`purchase_batch_id`),
  CONSTRAINT `fk_purchase_batch_photos_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
