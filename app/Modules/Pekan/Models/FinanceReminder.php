<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'category_id',
        'title',
        'description',
        'images',
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
        'images' => 'array',
    ];

    protected $appends = [
        'category_name',
    ];

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category?->name;
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FinanceReminderPayment::class, 'reminder_id')->orderByDesc('payment_date')->orderByDesc('created_at');
    }
}
