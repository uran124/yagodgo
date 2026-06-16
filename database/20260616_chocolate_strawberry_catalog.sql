CALL yg_add_column_if_missing('products', '`requires_production` tinyint(1) NOT NULL DEFAULT 0');
CALL yg_add_column_if_missing('products', '`production_spec_id` int UNSIGNED DEFAULT NULL');
CALL yg_add_column_if_missing('products', '`default_fulfillment_model` varchar(64) NOT NULL DEFAULT ''by_berrygo_on_site''');
CALL yg_add_column_if_missing('products', '`default_production_minutes` int UNSIGNED NOT NULL DEFAULT 120');
CALL yg_add_column_if_missing('products', '`default_executor_bonus_percent` decimal(5,2) NOT NULL DEFAULT 10.00');
CALL yg_add_column_if_missing('products', '`default_executor_bonus_amount` decimal(10,2) NOT NULL DEFAULT 0.00');

CREATE TABLE IF NOT EXISTS production_specs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  reference_image_path VARCHAR(255) DEFAULT NULL,
  berry_count INT UNSIGNED DEFAULT NULL,
  berry_size VARCHAR(100) DEFAULT NULL,
  chocolate_type VARCHAR(100) DEFAULT NULL,
  decor TEXT DEFAULT NULL,
  packaging TEXT DEFAULT NULL,
  ribbon_color VARCHAR(100) DEFAULT NULL,
  postcard_rules TEXT DEFAULT NULL,
  production_minutes INT UNSIGNED NOT NULL DEFAULT 120,
  storage_conditions TEXT DEFAULT NULL,
  photo_instruction TEXT DEFAULT NULL,
  handover_instruction TEXT DEFAULT NULL,
  allowed_replacements TEXT DEFAULT NULL,
  forbidden_replacements TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_production_specs_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO product_types (name, alias, meta_title, meta_description, meta_keywords, h1, short_description, text, seller_id, in_sitemap)
SELECT 'Клубника в шоколаде', 'klubnika-v-shokolade',
       'Клубника в шоколаде с доставкой — berryGo',
       'Клубника в шоколаде berryGo: стандартные подарочные наборы, единое качество, фото-контроль и доставка.',
       'клубника в шоколаде, клубника в шоколаде доставка, berryGo',
       'Клубника в шоколаде',
       'Подарочные наборы клубники в шоколаде под брендом berryGo.',
       'berryGo запускает собственную линейку клубники в шоколаде. Наборы изготавливаются по внутренним стандартам, проходят фото-контроль и доставляются клиенту от имени berryGo.',
       NULL,
       1
WHERE NOT EXISTS (SELECT 1 FROM product_types WHERE alias = 'klubnika-v-shokolade');

SET @choco_type_id := (SELECT id FROM product_types WHERE alias = 'klubnika-v-shokolade' ORDER BY id LIMIT 1);

INSERT INTO products (variety, origin_country, unit, price, image_path, product_type_id, seller_id, alias, box_size, box_unit, description, full_description, composition, meta_title, meta_description, meta_keywords, manufacturer, sale_price, is_active, in_sitemap, requires_production, default_fulfillment_model, default_production_minutes, default_executor_bonus_percent, default_executor_bonus_amount)
SELECT 'Набор 9 ягод', 'RU', 'набор', 2490, '/assets/img/chocolate-strawberry-placeholder.svg', @choco_type_id, NULL, 'shokoladnaya-klubnika-9-yagod', 1, 'набор',
       'Клубника в шоколаде, 9 ягод.', 'Стандартный подарочный набор berryGo из 9 ягод в шоколаде.', '9 ягод клубники, шоколад, декор, упаковка berryGo.',
       'Клубника в шоколаде 9 ягод — berryGo', 'Подарочный набор клубники в шоколаде на 9 ягод с доставкой berryGo.', 'клубника в шоколаде 9 ягод', 'berryGo', 0, 1, 1, 1, 'by_berrygo_on_site', 120, 10.00, 300.00
WHERE @choco_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products WHERE alias = 'shokoladnaya-klubnika-9-yagod');

