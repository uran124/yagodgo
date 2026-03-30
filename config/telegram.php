<?php

declare(strict_types=1);

return [
    'bot_token'      => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'admin_chat_id'  => getenv('TELEGRAM_ADMIN_CHAT_ID') ?: '',
    'admin_topic_id' => getenv('TELEGRAM_ADMIN_TOPIC_ID') !== false
        ? (int)getenv('TELEGRAM_ADMIN_TOPIC_ID')
        : null,
    'secret_token'   => getenv('TELEGRAM_SECRET_TOKEN') ?: '',
];
