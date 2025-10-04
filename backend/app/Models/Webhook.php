<?php

namespace App\Models;

use App\Traits\CanBeFiltered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="Webhook",
 *     type="object",
 *     title="Webhook",
 *     required={"id", "name", "url", "events", "created_at", "updated_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="User Registration Hook"),
 *     @OA\Property(property="url", type="string", format="url", example="https://api.example.com/webhooks"),
 *     @OA\Property(property="events", type="array", @OA\Items(type="string"), example={"user.created", "user.updated"}),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="secret", type="string", example="abc123..."),
 *     @OA\Property(property="max_retries", type="integer", example=3),
 *     @OA\Property(property="retry_delay", type="integer", example=60),
 *     @OA\Property(property="last_triggered_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Webhook extends Model
{
    use CanBeFiltered;
    protected $fillable = [
        'name',
        'url',
        'events',
        'secret',
        'active',
        'headers',
        'max_retries',
        'retry_delay',
        'last_triggered_at',
        'metadata',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Get webhook deliveries.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Get recent deliveries.
     */
    public function recentDeliveries(int $limit = 10): HasMany
    {
        return $this->deliveries()->latest()->limit($limit);
    }

    /**
     * Check if webhook listens to specific event.
     */
    public function listensTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Get webhook statistics.
     */
    public function getStatsAttribute(): array
    {
        $totalDeliveries = $this->deliveries()->count();
        $successfulDeliveries = $this->deliveries()->where('status', 'success')->count();

        return [
            'total_deliveries' => $totalDeliveries,
            'successful_deliveries' => $successfulDeliveries,
            'failed_deliveries' => $this->deliveries()->where('status', 'failed')->count(),
            'success_rate' => $totalDeliveries > 0 ? round(($successfulDeliveries / $totalDeliveries) * 100, 2) : 0,
        ];
    }
}
