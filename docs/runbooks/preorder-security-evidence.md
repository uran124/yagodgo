# Preorder Security Checks Evidence (Environment Protocol)

Протокол подтверждения security-checks в средах (не только локально).

## Метаданные
- Окружение: `<staging|prod>`
- Дата: `<date>`
- Исполнитель: `<name>`
- Версия/коммит: `<sha>`

## Check 1 — Ownership enforcement
- Шаги:
  1. Пользователь A создает/имеет offer.
  2. Пользователь B вызывает confirm/decline для intent A.
- Ожидаемо: отказ (`не найден`/403/409 в рамках текущей реализации).
- Фактически: `<result>`
- Статус: `pass|fail`

## Check 2 — Token format & entropy policy
- Шаги:
  1. Confirm для валидного offer.
  2. Проверка формата continue token.
- Ожидаемо: hex длиной 48 символов.
- Фактически: `<result>`
- Статус: `pass|fail`

## Check 3 — Invalid/reused token
- Шаги:
  1. Использовать недействительный token.
  2. Повторно использовать token после изменения статуса/завершения.
- Ожидаемо: отклонение перехода.
- Фактически: `<result>`
- Статус: `pass|fail`

## Check 4 — Sensitive data in logs
- Проверка логов jobs/API на утечки чувствительных данных.
- Ожидаемо: без секретов/полных телефонов.
- Фактически: `<result>`
- Статус: `pass|fail`

## Итог
- Общий security status: `pass|fail|partial`
- Открытые риски:
  - `<item>`
- План remediation:
  - `<item>`
