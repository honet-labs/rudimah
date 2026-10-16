<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceSavings extends Model
{
    use SoftDeletes;

    protected $connection = 'pekan';
    protected $table = 'finance_savings';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'sid',
        'tenant_id',
        'name',
        'target_amount_minor',
        'current_amount_minor',
        'currency',
        'start_date',
        'target_date',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'target_amount_minor' => 'integer',
        'current_amount_minor' => 'integer',
        'start_date' => 'date',
        'target_date' => 'date',
    ];

    protected $appends = [
        'progress_percent',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function getProgressPercentAttribute(): float
    {
        if ($this->target_amount_minor <= 0) return 0.0;
        return round(($this->current_amount_minor / $this->target_amount_minor) * 100, 1);
    }
}
