<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Services\Florix24InboundService;
use PDO;

final class Florix24ApiController
{
    public function __construct(private PDO $pdo) {}
    public function customer(): void { $this->respond('customers.read', fn() => (new Florix24InboundService($this->pdo))->customerByPhone((string)($_GET['phone'] ?? ''))); }
    public function order(): void { $this->respond('orders.create', fn() => (new Florix24InboundService($this->pdo))->createOrder($this->body())); }
    public function cancel(string $externalId): void { $this->respond('orders.cancel', fn() => (new Florix24InboundService($this->pdo))->cancel(urldecode($externalId))); }

    private function respond(string $permission, callable $action): void
    {
        $started = microtime(true); $client = null; $status = 200; $response = [];
        try { $client = $this->authorize($permission); $response = $action(); }
        catch (FlorixApiException $e) { $status = $e->httpStatus; $response = ['result'=>'error', 'error'=>$e->errorCode] + ($e->retryAfter ? ['retry_after'=>$e->retryAfter] : []); if ($e->retryAfter) header('Retry-After: '.$e->retryAfter); }
        catch (\RuntimeException $e) { $status = 422; $response = ['result'=>'error','error'=>$e->getMessage()]; }
        catch (\Throwable $e) { $status = 500; $response = ['result'=>'error','error'=>'internal_error']; }
        $correlation = (string)($_SERVER['HTTP_X_CORRELATION_ID'] ?? bin2hex(random_bytes(16)));
        $this->audit($client, $status, $response, $correlation, (int)round((microtime(true)-$started)*1000));
        http_response_code($status); header('Content-Type: application/json; charset=UTF-8'); header('X-Correlation-ID: '.$correlation); echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    private function body(): array { $data=json_decode((string)file_get_contents('php://input'), true); if (!is_array($data)) throw new \RuntimeException('validation_error'); return $data; }
    private function authorize(string $permission): array
    {
        if (!preg_match('/^Bearer\s+(.+)$/i', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) throw new FlorixApiException('invalid_token', 401);
        $q=$this->pdo->prepare("SELECT * FROM integration_clients WHERE source='florix24' LIMIT 1"); $q->execute(); $client=$q->fetch(PDO::FETCH_ASSOC);
        if (!$client || !(int)$client['is_active'] || $client['revoked_at'] || ($client['expires_at'] && strtotime($client['expires_at']) < time()) || !password_verify($m[1], $client['token_hash'])) throw new FlorixApiException('invalid_token', 401);
        $permissions=json_decode((string)$client['permissions'], true) ?: []; if (!in_array($permission, $permissions, true)) throw new FlorixApiException('permission_denied', 403);
        $this->limit($client); $this->pdo->prepare('UPDATE integration_clients SET last_used_at = CURRENT_TIMESTAMP WHERE id=?')->execute([$client['id']]); return $client;
    }
    private function limit(array $client): void
    {
        $window=date('Y-m-d H:i:00'); $this->pdo->prepare('INSERT INTO integration_rate_limit_windows (integration_client_id,window_started_at,request_count) VALUES (?,?,1) ON DUPLICATE KEY UPDATE request_count=request_count+1')->execute([$client['id'],$window]);
        $q=$this->pdo->prepare('SELECT request_count FROM integration_rate_limit_windows WHERE integration_client_id=? AND window_started_at=?');$q->execute([$client['id'],$window]); if ((int)$q->fetchColumn() > (int)$client['rate_limit_per_minute']) throw new FlorixApiException('rate_limit',429,60);
    }
    private function audit(?array $client, int $status, array $response, string $correlation, int $ms): void
    {
        try {$payload=$_SERVER['REQUEST_METHOD']==='GET'?$_GET:json_decode((string)file_get_contents('php://input'),true); $this->pdo->prepare('INSERT INTO integration_request_logs (integration_client_id,source,endpoint,request_payload,response_payload,http_status,external_order_id,partner_user_id,points_used,error_code,correlation_id,processing_ms) VALUES (?,\'florix24\',?,?,?,?,?,?,?,?,?,?,?)')->execute([$client['id']??null,parse_url((string)$_SERVER['REQUEST_URI'],PHP_URL_PATH),json_encode($payload,JSON_UNESCAPED_UNICODE),json_encode($response,JSON_UNESCAPED_UNICODE),$status,$payload['external_order_id']??null,$response['partner']['user_id']??null,$response['customer']['points_used']??0,$response['error']??null,$correlation,$ms]);}catch(\Throwable){}
    }
}
final class FlorixApiException extends \RuntimeException { public function __construct(public string $errorCode, public int $httpStatus, public int $retryAfter=0) { parent::__construct($errorCode); } }
