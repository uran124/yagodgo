# QA Smoke Run — Staging (Preorder + Purchase Batch Lifecycle)

## Scope

Проверить end-to-end сценарии по ТЗ:

1. `planned -> purchased -> arrived`
2. bounded offer wave
3. confirm / decline / expire
4. maintenance cancel for unconfirmed
5. in-stock preorder ETA `+2 дня` / fallback `ближайшая возможная`

---

## Preconditions

- Есть staging-база с таблицами:
  - `purchase_batches`
  - `preorder_intents`
  - `preorder_intent_events`
  - `notifications`
- Есть роли:
  - `admin` (или `manager` где указано)
  - `client`
- Создан активный товар `SKU-A` с `current_purchase_batch_id`.

---

## Scenario 1: `planned -> purchased -> arrived`

### 1.1 Create planned batch

1. Зайти под `admin` в `/admin/purchases/create`.
2. Создать закупку для `SKU-A` со статусом `Запланирована`.

**Expected**
- В списке закупок статус отображается как `Запланирована`.

### 1.2 Move to purchased

1. В `/admin/purchases` нажать `Выкуплена` для созданной партии.

**Expected**
- Статус партии становится `Выкуплена`.
- Запускается offer wave для `intent_created` (см. Scenario 2).

### 1.3 Move to arrived

1. В `/admin/purchases` нажать `Готова к выдаче`.

**Expected**
- Статус партии становится `Готова к выдаче`.
- Просроченные `offer_sent` переходят в `expired`.
- Подтвержденные `confirmed` переходят в `checkout_completed` (UI label: `Выполнен`).

---

## Scenario 2: bounded offer wave

### Setup

Создать 3 интента по `SKU-A`:
- User1: `requested_boxes = 2`, `status = intent_created`
- User2: `requested_boxes = 2`, `status = intent_created`
- User3: `requested_boxes = 5`, `status = intent_created`

Создать партию со `status = planned`, затем перевести в `purchased` с `boxes_free = 4`.

### Expected

- В `offer_sent` перейдут только интенты, которые укладываются в `boxes_free` (FIFO по `created_at`, `id`).
- Сумма распределенных коробок не превышает `boxes_free`.
- В `notifications` появляется запись с кодом `preorder_offer_sent`.

---

## Scenario 3: Confirm / Decline / Expire

### 3.1 Confirm

1. Клиент открывает страницу оффера `/preorder-offer/{id}`.
2. Нажимает `Да, подтверждаю`.

**Expected**
- `offer_sent -> confirmed`
- Возвращается `continue_url`.

### 3.2 Decline

1. Для другого оффера нажать `Нет, отказаться`.

**Expected**
- `offer_sent -> declined`
- UI статус показывает `Отменен`.

### 3.3 Expire

1. Установить `offer_expires_at` в прошлое.
2. Запустить maintenance (см. Scenario 4) или trigger arrived flow.

**Expected**
- `offer_sent -> expired`
- В UI показывается `Просрочен`.

---

## Scenario 4: Maintenance cancel for unconfirmed

### 4.1 Admin trigger

1. В `/admin/purchases` нажать `Обновить статусы предзаказов`.

**Expected**
- Старые `intent_created` (старше `preorder_unconfirmed_cancel_hours`) переходят в `declined`.
- Просроченные `offer_sent` переходят в `expired`.
- Показывается flash: сколько `истекло` и сколько `отменено неподтвержденных`.
- В `preorder_intent_events` появляются `auto_cancel_unconfirmed`.

---

## Scenario 5: In-stock preorder ETA `+2 дня`

### 5.1 With source date

1. На главной в секции `В наличии` нажать `Предзаказ` у карточки с `delivery_date`.

**Expected**
- В API-ответе на `/preorder-intents` есть `eta_delivery_date = source_delivery_date + 2 days`.
- Сообщение клиенту: `Предзаказ сохранён: на DD.MM.YYYY`.
- В `preorder_intent_events.meta_json` записаны:
  - `source_section = in_stock`
  - `source_delivery_date`
  - `eta_delivery_date`

### 5.2 Without source date

1. Нажать `Предзаказ` для in-stock карточки без даты.

**Expected**
- ETA дата не вычисляется (`null`).
- Сообщение клиенту: `на ближайшую возможную дату`.

---

## Evidence to collect (required)

- Скриншоты:
  - `/admin/purchases` (статусы до/после переходов)
  - `/preorder-offer/{id}` (confirm/decline)
  - главная карточка in-stock с кнопкой `Предзаказ`
- SQL выборки:
  - `preorder_intents` по `product_id`
  - `preorder_intent_events` по `preorder_intent_id`
  - `notifications` по кодам `preorder_offer_sent`, `preorder_ready_for_pickup`

---

## Pass criteria

Smoke-run считается пройденным, если:

1. Все 5 сценариев завершились ожидаемыми переходами статусов.
2. Нет переходов, нарушающих lifecycle (`planned -> purchased -> arrived`).
3. Нет oversell в offer wave (allocated <= free).
4. ETA `+2 дня` корректно рассчитывается для in-stock source.
5. События и уведомления пишутся в БД.
