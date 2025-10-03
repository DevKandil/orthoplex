<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'response_status',
        'response_body',
        'error_message',
        'delivered_at',
        'next_retry_at',
        'signature',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    /**
     * Get the webhook that owns this delivery.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Check if delivery was successful.
     */
    public function wasSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if delivery failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if delivery is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Get response time in milliseconds.
     */
    public function getResponseTimeAttribute(): ?int
    {
        if ($this->delivered_at && $this->created_at) {
            return $this->created_at->diffInMilliseconds($this->delivered_at);
        }

        return null;
    }
}
