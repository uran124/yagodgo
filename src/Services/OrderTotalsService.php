<?php
namespace App\Services;

use PDO;

class OrderTotalsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function recalculate(int $orderId): void
    {
        $stmt = $this->pdo->prepare(
            "SELECT SUM(quantity * unit_price) FROM order_items WHERE order_id = ?"
        );
        $stmt->execute([$orderId]);
        $rawTotal = (float)$stmt->fetchColumn();

        $oStmt = $this->pdo->prepare(
            "SELECT user_id, points_used, coupon_code, address_id, delivery_fee FROM orders WHERE id = ?"
        );
        $oStmt->execute([$orderId]);
        $oRow = $oStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $pointsUsed = (int)($oRow['points_used'] ?? 0);
        $deliveryFee = max(0, (int)($oRow['delivery_fee'] ?? 0));
        $userId = (int)($oRow['user_id'] ?? 0);
        $hasUsedReferral = $this->hasUsedReferralCoupon($userId);

        $discountApplied = $this->calculateDiscountApplied(
            $rawTotal,
            $pointsUsed,
            (string)($oRow['coupon_code'] ?? ''),
            $hasUsedReferral
        );
        $finalTotal = max(0, $rawTotal - $pointsUsed - $discountApplied) + $deliveryFee;

        $this->pdo->prepare(
            "UPDATE orders SET total_amount = ?, discount_applied = ? WHERE id = ?"
        )->execute([$finalTotal, $discountApplied, $orderId]);
    }

    private function hasUsedReferralCoupon(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $uStmt = $this->pdo->prepare("SELECT has_used_referral_coupon FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        return (int)$uStmt->fetchColumn() === 1;
    }

    private function calculateDiscountApplied(float $subtotal, int $pointsUsed, string $couponCode, bool $hasUsedReferral): int
    {
        if ($couponCode === '') {
            return 0;
        }

        $cStmt = $this->pdo->prepare(
            "SELECT type, discount, points FROM coupons WHERE code = ?"
        );
        $cStmt->execute([$couponCode]);
        $coupon = $cStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($coupon) {
            if ($coupon['type'] === 'discount') {
                $percent = (float)$coupon['discount'];
                return (int) floor(($subtotal - $pointsUsed) * ($percent / 100));
            }

            return 0;
        }

        return $hasUsedReferral ? (int) floor(($subtotal - $pointsUsed) * 0.10) : 0;
    }
}
