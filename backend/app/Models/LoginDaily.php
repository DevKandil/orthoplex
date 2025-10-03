<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginDaily extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'total_logins',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
