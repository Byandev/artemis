<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ForDeliveryTodaySeeder extends Seeder
{
    /**
     * Seed the database with test data for the For Delivery Today page.
     */
    public function run(): void
    {
        // Get or create a workspace
        $workspace = Workspace::first() ?? Workspace::factory()->create();

        // Get or create a page
        $page = Page::where('workspace_id', $workspace->id)->first()
            ?? Page::factory()->forWorkspace($workspace)->create();

        // Define some test rider names and customer names for variety
        $riderNames = [
            'Vinh Tran',
            'Linh Nguyen',
            'An Pham',
            'Tung Hoang',
            'Huong Le',
        ];

        $customerNames = [
            'Hoang Minh',
            'Thao Tran',
            'Khoa Nguyen',
            'Linh Pham',
            'Duc Hoang',
            'Mai Le',
            'Dung Tran',
            'Thanh Pham',
        ];

        // Create 20 orders with today's on-delivery parcel journeys
        for ($i = 0; $i < 20; $i++) {
            // Create order
            $order = Order::factory()
                ->forPage($page)
                ->onDelivery()
                ->create();

            // Create shipping address
            ShippingAddress::factory()
                ->state([
                    'order_id' => $order->id,
                    'full_name' => $customerNames[array_rand($customerNames)],
                ])
                ->create();

            // Create parcel journey with today's date
            ParcelJourney::factory()
                ->state([
                    'order_id' => $order->id,
                    'rider_name' => $riderNames[array_rand($riderNames)],
                ])
                ->onDeliveryToday()
                ->create();
        }

        $this->command->info('ForDeliveryToday seeder completed! Created 20 orders with on-delivery status for today.');
    }
}
