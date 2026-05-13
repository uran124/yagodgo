# Supply discount rollover

Скрипт: `bin/supply_discount_rollover.php`

## Назначение

Автоматически переводит свободные остатки старых партий (`boxes_free`) в режим выгодного остатка (`boxes_discount`) по правилу возраста партии.

## Запуск

Dry-run (рекомендуется перед боем):

```bash
php bin/supply_discount_rollover.php --dry-run --min-age-days=1 --limit=50
```

Боевой запуск:

```bash
php bin/supply_discount_rollover.php --min-age-days=1 --limit=50
```

## Параметры

- `--dry-run` — не изменяет БД, только показывает кандидатов.
- `--min-age-days=N` — минимальный возраст партии в днях для перевода.
- `--limit=N` — максимум партий за один запуск.

## Что делает скрипт

1. Выбирает партии со статусами `active/arrived/purchased`, где `boxes_free > 0` и возраст >= `min-age-days`.
2. Переводит весь `boxes_free` в `boxes_discount`.
3. Пишет движение в `stock_movements` с типом `move_to_discount`.
4. Синхронизирует агрегированные остатки в `products`.