INSERT INTO products (variety, origin_country, unit, price, image_path, product_type_id, seller_id, alias, box_size, box_unit, description, full_description, composition, meta_title, meta_description, meta_keywords, manufacturer, sale_price, is_active, in_sitemap, requires_production, default_fulfillment_model, default_production_minutes, default_executor_bonus_percent, default_executor_bonus_amount)
SELECT 'Набор 12 ягод', 'RU', 'набор', 3290, '/assets/img/chocolate-strawberry-placeholder.svg', @choco_type_id, NULL, 'shokoladnaya-klubnika-12-yagod', 1, 'набор',
       'Клубника в шоколаде, 12 ягод.', 'Стандартный подарочный набор berryGo из 12 ягод в шоколаде.', '12 ягод клубники, шоколад, декор, упаковка berryGo.',
       'Клубника в шоколаде 12 ягод — berryGo', 'Подарочный набор клубники в шоколаде на 12 ягод с доставкой berryGo.', 'клубника в шоколаде 12 ягод', 'berryGo', 0, 1, 1, 1, 'by_berrygo_on_site', 120, 10.00, 400.00
WHERE @choco_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products WHERE alias = 'shokoladnaya-klubnika-12-yagod');

INSERT INTO products (variety, origin_country, unit, price, image_path, product_type_id, seller_id, alias, box_size, box_unit, description, full_description, composition, meta_title, meta_description, meta_keywords, manufacturer, sale_price, is_active, in_sitemap, requires_production, default_fulfillment_model, default_production_minutes, default_executor_bonus_percent, default_executor_bonus_amount)
SELECT 'Набор 16 ягод', 'RU', 'набор', 4290, '/assets/img/chocolate-strawberry-placeholder.svg', @choco_type_id, NULL, 'shokoladnaya-klubnika-16-yagod', 1, 'набор',
       'Клубника в шоколаде, 16 ягод.', 'Стандартный подарочный набор berryGo из 16 ягод в шоколаде.', '16 ягод клубники, шоколад, декор, упаковка berryGo.',
       'Клубника в шоколаде 16 ягод — berryGo', 'Подарочный набор клубники в шоколаде на 16 ягод с доставкой berryGo.', 'клубника в шоколаде 16 ягод', 'berryGo', 0, 1, 1, 1, 'by_berrygo_on_site', 150, 10.00, 500.00
WHERE @choco_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products WHERE alias = 'shokoladnaya-klubnika-16-yagod');

INSERT INTO products (variety, origin_country, unit, price, image_path, product_type_id, seller_id, alias, box_size, box_unit, description, full_description, composition, meta_title, meta_description, meta_keywords, manufacturer, sale_price, is_active, in_sitemap, requires_production, default_fulfillment_model, default_production_minutes, default_executor_bonus_percent, default_executor_bonus_amount)
SELECT 'Набор 25 ягод', 'RU', 'набор', 6490, '/assets/img/chocolate-strawberry-placeholder.svg', @choco_type_id, NULL, 'shokoladnaya-klubnika-25-yagod', 1, 'набор',
       'Клубника в шоколаде, 25 ягод.', 'Большой подарочный набор berryGo из 25 ягод в шоколаде.', '25 ягод клубники, шоколад, декор, упаковка berryGo.',
       'Клубника в шоколаде 25 ягод — berryGo', 'Большой подарочный набор клубники в шоколаде на 25 ягод с доставкой berryGo.', 'клубника в шоколаде 25 ягод', 'berryGo', 0, 1, 1, 1, 'by_berrygo_on_site', 180, 10.00, 700.00
WHERE @choco_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products WHERE alias = 'shokoladnaya-klubnika-25-yagod');

