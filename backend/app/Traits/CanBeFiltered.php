<?php

namespace App\Traits;

use App\Http\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

trait CanBeFiltered
{
    /**
     * Apply filters to the query builder.
     * This method allows you to apply various filters
     * to the Eloquent query builder based on the provided filters array.
     *
     * @param Builder $builder
     * @param array $filters
     * @return Builder
     */
    public function scopeWithFilters(
        Builder $builder,
        array $filters = []
    ): Builder
    {
        return (new Filter(request()))->apply($builder, $filters);
    }
}
