<?php
namespace App\Services;

use PDO;

class SellerEconomicsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<string,mixed> */
    public function settingsForSeller(int $sellerId): array
    {
        $defaults = [
            'monetization_model' => 'commission',
            'commission_rate' => 30.0,
            'subscription_fee' => 0.0,
            'fixed_fee_per_order' => 0.0,
            'client_visibility' => 'seller_visible',
        ];

        if ($sellerId <= 0 || !$this->tableExists('partner_profiles')) {
            return $defaults;
        }

        $stmt = $this->pdo->prepare(
            "SELECT monetization_model, commission_rate, subscription_fee, fixed_fee_per_order, client_visibility
" .
            "FROM partner_profiles
" .
            "WHERE user_id = ? AND partner_type = 'marketplace_seller'"
        );
        $stmt->execute([$sellerId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$settings) {
            return $defaults;
        }

        return [
            'monetization_model' => (string)($settings['monetization_model'] ?? $defaults['monetization_model']),
            'commission_rate' => (float)($settings['commission_rate'] ?? $defaults['commission_rate']),
            'subscription_fee' => (float)($settings['subscription_fee'] ?? $defaults['subscription_fee']),
            'fixed_fee_per_order' => (float)($settings['fixed_fee_per_order'] ?? $defaults['fixed_fee_per_order']),
            'client_visibility' => (string)($settings['client_visibility'] ?? $defaults['client_visibility']),
        ];
    }

    /** @return array<string,float|string> */
    public function calculate(int $sellerId, float $sellerSubtotal, float $orderTotal, float $pointsUsed): array
    {
        $settings = $this->settingsForSeller($sellerId);
        $model = (string)$settings['monetization_model'];
        $commissionRate = max(0.0, (float)$settings['commission_rate']);
        $fixedFee = max(0.0, (float)$settings['fixed_fee_per_order']);

        if ($model === 'subscription') {
            $commission = 0.0;
            $commissionRate = 0.0;
        } elseif ($model === 'fixed_fee_per_order') {
            $commission = min($sellerSubtotal, $fixedFee);
            $commissionRate = $sellerSubtotal > 0 ? round($commission / $sellerSubtotal * 100, 2) : 0.0;
        } else {
            $commission = round($sellerSubtotal * $commissionRate / 100, 2);
        }

        $pointsApplied = $orderTotal > 0 ? round($pointsUsed * $sellerSubtotal / $orderTotal, 2) : 0.0;

        return [
            'monetization_model' => $model,
            'commission_rate' => $commissionRate,
            'commission' => $commission,
            'payout' => max(0.0, $sellerSubtotal - $commission),
            'points_applied' => $pointsApplied,
        ];
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
}
