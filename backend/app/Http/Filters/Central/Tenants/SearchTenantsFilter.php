<?php

namespace App\Http\Filters\Central\Tenants;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class SearchTenantsFilter implements FilterContract
{
    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            $builder->where('id', 'like', "%{$value}%");
        }
    }
}