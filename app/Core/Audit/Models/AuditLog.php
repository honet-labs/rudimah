<?php

namespace App\Core\Audit\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $connection = 'platform';
    protected $table = 'audit_logs';

    public $incrementing = true;

    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'action',
        'resource_type',
        'resource_id',
        'before_json',
        'after_json',
        'request_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];
}
