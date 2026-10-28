<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('pekan');

        if ($schema->hasTable('finance_transactions') && !$schema->hasColumn('finance_transactions', 'images')) {
            $schema->table('finance_transactions', function (Blueprint $table) {
                $table->json('images')->nullable()->after('notes');
            });
        }

        if ($schema->hasTable('finance_receipt_scans') && !$schema->hasColumn('finance_receipt_scans', 'images')) {
            $schema->table('finance_receipt_scans', function (Blueprint $table) {
                $table->json('images')->nullable()->after('extracted_json');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('pekan');

        if ($schema->hasTable('finance_transactions') && $schema->hasColumn('finance_transactions', 'images')) {
            $schema->table('finance_transactions', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }

        if ($schema->hasTable('finance_receipt_scans') && $schema->hasColumn('finance_receipt_scans', 'images')) {
            $schema->table('finance_receipt_scans', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }
    }
};
