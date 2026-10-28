<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinanceReminderPayment extends Model
{
    use SoftDeletes;

    protected $connection = 'pekan';
    protected $table = 'finance_reminder_payments';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'reminder_id',
        'transaction_id',
        'amount_minor',
        'currency',
        'payment_date',
        'tenor_number',
        'notes',
        'images',
        'created_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'payment_date' => 'date',
        'tenor_number' => 'integer',
        'images' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(FinanceReminder::class, 'reminder_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinanceTransaction::class, 'transaction_id');
    }
}
