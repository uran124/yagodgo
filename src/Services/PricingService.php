<?php
namespace App\Services;

use PDO;

class PricingService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, float|int>
     */
    public function getSettings(): array
    {
        $defaults = [
            'pricing_instant_margin_percent' => 50.0,
            'ui_preorder_discount_percent' => 10.0,
            'pricing_discount_stock_markup_fixed' => 100.0,
            'pricing_rounding_step' => 10,
        ];

        $keys = array_keys($defaults);
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ({$placeholders})"
        );
        $stmt->execute($keys);

        $settings = $defaults;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if (!array_key_exists($key, $defaults)) {
                continue;
            }

            if ($key === 'pricing_rounding_step') {
                $settings[$key] = max(1, (int) ($row['setting_value'] ?? $defaults[$key]));
                continue;
            }

            $settings[$key] = (float) ($row['setting_value'] ?? $defaults[$key]);
        }

        $instantMargin = (float)$settings['pricing_instant_margin_percent'];
        $preorderDiscount = max(0.0, min(99.0, (float)$settings['ui_preorder_discount_percent']));
        $settings['ui_preorder_discount_percent'] = $preorderDiscount;
        // Compatibility snapshot: preorder margin is derived from instant margin and preorder discount.
        $settings['pricing_preorder_margin_percent'] = round(((1 + ($instantMargin / 100)) * (1 - ($preorderDiscount / 100)) - 1) * 100, 4);

        return $settings;
    }

    public function floorToStep(float $price, int $step = 10): float
    {
        $safeStep = max(1, $step);
        return floor($price / $safeStep) * $safeStep;
    }

    /**
     * @return array<string, float>
     */
    public function calculateFromPurchase(float $purchasePricePerBox, float $boxSize): array
    {
        $settings = $this->getSettings();

        $instantPricePerBox = $this->floorToStep(
            $purchasePricePerBox * (1 + ((float)$settings['pricing_instant_margin_percent'] / 100)),
            (int)$settings['pricing_rounding_step']
        );
        $preorderPricePerBox = $this->floorToStep(
            $instantPricePerBox * (1 - ((float)$settings['ui_preorder_discount_percent'] / 100)),
            (int)$settings['pricing_rounding_step']
        );
        $discountPricePerBox = $purchasePricePerBox + (float)$settings['pricing_discount_stock_markup_fixed'];

        $safeBoxSize = $boxSize > 0 ? $boxSize : 1.0;

        return [
            'preorder_price_per_box' => $preorderPricePerBox,
            'instant_price_per_box' => $instantPricePerBox,
            'discount_price_per_box' => $discountPricePerBox,
            'preorder_unit_price' => round($preorderPricePerBox / $safeBoxSize, 0),
            'instant_unit_price' => round($instantPricePerBox / $safeBoxSize, 0),
            'discount_unit_price' => round($discountPricePerBox / $safeBoxSize, 0),
        ];
    }
}
