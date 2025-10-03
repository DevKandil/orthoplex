<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdprExport extends Model
{
    protected $fillable = [
        'user_id',
        'export_id',
        'email',
        'status',
        'format',
        'file_path',
        'file_name',
        'file_size',
        'requested_at',
        'completed_at',
        'expires_at',
        'error_message',
        'export_options',
        'include_deleted',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'export_options' => 'array',
        'include_deleted' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
