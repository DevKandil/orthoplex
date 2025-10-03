<?php

namespace App\Http\Filters\Tenants\Analytics;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class SuccessfulFilter implements FilterContract
{
    /**
     * Filter login events by success status.
     *
     * @param Builder $builder
     * @param string|null $value - Expected values: true, false, 1, 0
     * @return void
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value !== null) {
            $successful = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $builder->where('successful', $successful);
        }
    }
}
