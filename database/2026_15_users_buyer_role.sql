ALTER TABLE `users`
  MODIFY `role` enum('client','admin','courier','manager','partner','seller','buyer') NOT NULL DEFAULT 'client';
