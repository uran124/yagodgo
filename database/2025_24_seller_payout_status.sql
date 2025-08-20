ALTER TABLE seller_payouts
  MODIFY status ENUM('pending','scheduled','accrued','paid','cancelled') NOT NULL DEFAULT 'pending';

