# Runbook: Этап 9 — Post-release улучшения preorder

## 1) Product backlog
- Персональные окна подтверждения.
- Сегментация «тёплых» клиентов для приоритетных волн.
- Улучшение UX экрана оффера и статусов.

## 2) Технический долг
- Выделить PreorderIntentService в отдельный модуль домена.
- Архивировать старые intents/events по retention-политике.
- Оптимизировать индексы по реальной нагрузке.

### Практическая процедура архивирования
```bash
php bin/preorder_archive_old.php [retention_days=120]
```
По умолчанию архивируются закрытые intents (`checkout_completed`, `declined`, `expired`) старше 120 дней
в таблицы `preorder_intents_archive` и `preorder_intent_events_archive`.

## 3) Наблюдаемость
- Дашборд SLA по jobs (send/expire/remind/reallocate).
- Алертинг на spike `failed` у reminder job.
- Алертинг на отклонение conversion confirmed->checkout_completed.

## 4) План ревизии
- Ежемесячный аудит продуктовых метрик.
- Ежеквартальный аудит безопасности токенов и доступа.
- Полугодовой рефакторинг hot path запросов.
