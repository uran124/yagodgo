# BerryGo — roadmap и ТЗ: переход на модель продаж от закупки / поставки

**Дата подготовки:** 11.05.2026  
**Файл схемы, по которому сверялась структура:** `yago (7).sql`  
**Цель:** внедрить работу приложения от фактической закупки: чтобы по каждой партии было видно, сколько привезено, сколько забронировано, сколько свободно к заказу, сколько продано, сколько осталось и сколько списано.

---

## 0. Ключевая идея изменения

Сейчас приложение работает преимущественно от карточки товара: у товара есть цена, дата поставки, активность и остаток в ящиках.

Новая модель:

```text
Закупка / партия → остатки → цены → карточка товара → корзина → заказ → движение склада → уведомления → отчёт
```

То есть **товар остаётся витриной**, но реальная операционная логика должна идти от **поставки / закупочной партии**.

---

## 1. Что уже есть в текущей базе и что нельзя ломать

По SQL-схеме `yago (7).sql` уже существуют важные таблицы:

| Таблица | Что уже есть | Что важно не сломать |
|---|---|---|
| `products` | товары, цена, остаток `stock_boxes`, дата поставки `delivery_date`, акция `sale_price`, активность `is_active` | не удалять старые поля; они используются карточками, каталогом и заказами |
| `orders` | заказы, статусы `reserved/new/processing/assigned/delivered/cancelled`, дата доставки, бонусы, промокод | не менять существующие статусы резко; добавить новые поля поверх |
| `order_items` | состав заказа: `order_id`, `product_id`, `quantity`, `unit_price`, `boxes` | текущая логика считает цену как `quantity × unit_price`; это важно сохранить |
| `cart_items` | корзина: `user_id`, `product_id`, `quantity`, `unit_price` | текущий PK: `user_id + product_id`; это ограничивает разные режимы одного товара в одной корзине |
| `users` | роли `client/admin/courier/manager/partner/seller`, телефон, telegram_id, балансы | для закупщика надо аккуратно расширить enum роли |
| `points_transactions` | начисления/списания бонусов | не начислять бонусы на низкомаржинальные остатки |
| `settings` | универсальные настройки `setting_key/setting_value` | лучше использовать её для настроек наценки, а не заводить отдельную таблицу настроек |
| `mailing_clients` | база для рассылок | использовать для рассылок о свежей партии и выгодном остатке |
| `seller_payouts` | выплаты продавцам | не смешивать бонусы/выплаты продавцов с режимом выгодного остатка |

### Важный нюанс по цене

В текущем коде `products.price` и `order_items.unit_price` работают как **цена за единицу измерения** — кг/л, а не как цена за ящик.

Пример текущей логики:

```text
box_size = 2 кг
price = 550 ₽/кг
цена ящика = 550 × 2 = 1100 ₽
```

Поэтому новая логика должна считать цену за ящик от закупки, но затем переводить её в цену за кг/л для совместимости с текущим заказом:

```text
цена_за_кг = цена_за_ящик / box_size
```

---

## 2. Новые бизнес-сущности

### 2.1. Закупка / партия

**Партия** — конкретная закупка конкретного товара в конкретный день.

Пример:

| Поле | Значение |
|---|---|
| Товар | Клубника Клери 2 кг |
| Дата закупки | 12.05.2026 |
| Закупщик | Иван |
| Куплено | 30 ящиков |
| Цена закупки | 1000 ₽ за ящик |
| Под предзаказы | 18 ящиков |
| В свободную продажу | 10 ящиков |
| Резерв | 2 ящика |
| Цена предзаказа | 1300 ₽ за ящик |
| Цена сейчас | 1500 ₽ за ящик |
| Статус | active |

### 2.2. Движение склада

Любое изменение остатка должно фиксироваться в журнале:

```text
закупили → зарезервировали → продали → отменили → вернули → списали → перевели в выгодный остаток
```

Журнал нужен, чтобы через неделю можно было понять, куда ушёл каждый ящик.

---

## 3. Режимы продажи

Добавить три режима продажи.

| Режим | Внутренний код | Клиентский смысл | Цена | Бонусы |
|---|---|---|---|---|
| Предзаказ | `preorder` | клиент заказывает к следующей поставке | закупка + 30% | да |
| Свободная продажа | `instant` | товар есть сейчас | закупка + 50% | да |
| Выгодный остаток | `discount_stock` | остаток партии, обычно вчерашний | закупка + 100 ₽ или отдельная настройка | нет |

### 3.1. Почему цена “сейчас” выше

Клиент платит не только за ягоду, но и за:

- наличие прямо сейчас;
- хранение в холодильнике;
- риск остатков;
- работу закупщика;
- резерв под быстрый спрос.

### 3.2. Почему на выгодный остаток нет бонусов

Правило:

```text
Нет нормальной маржи → нет бонусов покупателю, продавцу и менеджеру.
```

Для `discount_stock` нужно отключить:

