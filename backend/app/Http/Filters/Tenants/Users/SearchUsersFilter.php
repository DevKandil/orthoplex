<?php

namespace App\Http\Filters\Tenants\Users;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class SearchUsersFilter implements FilterContract
{
    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            $builder->where(function ($query) use ($value) {
                $query->where('name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%");
            });
        }
    }
}