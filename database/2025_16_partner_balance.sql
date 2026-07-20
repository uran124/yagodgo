ALTER TABLE users
  MODIFY role enum('client','admin','courier','manager','partner') NOT NULL DEFAULT 'client';

ALTER TABLE users
  ADD COLUMN rub_balance int NOT NULL DEFAULT 0 AFTER points_balance;
