<?php

namespace Database\Seeders;

use App\Models\InventoryPurchasedOrder;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryPurchasedOrderDemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get(['id']);
        if ($users->isEmpty()) {
            return;
        }

        $targetCount = 120;

        // Keep pre-existing unowned rows visible to the first user.
        InventoryPurchasedOrder::query()
            ->whereNull('user_id')
            ->update(['user_id' => (int) $users->first()->id]);

        foreach ($users as $user) {
            $existingCount = InventoryPurchasedOrder::query()
                ->where('user_id', $user->id)
                ->count();

            if ($existingCount >= $targetCount) {
                continue;
            }

            $toCreate = $targetCount - $existingCount;
            $rows = [];

            for ($i = 1; $i <= $toCreate; $i++) {
                $status = (($i - 1) % 8) + 1;
                $cog = random_int(50000, 500000) / 100;
                $fee = random_int(5000, 50000) / 100;

                $rows[] = [
                    'user_id' => $user->id,
                    'issue_date' => now()->subDays(random_int(0, 60))->toDateString(),
                    'delivery_no' => 'DN-'.$user->id.'-'.str_pad((string) (2000 + $i), 4, '0', STR_PAD_LEFT),
                    'cust_po_no' => 'PO-'.$user->id.'-'.str_pad((string) (9000 + $i), 4, '0', STR_PAD_LEFT),
                    'control_no' => 'CTRL-'.$user->id.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'item' => 'Sample Item '.$i,
                    'cog_amount' => $cog,
                    'delivery_fee' => $fee,
                    'total_amount' => $cog + $fee,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            InventoryPurchasedOrder::insert($rows);
        }
    }
}
