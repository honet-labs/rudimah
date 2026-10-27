<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('pekan');

        if ($schema->hasTable('finance_reminders') && !$schema->hasColumn('finance_reminders', 'images')) {
            $schema->table('finance_reminders', function (Blueprint $table) {
                $table->json('images')->nullable()->after('description');
            });
        }

        if ($schema->hasTable('finance_savings') && !$schema->hasColumn('finance_savings', 'images')) {
            $schema->table('finance_savings', function (Blueprint $table) {
                $table->json('images')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('pekan');

        if ($schema->hasTable('finance_reminders') && $schema->hasColumn('finance_reminders', 'images')) {
            $schema->table('finance_reminders', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }

        if ($schema->hasTable('finance_savings') && $schema->hasColumn('finance_savings', 'images')) {
            $schema->table('finance_savings', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }
    }
};
