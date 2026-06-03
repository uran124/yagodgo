CREATE TABLE IF NOT EXISTS `support_chats` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `internal_note` text DEFAULT NULL,
  `internal_note_updated_by` int UNSIGNED DEFAULT NULL,
  `internal_note_updated_at` datetime DEFAULT NULL,
  `client_unread_count` int UNSIGNED NOT NULL DEFAULT 0,
  `staff_unread_count` int UNSIGNED NOT NULL DEFAULT 0,
  `last_message_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_support_chats_user_order` (`user_id`, `order_id`),
  KEY `idx_support_chats_last_message` (`last_message_at`),
  KEY `idx_support_chats_staff_unread` (`staff_unread_count`),
  KEY `idx_support_chats_user_last` (`user_id`, `last_message_at`),
  CONSTRAINT `fk_support_chats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_chats_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` int UNSIGNED NOT NULL,
  `sender_user_id` int UNSIGNED DEFAULT NULL,
  `sender_name_snapshot` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `hidden_from_client_at` datetime DEFAULT NULL,
  `hidden_from_client_by` int UNSIGNED DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `edited_by` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_messages_chat_id` (`chat_id`, `id`),
  KEY `idx_support_messages_sender` (`sender_user_id`),
  CONSTRAINT `fk_support_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `support_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_support_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_message_attachments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` int UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` int UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_attachments_message` (`message_id`),
  CONSTRAINT `fk_support_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `support_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
