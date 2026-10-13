<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceReminder extends Model
{
    use SoftDeletes;

    protected $connection = 'pekan';
    protected $table = 'finance_reminders';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'title',
        'description',
        'amount_minor',
        'currency',
        'due_date',
        'repeat_interval',
        'status',
        'total_tenor',
        'current_tenor',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'due_date' => 'date',
        'total_tenor' => 'integer',
        'current_tenor' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
