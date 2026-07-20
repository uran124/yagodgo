# Изменённые файлы модуля BerryGo → Florix24

## Новые файлы

```text
cron/process_florix24_queue.php
database/20260719_florix24_integration.sql
src/Controllers/Florix24IntegrationController.php
src/Services/Florix24InboundStatusService.php
src/Services/Florix24IntegrationService.php
src/Services/Florix24WebhookException.php
src/Services/OrderStatusApplicationService.php
src/Services/OrderStatusConflictException.php
tests/Florix24IntegrationServiceTest.php
kraswebsite.ru/bots/berrygo/src/Services/Florix24IntegrationService.php
BERRYGO_FLORIX24_MODULE_README.md
BERRYGO_FLORIX24_CHANGED_FILES.md
BERRYGO_FLORIX24_TEST_REPORT.md
```

## Изменённые файлы

```text
bin/migrate.php
routes/admin.php
routes/public.php
src/Controllers/BotController.php
src/Controllers/ClientController.php
src/Controllers/OrdersController.php
src/Controllers/SettingsController.php
src/Services/AdminOrdersPageService.php
src/Services/OrderGroupCreationService.php
src/Services/OrderStatusHistoryService.php
src/Services/PurchaseBatchService.php
src/Views/admin/orders/show.php
src/Views/admin/settings.php
src/helpers.php
tests/CsrfProtectionTest.php
kraswebsite.ru/bots/berrygo/src/Controllers/BotController.php
```

## Не включено в установочный патч

`database/db.sql` обновлён в рабочей копии проекта для будущей полной сборки, но не включён в накладной установочный архив, поскольку для рабочего сайта достаточно отдельной миграции и полный дамп может содержать данные/секреты.
