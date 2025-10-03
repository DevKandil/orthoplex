<?php

namespace App\Http\Filters\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface FilterContract
{
    /**
     * @param Builder     $builder
     * @param string|null $value
     * @return void
     */
    public function apply(Builder $builder, ?string $value): void;
}
