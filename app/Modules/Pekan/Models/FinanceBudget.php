<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceBudget extends Model
{
    use SoftDeletes;

    protected $connection = 'pekan';
    protected $table = 'finance_budgets';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'ida',
        'tenant_id',
        'name',
        'category_id',
        'amount_limit_minor',
        'spent_amount_minor',
        'currency',
        'period',
        'start_date',
        'end_date',
        'alert_threshold_pct',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_limit_minor' => 'integer',
        'spent_amount_minor' => 'integer',
        'alert_threshold_pct' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->amount_limit_minor <= 0) return 0.0;
        return round(($this->spent_amount_minor / $this->amount_limit_minor) * 100, 1);
    }
}
