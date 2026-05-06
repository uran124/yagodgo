# BerryGo Telegram Bot (VDS CZ)

Эта папка предназначена для **отдельного хостинга бота** (VDS в Чехии) и **не деплоится вместе с основным сайтом**.

## Что внутри

- `webhook.php` — HTTP endpoint для Telegram webhook;
- `bootstrap.php` — загрузка конфигов и подключение к БД;
- `src/Controllers/BotController.php` — логика бота;
- `src/Helpers/SensitiveData.php` — маскирование секретов в логах;
- `config/*.example.php` — примеры конфигов;
- `composer.json` — зависимости для запуска бота отдельно от сайта.

## Файлы, которые нужно создать на VDS перед запуском

- `config/database.php` (на основе `config/database.example.php`)
- `config/telegram.php` (на основе `config/telegram.example.php`)

## Быстрый запуск на VDS

1. Загрузите папку `kraswebsite.ru/bots/berrygo/` на VDS.
2. Внутри папки выполните:
   - `composer install --no-dev --optimize-autoloader`
3. Создайте `config/database.php` и `config/telegram.php` из example-файлов.
4. Убедитесь, что веб-сервер отдает `webhook.php` по HTTPS URL (например `https://kraswebsite.ru/bots/berrygo/webhook.php`).
5. Зарегистрируйте webhook в Telegram:
   - `https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://kraswebsite.ru/bots/berrygo/webhook.php&secret_token=<TELEGRAM_SECRET_TOKEN>`
6. Проверьте статус:
   - `https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/getWebhookInfo`

## Связка сайта и бота при разделении хостингов

- Сайт остается на текущем (RU) хостинге.
- Бот принимает входящие апдейты Telegram на VDS (CZ) через `webhook.php`.
- Бот продолжает работать с общей БД проекта через `config/database.php` (доступ к БД должен быть разрешен для IP VDS и защищен firewall/allowlist).
- Исходящие уведомления сайта в Telegram (`sendMessage`) остаются рабочими, потому что они идут напрямую в Telegram API.

## Безопасность

- Обязательно используйте `secret_token` в `config/telegram.php` и в `setWebhook`.
- Не храните реальные токены в git.
- Ограничьте доступ к БД с VDS по IP и отдельному пользователю с минимальными правами.
