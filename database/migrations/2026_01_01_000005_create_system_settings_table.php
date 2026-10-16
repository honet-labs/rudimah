<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');

        if (!$schema->hasTable('system_settings')) {
            $schema->create('system_settings', function (Blueprint $table) {
                $table->string('key', 100)->primary();
                $table->text('value')->nullable();
                $table->string('group', 50)->default('general')->index();
                $table->boolean('is_secret')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('system_settings');
    }
};
