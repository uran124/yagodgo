<?php
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

// testbot.php - тестовая страница для отправки сообщений и просмотра отладочной информации
// Подключаем конфиг Telegram
$telegramConfig = require __DIR__ . '/config/telegram.php';
$botToken     = $telegramConfig['bot_token'];
$chatId       = $telegramConfig['admin_chat_id'];
$topicId      = $telegramConfig['admin_topic_id'] ?? null;

// Файл лога для отладки
$logFile = __DIR__ . '/telegram_testbot.log';

function telegramApiRequest(string $botToken, string $method, array $data = []): array
{
    global $telegramConfig;

    $relayUrl = trim((string)($telegramConfig['relay_url'] ?? ''));
    $relaySecret = (string)($telegramConfig['relay_secret'] ?? '');

    if ($relayUrl !== '') {
        $url = $relayUrl;
        $payload = json_encode([
            'bot_token' => $botToken,
            'method' => $method,
            'params' => $data,
            'secret' => $relaySecret,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $url = "https://api.telegram.org/bot{$botToken}/{$method}";
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $response = false;
    $transportError = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $transportError = 'cURL: ' . curl_error($ch);
        }
        curl_close($ch);
    }

    if ($response === false) {
        $context = stream_context_create([
            'http' => [
                'header' => "Content-Type: application/json; charset=UTF-8\r\n",
                'method' => 'POST',
                'content' => $payload,
                'timeout' => 5,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $lastError = error_get_last();
            $streamError = $lastError['message'] ?? 'неизвестная ошибка stream';
            $transportError = $transportError ? ($transportError . '; stream: ' . $streamError) : ('stream: ' . $streamError);
        }
    }

    return ['response' => $response, 'error' => $transportError];
}

// Обработка отправки формы
$sendResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'send_message') && !empty($_POST['message'])) {
    $text = trim($_POST['message']);
    // Отправляем сообщение через API Telegram / relay
    $data = [
        'chat_id' => $chatId,
        'text'    => $text,
    ];
    if ($topicId !== null) {
        $data['message_thread_id'] = (int)$topicId;
    }
    $request = telegramApiRequest($botToken, 'sendMessage', $data);
    $response = $request['response'];
    $transportError = $request['error'];

    if ($response === false) {
        $sendResult = 'Ошибка при отправке запроса. ' . $transportError;
    } else {
        $sendResult = htmlspecialchars($response);
    }
    // Логируем попытку и результат
    $logEntry = date('Y-m-d H:i:s') . " | SendMessage: {$text} | Response: {$sendResult}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

$webhookResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'set_webhook')) {
    $webhookUrl = trim($_POST['webhook_url'] ?? '');
    if ($webhookUrl === '') {
        $webhookResult = 'Укажите webhook URL.';
    } else {
        $request = telegramApiRequest($botToken, 'setWebhook', ['url' => $webhookUrl]);
        if ($request['response'] === false) {
            $webhookResult = 'Ошибка setWebhook. ' . $request['error'];
        } else {
            $webhookResult = htmlspecialchars($request['response']);
        }
        $logEntry = date('Y-m-d H:i:s') . " | SetWebhook: {$webhookUrl} | Response: {$webhookResult}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'delete_webhook')) {
    $dropPendingUpdates = (($_POST['drop_pending_updates'] ?? '0') === '1');
    $request = telegramApiRequest($botToken, 'deleteWebhook', ['drop_pending_updates' => $dropPendingUpdates]);
    if ($request['response'] === false) {
        $webhookResult = 'Ошибка deleteWebhook. ' . $request['error'];
    } else {
        $webhookResult = htmlspecialchars($request['response']);
    }
    $logEntry = date('Y-m-d H:i:s') . " | DeleteWebhook: drop_pending_updates=" . ($dropPendingUpdates ? '1' : '0') . " | Response: {$webhookResult}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

$webhookInfo = telegramApiRequest($botToken, 'getWebhookInfo');
$webhookInfoText = $webhookInfo['response'] !== false ? $webhookInfo['response'] : ('Ошибка getWebhookInfo. ' . $webhookInfo['error']);

// Считываем последние строки лог-файла
$debugInfo = '';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $last = array_slice($lines, -20); // последние 20 строк
    $debugInfo = implode("\n", $last);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>TestBot — Отправка сообщений и отладка</title>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; display: flex; flex-direction: column; height: 100vh; }
        .top { display: flex; flex: 1; }
        .column { flex: 1; padding: 20px; box-sizing: border-box; }
        .left { background: #f5f5f5; }
        .right { background: #fff; border-left: 1px solid #ddd; white-space: pre-wrap; font-family: monospace; overflow-y: auto; }
        .bottom { height: 50%; padding: 20px; box-sizing: border-box; background: #eef; overflow-y: auto; white-space: pre-wrap; font-family: monospace; border-top: 1px solid #ccc; }
        textarea { width: 100%; height: 80px; }
        button { padding: 10px 20px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="top">
        <div class="column left">
            <h2>Отправить сообщение</h2>
            <form method="post">
                <input type="hidden" name="action" value="send_message">
                <label for="message">Текст сообщения:</label><br>
                <textarea id="message" name="message" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea><br>
                <button type="submit">Отправить</button>
            </form>
            <?php if ($sendResult !== null): ?>
                <div style="margin-top: 15px; padding: 10px; background: #e0ffe0; border: 1px solid #0c0;">
                    <strong>Результат:</strong>
                    <pre><?php echo $sendResult; ?></pre>
                </div>
            <?php endif; ?>

            <h2 style="margin-top: 30px;">Webhook</h2>
            <form method="post">
                <input type="hidden" name="action" value="set_webhook">
                <label for="webhook_url">Webhook URL:</label><br>
                <input id="webhook_url" name="webhook_url" type="url" style="width:100%;" placeholder="https://your-domain.tld/webhook.php" required>
                <button type="submit">Установить webhook</button>
            </form>

            <form method="post" style="margin-top: 10px;">
                <input type="hidden" name="action" value="delete_webhook">
                <label style="display:block; margin-bottom: 8px;">
                    <input type="checkbox" name="drop_pending_updates" value="1">
                    Удалить pending updates
                </label>
                <button type="submit" style="background:#ffe6e6; border:1px solid #cc0000;">Удалить webhook</button>
            </form>

            <?php if ($webhookResult !== null): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fff0e0; border: 1px solid #cc7a00;">
                    <strong>Webhook result:</strong>
                    <pre><?php echo $webhookResult; ?></pre>
                </div>
            <?php endif; ?>
        </div>
        <div class="column right">
            <h2>Webhook info</h2>
            <pre><?php echo htmlspecialchars($webhookInfoText); ?></pre>
            <h2>Отладка формы</h2>
            <pre><?php echo htmlspecialchars($sendResult ?? ''); ?></pre>
        </div>
    </div>
    <div class="bottom">
        <h2>Лог бота</h2>
        <pre><?php echo htmlspecialchars($debugInfo); ?></pre>
    </div>
</body>
</html>
