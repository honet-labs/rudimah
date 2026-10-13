<?php

namespace App\Core\Audit;

use App\Core\Audit\Models\AuditLog;
use App\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public static function log(string $action, string $resourceType, string $resourceId, ?array $before = null, ?array $after = null): void
    {
        $sensitiveKeys = ['password', 'password_confirmation', 'token', 'secret', 'code_hash', 'api_key'];

        $sanitizedBefore = self::redact($before, $sensitiveKeys);
        $sanitizedAfter = self::redact($after, $sensitiveKeys);

        AuditLog::create([
            'tenant_id' => TenantContext::id(),
            'actor_user_id' => auth()->id(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before_json' => $sanitizedBefore,
            'after_json' => $sanitizedAfter,
            'request_id' => Request::header('X-Request-ID') ?? (string) \Illuminate\Support\Str::uuid(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    private static function redact(?array $data, array $sensitiveKeys): ?array
    {
        if (!$data) return null;

        $result = [];
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = self::redact($value, $sensitiveKeys);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
