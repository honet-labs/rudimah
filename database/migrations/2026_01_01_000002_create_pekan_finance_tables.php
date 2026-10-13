<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('pekan');

        $schema->create('finance_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name', 150);
            $table->string('account_type', 50)->default('bank');
            $table->char('currency', 3)->default('IDR');
            $table->bigInteger('opening_balance_minor')->default(0);
            $table->bigInteger('current_balance_minor')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        $schema->create('finance_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('name', 150);
            $table->string('category_type', 20); // income, expense
            $table->uuid('parent_id')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'category_type']);
        });

        $schema->create('finance_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('account_id')->index();
            $table->uuid('category_id')->nullable()->index();
            $table->string('type', 20); // income, expense, transfer, savings
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('IDR');
            $table->date('transaction_date')->index();
            $table->date('input_date');
            $table->text('description')->nullable();
            $table->string('merchant_name', 200)->nullable();
            $table->string('receipt_number', 100)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('service_charge_minor')->default(0);
            $table->bigInteger('receipt_discount_minor')->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'transaction_date']);
        });

        $schema->create('finance_transaction_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('transaction_id')->index();
            $table->string('item_name', 255);
            $table->decimal('quantity', 12, 4)->default(1);
            $table->bigInteger('price_minor')->default(0);
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'transaction_id']);
        });

        $schema->create('finance_transaction_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('transaction_id')->index();
            $table->uuid('file_id');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'transaction_id', 'file_id']);
        });

        $schema->create('finance_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ida', 50)->nullable();
            $table->uuid('tenant_id')->index();
            $table->string('name', 150);
            $table->uuid('category_id')->nullable()->index();
            $table->bigInteger('amount_limit_minor')->default(0);
            $table->bigInteger('spent_amount_minor')->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->string('period', 20)->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('alert_threshold_pct')->default(80);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        $schema->create('finance_savings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sid', 50)->nullable();
            $table->uuid('tenant_id')->index();
            $table->string('name', 150);
            $table->bigInteger('target_amount_minor')->default(0);
            $table->bigInteger('current_amount_minor')->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        $schema->create('finance_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->default('IDR');
            $table->date('due_date')->index();
            $table->string('repeat_interval', 30)->default('monthly');
            $table->string('status', 20)->default('pending');
            $table->integer('total_tenor')->nullable();
            $table->integer('current_tenor')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'due_date']);
        });

        $schema->create('finance_receipt_scans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('provider_code', 50)->default('local_vision');
            $table->string('model_name', 100)->nullable();
            $table->string('status', 30)->default('pending_review');
            $table->string('original_filename', 255);
            $table->string('mime_type', 100);
            $table->json('extracted_json')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('pekan');
        $schema->dropIfExists('finance_receipt_scans');
        $schema->dropIfExists('finance_reminders');
        $schema->dropIfExists('finance_savings');
        $schema->dropIfExists('finance_budgets');
        $schema->dropIfExists('finance_transaction_attachments');
        $schema->dropIfExists('finance_transaction_items');
        $schema->dropIfExists('finance_transactions');
        $schema->dropIfExists('finance_categories');
        $schema->dropIfExists('finance_accounts');
    }
};
