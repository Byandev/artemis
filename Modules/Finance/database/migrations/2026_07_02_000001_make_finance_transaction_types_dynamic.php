<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legacy hardcoded transaction types. Seeded per-workspace so that
     * existing rows keep a matching type and every finance workspace starts
     * with the familiar catalogue. New types are managed via the CRUD page.
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

        // Drop the ENUM constraint on transaction_type so types become free-form
        // strings backed by the finance_transaction_types table instead.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE finance_transactions MODIFY transaction_type VARCHAR(255) NULL');
        }

        $this->seedTypes();
    }

    /**
     * Seed the types table with (a) the legacy catalogue for every workspace
     * that has finance activity and (b) any distinct value already stored on a
     * transaction, so no existing row is left referencing a missing type.
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

    public function down(): void
    {
        // Restore the ENUM constraint with the legacy value set.
        if (DB::getDriverName() === 'mysql') {
            $values = collect($this->legacyTypes)->map(fn ($v) => "'".$v."'")->implode(', ');
            DB::statement("ALTER TABLE finance_transactions MODIFY transaction_type ENUM($values) NULL");
        }

        Schema::dropIfExists('finance_transaction_types');
    }
};