- начисление бонусов покупателю;
- начисление бонусов менеджеру/продавцу;
- промокоды;
- списание бонусов;
- дополнительные скидки.

---

## 4. Настройки наценки

Использовать существующую таблицу `settings`, а не создавать новую таблицу.

Добавить ключи:

| Ключ | Значение по умолчанию | Описание |
|---|---:|---|
| `pricing_preorder_margin_percent` | `30` | наценка для предзаказа |
| `pricing_instant_margin_percent` | `50` | наценка для свободной продажи |
| `pricing_discount_stock_markup_fixed` | `100` | фиксированная наценка для выгодного остатка |
| `pricing_rounding_step` | `10` | округление цены вниз до 10 ₽ |
| `pricing_free_boxes_default` | `10` | сколько ящиков по умолчанию ставить в свободную продажу |
| `pricing_discount_stock_bonuses_allowed` | `0` | бонусы на выгодный остаток отключены |
| `pricing_discount_stock_coupons_allowed` | `0` | промокоды на выгодный остаток отключены |

### 4.1. Формулы

#### Предзаказ

```text
preorder_box_price = floor_to_10(purchase_price_per_box × 1.30)
```

#### Свободная продажа

```text
instant_box_price = floor_to_10(purchase_price_per_box × 1.50)
```

#### Выгодный остаток

На первом этапе:

```text
discount_box_price = purchase_price_per_box + 100
```

#### Округление вниз

```text
floor_to_10(price) = floor(price / 10) × 10
```

### 4.2. Перевод цены ящика в текущую модель сайта

Так как текущий сайт работает с ценой за кг/л:

```text
unit_price = box_price / product.box_size
```

Пример:

| Закупка за ящик | Ящик | Цена сейчас за ящик | Цена для `products.price` |
|---:|---:|---:|---:|
| 1000 ₽ | 2 кг | 1500 ₽ | 750 ₽/кг |
| 1180 ₽ | 2 кг | 1770 ₽ | 885 ₽/кг |
| 1250 ₽ | 2 кг | 1870 ₽ | 935 ₽/кг |

---

## 5. Миграции базы данных

Миграции делать отдельными файлами в `database/`, так как в проекте уже есть migration runner `bin/migrate.php`.

Рекомендуемая нумерация:

```text
database/2026_01_purchase_batches.sql
database/2026_02_stock_movements.sql
database/2026_03_purchase_batch_photos.sql
database/2026_04_products_supply_fields.sql
database/2026_05_orders_supply_fields.sql
database/2026_06_settings_pricing_defaults.sql
database/2026_07_users_buyer_role.sql
```

Важно: не переписывать уже применённые миграции. Только добавлять новые.

---

## 6. Таблица `purchase_batches`

Создать таблицу закупочных партий.

