<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connect the transaction's requested_by / approved_by / charge_to to real
     * users. They were free-text ledger fields; converting the (empty) columns to
     * nullable user foreign keys lets the app resolve names, permissions, etc.
     * `department` stays free-text.
     */
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            foreach (['requested_by', 'approved_by', 'charge_to'] as $column) {
                if (Schema::hasColumn('finance_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->foreignId('requested_by')->nullable()->after('description')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('requested_by')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('charge_to')->nullable()->after('department')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            foreach (['requested_by', 'approved_by', 'charge_to'] as $column) {
                $table->dropConstrainedForeignId($column);
            }
        });

        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->string('requested_by')->nullable()->after('description');
            $table->string('approved_by')->nullable()->after('requested_by');
            $table->string('charge_to')->nullable()->after('department');
        });
    }
};
