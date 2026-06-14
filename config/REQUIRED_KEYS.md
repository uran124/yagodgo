# Required runtime keys

BerryGo читает конфиги из `config/*.php`, а значения — из переменных окружения.

## Обязательные ключи

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_CHARSET` (рекомендуется `utf8mb4`)
- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_ADMIN_CHAT_ID`
- `SMS_API_ID`
- `EMAIL_FROM`

## Опциональные ключи

- `APP_ENV` — режим запуска (`production` по умолчанию; для локальной разработки используйте `local`).
- `APP_DEBUG` — включает подробный вывод ошибок, если значение `true`/`1`/`yes`/`on`; в production держите выключенным.
- `APP_LOG_FILE` — путь к application log; по умолчанию `log/app.log`.
- `TELEGRAM_ADMIN_TOPIC_ID`
- `TELEGRAM_SECRET_TOKEN`

## Как проверить

При старте `bootstrap/app.php` валидирует обязательные ключи. Если чего-то не хватает, приложение завершится с HTTP 500 и списком отсутствующих ключей.

В production при `APP_DEBUG=false` PHP-ошибки не выводятся в браузер: пользователь видит безопасное сообщение, а детали пишутся в application log.
