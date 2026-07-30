<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A fund request can now be charged to several users, each bearing a share of
     * the amount, so the single `charge_to` foreign key becomes a pivot carrying
     * that share. Existing rows move over whole (one user, the full amount).
     * Mirrors finance_transaction_charge_to so a transaction can be filled in
     * from a request without translating between the two shapes.
     */
    public function up(): void
    {
        Schema::create('finance_request_fund_charge_to', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fund_request_id')->constrained('finance_request_funds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // This user's share of the amount requested. The shares of a request
            // always add up to that amount.
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['fund_request_id', 'user_id']);
            $table->index('user_id');
        });

        if (Schema::hasColumn('finance_request_funds', 'charge_to')) {
            DB::statement('
                INSERT INTO finance_request_fund_charge_to (fund_request_id, user_id, amount, created_at, updated_at)
                SELECT id, charge_to, amount_requested, NOW(), NOW()
                FROM finance_request_funds
                WHERE charge_to IS NOT NULL
            ');

            Schema::table('finance_request_funds', function (Blueprint $table) {
                $table->dropConstrainedForeignId('charge_to');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('finance_request_funds', 'charge_to')) {
            Schema::table('finance_request_funds', function (Blueprint $table) {
                $table->foreignId('charge_to')->nullable()->after('requested_by')
                    ->constrained('users')->nullOnDelete();
            });

            // Only one user fits the restored column — keep the largest share.
            DB::statement('
                UPDATE finance_request_funds r
                SET charge_to = (
                    SELECT ct.user_id
                    FROM finance_request_fund_charge_to ct
                    WHERE ct.fund_request_id = r.id
                    ORDER BY ct.amount DESC, ct.id ASC
                    LIMIT 1
                )
            ');
        }

        Schema::dropIfExists('finance_request_fund_charge_to');
    }
};
