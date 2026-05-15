<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

class CsrAnalyticsRmoPercentageSeeder extends Seeder
{
    private const ORDER_PREFIX = 'CSR-RMO-PCT';

    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $workspace = Workspace::create([
                'name' => 'Demo Workspace',
                'slug' => 'demo-workspace',
                'owner_id' => User::query()->first()?->id ?? User::factory()->create()->id,
            ]);
        }

        $owner = User::query()->first() ?? User::factory()->create();
        if (! $workspace->users()->where('users.id', $owner->id)->exists()) {
            $workspace->users()->attach($owner->id, ['role' => 'owner']);
        }

        $shop = Shop::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'name' => 'CSR Analytics Demo Shop',
            ],
            [
                'avatar_url' => null,
            ],
        );

        $page = Page::query()->firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'shop_id' => $shop->id,
                'name' => 'CSR Analytics Demo Page',
            ],
            [
                'owner_id' => $owner->id,
                'status' => 'active',
            ],
        );

        $date = CarbonImmutable::now()->subDay()->toDateString();
        $confirmedAt = CarbonImmutable::parse($date)->setTime(10, 0);
        $deliveryDate = $date;
        $now = now();

        $samples = [
            ['name' => 'CSR RMO Test 01', 'confirmed' => 10, 'assigned' => 5, 'called' => 3],
            ['name' => 'CSR RMO Test 02', 'confirmed' => 10, 'assigned' => 8, 'called' => 6],
            ['name' => 'CSR RMO Test 03', 'confirmed' => 12, 'assigned' => 6, 'called' => 4],
            ['name' => 'CSR RMO Test 04', 'confirmed' => 16, 'assigned' => 12, 'called' => 9],
            ['name' => 'CSR RMO Test 05', 'confirmed' => 20, 'assigned' => 10, 'called' => 7],
        ];

        $cleanupUserIds = collect(range(1, 10))
            ->map(fn ($i) => sprintf('00000000-0000-4000-8000-%012d', $i))
            ->all();

        DB::table('call_logs')
            ->where('workspace_id', $workspace->id)
            ->whereIn('user_id', $cleanupUserIds)
            ->where('phone_number', 'like', '6397700%')
            ->delete();

        DB::table('pancake_shop_users')
            ->where('shop_id', $shop->id)
            ->whereIn('user_id', $cleanupUserIds)
            ->delete();

        $orderIds = Order::query()
            ->where('workspace_id', $workspace->id)
            ->where('order_number', 'like', self::ORDER_PREFIX.'-%')
            ->pluck('id');

        if ($orderIds->isNotEmpty()) {
            OrderForDelivery::query()->whereIn('order_id', $orderIds)->delete();
            Order::query()->whereIn('id', $orderIds)->delete();
        }

        foreach ($samples as $index => $sample) {
            $number = $index + 1;
            $pancakeUserId = sprintf('00000000-0000-4000-8000-%012d', $number);

            PancakeUser::query()->updateOrCreate(
                ['id' => $pancakeUserId],
                [
                    'name' => $sample['name'],
                    'email' => 'csr-rmo-test-'.$number.'@example.test',
                    'phone_number' => '6399900'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                    'status' => 'ACTIVE',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            DB::table('pancake_shop_users')->updateOrInsert(
                [
                    'shop_id' => $shop->id,
                    'user_id' => $pancakeUserId,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $orders = collect();

            for ($orderIndex = 1; $orderIndex <= $sample['confirmed']; $orderIndex++) {
                $order = Order::query()->create([
                    'order_number' => sprintf('%s-%02d-%03d', self::ORDER_PREFIX, $number, $orderIndex),
                    'status' => 3,
                    'status_name' => 'Delivered',
                    'shop_id' => $shop->id,
                    'page_id' => $page->id,
                    'workspace_id' => $workspace->id,
                    'total_amount' => 10000 + ($number * 100) + $orderIndex,
                    'final_amount' => 10000 + ($number * 100) + $orderIndex,
                    'discount' => 0,
                    'fb_id' => sprintf('csr-rmo-fb-%02d-%03d', $number, $orderIndex),
                    'customer_id' => (string) Str::uuid(),
                    'delivery_attempts' => 1,
                    'inserted_at' => $confirmedAt,
                    'confirmed_at' => $confirmedAt,
                    'delivered_at' => $confirmedAt->addHours(2),
                    'tracking_code' => sprintf('CSRRMOTRK%02d%03d', $number, $orderIndex),
                    'parcel_status' => 'delivered',
                    'confirmed_by' => $pancakeUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $orders->push($order);
            }

            $orders->take($sample['assigned'])->values()->each(function (Order $order, int $assignedIndex) use ($workspace, $shop, $page, $pancakeUserId, $deliveryDate, $sample, $number, $now) {
                $customerPhone = '6397700'.str_pad((string) (($number * 100) + $assignedIndex), 4, '0', STR_PAD_LEFT);
                $riderPhone = '6398800'.str_pad((string) (($number * 100) + $assignedIndex), 4, '0', STR_PAD_LEFT);

                OrderForDelivery::query()->create([
                    'order_id' => $order->id,
                    'page_id' => $page->id,
                    'shop_id' => $shop->id,
                    'workspace_id' => $workspace->id,
                    'status' => $assignedIndex < $sample['called'] ? 'DELIVERED' : 'PENDING',
                    'parcel_status' => $assignedIndex < $sample['called'] ? 'delivered' : 'in_transit',
                    'rider_name' => 'Demo Rider '.($assignedIndex + 1),
                    'rider_phone' => $riderPhone,
                    'customer_name' => 'Demo Customer '.($assignedIndex + 1),
                    'customer_phone' => $customerPhone,
                    'conferrer_id' => $pancakeUserId,
                    'assignee_id' => $pancakeUserId,
                    'customer_call_attempts' => $assignedIndex < $sample['called'] ? 1 : 0,
                    'customer_call_duration' => $assignedIndex < $sample['called'] ? 60 : 0,
                    'rider_call_attempts' => 0,
                    'rider_call_duration' => 0,
                    'delivery_date' => $deliveryDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($assignedIndex < $sample['called']) {
                    DB::table('call_logs')->insert([
                        'workspace_id' => $workspace->id,
                        'user_id' => $pancakeUserId,
                        'phone_number' => $customerPhone,
                        'type' => 'outgoing',
                        'duration' => 60,
                        'call_date' => $deliveryDate,
                        'call_time' => sprintf('10:%02d:00', $assignedIndex),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
        }

        $this->command->info('Seeded 5 CSR Analytics RMO percentage sample rows.');
        $this->command->table(
            ['CSR', 'RMO Confirmed', 'RMO Assigned', 'Expected RMO Percentage'],
            collect($samples)->map(fn ($sample) => [
                $sample['name'],
                $sample['confirmed'],
                $sample['assigned'],
                number_format(($sample['assigned'] / $sample['confirmed']) * 100, 2).'%',
            ])->all(),
        );
    }
}
