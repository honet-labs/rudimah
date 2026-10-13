<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCategory extends Model
{
    protected $connection = 'pekan';
    protected $table = 'finance_categories';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'category_type',
        'parent_id',
        'icon',
        'color',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'parent_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(FinanceCategory::class, 'parent_id');
    }
}
