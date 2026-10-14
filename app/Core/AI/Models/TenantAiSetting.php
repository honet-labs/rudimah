<?php

namespace App\Core\AI\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantAiSetting extends Model
{
    protected $connection = 'platform';
    protected $table = 'tenant_ai_settings';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'provider',
        'model',
        'api_endpoint',
        'api_key_encrypted',
        'system_instructions',
        'is_enabled',
        'temperature',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'temperature' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function setApiKeyAttribute(?string $value): void
    {
        $this->attributes['api_key_encrypted'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getApiKeyAttribute(): ?string
    {
        if (empty($this->attributes['api_key_encrypted'])) {
            return null;
        }

        try {
            return Crypt::decryptString($this->attributes['api_key_encrypted']);
        } catch (\Throwable) {
            return null;
        }
    }
}
