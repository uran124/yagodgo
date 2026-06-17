CALL yg_add_column_if_missing('products', '`default_materials_cost` decimal(10,2) NOT NULL DEFAULT 0.00');
CALL yg_add_column_if_missing('products', '`minimum_production_margin` decimal(10,2) NOT NULL DEFAULT 500.00');

CALL yg_add_column_if_missing('production_jobs', '`estimated_materials_cost` decimal(10,2) NOT NULL DEFAULT 0.00');
CALL yg_add_column_if_missing('production_jobs', '`estimated_acquiring_cost` decimal(10,2) NOT NULL DEFAULT 0.00');
CALL yg_add_column_if_missing('production_jobs', '`estimated_margin_amount` decimal(10,2) DEFAULT NULL');
CALL yg_add_column_if_missing('production_jobs', '`minimum_margin_amount` decimal(10,2) NOT NULL DEFAULT 0.00');
CALL yg_add_column_if_missing('production_jobs', '`margin_status` varchar(32) NOT NULL DEFAULT ''unknown''');

UPDATE products
SET default_materials_cost = CASE alias
    WHEN 'shokoladnaya-klubnika-9-yagod' THEN 850.00
    WHEN 'shokoladnaya-klubnika-12-yagod' THEN 1100.00
    WHEN 'shokoladnaya-klubnika-16-yagod' THEN 1450.00
    WHEN 'shokoladnaya-klubnika-25-yagod' THEN 2200.00
    WHEN 'klubnika-v-shokolade-s-cvetami' THEN 2600.00
    WHEN 'prazdnichnyy-nabor-klubniki-v-shokolade' THEN 1900.00
    ELSE default_materials_cost
  END,
  minimum_production_margin = CASE alias
    WHEN 'shokoladnaya-klubnika-9-yagod' THEN 500.00
    WHEN 'shokoladnaya-klubnika-12-yagod' THEN 650.00
    WHEN 'shokoladnaya-klubnika-16-yagod' THEN 800.00
    WHEN 'shokoladnaya-klubnika-25-yagod' THEN 1200.00
    WHEN 'klubnika-v-shokolade-s-cvetami' THEN 1200.00
    WHEN 'prazdnichnyy-nabor-klubniki-v-shokolade' THEN 900.00
    ELSE minimum_production_margin
  END
WHERE alias IN (
  'shokoladnaya-klubnika-9-yagod',
  'shokoladnaya-klubnika-12-yagod',
  'shokoladnaya-klubnika-16-yagod',
  'shokoladnaya-klubnika-25-yagod',
  'klubnika-v-shokolade-s-cvetami',
  'prazdnichnyy-nabor-klubniki-v-shokolade'
);
