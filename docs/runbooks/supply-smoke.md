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
