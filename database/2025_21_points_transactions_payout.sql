ALTER TABLE points_transactions
    MODIFY transaction_type ENUM('accrual', 'usage', 'payout') NOT NULL;
