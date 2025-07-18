# BerryGo Web App

This project contains a simple PHP application for the BerryGo delivery service.

## Running tests

Install dependencies using Composer and run PHPUnit:

```bash
composer install
vendor/bin/phpunit
```

### Database update

Checkout now supports discount coupons. Add the following field to the `orders`
table:

```sql
ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL;
```

To enable human readable URLs for materials, add the `alias` column to the
`materials` table:

```sql
ALTER TABLE materials
  ADD COLUMN alias VARCHAR(255) NOT NULL AFTER category_id,
  ADD UNIQUE KEY alias (alias);
```

To make pretty URLs for products and categories, add alias columns:

```sql
ALTER TABLE product_types
  ADD COLUMN alias VARCHAR(255) NOT NULL AFTER name,
  ADD UNIQUE KEY alias (alias);

ALTER TABLE products
  ADD COLUMN alias VARCHAR(255) NOT NULL AFTER product_type_id,
  ADD UNIQUE KEY alias (alias);
```
### Sitemap automation

Run `bin/generate_sitemap.php` regularly to refresh `sitemap.xml`. On hosting with cron add a task:

```
0 8 * * * php /path/to/project/bin/generate_sitemap.php
```

The schedule assumes cron uses the Asia/Krasnoyarsk time zone. If the server works in UTC set `0 1 * * *` instead.


### System page metadata

Use the `metadata` table to customize meta tags for built-in pages. These pages have no records yet, so insert all fields explicitly:

```sql
INSERT INTO metadata (page, title, description, keywords, h1, text) VALUES
  ('register',  'Регистрация – BerryGo', 'Создайте аккаунт для заказа свежих ягод', '', 'Регистрация', ''),
  ('reset-pin', 'Сброс PIN – BerryGo', 'Восстановите код доступа к приложению', '', 'Сброс PIN', ''),
  ('login',     'Вход – BerryGo', 'Авторизуйтесь для доступа к личному кабинету', '', 'Вход', '');
```

