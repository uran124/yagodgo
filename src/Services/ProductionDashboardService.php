<?php
namespace App\Services;

use PDO;

class ProductionDashboardService
{
    private const ACTIVE_STATUSES = [
        'new',
        'assigned',
        'materials_pending',
        'materials_sent',
        'materials_received',
        'in_progress',
        'photo_uploaded',
        'approved',
        'ready_for_handover',
        'problem',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<string,mixed> */
    public function buildIndexData(?string $statusFilter = null): array
    {
        if (!$this->tableExists('production_jobs')) {
            return [
                'statusFilter' => '',
                'summary' => [],
                'jobs' => [],
            ];
        }

        $statusFilter = $this->normalizeStatusFilter($statusFilter);

        return [
            'statusFilter' => $statusFilter,
            'summary' => $this->fetchSummary(),
            'jobs' => $this->fetchJobs($statusFilter),
        ];
    }

    /** @return array<string,int> */
    private function fetchSummary(): array
    {
        $summary = [
            'all_active' => 0,
            'new' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'photo_uploaded' => 0,
            'approved' => 0,
            'problem' => 0,
            'overdue' => 0,
        ];

        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM production_jobs WHERE status IN ({$placeholders}) GROUP BY status");
        $stmt->execute(self::ACTIVE_STATUSES);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $status = (string)$row['status'];
            $count = (int)$row['cnt'];
            $summary[$status] = $count;
            $summary['all_active'] += $count;
        }

        $overdueStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM production_jobs
" .
            "WHERE status IN ({$placeholders})
" .
            "  AND production_deadline IS NOT NULL
" .
            "  AND production_deadline < " . $this->currentTimestampExpression()
        );
        $overdueStmt->execute(self::ACTIVE_STATUSES);
        $summary['overdue'] = (int)$overdueStmt->fetchColumn();

        return $summary;
    }

    /** @return array<int,array<string,mixed>> */
    private function fetchJobs(string $statusFilter): array
    {
        $where = [];
        $params = [];
        if ($statusFilter === 'overdue') {
            $where[] = 'pj.status IN (' . implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?')) . ')';
            $params = array_merge($params, self::ACTIVE_STATUSES);
            $where[] = 'pj.production_deadline IS NOT NULL';
            $where[] = 'pj.production_deadline < ' . $this->currentTimestampExpression();
        } elseif ($statusFilter !== '') {
            $where[] = 'pj.status = ?';
            $params[] = $statusFilter;
        } else {
            $where[] = 'pj.status IN (' . implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?')) . ')';
            $params = array_merge($params, self::ACTIVE_STATUSES);
        }

        $productNameExpression = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "COALESCE(NULLIF(p.variety, ''), 'Товар #' || pj.product_id)"
            : "COALESCE(NULLIF(p.variety, ''), CONCAT('Товар #', pj.product_id))";

        $stmt = $this->pdo->prepare(
            "SELECT pj.*, o.status AS order_status, o.delivery_date, u.name AS client_name,
" .
            "       {$productNameExpression} AS product_name
" .
            "FROM production_jobs pj
" .
            "LEFT JOIN orders o ON o.id = pj.order_id
" .
            "LEFT JOIN users u ON u.id = o.user_id
" .
            "LEFT JOIN products p ON p.id = pj.product_id
" .
            "WHERE " . implode(' AND ', $where) . "
" .
            "ORDER BY CASE WHEN pj.production_deadline IS NULL THEN 1 ELSE 0 END, pj.production_deadline ASC, pj.id DESC
" .
            "LIMIT 100"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeStatusFilter(?string $statusFilter): string
    {
        $statusFilter = trim((string)$statusFilter);
        if ($statusFilter === 'overdue' || in_array($statusFilter, self::ACTIVE_STATUSES, true)) {
            return $statusFilter;
        }

        return '';
    }

    private function tableExists(string $table): bool
    {
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function currentTimestampExpression(): string
    {
        return (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? "datetime('now')" : 'NOW()';
    }
}
