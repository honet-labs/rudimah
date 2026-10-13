<?php

namespace App\Core\MFA\Models;

use App\Core\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaMethod extends Model
{
    protected $connection = 'platform';
    protected $table = 'mfa_methods';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'method_type', // totp, passkey, email, whatsapp, telegram
        'credential_identifier',
        'secret_encrypted',
        'is_verified',
        'last_used_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
