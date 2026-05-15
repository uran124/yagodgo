# Preorder Load Test Evidence

Фиксация факта проведения нагрузочных прогонов в staging/production-like средах.

## 1) Параметры окружения
- Окружение: `<env>`
- Дата: `<date>`
- Исполнитель: `<name>`
- Версия/коммит: `<sha>`

## 2) Сценарии
### Сценарий A: Волна офферов
- Команда: `php bin/preorder_load_probe.php <product_id> <sample_intents> <available_boxes> <price>`
- Входные параметры: `product_id=... sample_intents=... available_boxes=...`
- Результат: `elapsed_ms=... offered=... allocated=...`
- Статус: `pass|fail`

### Сценарий B: Expire job
- Команда: `php bin/preorder_expire_offers.php`
- Объем `offer_sent`: `...`
- Время выполнения: `... ms`
- Статус: `pass|fail`

### Сценарий C: Reminder job
- Команда: `php bin/preorder_remind_expiring.php`
- sent=`...` failed=`...`
- Статус: `pass|fail`

## 3) Acceptance thresholds (зафиксировать до прогона)
- A1: `preorder_load_probe elapsed_ms <= <threshold_ms>`
- A2: `expire job elapsed_ms <= <threshold_ms>`
- A3: `reminder failed rate <= <threshold_percent>`

## 4) Итог
- Общий статус: `pass|fail`
- Найденные узкие места:
  - `<item>`
- Рекомендации:
  - `<item>`
