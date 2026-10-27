<?php

namespace Tests\Feature;

use App\Core\Applications\AppRegistry;
use Tests\TestCase;

class PekanParityTest extends TestCase
{
    public function test_pekan_app_manifest_is_registered_and_enabled(): void
    {
        $manifest = AppRegistry::find('pekan');

        $this->assertNotNull($manifest, 'Modul PEKAN harus terdaftar dalam AppRegistry RUDIMAH.');
        $this->assertEquals('PEKAN', $manifest->name);
        $this->assertTrue($manifest->enabled, 'Modul PEKAN harus berstatus enabled.');
    }

    public function test_skeleton_apps_are_registered(): void
    {
        $app1 = AppRegistry::find('app1');
        $this->assertNotNull($app1, 'Modul App1 harus terdaftar sebagai skeleton.');
        $this->assertFalse($app1->enabled, 'Modul App1 belum boleh diaktifkan sebelum spesifikasi fungsional tersedia.');

        $app2 = AppRegistry::find('app2');
        $this->assertNotNull($app2, 'Modul App2 harus terdaftar sebagai skeleton.');
        $this->assertFalse($app2->enabled, 'Modul App2 belum boleh diaktifkan sebelum spesifikasi fungsional tersedia.');
    }

    public function test_finance_category_model_has_tenant_scope_and_attributes(): void
    {
        $category = new \App\Modules\Pekan\Models\FinanceCategory([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Belanja Rumah Tangga',
            'category_type' => 'expense',
            'is_active' => true,
        ]);

        $this->assertEquals('Belanja Rumah Tangga', $category->name);
        $this->assertEquals('expense', $category->category_type);
        $this->assertTrue($category->is_active);
    }

    public function test_finance_category_supports_savings_type(): void
    {
        $category = new \App\Modules\Pekan\Models\FinanceCategory([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Dana Darurat',
            'category_type' => 'savings',
            'is_active' => true,
        ]);

        $this->assertEquals('Dana Darurat', $category->name);
        $this->assertEquals('savings', $category->category_type);
        $this->assertTrue($category->is_active);
    }

    public function test_pekan_edit_routes_are_registered(): void
    {
        $routes = \Illuminate\Support\Facades\Route::getRoutes();

        $this->assertNotNull($routes->getByName('pekan.categories.update'));
        $this->assertNotNull($routes->getByName('pekan.transactions.edit'));
        $this->assertNotNull($routes->getByName('pekan.transactions.update'));
        $this->assertNotNull($routes->getByName('pekan.budgets.edit'));
        $this->assertNotNull($routes->getByName('pekan.budgets.update'));
        $this->assertNotNull($routes->getByName('pekan.savings.edit'));
        $this->assertNotNull($routes->getByName('pekan.savings.update'));
        $this->assertNotNull($routes->getByName('pekan.reminders.edit'));
        $this->assertNotNull($routes->getByName('pekan.reminders.update'));
    }
}
