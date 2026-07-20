<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\IncomeStatement;
use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\TransactionType;

/**
 * Seeds a non-gencys-partner workspace ("efb-test") with Pancake delivered
 * orders + finance transactions across May & June 2026, so the Income
 * Statement page has real numbers to generate against.
 *
 * Idempotent: re-running wipes and rebuilds this workspace's test data.
 *
 *   php artisan db:seed --class=EfbTestIncomeStatementSeeder
 */
class EfbTestIncomeStatementSeeder extends Seeder
{
    /** Full months (Y-m) to seed. */
    private array $months = ['2026-05', '2026-06'];

    public function run(): void
    {
        $user = User::where('email', 'hello@artemis.ph')->first() ?? User::first();

        if (! $user) {
            $this->command?->error('No user found to own the test workspace.');

            return;
        }

        $workspace = Workspace::where('slug', 'efb-test')->first();

        if (! $workspace) {
            $workspace = Workspace::factory()->forOwner($user)->create([
                'name' => 'EFB Test',
                'slug' => 'efb-test',
                'finance_module_enabled' => true,
                'is_gencys_partner' => false,
            ]);
        } else {
            $workspace->forceFill([
                'owner_id' => $user->id,
                'finance_module_enabled' => true,
                'is_gencys_partner' => false,
            ])->save();

            if (! $workspace->users()->where('users.id', $user->id)->exists()) {
                $workspace->users()->attach($user->id, ['role' => 'owner']);
            }

            // Clear prior test data so the seeder is repeatable.
            IncomeStatement::where('workspace_id', $workspace->id)->delete();
            Order::where('workspace_id', $workspace->id)->delete();
            Transaction::where('workspace_id', $workspace->id)->delete();
            Account::where('workspace_id', $workspace->id)->delete();
            TransactionType::where('workspace_id', $workspace->id)->delete();
        }

        $account = Account::create([
            'workspace_id' => $workspace->id,
            'name' => 'Main Cash',
            'currency' => 'PHP',
            'opening_balance' => 0,
        ]);

        $types = collect(['ad_spent', 'salary', 'subscription', 'expenses', 'transfer', 'funds'])
            ->mapWithKeys(fn ($name) => [
                $name => TransactionType::create(['workspace_id' => $workspace->id, 'name' => $name]),
            ]);

        foreach ($this->months as $month) {
            $this->seedMonth($workspace, $account, $types, $month);
        }

        $this->command?->info("Seeded workspace 'efb-test' (owner {$user->email}) with income-statement test data for ".implode(' & ', $this->months).'.');
        $this->command?->info('Open: /workspaces/efb-test/finance/income-statements');
    }

    private function seedMonth(Workspace $workspace, Account $account, Collection $types, string $month): void
    {
        // Delivered orders (counted as revenue) — spread across the month.
        for ($i = 0; $i < 120; $i++) {
            Order::factory()->forWorkspace($workspace)->create([
                'shop_id' => 1,
                'page_id' => 1,
                'status_name' => 'Delivered',
                'parcel_status' => 'Delivered',
                'final_amount' => random_int(80000, 260000) / 100, // 800.00 – 2,600.00
                'delivered_at' => $month.'-'.sprintf('%02d', random_int(1, 28)).' 10:00:00',
            ]);
        }

        // Undelivered noise — must NOT count toward delivered revenue.
        for ($i = 0; $i < 20; $i++) {
            Order::factory()->forWorkspace($workspace)->create([
                'shop_id' => 1,
                'page_id' => 1,
                'status_name' => 'On Delivery',
                'parcel_status' => 'On Delivery',
                'final_amount' => random_int(80000, 260000) / 100,
                'delivered_at' => null,
            ]);
        }

        // Expense transactions (type=out), grouped by type. [type, count, [minPeso, maxPeso]]
        $outs = [
            ['ad_spent', 4, [8000, 22000]],
            ['salary', 3, [7000, 14000]],
            ['subscription', 1, [1500, 4000]],
            ['expenses', 6, [500, 3500]],
            ['transfer', 2, [4000, 12000]], // internal movement — uncheck this when generating
        ];

        foreach ($outs as [$typeName, $count, $range]) {
            for ($i = 0; $i < $count; $i++) {
                Transaction::create([
                    'workspace_id' => $workspace->id,
                    'account_id' => $account->id,
                    'date' => $month.'-'.sprintf('%02d', random_int(1, 28)),
                    'description' => ucfirst($typeName).' payment',
                    'type' => 'out',
                    'transaction_type_id' => $types[$typeName]->id,
                    'amount' => random_int($range[0] * 100, $range[1] * 100) / 100,
                ]);
            }
        }

        // An inflow — naturally excluded from expenses (only type=out is summed).
        Transaction::create([
            'workspace_id' => $workspace->id,
            'account_id' => $account->id,
            'date' => $month.'-05',
            'description' => 'Capital injection',
            'type' => 'in',
            'transaction_type_id' => $types['funds']->id,
            'amount' => 50000,
        ]);
    }
}
