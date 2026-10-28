<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('pekan');

        if ($schema->hasTable('finance_reminders') && !$schema->hasColumn('finance_reminders', 'category_id')) {
            $schema->table('finance_reminders', function (Blueprint $table) {
                $table->uuid('category_id')->nullable()->after('tenant_id')->index();
            });
        }

        if (!$schema->hasTable('finance_reminder_payments')) {
            $schema->create('finance_reminder_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('reminder_id')->index();
                $table->uuid('transaction_id')->nullable()->index();
                $table->bigInteger('amount_minor');
                $table->char('currency', 3)->default('IDR');
                $table->date('payment_date')->index();
                $table->integer('tenor_number')->nullable();
                $table->text('notes')->nullable();
                $table->json('images')->nullable();
                $table->uuid('created_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
                $table->index(['tenant_id', 'reminder_id']);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('pekan');

        if ($schema->hasTable('finance_reminder_payments')) {
            $schema->dropIfExists('finance_reminder_payments');
        }

        if ($schema->hasTable('finance_reminders') && $schema->hasColumn('finance_reminders', 'category_id')) {
            $schema->table('finance_reminders', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }
    }
};
