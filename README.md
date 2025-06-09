# YagodGO Web App

This project contains a simple PHP application for the YagodGO delivery service.

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