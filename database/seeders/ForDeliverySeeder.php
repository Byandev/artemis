<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Page;
use App\Models\ParcelJourney;
use App\Models\Product;
use App\Models\Rider;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ForDeliverySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a workspace
        $workspace = Workspace::first() ?: Workspace::create([
            'name' => 'Demo Workspace',
            'slug' => 'demo-workspace-'.Str::random(6),
        ]);

        // Create a page
        $page = Page::create([
            'workspace_id' => $workspace->id,
            'name' => 'Demo Page',
            'owner_id' => 1,
            'shop_id' => 1,
        ]);

        // Create an order with shipping address
        $order = Order::create([
            'order_number' => 'ORD-'.now()->format('YmdHis'),
            'status' => 2, // shipped/on-delivery status
            'status_name' => 'On Delivery',
            'shop_id' => $page->shop_id ?? 1,
            'page_id' => $page->id,
            'workspace_id' => $workspace->id,
            'total_amount' => 100.0,
            'final_amount' => 100.0,
            'discount' => 0,
            'fb_id' => $page->id.'_PSID',
            'inserted_at' => now(),
            'shipped_at' => now()->subHour(),
            'tracking_code' => 'TRACK'.rand(1000, 9999),
            'parcel_status' => 'On Delivery',
        ]);

        ShippingAddress::create([
            'order_id' => $order->id,
            'province_name' => 'Demo Province',
            'district_name' => 'Demo City',
            'commune_name' => 'Demo Commune',
            'address' => '123 Demo St',
            'full_name' => 'Jane Doe',
            'full_address' => '123 Demo St, Demo City',
            'phone_number' => '09171234567',
        ]);

        // Create a demo product (if none exists)
        $owner = User::first();

        $product = Product::first() ?: Product::create([
            'title' => 'Demo Product',
            'description' => 'Demo product description',
            'workspace_id' => $workspace->id ?? null,
            'owner_id' => $owner?->id ?? 1,
        ]);

        // Create an order item referencing the product
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
        ]);

        // Create a Rider and associate with the parcel journey
        $rider = Rider::firstOrCreate([
            'mobile' => '09181234567',
        ], [
            'name' => 'John Rider',
            'mobile' => '09181234567',
        ]);

        // Create a ParcelJourney with explicit rider_id
        $note = 'Some status note 【LocationA】';

        ParcelJourney::create([
            'order_id' => $order->id,
            'status' => 'On Delivery',
            'rider_name' => $rider->name,
            'rider_mobile' => $rider->mobile,
            'rider_id' => $rider->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
