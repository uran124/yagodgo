# Дорожная карта внедрения предзаказа

Документ объединяет согласованные правила этапа 1 и план реализации этапа 2.

---

## Этап 1 — бизнес-правила (согласовано)

### Ключевые условия
- Без лимитов на клиента.
- Подтверждение оффера: 4 часа.
- До поступления партии кнопка «Предзаказ -10%» создает намерение (intent), а не финальный заказ.

### Состояния
1. `intent_created`
2. `offer_sent`
3. `confirmed`
4. `declined`
5. `expired`
6. `checkout_completed`

### Распределение
- FIFO по времени создания намерения.
- При `declined/expired` объем уходит следующему в очереди.

### Уведомление клиенту
> Здравствуйте! Получили свежую партию **[Товар]**.
> У вас предварительный заказ: **[Количество]**, цена: **[Цена за коробку]**.
> Подтверждаете? Ответьте в течение **4 часов**.

---

## Этап 2 — техническая реализация (в работу)

Цель этапа 2: реализовать backend- и UI-основу для процесса `intent -> offer -> confirm/decline/expire -> checkout`.

## 2.1. Изменения БД

### Таблица `preorder_intents`
Минимальные поля:
- `id` BIGINT PK
- `user_id` BIGINT NOT NULL
- `product_id` BIGINT NOT NULL
- `requested_boxes` DECIMAL(10,2) NOT NULL
- `status` ENUM('intent_created','offer_sent','confirmed','declined','expired','checkout_completed') NOT NULL
- `offered_price_per_box` DECIMAL(10,2) NULL
- `offer_expires_at` DATETIME NULL
- `checkout_token` VARCHAR(64) NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Индексы:
- `(product_id, status, created_at)` — FIFO-выборка очереди.
- `(user_id, product_id, status)` — дедуп активных намерений.
- `(offer_expires_at, status)` — фоновое истечение офферов.
- `(checkout_token)` UNIQUE — безопасная ссылка продолжения.

Ограничения/инварианты:
- `requested_boxes > 0`.
- Для `offer_sent` обязательно заполнены `offered_price_per_box` и `offer_expires_at`.

## 2.2. Backend API/контроллеры

### 1) Создание намерения
`POST /preorder-intents`
- Вход: `product_id`, `requested_boxes`.
- Выход: созданное/обновленное активное намерение.
- Правило: если у пользователя уже есть активный intent по товару, обновляем количество и `updated_at` (без дублей).

### 2) Подтверждение оффера
`POST /preorder-intents/{id}/confirm`
- Условия: `status = offer_sent`, `now <= offer_expires_at`.
- Результат: `status = confirmed`, генерация `checkout_token`.

### 3) Отказ от оффера
`POST /preorder-intents/{id}/decline`
- Условия: `status = offer_sent`.
- Результат: `status = declined`.

### 4) Продолжение оформления
`GET /preorder/continue/{checkout_token}`
- Проверка токена и статуса `confirmed`.
- Предзаполнение checkout товаром и количеством.

## 2.3. Интеграция с закупкой/партиями

Точка запуска волны офферов:
- после фиксации фактической партии (объем + цена) закупщиком.

Процесс:
1. Считать доступный объем по товару для предзаказа.
2. Взять FIFO-очередь `intent_created`.
3. Перевести часть намерений в `offer_sent`, проставить `offered_price_per_box` и `offer_expires_at = now + 4h`.
4. Отправить уведомления с CTA «Да/Нет».

## 2.4. Фоновые задачи

### Job A: `expire_preorder_offers`
- Период: каждые 5–10 минут.
- Действие: все `offer_sent` с `offer_expires_at < now` -> `expired`.

### Job B: `reallocate_expired_or_declined`
- Период: каждые 5–10 минут или событие после `declined/expired`.
- Действие: освободившийся объем сразу предлагать следующим в FIFO (`offer_sent`).

### Job C: `remind_offer_expiring`
- Период: каждые 10 минут.
- Действие: напоминание клиентам за 60 минут до `offer_expires_at`.

## 2.5. UI изменения

### Карточка товара
- Кнопка «Купить сейчас» — текущий поток.
- Кнопка «Предзаказ -10%»:
  - если нет активной ожидаемой поставки: создать intent и показать информационный success-state;
  - если поставка есть и клиенту отправлен оффер: вести на экран подтверждения.

### Экран подтверждения оффера
Показывать:
- товар,
- количество,
- актуальную цену,
- дедлайн (4 часа).

Действия:
- «Да, подтверждаю»;
- «Нет».

## 2.6. Безопасность и надежность

- Идемпотентность confirm/decline endpoint’ов.
- Транзакционная блокировка при распределении объема (избежать гонок).
- Подписанный/рандомный `checkout_token` с ограниченным сроком жизни.
- Логирование всех переходов статусов.

## 2.7. Минимальные тест-кейсы этапа 2

1. Создание intent и дедуп по пользователю/товару.
2. Перевод в `offer_sent` при поступлении партии.
3. Подтверждение до истечения 4 часов -> `confirmed`.
4. Подтверждение после истечения -> отказ и `expired`.
5. `declined` освобождает объем и триггерит перераспределение.
6. `checkout_token` работает один раз и только для `confirmed`.

## 2.8. DoD этапа 2

Этап 2 считается завершенным, когда:
- есть миграция `preorder_intents` и необходимые индексы;
- доступны endpoint’ы create/confirm/decline/continue;
- работают jobs expire/reallocate/remind;
- реализован UI для intent и экрана подтверждения;
- пройдены минимальные тест-кейсы.
