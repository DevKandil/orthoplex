<?php

namespace App\Http\Filters\Tenants\Analytics;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class UserIdFilter implements FilterContract
{
    /**
     * Filter login events by user ID.
     *
     * @param Builder $builder
     * @param string|null $value - User ID
     * @return void
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            $builder->where('user_id', $value);
        }
    }
}
