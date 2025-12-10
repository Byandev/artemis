<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\ParcelJourneyNotification;
use App\Models\ShippingAddress;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RTSAnalyticsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        // Ensure workspace exists
        $workspace = Workspace::firstOrCreate(
            ['name' => 'Seed Workspace', 'owner_id' => $user->id],
            ['slug' => 'seed-workspace', 'description' => 'Workspace used for RTS analytics seeding', 'owner_id' => $user->id]
        );

        // Add the user as owner in the pivot table (don't duplicate)
        $workspace->users()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        // Create multiple shops and pages (configurable counts)
        $shops = [];
        $pagesByShop = [];

        $numShops = 10; // total shops to create
        $pagesPerShop = 5; // pages per shop

        for ($s = 1; $s <= $numShops; $s++) {
            $shop = Shop::firstOrCreate([
                'workspace_id' => $workspace->id,
                'name' => 'Seed Shop '.$s,
            ]);

            $shops[] = $shop;
            $pagesByShop[$shop->id] = [];

            for ($p = 1; $p <= $pagesPerShop; $p++) {
                $page = Page::firstOrCreate([
                    'workspace_id' => $workspace->id,
                    'shop_id' => $shop->id,
                    'owner_id' => $user->id,
                    'name' => "Seed Page {$s}-{$p}",
                ]);

                $pagesByShop[$shop->id][] = $page;
            }
        }

        // Create orders with a predictable distribution of statuses:
        // - status 3 => delivered
        // - status 4 => returned
        // - status 5 => returned (rts)
        $orders = [];
        for ($i = 1; $i <= 20; $i++) {
            if ($i <= 12) {
                $status = 3; // delivered
                $statusName = 'delivered';
            } elseif ($i <= 16) {
                $status = 4; // returned
                $statusName = 'returned';
            } else {
                $status = 5; // returned / rts
                $statusName = 'returned_rt';
            }

            // pick a random shop and one of its pages for this order
            $shop = $shops[array_rand($shops)];
            $page = $pagesByShop[$shop->id][array_rand($pagesByShop[$shop->id])];

            $order = Order::create([
                'order_number' => 'SEED-'.now()->timestamp.'-'.$i,
                'status' => $status,
                'status_name' => $statusName,
                'shop_id' => $shop->id,
                'page_id' => $page->id,
                'workspace_id' => $workspace->id,
                'total_amount' => rand(1000, 50000) / 100,
                'final_amount' => rand(500, 49000) / 100,
                'discount' => 0,
                'ad_id' => null,
                'fb_id' => Str::random(12),
                'delivery_attempts' => $status === 3 ? 1 : 2,
                'first_delivery_attempt' => now()->subDays(rand(1, 10)),
                'inserted_at' => now()->subDays(rand(10, 30)),
                'confirmed_at' => now()->subDays(rand(9, 29)),
                'delivered_at' => $status === 3 ? now()->subDays(rand(1, 8)) : null,
                'returned_at' => in_array($status, [4, 5]) ? now()->subDays(rand(1, 6)) : null,
                'returning_at' => in_array($status, [4, 5]) ? now()->subDays(rand(6, 15)) : null,
                'shipped_at' => now()->subDays(rand(11, 25)),
                'tracking_code' => $status === 3 ? 'TRK'.Str::upper(Str::random(8)) : null,
                'parcel_status' => $status === 3 ? 'delivered' : ($status === 4 ? 'returned' : 'rts'),
            ]);

            // Using exact city names from ph_geojson.json (NAME_2 and NAME_1 fields)
            $locations = [
                ['city' => 'Manila', 'province' => 'Manila'],
                ['city' => 'Valenzuela', 'province' => 'Manila'],
                ['city' => 'Malabon', 'province' => 'Manila'],
                ['city' => 'Quezon City', 'province' => 'Manila'],
                ['city' => 'Kalookan City', 'province' => 'Manila'],
                ['city' => 'Makati City', 'province' => 'Manila'],
                ['city' => 'Pasig City', 'province' => 'Manila'],
                ['city' => 'Taguig City', 'province' => 'Manila'],
                ['city' => 'Mandaluyong', 'province' => 'Manila'],
                ['city' => 'Marikina', 'province' => 'Manila'],
                ['city' => 'Muntinlupa', 'province' => 'Manila'],
                ['city' => 'Las Piñas', 'province' => 'Manila'],
                ['city' => 'Parañaque', 'province' => 'Manila'],
                ['city' => 'Pasay City', 'province' => 'Manila'],
                ['city' => 'Navotas', 'province' => 'Manila'],
                ['city' => 'Pateros', 'province' => 'Manila'],
                ['city' => 'San Juan', 'province' => 'Manila'],
            ];

            $location = $locations[array_rand($locations)];

            // Create a shipping address for the order
            ShippingAddress::firstOrCreate([
                'order_id' => $order->id,
            ], [
                'province_name' => $location['province'],
                'district_name' => $location['city'],
                'commune_name' => 'Commune '.rand(1, 50),
                'address' => 'Street '.rand(1, 200),
                'full_address' => 'Street '.rand(1, 200).', '.$location['city'].', '.$location['province'],
                'full_name' => 'Customer '.$i,
                'phone_number' => '080'.rand(10000000, 99999999),
            ]);

            $orders[] = $order;
        }

        // Add parcel journeys and notifications for some orders to simulate tracking
        foreach (array_slice($orders, 0, 10) as $order) {
            $pj = ParcelJourney::create([
                'order_id' => $order->id,
                'status' => $order->parcel_status ?? 'in_transit',
                'rider_name' => 'Rider '.Str::random(4),
                'rider_mobile' => '080'.rand(10000000, 99999999),
                'note' => 'Seeded parcel journey',
            ]);

            ParcelJourneyNotification::create([
                'order_id' => $order->id,
                'parcel_journey_id' => $pj->id,
                'type' => 'sms',
                'status' => 'sent',
                'receiver_name' => 'Customer '.$order->id,
                'receiver_identity' => '080'.rand(10000000, 99999999),
                'message' => 'Test parcel notification',
            ]);
        }
    }
}
