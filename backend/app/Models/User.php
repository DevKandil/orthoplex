<?php

namespace App\Models;

use App\Traits\CanBeFiltered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;
use PragmaRX\Google2FALaravel\Support\Authenticator;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     required={"id", "name", "email", "created_at", "updated_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="email_verified_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="login_count", type="integer", example=42),
 *     @OA\Property(property="google2fa_enabled", type="boolean", example=false),
 *     @OA\Property(property="timezone", type="string", example="UTC"),
 *     @OA\Property(property="locale", type="string", example="en"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 * )
 */
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes, CanBeFiltered;

    /**
     * Enable optimistic locking using updated_at column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'last_login_at',
        'login_count',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'google2fa_secret',
        'google2fa_enabled',
        'timezone',
        'locale',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'google2fa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'login_count' => 'integer',
            'google2fa_enabled' => 'boolean',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'tenant_id' => tenant('id'),
        ];
    }

    /**
     * Determine if the user has enabled two factor authentication.
     *
     * @return bool
     */
    public function hasEnabledTwoFactorAuthentication()
    {
        return $this->google2fa_enabled && !empty($this->google2fa_secret);
    }

    /**
     * Get the user's two factor authentication recovery codes.
     *
     * @return array
     */
    public function recoveryCodes()
    {
        return json_decode(decrypt($this->two_factor_recovery_codes), true);
    }

    /**
     * Replace the given recovery code with a new one in the user's stored codes.
     *
     * @param  string  $code
     * @return void
     */
    public function replaceRecoveryCode($code)
    {
        $this->forceFill([
            'two_factor_recovery_codes' => encrypt(str_replace(
                $code,
                \Illuminate\Support\Str::random(10) . '-' . \Illuminate\Support\Str::random(10),
                decrypt($this->two_factor_recovery_codes)
            )),
        ])->save();
    }

    /**
     * Get login events for this user.
     */
    public function loginEvents()
    {
        return $this->hasMany(LoginEvent::class);
    }

    /**
     * Get audit logs for this user.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    /**
     * Get GDPR exports for this user.
     */
    public function gdprExports()
    {
        return $this->hasMany(GdprExport::class);
    }

    /**
     * Get GDPR delete requests for this user.
     */
    public function gdprDeleteRequests()
    {
        return $this->hasMany(GdprDeleteRequest::class);
    }

    /**
     * Scope to filter inactive users.
     */
    public function scopeInactive($query, $window = 'week')
    {
        $date = match($window) {
            'hour'  => now()->subHour(),
            'day'   => now()->subDay(),
            'week'  => now()->subWeek(),
            'month' => now()->subMonth(),
            default => now()->subWeek(),
        };

        return $query->where(function ($q) use ($date) {
            $q->where('last_login_at', '<', $date)
                ->orWhereNull('last_login_at');
        });
    }

    /**
     * Check if user can be deleted (GDPR compliance).
     */
    public function canBeDeleted(): bool
    {
        // Add business logic for what prevents user deletion
        return !$this->hasRole('owner');
    }
}
