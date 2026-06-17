<?php
namespace App\Services;

use PDO;

class PartnerProfileService
{
    private PDO $pdo;

    /** @var array<int,string> */
    private array $partnerTypes = ['internal_staff', 'production_partner', 'marketplace_seller', 'brand_partner'];
    /** @var array<int,string> */
    private array $statuses = ['draft', 'active', 'paused', 'blocked'];
    /** @var array<int,string> */
    private array $fulfillmentModels = ['by_berrygo_on_site', 'by_berrygo_remote', 'by_partner_under_berrygo_brand', 'by_seller', 'by_berrygo_from_seller_stock'];
    /** @var array<int,string> */
    private array $monetizationModels = ['salary', 'internal_bonus', 'fixed_payout', 'commission', 'subscription', 'commission_plus_subscription', 'fixed_fee_per_order'];
    /** @var array<int,string> */
    private array $visibilityModes = ['berrygo_only', 'partner_visible', 'seller_visible'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function save(array $data): bool
    {
        $userId = (int)($data['user_id'] ?? 0);
        if ($userId <= 0 || !$this->tableExists('partner_profiles')) {
            return false;
        }

        $profile = [
            'user_id' => $userId,
            'partner_type' => $this->oneOf((string)($data['partner_type'] ?? 'production_partner'), $this->partnerTypes, 'production_partner'),
            'status' => $this->oneOf((string)($data['status'] ?? 'draft'), $this->statuses, 'draft'),
            'default_fulfillment_model' => $this->oneOf((string)($data['default_fulfillment_model'] ?? 'by_partner_under_berrygo_brand'), $this->fulfillmentModels, 'by_partner_under_berrygo_brand'),
            'monetization_model' => $this->oneOf((string)($data['monetization_model'] ?? 'commission'), $this->monetizationModels, 'commission'),
            'client_visibility' => $this->oneOf((string)($data['client_visibility'] ?? 'berrygo_only'), $this->visibilityModes, 'berrygo_only'),
            'commission_rate' => max(0, (float)($data['commission_rate'] ?? 30)),
            'subscription_fee' => max(0, (float)($data['subscription_fee'] ?? 0)),
            'fixed_fee_per_order' => max(0, (float)($data['fixed_fee_per_order'] ?? 0)),
            'default_bonus_percent' => max(0, (float)($data['default_bonus_percent'] ?? 10)),
            'max_active_jobs' => max(1, (int)($data['max_active_jobs'] ?? 1)),
            'notes' => $data['notes'] ?? null,
        ];

        if ($this->find($userId)) {
            $stmt = $this->pdo->prepare(
                'UPDATE partner_profiles
                    SET partner_type = :partner_type,
                        status = :status,
                        default_fulfillment_model = :default_fulfillment_model,
                        monetization_model = :monetization_model,
                        client_visibility = :client_visibility,
                        commission_rate = :commission_rate,
                        subscription_fee = :subscription_fee,
                        fixed_fee_per_order = :fixed_fee_per_order,
                        default_bonus_percent = :default_bonus_percent,
                        max_active_jobs = :max_active_jobs,
                        notes = :notes,
                        updated_at = ' . $this->currentTimestampExpression() . '
                  WHERE user_id = :user_id'
            );
            return $stmt->execute($profile);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO partner_profiles (
                user_id, partner_type, status, default_fulfillment_model, monetization_model,
                client_visibility, commission_rate, subscription_fee, fixed_fee_per_order,
                default_bonus_percent, max_active_jobs, notes, created_at, updated_at
            ) VALUES (
                :user_id, :partner_type, :status, :default_fulfillment_model, :monetization_model,
                :client_visibility, :commission_rate, :subscription_fee, :fixed_fee_per_order,
                :default_bonus_percent, :max_active_jobs, :notes,
                ' . $this->currentTimestampExpression() . ', ' . $this->currentTimestampExpression() . '
            )'
        );

        return $stmt->execute($profile);
    }

    /** @return array<string,mixed>|null */
    public function find(int $userId): ?array
    {
        if ($userId <= 0 || !$this->tableExists('partner_profiles')) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM partner_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);

        return $profile ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function eligibleProductionExecutors(): array
    {
        if (!$this->tableExists('partner_profiles')) {
            return [];
        }

        $stmt = $this->pdo->query(
            "SELECT u.id, u.name, u.role, pp.partner_type AS executor_type, pp.status,\n" .
            "       pp.default_fulfillment_model, pp.monetization_model, pp.client_visibility,\n" .
            "       pp.default_bonus_percent, pp.max_active_jobs,\n" .
            "       COALESCE(active_jobs.active_count, 0) AS active_jobs_count\n" .
            "FROM partner_profiles pp\n" .
            "JOIN users u ON u.id = pp.user_id\n" .
            "LEFT JOIN (\n" .
            "    SELECT executor_type, executor_id, COUNT(*) AS active_count\n" .
            "    FROM production_jobs\n" .
            "    WHERE status IN ('assigned','materials_pending','materials_sent','materials_received','in_progress','photo_uploaded','approved','ready_for_handover')\n" .
            "    GROUP BY executor_type, executor_id\n" .
            ") active_jobs ON active_jobs.executor_type = pp.partner_type AND active_jobs.executor_id = pp.user_id\n" .
            "WHERE pp.status = 'active'\n" .
            "  AND pp.partner_type IN ('internal_staff','production_partner','brand_partner')\n" .
            "  AND COALESCE(active_jobs.active_count, 0) < pp.max_active_jobs\n" .
            "ORDER BY CASE pp.partner_type WHEN 'internal_staff' THEN 1 WHEN 'production_partner' THEN 2 ELSE 3 END, u.name, u.id"
        );

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /** @param array<int,string> $allowed */
    private function oneOf(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
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
