<?php
namespace App\Helpers;

class TelegramSender
{
    private string $botToken;
    private ?string $proxyUrl;
    private ?string $proxySecret;

    public function __construct(string $botToken, ?string $proxyUrl = null, ?string $proxySecret = null)
    {
        $this->botToken = $botToken;
        $this->proxyUrl = $proxyUrl ? rtrim($proxyUrl, '/') : null;
        $this->proxySecret = $proxySecret;
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

        if ($this->proxyUrl) {
            $url = $this->proxyUrl;
            $payload = [
                'bot_token' => $this->botToken,
                'method' => 'sendMessage',
                'params' => $payload,
                'secret' => $this->proxySecret,
            ];
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
