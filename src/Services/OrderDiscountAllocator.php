<?php
namespace App\Services;

class OrderDiscountAllocator
{
    /**
     * @param array<int,int|float> $amounts
     * @return array<int,int>
     */
    public function allocateFixedAmount(array $amounts, int $total): array
    {
        $total = max(0, $total);
        $sum = array_sum(array_map(static fn($v) => max(0, (float)$v), $amounts));
        $keys = array_keys($amounts);
        $allocated = array_fill_keys($keys, 0);
        if ($total <= 0 || $sum <= 0 || $keys === []) {
            return $allocated;
        }

        $running = 0;
        foreach ($keys as $key) {
            $part = (int)floor($total * (max(0, (float)$amounts[$key]) / $sum));
            $allocated[$key] = $part;
            $running += $part;
        }

        $allocated[$keys[0]] += $total - $running;
        return $allocated;
    }

    /**
     * @param array<int,int|float> $amounts
     * @return array<int,int>
     */
    public function allocatePercentDiscount(array $amounts, float $percent): array
    {
        $totalDiscount = (int)floor(array_sum(array_map(static fn($v) => max(0, (float)$v), $amounts)) * max(0.0, $percent) / 100);
        return $this->allocateFixedAmount($amounts, $totalDiscount);
    }

    /**
     * @param array<int,int|float> $amounts
     * @return array<int,int>
     */
    public function allocatePoints(array $amounts, int $requestedPoints, int $availablePoints, int $afterDiscountTotal): array
    {
        $points = min(max(0, $requestedPoints), max(0, $availablePoints), max(0, $afterDiscountTotal));
        return $this->allocateFixedAmount($amounts, $points);
    }
}
