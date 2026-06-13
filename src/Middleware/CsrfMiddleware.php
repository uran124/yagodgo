<?php
namespace App\Middleware;

class CsrfMiddleware
{
    public function shouldProtect(string $method, string $uri): bool
    {
        return strtoupper($method) === 'POST' && !is_csrf_exempt_path($uri);
    }

    public function handle(string $method, string $uri): void
    {
        if (!$this->shouldProtect($method, $uri)) {
            return;
        }

        if (verify_csrf_token(csrf_request_token())) {
            return;
        }

        $this->rejectInvalidToken();
    }

    private function rejectInvalidToken(): void
    {
        http_response_code(419);
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (stripos($accept, 'application/json') !== false || strtolower($requestedWith) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Недействительный токен безопасности'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo 'Недействительный токен безопасности. Обновите страницу и попробуйте снова.';
        exit;
    }
}
