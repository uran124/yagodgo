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

// Обработка отправки формы
$sendResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message'])) {
    $text = trim($_POST['message']);
    // Отправляем сообщение через API Telegram
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text'    => $text,
    ];
    if ($topicId !== null) {
        $data['message_thread_id'] = (int)$topicId;
    }
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json; charset=UTF-8\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 5,
        ],
    ];
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $sendResult = 'Ошибка при отправке запроса.';
    } else {
        $sendResult = htmlspecialchars($response);
    }
    // Логируем попытку и результат
    $logEntry = date('Y-m-d H:i:s') . " | SendMessage: {$text} | Response: {$sendResult}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

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
        </div>
        <div class="column right">
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
