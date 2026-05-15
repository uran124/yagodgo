# Runbook: Этап 8 — Go-live и масштабирование preorder

## Phase A: Тихий прогрев
- Включить только сбор intent.
- Проверить конверсию intent и корректность статусов.
- Команда проверки метрик:
```bash
php bin/preorder_metrics_report.php <from_date> <to_date>
```

## Phase B: Ручные волны офферов
- Закупщик запускает `preorder_send_offers.php` вручную.
- Контроль метрик sent/confirmed/declined/expired.
- Фиксировать ежедневный отчет в формате:
  - дата
  - product_id
  - отправлено офферов
  - подтверждено
  - declined/expired

## Phase C: Автоматизация
- Cron для `preorder_expire_offers.php` и `preorder_remind_expiring.php`.
- Перераспределение через `preorder_reallocate.php` по событию.
- Рекомендованные cron:
```cron
*/10 * * * * php /path/to/bin/preorder_remind_expiring.php
*/5 * * * * php /path/to/bin/preorder_expire_offers.php
```

## Phase D: Оптимизация конверсии
- A/B текста SMS-напоминаний.
- Тесты окна TTL (4h как baseline).

## Метрики Go-live
- intent -> offer
- offer -> confirmed
- confirmed -> checkout_completed
- доля expired/declined
- доля reallocated

## Критерии готовности этапа 8
- Метрики собираются ежедневно.
- Есть еженедельный review с backlog-улучшениями.

## Шаблон weekly review
- Период: `<from>` — `<to>`.
- Intent -> offer: `<значение>%`.
- Offer -> confirmed: `<значение>%`.
- Confirmed -> checkout_completed: `<значение>%`.
- Топ-3 причины declined/expired.
- Решения на следующую неделю (owner + deadline).
