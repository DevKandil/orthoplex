<?php

namespace App\Http\Filters;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class Filter
{
    /**
     * Filter constructor.
     * @param Request $request
     */
    public function __construct(protected Request $request) {}

    /**
     * @param Builder $builder
     * @param array $filters
     * @return Builder
     */
    public function apply(
        Builder $builder,
        array   $filters
    ): Builder
    {
        foreach ($filters as $key => $filter) {
            // Instantiate filter if it's a class name
            if (is_string($filter) && class_exists($filter)) {
                $filter = new $filter();
            }

            if (!$filter instanceof FilterContract) continue;
            $filter->apply($builder, $this->request->get($key));
        }
        return $builder;
    }
}
