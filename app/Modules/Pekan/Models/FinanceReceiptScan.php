<?php

namespace App\Modules\Pekan\Models;

use App\Core\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;

class FinanceReceiptScan extends Model
{
    protected $connection = 'pekan';
    protected $table = 'finance_receipt_scans';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'provider_code',
        'model_name',
        'status',
        'original_filename',
        'mime_type',
        'images',
        'extracted_json',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'extracted_json' => 'array',
        'images' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }
}
