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