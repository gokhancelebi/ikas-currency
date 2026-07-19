<?php

namespace App\Lib\IkasSync;

class PricingService
{
    public function __construct(
        private RateService $rateService
    ) {
    }

    /**
     * @return array{total: float, comparison: float, commission: float}
     */
    public function calculate(float $basePrice, float $profitPercent, float $discountPercent, string $priceType): array
    {
        $profitMultiplier = $profitPercent / 100;
        $profitAmount = $basePrice * $profitMultiplier;
        $total = $basePrice + $profitAmount;

        if ($priceType !== 'TL') {
            $rate = $this->rateService->getRate($priceType);
            if ($rate !== false) {
                $total = $total * $rate;
            }
        }

        $discountMultiplier = $discountPercent / 100;
        $discountAmount = $total * $discountMultiplier;
        $comparison = $total + $discountAmount;
        $commission = $total * (2 / 100);

        return [
            'total' => $total,
            'comparison' => $comparison,
            'commission' => $commission,
        ];
    }

    public function pricesChanged(float $currentTotal, float $currentComparison, float $currentCommission, array $calculated): bool
    {
        return abs($currentTotal - $calculated['total']) > 0.01
            || abs($currentComparison - $calculated['comparison']) > 0.01
            || abs($currentCommission - $calculated['commission']) > 0.01;
    }
}
