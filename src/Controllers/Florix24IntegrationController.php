<?php
namespace App\Controllers;

use App\Services\Florix24InboundStatusService;
use App\Services\Florix24IntegrationService;
use App\Services\Florix24WebhookException;
use PDO;
use Throwable;

class Florix24IntegrationController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function testConnection(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $baseUrl = trim((string)($_POST['base_url'] ?? ''));
            $token = trim((string)($_POST['api_token'] ?? ''));
            $service = new Florix24IntegrationService($this->pdo);
            if ($token === '') {
                $token = $service->settings()['florix24_api_token'] ?? '';
            }
            $result = $service->testConnection($baseUrl !== '' ? $baseUrl : null, $token);
            http_response_code($result['ok'] ? 200 : 422);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage(), 'http_code' => 0], JSON_UNESCAPED_UNICODE);
        }
    }

    public function retry(): void
    {
        $id = (int)($_POST['event_id'] ?? 0);
        $ok = (new Florix24IntegrationService($this->pdo))->retryEvent($id);
        header('Location: /admin/settings/integrations?' . ($ok ? 'message=' . urlencode('Событие поставлено на повторную отправку.') : 'error=' . urlencode('Событие не найдено или не требует повтора.')));
        exit;
    }

    public function webhook(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        $raw = file_get_contents('php://input') ?: '';
        $headers = [
            'x-florix-event-id' => (string)($_SERVER['HTTP_X_FLORIX_EVENT_ID'] ?? ''),
            'x-florix-timestamp' => (string)($_SERVER['HTTP_X_FLORIX_TIMESTAMP'] ?? ''),
            'x-florix-signature' => (string)($_SERVER['HTTP_X_FLORIX_SIGNATURE'] ?? ''),
        ];
        try {
            $result = (new Florix24InboundStatusService($this->pdo))->handle($raw, $headers);
            http_response_code(200);
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Florix24WebhookException $e) {
            http_response_code($e->httpStatus);
            echo json_encode(['success' => false, 'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => ['code' => 'server_error', 'message' => 'Webhook не обработан.']], JSON_UNESCAPED_UNICODE);
        }
    }
}
