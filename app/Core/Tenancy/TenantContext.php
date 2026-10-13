<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Models\Tenant;

class TenantContext
{
    private static ?Tenant $currentTenant = null;

    public static function set(?Tenant $tenant): void
    {
        self::$currentTenant = $tenant;
    }

    public static function get(): ?Tenant
    {
        return self::$currentTenant;
    }

    public static function id(): ?string
    {
        return self::$currentTenant?->id;
    }

    public static function clear(): void
    {
        self::$currentTenant = null;
    }
}
