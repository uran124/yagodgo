# Отчёт проверки модуля BerryGo → Florix24

**Дата:** 19.07.2026

## Выполнено

### PHP syntax lint

Проверены все PHP-файлы рабочей копии проекта:

```text
227 файлов
ошибок синтаксиса: 0
```

Команда:

```bash
find . -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Статические проверки проекта

Проверка патчированной версии выдаёт те же три замечания, что и исходный архив:

```text
src/Views/layouts/admin_main2.php:42
src/Views/layouts/main2.php:209
src/Views/admin/users/index2.php:23
```

Это старые резервные шаблоны `*2.php`, не изменённые модулем. Новых замечаний статическая проверка не добавила.

### Проверка покрытия точек создания заказа

Подключены:

```text
заказ из админки через OrderGroupCreationService
заказ покупателя через OrderGroupCreationService
старый checkout OrdersController
основной Telegram BotController
отдельный runtime Telegram-бота
```

### Проверка точек изменения статуса

Центральная постановка события выполняется из `OrderStatusHistoryService`. Её используют:

```text
администратор
селлер
клиент при подтверждении брони
автоматическая отмена брони по партии
входящий webhook Florix24 с подавлением обратной отправки
```

### Проверка контракта Florix24

Код подготовлен под фактические endpoints установленного патча Florix24:

```text
POST  /api/v1/orders
GET   /api/v1/orders/{id}/status
PATCH /api/v1/orders/external/{external_order_id}/status
```

Входящий webhook проверяет заголовки:

```text
X-Florix-Event-Id
X-Florix-Timestamp
X-Florix-Signature
```

## Ограничения среды проверки

В текущем изолированном окружении отсутствовали:

```text
vendor/ проекта
PHP PDO-драйверы
PHP cURL
доступ к рабочим доменам и рабочей MySQL
```

Поэтому PHPUnit и реальные HTTP-запросы между рабочими BerryGo/Florix24 здесь не выполнялись. Автоматические PHPUnit-тесты добавлены в архив и должны быть запущены на сервере/в CI после установки зависимостей.

## Обязательная боевая проверка

После установки провести четыре сценария из `BERRYGO_FLORIX24_MODULE_README.md`: заказ с сайта, заказ из админки, статусы в обе стороны и восстановление после неверного токена.
