<?php

namespace App\Http\Filters\Tenants\Analytics;

use App\Http\Filters\Contracts\FilterContract;
use Illuminate\Database\Eloquent\Builder;

class IpAddressFilter implements FilterContract
{
    /**
     * Filter login events by IP address.
     *
     * @param Builder $builder
     * @param string|null $value - IP address (supports partial match)
     * @return void
     */
    public function apply(Builder $builder, ?string $value): void
    {
        if ($value) {
            // Support partial IP matching (e.g., "192.168" matches all IPs starting with that)
            $builder->where('ip_address', 'LIKE', $value . '%');
        }
    }
}
