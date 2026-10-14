<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');

        $schema->create('tenant_ai_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 50)->default('gemini'); // gemini, openai, hermes, openclaw, mock
            $table->string('model', 100)->default('gemini-1.5-flash');
            $table->string('api_endpoint', 255)->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->text('system_instructions')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->timestamps();

            $table->unique('tenant_id');
        });

        $schema->create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('provider', 50)->default('gemini');
            $table->string('model', 100)->default('gemini-1.5-flash');
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'created_at']);
        });

        $schema->create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->uuid('tenant_id')->index();
            $table->string('role', 20); // user, assistant, system
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('platform');
        $schema->dropIfExists('ai_messages');
        $schema->dropIfExists('ai_conversations');
        $schema->dropIfExists('tenant_ai_settings');
    }
};
