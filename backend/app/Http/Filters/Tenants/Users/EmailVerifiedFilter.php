<?php

namespace App\Http\Filters\Tenants\Users;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class EmailVerifiedFilter implements FilterContract
{
    /**
     * @inheritDoc
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value !== null) {
            if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                $builder->whereNotNull('email_verified_at');
            } else {
                $builder->whereNull('email_verified_at');
            }
        }
    }
}