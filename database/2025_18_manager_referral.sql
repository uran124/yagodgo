ALTER TABLE orders
  ADD COLUMN manager_points_accrued int NOT NULL DEFAULT 0 AFTER points_accrued;
