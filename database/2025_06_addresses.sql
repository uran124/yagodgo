ALTER TABLE addresses
  ADD COLUMN recipient_name varchar(100) NOT NULL DEFAULT '',
  ADD COLUMN recipient_phone varchar(20) NOT NULL DEFAULT '',
  ADD COLUMN is_primary tinyint(1) NOT NULL DEFAULT 0;
