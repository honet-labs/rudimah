<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');

        $schema->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('active');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 255)->unique();
            $table->string('password', 255);
            $table->string('full_name', 200);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_super_admin')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone', 50)->nullable();
            $table->string('avatar_path', 255)->nullable();
            $table->json('preferences')->nullable();
            $table->timestamps();
        });

        $schema->create('tenant_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 50)->default('member');
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        $schema->create('mfa_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('method_type', 50); // totp, passkey, email, whatsapp, telegram
            $table->string('credential_identifier', 255)->nullable();
            $table->text('secret_encrypted')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        $schema->create('mfa_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('challenge_type', 50);
            $table->string('code_hash', 255);
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        $schema->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('actor_user_id')->nullable()->index();
            $table->string('action', 150);
            $table->string('resource_type', 100);
            $table->string('resource_id', 100);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->string('request_id', 100)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        $schema->create('auth_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload')->nullable();
            $table->integer('last_activity')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('platform');
        $schema->dropIfExists('auth_sessions');
        $schema->dropIfExists('audit_logs');
        $schema->dropIfExists('mfa_challenges');
        $schema->dropIfExists('mfa_methods');
        $schema->dropIfExists('tenant_memberships');
        $schema->dropIfExists('user_profiles');
        $schema->dropIfExists('users');
        $schema->dropIfExists('tenants');
    }
};
