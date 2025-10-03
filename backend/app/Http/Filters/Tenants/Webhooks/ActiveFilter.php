<?php

namespace App\Http\Filters\Tenants\Webhooks;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class ActiveFilter implements FilterContract
{
    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value !== null) {
            $builder->where('active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        }
    }
}