<?php

namespace Modules\Finance\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Command;
use Modules\Finance\Models\TransactionType;

/**
 * Seed a workspace's finance transaction-type catalogue with the standard
 * chart-of-accounts categories, each pre-tagged with its income-statement
 * section (cost_of_sales | opex | null = excluded). Every type is a debit by
 * nature — these are all expense / outflow categories.
 *
 * Idempotent: names are unique per workspace, so re-running only inserts the
 * ones that are missing and leaves existing rows (and any tagging set on them)
 * untouched.
 */
class SeedTransactionTypesCommand extends Command
{
    protected $signature = 'finance:seed-transaction-types {workspace : The workspace id to seed}';

    protected $description = 'Seed the standard finance transaction types for a workspace';

    /**
     * The catalogue to seed, in display order: name => income-statement section.
     * 'cost_of_sales' deducts for Gross Profit, 'opex' for Net Profit, and null
     * excludes the type from the income statement (balance-sheet movements).
     */
    private const TYPES = [
        'Administrative and Office Expense' => 'opex',
        'Adspent' => 'cost_of_sales',
        'Capex' => null,
        'Bank Charges' => 'opex',
        'Commission' => 'opex',
        'Cost of Goods' => 'cost_of_sales',
        'Delivery Fee of COGS' => 'cost_of_sales',
        'Dividends' => null,
        "Employee's Benefits" => 'opex',
        'Employees Cash Advance' => null,
        'Marketing and Advertising Expense' => 'opex',
        'Marketing Tools Subscription' => 'opex',
        'Miscellaneous' => 'opex',
        'Office Supplies' => 'opex',
        'Permits and Licenses' => 'opex',
        'Professional Fee' => 'opex',
        'Recruitment Expense' => 'opex',
        'Rent and Utilities' => 'opex',
        'Representation & Transportation Allowance' => 'opex',
        'Representation Expense' => 'opex',
        'Salaries and Wages' => 'opex',
        'Tax Expense / VAT' => 'opex',
        'Training and Development' => 'opex',
        'Transfer of Funds' => null,
        'Transportation Expense' => 'opex',
        'Trips and Events' => 'opex',
        'Gencys Remittance' => null,
    ];

    public function handle(): int
    {
        $workspace = Workspace::find($this->argument('workspace'));

        if (! $workspace) {
            $this->error("Workspace [{$this->argument('workspace')}] not found.");

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach (self::TYPES as $name => $section) {
            $type = TransactionType::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $name],
                ['nature' => 'debit', 'income_statement_section' => $section],
            );

            if ($type->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->info("Workspace [{$workspace->id}] {$workspace->name}: {$created} created, {$skipped} already existed.");

        return self::SUCCESS;
    }
}
