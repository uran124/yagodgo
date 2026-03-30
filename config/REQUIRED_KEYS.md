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

- `TELEGRAM_ADMIN_TOPIC_ID`
- `TELEGRAM_SECRET_TOKEN`

## Как проверить

При старте `bootstrap/app.php` валидирует обязательные ключи. Если чего-то не хватает, приложение завершится с HTTP 500 и списком отсутствующих ключей.
