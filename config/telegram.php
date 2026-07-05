<?php

declare(strict_types=1);

return [
    'bot_token'      => getenv('TELEGRAM_BOT_TOKEN') ?: '8101596626:AAFekmWOpK7OI9AYtuJ49cxMIGjwBZ-Ydtg',
    'admin_chat_id'  => getenv('TELEGRAM_ADMIN_CHAT_ID') ?: '-1002055168794',
    'admin_topic_id' => getenv('TELEGRAM_ADMIN_TOPIC_ID') !== false
        ? (int)getenv('TELEGRAM_ADMIN_TOPIC_ID')
        : null,
    'secret_token'   => getenv('TELEGRAM_SECRET_TOKEN') ?: 'optional_webhook_secret',
    'relay_url'      => getenv('TELEGRAM_RELAY_URL') ?: 'https://kraswebsite.ru/bots/berrygo/telegram_proxy.php',
    'relay_secret'   => getenv('TELEGRAM_RELAY_SECRET') ?: 'shared_secret_between_hosts',
];
