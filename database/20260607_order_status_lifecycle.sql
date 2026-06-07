ALTER TABLE orders
  MODIFY status enum('reserved','new','processing','assigned','delivered','confirmed','shipped','completed','cancelled','returned') NOT NULL DEFAULT 'new';

UPDATE orders
   SET status = CASE status
       WHEN 'processing' THEN 'confirmed'
       WHEN 'assigned' THEN 'shipped'
       WHEN 'delivered' THEN 'completed'
       ELSE status
   END;

ALTER TABLE orders
  MODIFY status enum('reserved','new','confirmed','shipped','completed','cancelled','returned') NOT NULL DEFAULT 'new';

CREATE TABLE IF NOT EXISTS order_status_history (
  id int UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id int UNSIGNED NOT NULL,
  from_status varchar(32) DEFAULT NULL,
  to_status varchar(32) NOT NULL,
  changed_by_user_id int UNSIGNED DEFAULT NULL,
  changed_by_role varchar(32) DEFAULT NULL,
  comment varchar(255) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_order_status_history_order_id (order_id),
  KEY idx_order_status_history_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
