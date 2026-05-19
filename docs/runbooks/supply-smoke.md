# Supply smoke-check

Скрипт: `bin/supply_smoke.php`

## Назначение

Быстрая проверка готовности supply-контура перед релизом/после миграций.

Проверяет:
- наличие ключевых таблиц (`purchase_batches`, `stock_movements`, `purchase_batch_photos`);
- наличие ключевых pricing-настроек в `settings`;
- количество партий;
- отсутствие аномалий инварианта `boxes_remaining`.

## Запуск

```bash
php bin/supply_smoke.php
```

## Интерпретация

- Exit code `0` — smoke-check пройден.
- Exit code `1` — есть проблемы в checks.
- Exit code `2` — ошибка подключения к БД.


## Композитная проверка релиза

```bash
php bin/supply_release_check.php
```

Скрипт последовательно запускает:
- `php bin/migrate.php status` (и проверяет, что `Pending: 0`),
- `php bin/supply_smoke.php`,
- `php bin/supply_digest.php --threshold=2`.


## Дополнительная проверка агрегатов продуктов

Скрипт также проверяет `product_aggregate_anomalies`: расхождения между агрегатами в `products` и суммами по `purchase_batches` (для статусов `active/arrived/purchased`).

Если расхождения есть, можно сначала оценить объём:

```bash
php bin/supply_repair_product_aggregates.php --dry-run
```

Затем выполнить выравнивание:

```bash
php bin/supply_repair_product_aggregates.php
```
