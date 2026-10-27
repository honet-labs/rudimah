<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceTransaction extends Model
{
    use SoftDeletes;

    protected $connection = 'pekan';
    protected $table = 'finance_transactions';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'account_id',
        'category_id',
        'type',
        'amount_minor',
        'currency',
        'transaction_date',
        'input_date',
        'description',
        'merchant_name',
        'receipt_number',
        'payment_method',
        'subtotal_minor',
        'tax_minor',
        'service_charge_minor',
        'receipt_discount_minor',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_minor' => 'integer',
        'subtotal_minor' => 'integer',
        'tax_minor' => 'integer',
        'service_charge_minor' => 'integer',
        'receipt_discount_minor' => 'integer',
        'transaction_date' => 'date',
        'input_date' => 'date',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'category_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FinanceTransactionItem::class, 'transaction_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(FinanceTransactionAttachment::class, 'transaction_id');
    }
}