INSERT INTO products (variety, origin_country, unit, price, image_path, product_type_id, seller_id, alias, box_size, box_unit, description, full_description, composition, meta_title, meta_description, meta_keywords, manufacturer, sale_price, is_active, in_sitemap, requires_production, default_fulfillment_model, default_production_minutes, default_executor_bonus_percent, default_executor_bonus_amount)
SELECT 'Клубника с цветами', 'RU', 'набор', 5990, '/assets/img/chocolate-strawberry-placeholder.svg', @choco_type_id, NULL, 'klubnika-v-shokolade-s-cvetami', 1, 'набор',
       'Клубника в шоколаде с цветочным акцентом.', 'Подарочный набор berryGo: клубника в шоколаде и цветочный акцент.', 'Клубника, шоколад, декор, цветочный акцент, упаковка berryGo.',
       'Клубника в шоколаде с цветами — berryGo', 'Подарочный набор клубники в шоколаде с цветами с доставкой berryGo.', 'клубника в шоколаде с цветами', 'berryGo', 0, 1, 1, 1, 'by_berrygo_on_site', 180, 10.00, 700.00
WHERE @choco_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products WHERE alias = 'klubnika-v-shokolade-s-cvetami');

INSERT INTO products (variety, origin_country, unit, price, image_path, product_type_id, seller_id, alias, box_size, box_unit, description, full_description, composition, meta_title, meta_description, meta_keywords, manufacturer, sale_price, is_active, in_sitemap, requires_production, default_fulfillment_model, default_production_minutes, default_executor_bonus_percent, default_executor_bonus_amount)
SELECT 'Праздничный набор', 'RU', 'набор', 4990, '/assets/img/chocolate-strawberry-placeholder.svg', @choco_type_id, NULL, 'prazdnichnyy-nabor-klubniki-v-shokolade', 1, 'набор',
       'Праздничная клубника в шоколаде.', 'Тематический подарочный набор berryGo для дня рождения или праздника.', 'Клубника, шоколад, тематический декор, упаковка berryGo.',
       'Праздничная клубника в шоколаде — berryGo', 'Тематический набор клубники в шоколаде с доставкой berryGo.', 'праздничная клубника в шоколаде', 'berryGo', 0, 1, 1, 1, 'by_berrygo_on_site', 150, 10.00, 600.00
WHERE @choco_type_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM products WHERE alias = 'prazdnichnyy-nabor-klubniki-v-shokolade');

INSERT INTO production_specs (product_id, title, reference_image_path, berry_count, berry_size, chocolate_type, decor, packaging, ribbon_color, postcard_rules, production_minutes, storage_conditions, photo_instruction, handover_instruction, allowed_replacements, forbidden_replacements)
SELECT p.id, CONCAT('Стандарт berryGo · ', p.variety), p.image_path,
       CASE
         WHEN p.alias = 'shokoladnaya-klubnika-9-yagod' THEN 9
         WHEN p.alias = 'shokoladnaya-klubnika-12-yagod' THEN 12
         WHEN p.alias = 'shokoladnaya-klubnika-16-yagod' THEN 16
         WHEN p.alias = 'shokoladnaya-klubnika-25-yagod' THEN 25
         ELSE NULL
       END,
       'средняя/крупная ягода без повреждений', 'молочный/тёмный шоколад по стандарту карточки',
       'декор по карточке товара, без самостоятельной замены цветов и посыпок',
       'коробка berryGo, пищевая подложка, аккуратная укладка',
       'по стандарту карточки товара',
       'текст открытки строго из заказа, без исправлений от исполнителя',
       p.default_production_minutes,
       'хранить в прохладном месте, не перегревать, передавать курьеру сразу после готовности',
       'фото сверху, крупный план ягод, фото в упаковке, фото открытки при наличии',
       'передать курьеру в упаковке berryGo, без прямого контакта с клиентом',
       'замены только после согласования с менеджером',
       'нельзя менять количество ягод, упаковку, цветовую концепцию и текст открытки без менеджера'
FROM products p
WHERE p.product_type_id = @choco_type_id
  AND p.requires_production = 1
  AND NOT EXISTS (SELECT 1 FROM production_specs ps WHERE ps.product_id = p.id);

UPDATE products p
JOIN production_specs ps ON ps.product_id = p.id
SET p.production_spec_id = ps.id
WHERE p.product_type_id = @choco_type_id
  AND p.requires_production = 1
  AND p.production_spec_id IS NULL;
