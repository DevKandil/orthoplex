<?php

namespace App\Models;

use App\Traits\CanBeFiltered;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    use HasFactory, CanBeFiltered;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'login_method',
        'successful',
        'failure_reason',
        'metadata',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'metadata' => 'array',
            'attempted_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the login event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for successful logins.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('successful', true);
    }

    /**
     * Scope for failed logins.
     */
    public function scopeFailed($query)
    {
        return $query->where('successful', false);
    }

    /**
     * Scope for specific login method.
     */
    public function scopeByMethod($query, string $method)
    {
        return $query->where('login_method', $method);
    }

    /**
     * Scope for specific time window.
     */
    public function scopeInWindow($query, string $window = '7d')
    {
        $date = match($window) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subWeek(),
        };

        return $query->where('attempted_at', '>=', $date);
    }
}