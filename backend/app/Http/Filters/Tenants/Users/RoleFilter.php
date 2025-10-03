<?php

namespace App\Http\Filters\Tenants\Users;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class RoleFilter implements FilterContract
{
    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            $builder->whereHas('roles', function ($query) use ($value) {
                $query->where('name', $value);
            });
        }
    }
}