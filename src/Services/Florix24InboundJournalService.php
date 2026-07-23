<?php
declare(strict_types=1);
namespace App\Services;
use PDO;

final class Florix24InboundJournalService
{
    public function __construct(private PDO $pdo) {}
    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public function search(array $filters, int $limit = 100): array
    {
        $where=['source = ?']; $params=['florix24'];
        if (($filters['status'] ?? '') !== '') { $where[]='http_status = ?'; $params[]=(int)$filters['status']; }
        if (($filters['external_order_id'] ?? '') !== '') { $where[]='external_order_id LIKE ?'; $params[]='%'.trim((string)$filters['external_order_id']).'%'; }
        if (($filters['correlation_id'] ?? '') !== '') { $where[]='correlation_id = ?'; $params[]=trim((string)$filters['correlation_id']); }
        $sql=' FROM integration_request_logs WHERE '.implode(' AND ',$where);
        $count=$this->pdo->prepare('SELECT COUNT(*)'.$sql);$count->execute($params);
        // Payloads remain in the immutable audit table but are deliberately not
        // exposed by this operator-facing listing.
        $stmt=$this->pdo->prepare('SELECT id,endpoint,http_status,external_order_id,partner_user_id,points_used,error_code,correlation_id,processing_ms,created_at'.$sql.' ORDER BY id DESC LIMIT '.max(1,min(200,$limit)));$stmt->execute($params);
        return ['rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC)?:[],'total'=>(int)$count->fetchColumn()];
    }
}
