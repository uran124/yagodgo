# Supply digest (зависшие остатки и аномалии)

Скрипт: `bin/supply_digest.php`

## Что делает

- Находит активные партии со "зависшим" остатком старше N дней.
- Выводит аномалии по остаткам (например, отрицательный остаток или `boxes_written_off > boxes_total`).
- Печатает JSON-отчёт в stdout.

## Запуск

```bash
php bin/supply_digest.php
```

Порог возраста партии в днях (по умолчанию 2):

```bash
php bin/supply_digest.php 3
```

## Рекомендация по cron

Ежедневно утром запускать скрипт и отправлять JSON в канал мониторинга/Telegram-бота.

## Отправка summary в Telegram

```bash
php bin/supply_digest.php 2 --telegram
```

Требуются `TELEGRAM_BOT_TOKEN` и `TELEGRAM_ADMIN_CHAT_ID` (берутся из `config/telegram.php`).