```sql
CREATE TABLE `purchase_batches` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int UNSIGNED NOT NULL,
  `buyer_user_id` int UNSIGNED DEFAULT NULL,

  `purchased_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `arrived_at` datetime DEFAULT NULL,

  `box_size_snapshot` decimal(10,2) NOT NULL DEFAULT 0,
  `box_unit_snapshot` enum('кг','л') NOT NULL DEFAULT 'кг',

  `boxes_total` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_reserved` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_free` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_sold` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_discount` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_written_off` decimal(10,2) NOT NULL DEFAULT 0,
  `boxes_remaining` decimal(10,2) NOT NULL DEFAULT 0,

  `purchase_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `extra_cost_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `cost_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,

  `preorder_margin_percent` decimal(5,2) NOT NULL DEFAULT 30.00,
  `instant_margin_percent` decimal(5,2) NOT NULL DEFAULT 50.00,
  `discount_markup_fixed` decimal(10,2) NOT NULL DEFAULT 100.00,

  `preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `instant_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  `discount_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,

  `preorder_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  `instant_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  `discount_unit_price` decimal(10,2) NOT NULL DEFAULT 0,

  `status` enum('planned','purchased','arrived','active','sold_out','closed','cancelled') NOT NULL DEFAULT 'purchased',
  `comment` text DEFAULT NULL,

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_purchase_batches_product` (`product_id`),
  KEY `idx_purchase_batches_buyer` (`buyer_user_id`),
  KEY `idx_purchase_batches_status` (`status`),
  KEY `idx_purchase_batches_purchased_at` (`purchased_at`),

  CONSTRAINT `fk_purchase_batches_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_purchase_batches_buyer`
    FOREIGN KEY (`buyer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Примечание

Поля `box_size_snapshot` и `box_unit_snapshot` нужны, чтобы история партии не сломалась, если потом изменится карточка товара.

---

## 7. Таблица `stock_movements`

Создать журнал движения остатков.

```sql
CREATE TABLE `stock_movements` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED DEFAULT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,

  `movement_type` enum(
    'purchase',
    'reserve',
    'unreserve',
    'sale',
    'return_to_stock',
    'move_to_discount',
    'writeoff',
    'correction'
  ) NOT NULL,

  `stock_mode` enum('preorder','instant','discount_stock','internal') NOT NULL DEFAULT 'internal',
  `boxes_delta` decimal(10,2) NOT NULL,
  `boxes_balance_after` decimal(10,2) DEFAULT NULL,

  `comment` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_stock_movements_batch` (`purchase_batch_id`),
  KEY `idx_stock_movements_product` (`product_id`),
  KEY `idx_stock_movements_order` (`order_id`),
  KEY `idx_stock_movements_type` (`movement_type`),

  CONSTRAINT `fk_stock_movements_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_movements_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_movements_order`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_stock_movements_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Правило

`purchase_batches` хранит быстрые текущие остатки, но **источник правды — `stock_movements`**. После каждого движения сервис должен пересчитать кэшированные поля партии.

---

## 8. Таблица `purchase_batch_photos`

```sql
CREATE TABLE `purchase_batch_photos` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_batch_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_purchase_batch_photos_batch` (`purchase_batch_id`),

  CONSTRAINT `fk_purchase_batch_photos_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 9. Изменения в `products`

Не удалять текущие поля. Добавить новые поля для текущей активной партии и остатков по режимам.

```sql
ALTER TABLE `products`
  ADD COLUMN `current_purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `free_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `reserved_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `sold_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `written_off_stock_boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `preorder_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `instant_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `preorder_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `instant_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `discount_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `stock_status` enum('in_stock','preorder','arriving_today','sold_out','hidden') NOT NULL DEFAULT 'sold_out';
```

Индексы:

```sql
ALTER TABLE `products`
  ADD KEY `idx_products_current_batch` (`current_purchase_batch_id`),
  ADD KEY `idx_products_stock_status` (`stock_status`);
```

Связь можно добавить после проверки данных:

```sql
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_current_batch`
    FOREIGN KEY (`current_purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;
```

### Важное правило совместимости

На первом этапе `products.price` можно обновлять значением `instant_unit_price`, чтобы старые карточки и корзина не сломались.

Но в новой карточке использовать новые поля:

- `instant_price_per_box` для “купить сейчас”;
- `preorder_price_per_box` для “предзаказ”;
- `discount_price_per_box` для “выгодный остаток”.

---

## 10. Изменения в `orders`

```sql
ALTER TABLE `orders`
  ADD COLUMN `order_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  ADD COLUMN `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `reserved_at` datetime DEFAULT NULL,
  ADD COLUMN `fulfilled_from_stock_at` datetime DEFAULT NULL,
  ADD COLUMN `bonuses_allowed` tinyint(1) NOT NULL DEFAULT 1,
  ADD COLUMN `coupons_allowed` tinyint(1) NOT NULL DEFAULT 1;
```

Индексы:

```sql
ALTER TABLE `orders`
  ADD KEY `idx_orders_order_mode` (`order_mode`),
  ADD KEY `idx_orders_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_orders_delivery_mode` (`delivery_date`, `order_mode`);
```

Связь:

```sql
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_purchase_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;
```

---

## 11. Изменения в `order_items`

```sql
ALTER TABLE `order_items`
  ADD COLUMN `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `stock_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  ADD COLUMN `cost_unit_price` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `cost_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `sale_price_per_box` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `margin_amount` decimal(10,2) NOT NULL DEFAULT 0;
```

Индексы:

```sql
ALTER TABLE `order_items`
  ADD KEY `idx_order_items_purchase_batch` (`purchase_batch_id`),
  ADD KEY `idx_order_items_stock_mode` (`stock_mode`);
```

Связь:

```sql
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_purchase_batch`
    FOREIGN KEY (`purchase_batch_id`) REFERENCES `purchase_batches` (`id`) ON DELETE SET NULL;
```

### Важное ограничение MVP

Сейчас `order_items` имеет первичный ключ `order_id + product_id`. Поэтому в одном заказе нельзя иметь один и тот же товар двумя строками из разных партий/режимов.

Для MVP принять ограничение:

```text
Один товар в одном заказе может быть только в одном режиме: preorder / instant / discount_stock.
```

Если позже нужно разрешить один товар в одном заказе из разных партий, потребуется отдельная миграция:

- добавить `id` в `order_items`;
- заменить PK на `id`;
- оставить индекс `order_id + product_id`.

---

## 12. Изменения в `cart_items`

Сейчас `cart_items` имеет первичный ключ `user_id + product_id`. Это значит, что один и тот же товар не может лежать в корзине одновременно как “купить сейчас” и как “предзаказ”.

### MVP-решение, безопасное

Добавить поля:

```sql
ALTER TABLE `cart_items`
  ADD COLUMN `purchase_batch_id` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `stock_mode` enum('preorder','instant','discount_stock') NOT NULL DEFAULT 'instant',
  ADD COLUMN `boxes` decimal(10,2) NOT NULL DEFAULT 0,
  ADD COLUMN `sale_price_per_box` decimal(10,2) NOT NULL DEFAULT 0;
```

При добавлении товара, если такой `product_id` уже есть в корзине, но режим другой — показывать сообщение:

```text
Этот товар уже есть в корзине в другом режиме. Оформите текущую корзину или замените режим заказа.
```

### Полное решение, но отдельной итерацией

Позже перевести `cart_items` на отдельный `id`:

```text
id, user_id, product_id, purchase_batch_id, stock_mode
```

Тогда можно будет класть в корзину один товар из разных партий/режимов.

---

## 13. Изменение роли пользователя

В `users.role` сейчас enum:

```text
client, admin, courier, manager, partner, seller
```

Добавить роль:

```text
buyer
```

Миграция:

```sql
ALTER TABLE `users`
  MODIFY `role` enum('client','admin','courier','manager','partner','seller','buyer') NOT NULL DEFAULT 'client';
```

Добавить helper:

```php
function requireBuyer(): void
{
    requireRole('buyer', 'manager', 'admin');
}
```

---

## 14. Сервисы, которые нужно добавить

### 14.1. `PurchaseBatchService`

Файл:

```text
src/Services/PurchaseBatchService.php
```

Ответственность:

- создание закупки;
- расчёт цен;
- установка статуса партии;
- распределение партии;
- перевод в свободную продажу;
- перевод в выгодный остаток;
- закрытие партии.

Методы:

```php
createBatch(array $data): int
calculatePrices(int $productId, float $purchasePricePerBox, ?array $settings = null): array
markArrived(int $batchId): void
allocateToPreorders(int $batchId): void
moveToDiscountStock(int $batchId, float $boxes): void
writeOff(int $batchId, float $boxes, string $comment): void
closeBatch(int $batchId): void
```

### 14.2. `StockService`

Файл:

```text
src/Services/StockService.php
```

Ответственность:

- проверка доступного остатка;
- резервирование;
- продажа;
- отмена резерва;
- списание;
- пересчёт кэшей.

Методы:

```php
getAvailableBoxes(int $productId, string $mode): float
reserve(int $productId, int $batchId, float $boxes, int $orderId, string $mode): void
unreserve(int $productId, int $batchId, float $boxes, int $orderId): void
sell(int $productId, int $batchId, float $boxes, int $orderId): void
writeOff(int $batchId, float $boxes, int $userId, string $comment): void
recalculateBatchCounters(int $batchId): void
syncProductStock(int $productId): void
```

### 14.3. `PricingService`

Файл:

```text
src/Services/PricingService.php
```

Ответственность:

- чтение настроек из `settings`;
- расчёт цены предзаказа;
- расчёт цены свободной продажи;
- расчёт выгодного остатка;
- округление.

Методы:

```php
getSettings(): array
floorToStep(float $price, int $step = 10): float
calculateFromPurchase(float $purchasePricePerBox, float $boxSize): array
```

### 14.4. `BatchNotificationService`

Файл:

```text
src/Services/BatchNotificationService.php
```

Ответственность:

- уведомить клиентов по предзаказам;
- уведомить базу о свободном остатке;
- уведомить о выгодном остатке;
- логировать отправки.

---

## 15. Контроллеры и роуты

### 15.1. Новый контроллер

Файл:

```text
src/Controllers/PurchaseBatchesController.php
```

Методы:

```php
index()
create()
store()
show(int $id)
markArrived()
moveToDiscount()
writeOff()
close()
```

### 15.2. Роуты для admin

Добавить в `routes/admin.php`:

```text
GET  /admin/purchases
GET  /admin/purchases/create
POST /admin/purchases/store
GET  /admin/purchases/{id}
POST /admin/purchases/arrived
POST /admin/purchases/move-to-discount
POST /admin/purchases/write-off
POST /admin/purchases/close
```

### 15.3. Роуты для manager

Добавить в `routes/manager.php`:

```text
GET  /manager/purchases
GET  /manager/purchases/create
POST /manager/purchases/store
GET  /manager/purchases/{id}
POST /manager/purchases/arrived
POST /manager/purchases/move-to-discount
POST /manager/purchases/write-off
```

### 15.4. Роуты для buyer

Создать:

```text
routes/buyer.php
```

Роуты:

```text
GET  /buyer/purchases
GET  /buyer/purchases/create
POST /buyer/purchases/store
```

Подключить в основном роутинге.

---

## 16. Админка: раздел “Закупка”

### 16.1. Доступ

Показать пункт меню:

- admin;
- manager;
- buyer — в упрощённом виде.

### 16.2. Страница списка закупок

Файл:

```text
src/Views/admin/purchases/index.php
```

Колонки:

| Колонка | Описание |
|---|---|
| Дата | дата закупки |
| Товар | название товара |
| Закупщик | кто добавил |
| Куплено | всего ящиков |
| Цена закупки | за ящик |
| Цена предзаказа | за ящик |
| Цена сейчас | за ящик |
| Забронировано | под предзаказы |
| Свободно | для продажи сейчас |
| Продано | ушло в заказы |
| Выгодный остаток | отдельный остаток |
| Списано | брак/потери |
| Остаток | общий текущий остаток |
| Статус | статус партии |
| Действия | открыть / поступила / уценить / списать / закрыть |

### 16.3. Кнопка “Добавить закупку”

Файл:

```text
src/Views/admin/purchases/create.php
```

Поля:

| Поле | Тип | Обязательно |
|---|---|---|
| Товар | select | да |
| Дата закупки | date/time | да |
| Количество ящиков | number | да |
| Цена закупки за ящик | money | да |
| Доп. расходы на ящик | money | нет |
| Сколько поставить в свободную продажу | number | да, по умолчанию 10 |
| Резерв | number | нет |
| Комментарий | textarea | нет |
| Фото партии | upload multiple | нет |
| Статус | purchased/arrived | да |

После ввода цены закупки показывать предварительный расчёт:

```text
Цена предзаказа: 1300 ₽ / ящик
Цена сейчас: 1500 ₽ / ящик
Цена выгодного остатка: 1100 ₽ / ящик
```

---

## 17. Кабинет закупщика

### 17.1. Задача

Закупщик должен видеть только то, что ему нужно:

1. сколько уже заказано на сегодня/завтра;
2. что нужно купить;
3. сколько взять дополнительно в свободную продажу;
4. добавить фактическую закупку.

### 17.2. Экран

Файл:

```text
src/Views/buyer/purchases/index.php
```

Блок:

```text
План закупки на сегодня
```

Таблица:

| Товар | Предзаказы | Свободная продажа по умолчанию | Рекомендовано купить |
|---|---:|---:|---:|
| Клубника Клери | 18 | 10 | 28 |
| Черешня | 4 | 3 | 7 |

Кнопка:

```text
Добавить фактическую закупку
```

---

## 18. Клиентская карточка товара

Файлы:

```text
src/Views/client/_card.php
src/Views/client/product.php
```

### 18.1. Что показать в карточке

Пример:

```text
Клубника Клери 2 кг

В наличии сейчас: 10 ящиков
Цена сегодня: 1500 ₽ / ящик

Предзаказ на следующий привоз: 1300 ₽ / ящик
```

Кнопки:

```text
Купить сейчас
Забронировать к поставке
```

### 18.2. Бейджи

| Условие | Бейдж |
|---|---|
| `free_stock_boxes > 0` | В наличии сегодня |
| `stock_status = arriving_today` | Приедет сегодня |
| `free_stock_boxes = 0`, но предзаказ разрешён | Предзаказ |
| всё распродано | Раскупили |

### 18.3. Остатки в свободной продаже

Показывать клиенту только свободный остаток:

```text
Осталось 3 ящика
```

Не показывать:

- общий остаток партии;
- закупочную цену;
- резерв;
- остатки под предзаказы.

---

## 19. Корзина и checkout

Файлы ориентировочно:

```text
src/Controllers/ClientController.php
src/Views/client/cart.php
src/Views/client/checkout.php
src/Models/Order.php
src/Models/OrdersRepository.php
```

### 19.1. Добавление в корзину

При клике “Купить сейчас” передавать:

```text
product_id
stock_mode = instant
purchase_batch_id = current_purchase_batch_id
unit_price = instant_unit_price
boxes = quantity
```

При клике “Предзаказ”:

```text
product_id
stock_mode = preorder
unit_price = preorder_unit_price
boxes = quantity
```

При клике “Выгодный остаток”:

```text
product_id
stock_mode = discount_stock
purchase_batch_id = current_purchase_batch_id
unit_price = discount_unit_price
boxes = quantity
```

### 19.2. Проверка остатка

Перед добавлением в корзину и перед созданием заказа:

```text
если stock_mode = instant:
  проверить free_stock_boxes

если stock_mode = discount_stock:
  проверить discount_stock_boxes

если stock_mode = preorder:
  можно создать reserved-заказ без физического остатка
```

### 19.3. Резервирование

На этапе создания заказа:

- `instant` → сразу резервировать свободный остаток;
- `discount_stock` → сразу резервировать выгодный остаток;
- `preorder` → создать заказ со статусом `reserved`, физический резерв появится после закупки.

---

## 20. Заказы и статусы

Текущие статусы сохранить:

```text
reserved, new, processing, assigned, delivered, cancelled
```

Новая трактовка:

| Статус | Значение |
|---|---|
| `reserved` | предзаказ создан, ждёт закупку/поставку |
| `new` | товар есть, заказ создан |
| `processing` | заказ собирается |
| `assigned` | передан курьеру/исполнителю |
| `delivered` | доставлен |
| `cancelled` | отменён |

### 20.1. При отмене заказа

Если отменяется `instant` или `discount_stock` до доставки:

1. сделать `unreserve`;
2. вернуть остаток в соответствующий режим;
3. записать движение в `stock_movements`.

Если отменяется `preorder` до поступления:

1. просто отменить заказ;
2. убрать из плана закупки.

---

## 21. Бонусы и промокоды

Файлы ориентировочно:

```text
src/Models/Order.php
src/Models/PointsTransaction.php
src/Controllers/OrdersController.php
src/Controllers/ClientController.php
```

### 21.1. Правила

| Режим | Покупатель получает бонусы | Менеджер/продавец получает бонусы | Промокод | Списание бонусов |
|---|---|---|---|---|
| `preorder` | да | да | да | да |
| `instant` | да | да | да | да |
| `discount_stock` | нет | нет | нет | нет |

### 21.2. Техническое правило

Если `order_mode = discount_stock`:

```text
points_accrued = 0
manager_points_accrued = 0
points_used = 0
discount_applied = 0
coupon_code = null
bonuses_allowed = 0
coupons_allowed = 0
```

---

## 22. Telegram-уведомления

Файлы ориентировочно:

```text
src/Helpers/TelegramSender.php
src/Controllers/BotController.php
src/Controllers/MailingController.php
log/telegram_notify.log
```

### 22.1. Событие “Партия поступила”

Когда менеджер нажимает:

```text
Партия поступила
```

Система должна:

1. сменить статус партии на `arrived` или `active`;
2. распределить товар по предзаказам;
3. обновить остатки товара;
4. обновить карточки;
5. отправить уведомления клиентам с предзаказами;
6. предложить менеджеру сделать рассылку о свободном остатке.

### 22.2. Сообщение клиенту по предзаказу

```text
🍓 Ваша ягода поступила!

Клубника уже у нас и хранится в холодильнике.
Заказ №{order_id} готовится к выдаче/доставке.

Дата: {delivery_date}
Интервал: {slot}
```

### 22.3. Сообщение по свободной продаже

```text
🍓 Свежая партия приехала!

В свободной продаже осталось {free_stock_boxes} ящиков.
Можно заказать на сегодня.

Купить: {link}
```

### 22.4. Сообщение по выгодному остатку

```text
🍓 Сегодня выгодно

Осталось {discount_stock_boxes} ящиков из холодильника по сниженной цене.
Бонусы и промокоды на этот товар не действуют.

Забрать: {link}
```

---

## 23. Выгодный остаток

### 23.1. Где показывать

На основном сайте и главной странице **не акцентировать**.

Использовать:

- Авито;
- Telegram;
- прямую ссылку;
- менеджерские продажи.

### 23.2. Скрытая страница

Создать страницу:

```text
/today-deal
```

или:

```text
/vygodno
```

Не добавлять в главное меню.  
Не добавлять в sitemap.  
Добавить:

```html
<meta name="robots" content="noindex, nofollow">
```

### 23.3. Кнопка в админке

В партии добавить кнопку:

```text
Перевести в выгодный остаток
```

Форма:

| Поле | Описание |
|---|---|
| Количество ящиков | сколько перевести |
| Цена | по умолчанию закупка + 100 ₽ |
| Комментарий | причина |

После перевода:

- увеличить `discount_stock_boxes`;
- уменьшить обычный остаток;
- записать `stock_movements.move_to_discount`;
- отключить бонусы/промокоды.

---

## 24. Главная страница

Файл:

```text
src/Views/client/home.php
```

Главная должна продавать не уценку, а:

- свежий привоз;
- наличие сегодня;
- предзаказ;
- холодильное хранение;
- отличие от продажи с машины и дороги.

### 24.1. Первый экран

```text
Свежая ягода с утренней закупки — можно купить сегодня или забронировать следующий привоз

Закупаем утром, храним в холодильнике и показываем честное наличие по ящикам.
```

Кнопки:

```text
Купить сегодня
Оформить предзаказ
```

---

## 25. Отчёты

Добавить отчёты для admin/manager.

### 25.1. Отчёт по партиям

| Партия | Куплено | Цена закупки | Выручка | Себестоимость | Маржа | Продано | Остаток | Списано |
|---|---:|---:|---:|---:|---:|---:|---:|---:|

### 25.2. Отчёт по режимам

| Режим | Заказов | Ящиков | Выручка | Маржа |
|---|---:|---:|---:|---:|
| Предзаказ |  |  |  |  |
| Свободная продажа |  |  |  |  |
| Выгодный остаток |  |  |  |  |

### 25.3. KPI

| KPI | Что показывает |
|---|---|
| Доля предзаказов | насколько закупка стала прогнозируемой |
| Доля свободной продажи | сколько забирает горячий спрос |
| Остатки к концу дня | риск списаний |
| Списания | потери |
| Маржа по партии | реальная экономика |
| Средняя цена продажи ящика | эффективность ценообразования |

---

## 26. Пошаговый roadmap внедрения

## Этап 0. Подготовка и защита от поломки

**Цель:** безопасно зайти в разработку.

Задачи:

1. Сделать backup production-БД.
2. Поднять копию базы из `yago (7).sql` локально.
3. Проверить команду:

```bash
php bin/migrate.php status
```

4. Проверить текущие тесты:

```bash
vendor/bin/phpunit
```

5. Запретить правку старых применённых миграций.
6. Все изменения делать новыми миграциями `2026_XX_*.sql`.

Критерий готовности:

- локальная база поднята;
- миграции применяются;
- текущий каталог и оформление заказа работают как до изменений.

---

## Этап 1. Миграции и модели данных

**Цель:** добавить партии и движения остатков, не меняя клиентскую витрину.

Задачи:

1. Создать `purchase_batches`.
2. Создать `stock_movements`.
3. Создать `purchase_batch_photos`.
4. Добавить поля в `products`.
5. Добавить поля в `orders`.
6. Добавить поля в `order_items`.
7. Добавить поля в `cart_items`.
8. Добавить настройки в `settings`.
9. Добавить роль `buyer` в `users.role`.

Критерий готовности:

- миграции применяются без ошибок;
- старые заказы открываются;
- старый каталог открывается;
- старая корзина не падает;
- поля старых товаров заполнены дефолтами.

---

## Этап 2. Сервисы цен и склада

**Цель:** вся математика должна быть в сервисах, а не во view/контроллерах.

Задачи:

1. Создать `PricingService`.
2. Создать `StockService`.
3. Создать `PurchaseBatchService`.
4. Реализовать округление вниз до 10 ₽.
5. Реализовать расчёт цены по закупке:
   - предзаказ +30%;
   - свободная продажа +50%;
   - выгодный остаток +100 ₽.
6. Реализовать пересчёт остатков партии.
7. Реализовать синхронизацию `products.free_stock_boxes` и `products.stock_status`.

Критерий готовности:

- тестовый вызов создания партии создаёт цены;
- остатки корректно пишутся;
- движение появляется в `stock_movements`;
- `products` получает актуальный свободный остаток.

---

## Этап 3. Админка “Закупка”

**Цель:** менеджер и админ могут добавить фактическую закупку.

Задачи:

1. Создать `PurchaseBatchesController`.
2. Добавить роуты в `routes/admin.php`.
3. Добавить роуты в `routes/manager.php`.
4. Добавить пункт меню “Закупка”.
5. Создать страницу списка закупок.
6. Создать форму “Добавить закупку”.
7. Реализовать загрузку фото партии.
8. Реализовать кнопки:
   - “Партия поступила”;
   - “Перевести в выгодный остаток”;
   - “Списать”;
   - “Закрыть”.

Критерий готовности:

- менеджер добавляет закупку;
- цена на сайте обновляется;
- свободный остаток появляется в товаре;
- партия видна в списке закупок.

---

## Этап 4. Клиентская карточка и каталог

**Цель:** клиент видит честное наличие и два сценария покупки.

Задачи:

1. Обновить `ClientCatalogService` и выборки товаров.
2. Добавить в SELECT новые поля:
   - `free_stock_boxes`;
   - `reserved_stock_boxes`;
   - `discount_stock_boxes`;
   - `instant_price_per_box`;
   - `preorder_price_per_box`;
   - `stock_status`.
3. Обновить `src/Views/client/_card.php`.
4. Обновить `src/Views/client/product.php`.
5. Добавить кнопки:
   - “Купить сейчас”;
   - “Забронировать к поставке”.
6. Ограничить количество в input по свободному остатку.
7. При нулевом остатке менять CTA на предзаказ.

Критерий готовности:

- в карточке видно “В наличии: N ящиков”;
- цена “сейчас” считается от партии;
- предзаказ имеет отдельную цену;
- нельзя купить больше свободного остатка.

---

## Этап 5. Корзина и checkout

**Цель:** заказ должен списывать правильный остаток.

Задачи:

1. Передавать `stock_mode` при добавлении в корзину.
2. Передавать `purchase_batch_id` для `instant` и `discount_stock`.
3. Проверять остаток перед добавлением в корзину.
4. Проверять остаток повторно перед созданием заказа.
5. При создании заказа:
   - для `instant` резервировать свободный остаток;
   - для `discount_stock` резервировать выгодный остаток;
   - для `preorder` создавать `reserved` без физического резерва.
6. Фиксировать в `order_items`:
   - партию;
   - себестоимость;
   - цену продажи;
   - маржу;
   - режим.

Критерий готовности:

- заказ “купить сейчас” уменьшает свободный остаток;
- заказ “предзаказ” попадает в план закупки;
- заказ “выгодный остаток” не начисляет бонусы;
- отмена заказа возвращает остаток.

---

## Этап 6. Кабинет закупщика

**Цель:** закупщик утром видит план и вносит факт закупки с телефона.

Задачи:

1. Добавить `routes/buyer.php`.
2. Добавить `requireBuyer()`.
3. Создать buyer-layout или использовать упрощённый admin layout.
4. Создать страницу плана закупки.
5. Создать форму добавления закупки.
6. Ограничить доступ закупщика:
   - не видит пользователей;
   - не видит бонусы;
   - не меняет настройки;
   - не удаляет заказы.

Критерий готовности:

- пользователь с ролью `buyer` может добавить закупку;
- admin/manager видят, кто добавил партию;
- закупщик не имеет лишних прав.

---

## Этап 7. Telegram-уведомления

**Цель:** партия поступила → клиенты и база узнают об этом.

Задачи:

1. Создать `BatchNotificationService`.
2. При статусе `arrived/active` уведомлять клиентов по предзаказам.
3. Добавить кнопку “Отправить рассылку о свободном остатке”.
4. Добавить кнопку “Отправить рассылку о выгодном остатке”.
5. Логировать отправку.
6. Не отправлять повторно одно и то же уведомление.

Критерий готовности:

- после поступления партии клиент получает сообщение;
- менеджер может отправить рассылку о свободных 10 ящиках;
- сообщения не дублируются.

---

## Этап 8. Выгодный остаток и Авито-сценарий

**Цель:** продавать вчерашний/остаточный товар без порчи основной витрины.

Задачи:

1. Реализовать режим `discount_stock`.
2. Создать скрытую страницу `/today-deal` или `/vygodno`.
3. Не выводить её на главную.
4. Не добавлять в sitemap.
5. Добавить `noindex, nofollow`.
6. Отключить бонусы и промокоды.
7. Добавить в админке копируемую ссылку для Авито/Telegram.

Критерий готовности:

- выгодный остаток можно купить по ссылке;
- бонусы не начисляются;
- основная карточка свежего товара не смешивается с уценкой;
- остаток корректно списывается.

---

## Этап 9. Отчёты и аналитика

**Цель:** видеть экономику партии.

Задачи:

1. Отчёт по партиям.
2. Отчёт по режимам продажи.
3. Отчёт по списаниям.
4. Отчёт по марже.
5. Показ доли предзаказов.
6. Показ доли свободной продажи.

Критерий готовности:

- по каждой партии видно:
  - закуплено;
  - продано;
  - остаток;
  - выручка;
  - себестоимость;
  - маржа;
  - списания.

---

## Этап 10. Тесты

Добавить/обновить тесты:

```text
tests/PricingServiceTest.php
tests/StockServiceTest.php
tests/PurchaseBatchServiceTest.php
tests/PurchaseBatchesControllerTest.php
tests/CartStockModeTest.php
tests/DiscountStockNoBonusesTest.php
```

### Обязательные сценарии

1. Цена закупки 1000 ₽ → предзаказ 1300 ₽ → свободная цена 1500 ₽.
2. Цена закупки 1180 ₽ → предзаказ 1530 ₽ → свободная цена 1770 ₽.
3. Создание партии на 30 ящиков создаёт остаток.
4. Продажа 1 ящика уменьшает свободный остаток.
5. Нельзя купить 3 ящика, если свободно 2.
6. Отмена заказа возвращает остаток.
7. Выгодный остаток не начисляет бонусы.
8. Предзаказ получает статус `reserved`.
9. После поступления партии предзаказ резервируется.
10. Старый заказ без `purchase_batch_id` открывается без ошибки.

---

## 27. Правила rollout

### 27.1. До деплоя

1. Сделать backup базы.
2. Прогнать миграции на копии.
3. Прогнать тесты.
4. Проверить страницы:
   - `/`;
   - `/catalog`;
   - карточка товара;
   - корзина;
   - checkout;
   - `/admin/orders`;
   - `/admin/products`.

### 27.2. После деплоя

1. Создать тестовую закупку на скрытом/тестовом товаре.
2. Проверить расчёт цены.
3. Проверить остаток в карточке.
4. Сделать тестовый заказ.
5. Проверить движение склада.
6. Отменить заказ и проверить возврат остатка.
7. Проверить, что старые заказы открываются.

---

## 28. Главное правило для разработчиков

Не заменять старую логику резко. Делать слой поставок поверх текущей схемы.

Старые поля оставить:

```text
products.price
products.sale_price
products.stock_boxes
products.delivery_date
orders.status
order_items.unit_price
order_items.quantity
order_items.boxes
```

Новые поля использовать для новой логики, но сохранять обратную совместимость.

---

## 29. Итоговый критерий готовности

Доработка считается выполненной, если по любой партии можно открыть карточку и увидеть:

1. сколько купили;
2. по какой закупочной цене;
3. какая цена предзаказа;
4. какая цена свободной продажи;
5. сколько ушло под предзаказы;
6. сколько свободно к заказу сейчас;
7. сколько продано;
8. сколько осталось;
9. сколько переведено в выгодный остаток;
10. сколько списано;
11. какая маржа;
12. какие заказы связаны с партией;
13. какие движения склада были по партии.

Главная бизнес-формула:

```text
BerryGo продаёт не абстрактный товар, а конкретную свежую партию с понятной ценой, остатком и историей движения.
```
