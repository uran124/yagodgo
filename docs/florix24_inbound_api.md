# Входящий API Florix24

## Ключ и права

Администратор создаёт или ротирует ключ в **Настройки → Интеграции → Входящий API Florix24**. Значение показывается один раз, начинается с `bg_live_`, а BerryGo хранит только `password_hash`, префикс, даты создания/последнего использования и отзыва.

Ключ Florix24 получает права `customers.read`, `orders.create`, `orders.cancel`, `catalog.read`. Каждый запрос использует `Authorization: Bearer <token>`. Превышение 60 запросов в минуту возвращает `429`, `Retry-After: 60` и `{ "result": "error", "error": "rate_limit", "retry_after": 60 }`.

IP-ограничение по умолчанию выключено. После подтверждения исходящих адресов Florix24 его можно включить в этой же карточке, указав IP или CIDR по одному на строку. До отдельной настройки trusted proxy BerryGo использует только `REMOTE_ADDR` и намеренно не доверяет `X-Forwarded-For`.

## Endpoints

* `GET /api/v1/integrations/florix/customers/by-phone?phone=79000000000` — требуется `customers.read`.
* `POST /api/v1/integrations/florix/orders` — требуется `orders.create`; `external_order_id` идемпотентен в пределах источника `florix24`.
* `POST /api/v1/integrations/florix/orders/{external_order_id}/cancel` — требуется `orders.cancel`; отмена откатывает резерв/продажу склада по стандартным правилам BerryGo, записывает историю статуса, а возврат баллов и reversal партнёра идемпотентны.

Во всех API-ответах присутствует или возвращается заголовок `X-Correlation-ID`. Токены никогда не записываются в журнал запросов.

## YML

Статический файл публикуется по `https://berrygo.ru/feeds/catalog.yml`. После изменения влияющих на каталог полей товара BerryGo помечает feed как устаревший. Добавьте cron каждые 10 минут:

```cron
*/10 * * * * cd /path/to/yagodgo && /usr/bin/php bin/generate_catalog_feed.php >> log/catalog_feed.log 2>&1
```

Генератор создаёт `feeds/catalog.yml.tmp`, проверяет XML и атомарно заменяет готовый файл только после успеха.

## Миграции

Перед применением pending SQL-миграций `php bin/migrate.php up` создаёт согласованный `mysqldump` в `backups/database` (или в `MIGRATION_BACKUP_DIR`). Если backup не удаётся, миграции не выполняются. Флаг `--skip-backup` предназначен только для случая, когда резервная копия уже была проверена внешней процедурой. Florix24-миграции в runner проверяют таблицы, колонки и индекс перед DDL, поэтому корректно сходятся после частично применённого hotfix; чистая установка получает ту же схему из `database/db.sql`.

## Acceptance coverage

`Florix24InboundServiceTest` покрывает нормализацию телефона, поиск баланса, создание нового клиента, снимок текущей цены BerryGo, максимальное списание баллов, назначение разрешённого партнёра, идемпотентный replay и отказ для неактивного товара. `Florix24PartnerRewardTest` покрывает отмену completed-заказа, возврат/сторно партнёра и историю статуса; `CatalogFeedServiceTest` проверяет XML, активность offers и приоритет внешнего изображения.
