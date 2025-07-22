ALTER TABLE delivery_slots DROP COLUMN date;
TRUNCATE TABLE delivery_slots;
INSERT INTO delivery_slots (time_from, time_to) VALUES
  ('09:00', '12:00'),
  ('12:00', '15:00'),
  ('15:00', '18:00'),
  ('18:00', '22:00');
