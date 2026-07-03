<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legacy hardcoded transaction types. Seeded per-workspace so that
     * existing rows can be linked to a matching type and every finance
     * workspace starts with the familiar catalogue. New types are managed
     * via the Transaction Types CRUD page.
     */
    private array $legacyTypes = [
        'funds', 'profit_share', 'expenses', 'transfer', 'remittance', 'loan',
        'loan_payment', 'refund', 'voided', 'courier_damaged_settlement',
        'capex', 'interest', 'interest_fee',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('finance_transaction_types')) {
            Schema::create('finance_transaction_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                // A type name is unique within a workspace; the same name may
                // exist independently in other workspaces.
                $table->unique(['workspace_id', 'name']);
            });
        }

        // NOTE: the legacy `transaction_type` ENUM column is intentionally left
        // untouched. Dynamic types are referenced through a new FK column so
        // existing rows and any code still reading the enum keep working.
        Schema::table('finance_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_transactions', 'transaction_type_id')) {
                $table->foreignId('transaction_type_id')
                    ->nullable()
                    ->after('transaction_type')
                    ->constrained('finance_transaction_types')
                    ->nullOnDelete();
            }
        });

        $this->seedTypes();
        $this->backfillTypeIds();
    }

    /**
     * Seed the types table with (a) the legacy catalogue for every workspace
     * that has finance activity and (b) any distinct value already stored on a
     * transaction, so no existing row is left without a matching type.
     */
    private function seedTypes(): void
    {
        $workspaceIds = DB::table('finance_accounts')->distinct()->pluck('workspace_id')
            ->merge(DB::table('finance_transactions')->distinct()->pluck('workspace_id'))
            ->filter()
            ->unique();

        $now = now();
        $rows = [];

        foreach ($workspaceIds as $workspaceId) {
            foreach ($this->legacyTypes as $name) {
                $rows[] = ['workspace_id' => $workspaceId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::table('finance_transactions')
            ->select('workspace_id', 'transaction_type')
            ->whereNotNull('transaction_type')
            ->where('transaction_type', '!=', '')
            ->distinct()
            ->get()
            ->each(function ($row) use (&$rows, $now) {
                $rows[] = ['workspace_id' => $row->workspace_id, 'name' => $row->transaction_type, 'created_at' => $now, 'updated_at' => $now];
            });

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('finance_transaction_types')->insertOrIgnore($chunk);
        }
    }

    /**
     * Link existing transactions to their seeded type by matching the legacy
     * enum value to a type of the same name in the same workspace. The enum
     * column itself is preserved.
     */
    private function backfillTypeIds(): void
    {
        DB::table('finance_transaction_types')->orderBy('id')->chunk(500, function ($types) {
            foreach ($types as $type) {
                DB::table('finance_transactions')
                    ->where('workspace_id', $type->workspace_id)
                    ->where('transaction_type', $type->name)
                    ->whereNull('transaction_type_id')
                    ->update(['transaction_type_id' => $type->id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('finance_transactions', 'transaction_type_id')) {
                $table->dropConstrainedForeignId('transaction_type_id');
            }
        });

        Schema::dropIfExists('finance_transaction_types');
    }
};
