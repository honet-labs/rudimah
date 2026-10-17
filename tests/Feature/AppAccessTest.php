<?php

namespace Tests\Feature;

use App\Core\Identity\Models\User;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\Models\TenantMembership;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppAccessTest extends TestCase
{
    public function test_launch_app_redirects_to_active_tenant(): void
    {
        $tenant = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'keluarga-uji',
            'name' => 'Keluarga Uji',
            'slug' => 'keluarga-uji',
            'status' => 'active',
        ]);

        $user = new User([
            'id' => (string) Str::uuid(),
            'email' => 'superadmin@test.local',
            'full_name' => 'Super Admin Test',
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);
        session(['current_tenant_slug' => $tenant->slug]);

        // Simulasikan pemanggilan launchApp melalui controller
        $request = \Illuminate\Http\Request::create('/apps/pekan', 'GET');
        $request->setUserResolver(fn () => $user);

        $controller = new \App\Core\Identity\Http\Controllers\WorkspaceController();
        $response = $controller->launchApp($request, 'pekan');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/t/keluarga-uji/apps/pekan', $response->getTargetUrl());
    }

    public function test_launch_skeleton_apps_resolves_correctly(): void
    {
        $tenant = new Tenant([
            'id' => (string) Str::uuid(),
            'code' => 'keluarga-uji',
            'name' => 'Keluarga Uji',
            'slug' => 'keluarga-uji',
            'status' => 'active',
        ]);

        $user = new User([
            'id' => (string) Str::uuid(),
            'email' => 'superadmin@test.local',
            'full_name' => 'Super Admin Test',
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->actingAs($user);
        session(['current_tenant_slug' => $tenant->slug]);

        $request = \Illuminate\Http\Request::create('/apps/app1', 'GET');
        $request->setUserResolver(fn () => $user);

        $controller = new \App\Core\Identity\Http\Controllers\WorkspaceController();
        $response = $controller->launchApp($request, 'app1');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/t/keluarga-uji/apps/app1', $response->getTargetUrl());
    }
}
