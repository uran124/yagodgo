# Runbook: Этап 8 — Go-live и масштабирование preorder

## Phase A: Тихий прогрев
- Включить только сбор intent.
- Проверить конверсию intent и корректность статусов.

## Phase B: Ручные волны офферов
- Закупщик запускает `preorder_send_offers.php` вручную.
- Контроль метрик sent/confirmed/declined/expired.

## Phase C: Автоматизация
- Cron для `preorder_expire_offers.php` и `preorder_remind_expiring.php`.
- Перераспределение через `preorder_reallocate.php` по событию.

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
