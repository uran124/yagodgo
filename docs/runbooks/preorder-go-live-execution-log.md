# Preorder Go-live Execution Log (Phase A/B/C/D)

Этот файл — артефакт фактического прохождения фаз go-live.

## Общие поля
- Окружение: `staging|production`
- Ответственный: `<name>`
- Период: `<from> - <to>`
- Версия/коммит: `<sha>`

---

## Phase A — Тихий прогрев (intent only)
- Старт: `<datetime>`
- Окончание: `<datetime>`
- Статус: `planned|in_progress|done|blocked`
- Действия:
  - [ ] Включен только сбор intent
  - [ ] Проверена корректность статусов
  - [ ] Снят метрик-репорт `bin/preorder_metrics_report.php`
- Артефакты:
  - Ссылка на отчет/лог: `<url-or-path>`
  - Вывод ключевых метрик: `intent_created=...`

## Phase B — Ручные волны офферов
- Старт: `<datetime>`
- Окончание: `<datetime>`
- Статус: `planned|in_progress|done|blocked`
- Действия:
  - [ ] Запуск `preorder_send_offers.php`
  - [ ] Зафиксированы sent/confirmed/declined/expired
  - [ ] Ежедневный отчет оформлен
- Артефакты:
  - Лог запусков: `<url-or-path>`
  - Отчет: `<url-or-path>`

## Phase C — Автоматизация cron
- Старт: `<datetime>`
- Окончание: `<datetime>`
- Статус: `planned|in_progress|done|blocked`
- Действия:
  - [ ] Настроен cron remind
  - [ ] Настроен cron expire
  - [ ] Проверена реакция reallocate по событию
- Артефакты:
  - Конфигурация cron: `<url-or-path>`
  - Примеры логов cron: `<url-or-path>`

## Phase D — Оптимизация конверсии
- Старт: `<datetime>`
- Окончание: `<datetime>`
- Статус: `planned|in_progress|done|blocked`
- Действия:
  - [ ] A/B тексты напоминаний
  - [ ] Проверка окна TTL (4h baseline)
  - [ ] Итоговый weekly review
- Артефакты:
  - Результаты A/B: `<url-or-path>`
  - Weekly review: `<url-or-path>`

---

## Итог Go-live
- Итоговый статус: `not_started|partial|completed`
- Решение: `go|hold|rollback`
- Риски/блокеры:
  - `<item>`
