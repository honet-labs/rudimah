<?php

namespace App\Core\Identity\Models;

use App\Core\Tenancy\Models\TenantMembership;
use App\Core\MFA\Models\MfaMethod;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use Notifiable;

    protected $connection = 'platform';
    protected $table = 'users';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'password',
        'full_name',
        'is_active',
        'is_super_admin',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): string
    {
        return $this->full_name ?? '';
    }

    /**
     * Mutator password aman: memastikan plain text selalu di-hash tepat satu kali,
     * dan tidak melakukan hashing ulang jika nilai sudah berupa string hash yang valid.
     */
    public function setPasswordAttribute($value): void
    {
        if (empty($value)) {
            return;
        }

        // Cek apakah nilai sudah berupa hash bcrypt ($2y$, $2a$, $2b$) atau argon ($argon2)
        $isAlreadyHashed = is_string($value) && (
            str_starts_with($value, '$2y$') ||
            str_starts_with($value, '$2a$') ||
            str_starts_with($value, '$2b$') ||
            str_starts_with($value, '$argon2')
        );

        $this->attributes['password'] = $isAlreadyHashed ? $value : \Illuminate\Support\Facades\Hash::make($value);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'user_id');
    }

    public function mfaMethods(): HasMany
    {
        return $this->hasMany(MfaMethod::class, 'user_id');
    }
}
