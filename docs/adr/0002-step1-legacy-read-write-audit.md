# ADR-0002 / Шаг 1: аудит legacy-логики (catalog → cart → checkout)

- **Дата:** 2026-05-27
- **Статус:** Completed
- **Связано с:** `docs/adr/0002-box-as-sku-sales-model.md`

## Цель шага

Собрать карту текущих read/write-path, где ещё смешиваются старая и новая модели продажи, и зафиксировать конкретные точки для миграции на `purchase_batches` как единственный источник истины.

## Аудит legacy-полей и конфликтующих источников

Проверены ключевые legacy-поля:

- `free_stock_boxes`
- `discount_stock_boxes`
- `instant_price_per_box`
- `preorder_price_per_box`
- `current_purchase_batch_id`

### Критические findings (первый приоритет)

1. **Главная/каталог используют смешанную модель доступности.**
   - Условия секций каталога опираются на `products.discount_stock_boxes`.
   - Одновременно расчёт флагов доступности строится через агрегат `purchase_batches`.
   - Это создаёт расхождение UI-статуса и фактической доступности для корзины.

2. **Каталог привязывает цену к `products.current_purchase_batch_id` вместо резолва продаваемой партии.**
   - В выборке цена идёт через `LEFT JOIN purchase_batches pb ON pb.id = p.current_purchase_batch_id`.
   - При нескольких партиях товара это не гарантирует корректную продаваемую партию.

3. **Checkout читает партию через `products.current_purchase_batch_id` (не через строку корзины).**
   - Это нарушает инвариант ADR-0002: корзина/заказ должны быть привязаны к конкретной партии в момент добавления.

4. **Есть явная двусторонняя синхронизация `purchase_batches -> products` в сервисах/утилитах.**
   - `StockService` и `PurchaseBatchService` обновляют `products.free_stock_boxes`/ценовые поля.
   - Отдельный repair-скрипт пересчитывает агрегаты обратно в `products`.

5. **Есть код, который до сих пор считает “доступность товара” напрямую через `products.free_stock_boxes > 0`.**
   - Пример: `ProductsController::getAllActive()`.

## Карта модулей и источников данных

| Зона | Файл / модуль | Что сейчас используется | Риск |
|---|---|---|---|
| Главная/каталог | `src/Services/ClientCatalogService.php` | Смешение `products.discount_stock_boxes` + `purchase_batches` availability + `current_purchase_batch_id` для цены | Высокий |
| Публичный список товаров | `src/Controllers/ProductsController.php` (`getAllActive`) | `products.free_stock_boxes > 0` | Высокий |
| Checkout | `src/Controllers/OrdersController.php` | Чтение cart с привязкой к `products`/batch через product snapshot-подход | Высокий |
| Синхронизация остатков | `src/Services/StockService.php` | Обновление `products.free_stock_boxes` и `discount_stock_boxes` | Высокий |
| Синхронизация batch snapshot | `src/Services/PurchaseBatchService.php` | Обновление `products.current_purchase_batch_id` и ценовых полей | Высокий |
| Операционный repair | `bin/supply_repair_product_aggregates.php` | Проекция агрегатов batch в `products` | Средний (оставить только compatibility) |

## Решение по шагу 1

Зафиксировано, что далее в реализации:

1. **Нельзя читать availability/price для клиентского пути из `products.*stock*` и `products.*price*`.**
2. **Нужен единый batch-resolver** для home/catalog/product/cart/checkout.
3. **Cart item должен хранить `purchase_batch_id` + snapshot цены** как основной источник для checkout.
4. **Legacy-поля остаются только как compatibility projection** (one-way), без участия в бизнес-решениях.

## Готовый backlog на следующий шаг (шаг 2)

1. Ввести `SellableBatchResolver` (FIFO или priority-first — выбрать и зафиксировать в config).
2. Переподключить `ClientCatalogService`:
   - убрать фильтрацию по `products.discount_stock_boxes`;
   - цену и статус получать только из resolver/batch-данных.
3. Подготовить миграцию/проверку `cart_items` на обязательный `purchase_batch_id` и price snapshot.
4. Обновить checkout flow: чтение только из `cart_items.purchase_batch_id` + atomic lock/write-off.

## Команды аудита

- `rg -n "free_stock_boxes|discount_stock_boxes|instant_price_per_box|preorder_price_per_box|current_purchase_batch_id|purchase_batch_id|boxes_free|add.?to.?cart|cart|checkout"`
- Просмотр ключевых модулей:
  - `src/Services/ClientCatalogService.php`
  - `src/Controllers/ProductsController.php`
  - `src/Controllers/OrdersController.php`
  - `src/Services/StockService.php`
  - `src/Services/PurchaseBatchService.php`
  - `bin/supply_repair_product_aggregates.php`
