<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LocalFeatureDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $now = now();

        $owner = User::firstOrCreate(
            ['email' => 'local-owner@example.com'],
            [
                'name' => 'Miguel Santos',
                'password' => Hash::make('password'),
                'email_verified_at' => $now,
            ]
        );

        $member = User::firstOrCreate(
            ['email' => 'local-member@example.com'],
            [
                'name' => 'Andrea Reyes',
                'password' => Hash::make('password'),
                'email_verified_at' => $now,
            ]
        );

        $workspace = Workspace::firstOrCreate(
            ['slug' => 'local-demo-workspace'],
            [
                'name' => 'Northstar Commerce PH',
                'description' => 'Operational workspace demo dataset',
                'owner_id' => $owner->id,
            ]
        );

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $owner->id],
            ['role' => 'owner', 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('workspace_user')->updateOrInsert(
            ['workspace_id' => $workspace->id, 'user_id' => $member->id],
            ['role' => 'member', 'created_at' => $now, 'updated_at' => $now]
        );

        $this->seedRoles($workspace->id, $now);
        $teamIds = $this->seedTeams($workspace->id, $now);
        $this->attachTeamMembers($teamIds, [$owner->id, $member->id]);

        $shopIds = $this->seedShops($workspace->id, $now);
        $productRows = $this->seedProducts($workspace->id, $owner->id, $now);
        $pageIds = $this->seedPages($workspace->id, $owner->id, $shopIds, $productRows, $now);

        $this->seedDailyActivity(
            workspaceId: $workspace->id,
            ownerId: $owner->id,
            shopIds: $shopIds,
            pageIds: $pageIds,
            products: $productRows,
            now: $now,
        );
    }

    private function seedRoles(int $workspaceId, Carbon $now): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Full access to workspace settings and members.'],
            ['name' => 'editor', 'description' => 'Can edit content but cannot manage workspace settings.'],
            ['name' => 'member', 'description' => 'Standard access to workspace features.'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'name' => $role['name']],
                ['description' => $role['description'], 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    private function seedTeams(int $workspaceId, Carbon $now): array
    {
        $names = ['Operations Team', 'Sales Team', 'Customer Support Team'];
        $ids = [];

        foreach ($names as $name) {
            $id = DB::table('teams')->where('workspace_id', $workspaceId)->where('name', $name)->value('id');

            if (! $id) {
                $id = DB::table('teams')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ids[] = (int) $id;
        }

        return $ids;
    }

    private function attachTeamMembers(array $teamIds, array $userIds): void
    {
        foreach ($teamIds as $teamId) {
            foreach ($userIds as $userId) {
                DB::table('team_user')->updateOrInsert([
                    'team_id' => $teamId,
                    'user_id' => $userId,
                ], []);
            }
        }
    }

    private function seedShops(int $workspaceId, Carbon $now): array
    {
        $names = $this->businessProfile()['shop_names'];
        $ids = [];

        foreach ($names as $name) {
            $id = DB::table('shops')->where('workspace_id', $workspaceId)->where('name', $name)->value('id');

            if (! $id) {
                $id = DB::table('shops')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'name' => $name,
                    'avatar_url' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $ids[] = (int) $id;
        }

        return $ids;
    }

    private function seedProducts(int $workspaceId, int $ownerId, Carbon $now): array
    {
        $catalog = $this->businessProfile()['catalog'];

        $rows = [];

        foreach ($catalog as $item) {
            $name = $item['name'];
            $code = $item['code'];

            $existing = DB::table('products')
                ->where('workspace_id', $workspaceId)
                ->where('name', $name)
                ->first(['id', 'name']);

            if (! $existing) {
                $id = DB::table('products')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'owner_id' => $ownerId,
                    'title' => $name,
                    'name' => $name,
                    'code' => $code,
                    'category' => $item['category'],
                    'status' => $item['status'],
                    'description' => $item['description'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $rows[] = ['id' => (int) $id, 'name' => $name, 'demand_weight' => $item['demand_weight']];
                continue;
            }

            $rows[] = ['id' => (int) $existing->id, 'name' => $existing->name, 'demand_weight' => $item['demand_weight']];
        }

        return $rows;
    }

    private function seedPages(int $workspaceId, int $ownerId, array $shopIds, array $products, Carbon $now): array
    {
        $pageIds = [];

        foreach ($shopIds as $shopIndex => $shopId) {
            $shopName = (string) DB::table('shops')->where('id', $shopId)->value('name');
            for ($i = 1; $i <= 3; $i++) {
                $name = sprintf('%s - Campaign %d', $shopName, $i);
                $product = $products[array_rand($products)];

                $id = DB::table('pages')
                    ->where('workspace_id', $workspaceId)
                    ->where('shop_id', $shopId)
                    ->where('name', $name)
                    ->value('id');

                if (! $id) {
                    $id = DB::table('pages')->insertGetId([
                        'workspace_id' => $workspaceId,
                        'shop_id' => $shopId,
                        'owner_id' => $ownerId,
                        'product_id' => $product['id'],
                        'name' => $name,
                        'facebook_url' => null,
                        'pos_token' => null,
                        'botcake_token' => null,
                        'infotxt_token' => null,
                        'infotxt_user_id' => null,
                        'orders_last_synced_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $pageIds[] = (int) $id;
            }
        }

        return $pageIds;
    }

    private function seedDailyActivity(
        int $workspaceId,
        int $ownerId,
        array $shopIds,
        array $pageIds,
        array $products,
        Carbon $now,
    ): void {
        $startDate = $now->copy()->subYear()->startOfDay();
        $endDate = $now->copy()->startOfDay();

        // Generate the 1-year daily dataset only once in local.
        $alreadySeeded = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('order_number', 'like', "NSC-{$workspaceId}-%")
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dayKey = $date->format('Ymd');
            $target = $this->dailyTargetCount($dayKey);

            $this->seedDailyOrders($workspaceId, $shopIds, $pageIds, $date, $dayKey, $target, $now);
            $this->seedDailyInventoryTransactions($workspaceId, $date, $dayKey, $target, $now);
            $this->seedDailyPurchasedOrders($ownerId, $products, $date, $dayKey, $target, $now);
        }
    }

    private function seedDailyOrders(int $workspaceId, array $shopIds, array $pageIds, Carbon $date, string $dayKey, int $target, Carbon $now): void
    {
        $profile = $this->businessProfile();
        $prefix = "NSC-{$workspaceId}-{$dayKey}-";
        $existing = DB::table('pancake_orders')
            ->where('workspace_id', $workspaceId)
            ->where('order_number', 'like', $prefix.'%')
            ->count();

        if ($existing >= $target) {
            return;
        }

        for ($i = $existing + 1; $i <= $target; $i++) {
            $statusPool = [
                ['code' => 3, 'name' => 'delivered', 'parcel' => 'Delivered'],
                ['code' => 4, 'name' => 'returned', 'parcel' => 'Returned'],
                ['code' => 5, 'name' => 'returned_rt', 'parcel' => 'RTS'],
                ['code' => 3, 'name' => 'delivered', 'parcel' => 'Delivered'],
                ['code' => 3, 'name' => 'delivered', 'parcel' => 'Delivered'],
            ];
            $selected = $statusPool[array_rand($statusPool)];

            $confirmedAt = $date->copy()->setTime(10, 0, 0);
            $shippedAt = $confirmedAt->copy()->addHours(8);
            $deliveredAt = $selected['code'] === 3 ? $confirmedAt->copy()->addDays(2) : null;
            $returningAt = in_array($selected['code'], [4, 5], true) ? $confirmedAt->copy()->addDays(2) : null;
            $returnedAt = in_array($selected['code'], [4, 5], true) ? $confirmedAt->copy()->addDays(4) : null;

            DB::table('pancake_orders')->insert([
                'order_number' => $prefix.sprintf('%03d', $i),
                'status' => $selected['code'],
                'status_name' => $selected['name'],
                'shop_id' => $shopIds[array_rand($shopIds)],
                'page_id' => $pageIds[array_rand($pageIds)],
                'workspace_id' => $workspaceId,
                'total_amount' => random_int($profile['order_total_min_cents'], $profile['order_total_max_cents']) / 100,
                'final_amount' => random_int($profile['order_final_min_cents'], $profile['order_final_max_cents']) / 100,
                'discount' => random_int($profile['order_discount_min_cents'], $profile['order_discount_max_cents']) / 100,
                'ad_id' => null,
                'fb_id' => null,
                'customer_id' => (string) Str::uuid(),
                'delivery_attempts' => in_array($selected['code'], [4, 5], true) ? 2 : 1,
                'first_delivery_attempt' => $confirmedAt->copy()->addDay(),
                'inserted_at' => $confirmedAt,
                'confirmed_at' => $confirmedAt,
                'returned_at' => $returnedAt,
                'returning_at' => $returningAt,
                'delivered_at' => $deliveredAt,
                'shipped_at' => $shippedAt,
                'tracking_code' => 'TRK'.str_pad($dayKey.sprintf('%03d', $i), 12, '0', STR_PAD_LEFT),
                'parcel_status' => $selected['parcel'],
                'customer_succeed_order_count' => random_int(0, 5),
                'customer_returned_order_count' => random_int(0, 3),
                'assignee_id' => null,
                'conferrer_id' => null,
                'last_editor_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedDailyInventoryTransactions(int $workspaceId, Carbon $date, string $dayKey, int $target, Carbon $now): void
    {
        $prefix = "TXN-{$workspaceId}-{$dayKey}-";
        $existing = DB::table('inventory_transactions')
            ->where('workspace_id', $workspaceId)
            ->whereDate('date', $date->toDateString())
            ->where('ref_no', 'like', $prefix.'%')
            ->count();

        if ($existing >= $target) {
            return;
        }

        for ($i = $existing + 1; $i <= $target; $i++) {
            $poIn = random_int(5, 25);
            $poOut = random_int(0, 15);
            $rtsIn = random_int(0, 8);
            $rtsOut = random_int(0, 6);
            $rtsBad = random_int(0, 3);

            DB::table('inventory_transactions')->insert([
                'workspace_id' => $workspaceId,
                'date' => $date->toDateString(),
                'ref_no' => $prefix.sprintf('%03d', $i),
                'po_qty_in' => $poIn,
                'po_qty_out' => $poOut,
                'rts_goods_out' => $rtsOut,
                'rts_goods_in' => $rtsIn,
                'rts_bad' => $rtsBad,
                'remaining_qty' => max(0, ($poIn + $rtsIn) - ($poOut + $rtsOut + $rtsBad)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedDailyPurchasedOrders(int $ownerId, array $productNames, Carbon $date, string $dayKey, int $target, Carbon $now): void
    {
        $profile = $this->businessProfile();
        $prefix = "DN-{$dayKey}-";
        $existing = DB::table('inventory_purchased_orders')
            ->where('user_id', $ownerId)
            ->whereDate('issue_date', $date->toDateString())
            ->where('delivery_no', 'like', $prefix.'%')
            ->count();

        if ($existing >= $target) {
            return;
        }

        for ($i = $existing + 1; $i <= $target; $i++) {
            $cog = random_int($profile['po_cog_min_cents'], $profile['po_cog_max_cents']) / 100;
            $fee = random_int($profile['po_fee_min_cents'], $profile['po_fee_max_cents']) / 100;

            DB::table('inventory_purchased_orders')->insert([
                'user_id' => $ownerId,
                'issue_date' => $date->toDateString(),
                'delivery_no' => $prefix.sprintf('%04d', $i),
                'cust_po_no' => 'PO-'.$dayKey.'-'.sprintf('%04d', $i),
                'control_no' => 'CTRL-'.$dayKey.'-'.sprintf('%04d', $i),
                'item' => $this->pickWeightedProductName($productNames),
                'cog_amount' => $cog,
                'delivery_fee' => $fee,
                'total_amount' => $cog + $fee,
                'status' => random_int(1, 8),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function dailyTargetCount(string $dayKey): int
    {
        return (abs(crc32($dayKey)) % 5) + 1;
    }

    private function pickWeightedProductName(array $products): string
    {
        if ($products === []) {
            return 'Premium Wireless Headphones';
        }

        $totalWeight = array_sum(array_map(
            fn (array $product): int => max(1, (int) ($product['demand_weight'] ?? 1)),
            $products,
        ));

        $roll = random_int(1, max(1, $totalWeight));
        $running = 0;

        foreach ($products as $product) {
            $running += max(1, (int) ($product['demand_weight'] ?? 1));

            if ($roll <= $running) {
                return (string) ($product['name'] ?? 'Premium Wireless Headphones');
            }
        }

        return (string) ($products[array_key_first($products)]['name'] ?? 'Premium Wireless Headphones');
    }

    private function businessProfile(): array
    {
        return [
            'shop_names' => [
                'Northstar Flagship Store',
                'Metro Essentials Hub',
                'Home Living Outlet',
                'Beauty & Wellness Corner',
                'Gadget Deals Central',
            ],
            'order_total_min_cents' => 3500,
            'order_total_max_cents' => 68000,
            'order_final_min_cents' => 3000,
            'order_final_max_cents' => 62000,
            'order_discount_min_cents' => 0,
            'order_discount_max_cents' => 1500,
            'po_cog_min_cents' => 12000,
            'po_cog_max_cents' => 140000,
            'po_fee_min_cents' => 1200,
            'po_fee_max_cents' => 9000,
            'catalog' => [
                ['name' => 'Premium Wireless Headphones', 'code' => 'PWH-001', 'category' => 'Electronics', 'status' => 'Scaling', 'description' => 'Wireless headset with ANC and 30-hour battery life.', 'demand_weight' => 8],
                ['name' => 'Smart Fitness Tracker', 'code' => 'SFT-002', 'category' => 'Wearables', 'status' => 'Scaling', 'description' => 'Fitness band with heart-rate, sleep, and activity tracking.', 'demand_weight' => 6],
                ['name' => 'Eco-Friendly Water Bottle', 'code' => 'EWB-003', 'category' => 'Lifestyle', 'status' => 'Testing', 'description' => 'Stainless steel insulated bottle for daily use.', 'demand_weight' => 3],
                ['name' => 'Portable Phone Charger', 'code' => 'PPC-004', 'category' => 'Electronics', 'status' => 'Scaling', 'description' => '20,000mAh power bank with fast charge support.', 'demand_weight' => 9],
                ['name' => 'Ergonomic Office Chair', 'code' => 'EOC-005', 'category' => 'Furniture', 'status' => 'Testing', 'description' => 'Mesh office chair with lumbar support and adjustable armrests.', 'demand_weight' => 2],
                ['name' => 'LED Desk Lamp', 'code' => 'LDL-006', 'category' => 'Home', 'status' => 'Scaling', 'description' => 'Desk lamp with brightness control and USB charging.', 'demand_weight' => 3],
                ['name' => 'Wireless Gaming Mouse', 'code' => 'WGM-007', 'category' => 'Electronics', 'status' => 'Testing', 'description' => 'High-precision RGB gaming mouse with low latency mode.', 'demand_weight' => 8],
                ['name' => 'Organic Green Tea Set', 'code' => 'OGT-008', 'category' => 'Food', 'status' => 'Inactive', 'description' => 'Curated organic green tea sampler pack.', 'demand_weight' => 1],
                ['name' => 'Bluetooth Speaker Pro', 'code' => 'BSP-009', 'category' => 'Electronics', 'status' => 'Scaling', 'description' => 'Portable waterproof speaker with deep bass profile.', 'demand_weight' => 8],
                ['name' => 'Yoga Mat Premium', 'code' => 'YMP-010', 'category' => 'Sports', 'status' => 'Testing', 'description' => 'Non-slip yoga mat with extra cushioning.', 'demand_weight' => 2],
                ['name' => 'Smart Home Security Camera', 'code' => 'SHC-011', 'category' => 'Electronics', 'status' => 'Scaling', 'description' => '1080p Wi-Fi camera with motion alerts.', 'demand_weight' => 7],
                ['name' => 'Bamboo Cutting Board Set', 'code' => 'BCB-012', 'category' => 'Kitchen', 'status' => 'Testing', 'description' => 'Durable bamboo chopping board set in 3 sizes.', 'demand_weight' => 2],
                ['name' => 'Mechanical Keyboard RGB', 'code' => 'MKR-013', 'category' => 'Electronics', 'status' => 'Scaling', 'description' => 'Mechanical keyboard with hot-swappable switches.', 'demand_weight' => 8],
                ['name' => 'Stainless Steel Cookware Set', 'code' => 'SCS-014', 'category' => 'Kitchen', 'status' => 'Failed', 'description' => '10-piece cookware set for induction and gas stoves.', 'demand_weight' => 1],
                ['name' => 'Digital Drawing Tablet', 'code' => 'DDT-015', 'category' => 'Electronics', 'status' => 'Testing', 'description' => 'Drawing tablet with pressure sensitivity and tilt support.', 'demand_weight' => 5],
                ['name' => 'Running Shoes Elite', 'code' => 'RSE-016', 'category' => 'Sports', 'status' => 'Scaling', 'description' => 'Lightweight trainers with responsive midsole foam.', 'demand_weight' => 2],
                ['name' => 'Smart LED Light Bulbs', 'code' => 'SLB-017', 'category' => 'Home', 'status' => 'Scaling', 'description' => 'Color-changing smart bulbs with app control.', 'demand_weight' => 3],
                ['name' => 'Camping Tent 4-Person', 'code' => 'CT4-018', 'category' => 'Outdoor', 'status' => 'Testing', 'description' => 'All-weather tent with quick-setup frame.', 'demand_weight' => 1],
                ['name' => 'Coffee Maker Deluxe', 'code' => 'CMD-019', 'category' => 'Kitchen', 'status' => 'Inactive', 'description' => 'Programmable coffee maker with thermal carafe.', 'demand_weight' => 1],
                ['name' => 'Phone Case Ultra Protective', 'code' => 'PCU-020', 'category' => 'Accessories', 'status' => 'Scaling', 'description' => 'Shockproof case with raised camera protection.', 'demand_weight' => 10],
            ],
        ];
    }
}
