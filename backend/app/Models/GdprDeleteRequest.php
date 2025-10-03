<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprDeleteRequest extends Model
{
    protected $fillable = [
        'user_id',
        'request_id',
        'email',
        'status',
        'reason',
        'requested_at',
        'processed_at',
        'scheduled_deletion_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
