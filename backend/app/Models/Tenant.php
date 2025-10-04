<?php

namespace App\Models;

use App\Traits\CanBeFiltered;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains, CanBeFiltered;

    protected $casts = [
        'data' => 'array',
    ];

    protected $fillable = [
        'id',
        'data',
        // Virtual attributes
        'name',
        'email',
        'username',
        'created_by',
    ];

    // Define custom columns (columns that exist on the table)
    public static function getCustomColumns(): array
    {
        return ['id', 'created_at', 'updated_at', 'data'];
    }
}