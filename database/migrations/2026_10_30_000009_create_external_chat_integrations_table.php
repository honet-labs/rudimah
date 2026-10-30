<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');

        $schema->create('external_chat_pairings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 20)->unique()->index();
            $table->string('channel', 20)->default('any'); // whatsapp, telegram, any
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id']);
        });

        $schema->create('external_chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20); // whatsapp, telegram
            $table->string('channel_user_id', 100); // nomor WA (62812xxx@c.us) atau Telegram Chat ID
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('conversation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'channel_user_id'], 'ecs_channel_user_unique');
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('platform');
        $schema->dropIfExists('external_chat_sessions');
        $schema->dropIfExists('external_chat_pairings');
    }
};
