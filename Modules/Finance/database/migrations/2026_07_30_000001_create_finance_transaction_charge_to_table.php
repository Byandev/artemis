<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A transaction can now be charged to several users, each bearing a share of
     * the amount, so the single `charge_to` foreign key becomes a pivot carrying
     * that share. Existing rows move over whole (one user, the full amount).
     */
    public function up(): void
    {
        Schema::create('finance_transaction_charge_to', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('finance_transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // This user's share of the transaction amount. The shares of a
            // transaction always add up to its amount.
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['transaction_id', 'user_id']);
            $table->index('user_id');
        });

        if (Schema::hasColumn('finance_transactions', 'charge_to')) {
            DB::statement('
                INSERT INTO finance_transaction_charge_to (transaction_id, user_id, amount, created_at, updated_at)
                SELECT id, charge_to, amount, NOW(), NOW()
                FROM finance_transactions
                WHERE charge_to IS NOT NULL
            ');

            Schema::table('finance_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('charge_to');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('finance_transactions', 'charge_to')) {
            Schema::table('finance_transactions', function (Blueprint $table) {
                $table->foreignId('charge_to')->nullable()->after('department')
                    ->constrained('users')->nullOnDelete();
            });

            // Only one user fits the restored column — keep the largest share.
            DB::statement('
                UPDATE finance_transactions t
                SET charge_to = (
                    SELECT ct.user_id
                    FROM finance_transaction_charge_to ct
                    WHERE ct.transaction_id = t.id
                    ORDER BY ct.amount DESC, ct.id ASC
                    LIMIT 1
                )
            ');
        }

        Schema::dropIfExists('finance_transaction_charge_to');
    }
};
