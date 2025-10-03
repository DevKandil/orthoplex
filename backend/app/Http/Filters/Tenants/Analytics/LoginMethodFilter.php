<?php

namespace App\Http\Filters\Tenants\Analytics;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class LoginMethodFilter implements FilterContract
{
    /**
     * Filter login events by login method.
     *
     * @param Builder $builder
     * @param string|null $value - Expected values: password, magic_link, 2fa
     * @return void
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            $builder->where('login_method', $value);
        }
    }
}
