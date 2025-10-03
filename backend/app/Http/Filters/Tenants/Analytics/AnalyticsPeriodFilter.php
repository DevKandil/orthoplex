<?php

namespace App\Http\Filters\Tenants\Analytics;

use App\Http\Filters\Contracts\FilterContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsPeriodFilter implements FilterContract
{

    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            $startDate = $this->getStartDate($value);
            $builder->where('attempted_at', '>=', $startDate);
        }
    }

    /**
     * Get start date based on period.
     */
    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '7d' => now()->subDays(7),
            '90d' => now()->subDays(90),
            '1y' => now()->subYear(),
            default => now()->subDays(30)
        };
    }
}
