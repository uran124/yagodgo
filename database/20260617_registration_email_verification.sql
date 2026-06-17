ALTER TABLE users
  ADD COLUMN email varchar(255) DEFAULT NULL AFTER phone,
  ADD COLUMN email_verified_at datetime DEFAULT NULL AFTER email,
  ADD COLUMN email_verification_token_hash varchar(255) DEFAULT NULL AFTER email_verified_at,
  ADD COLUMN email_verification_expires_at datetime DEFAULT NULL AFTER email_verification_token_hash;

CREATE UNIQUE INDEX users_email_unique ON users (email);

INSERT INTO settings (setting_key, setting_value) VALUES
  ('registration_phone_verification_enabled', '1'),
  ('registration_email_verification_ttl_minutes', '60')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
