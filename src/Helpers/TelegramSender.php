<?php
namespace App\Helpers;

class TelegramSender
{
    private string $botToken;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    public function send(int|string $chatId, string $message, ?int $topicId = null): bool
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text'    => $message,
        ];
        if ($topicId !== null) {
            $payload['message_thread_id'] = $topicId;
        }
        $options = [
            'http' => [
                'header'  => "Content-Type: application/json; charset=UTF-8\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload),
                'timeout' => 5,
            ],
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        return $response !== false;
    }
}
