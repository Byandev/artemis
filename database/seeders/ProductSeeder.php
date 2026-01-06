<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first workspace or create one if none exists
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->command->warn('No workspace found. Please create a workspace first.');

            return;
        }

        // Get the workspace owner
        $owner = User::find($workspace->owner_id);

        if (! $owner) {
            $this->command->warn('No workspace owner found.');

            return;
        }

        $products = [
            [
                'name' => 'Premium Wireless Headphones',
                'code' => 'PWH-001',
                'category' => 'Electronics',
                'status' => 'Scaling',
                'description' => 'High-quality wireless headphones with active noise cancellation and 30-hour battery life.',
            ],
            [
                'name' => 'Smart Fitness Tracker',
                'code' => 'SFT-002',
                'category' => 'Wearables',
                'status' => 'Scaling',
                'description' => 'Advanced fitness tracker with heart rate monitoring, sleep tracking, and GPS.',
            ],
            [
                'name' => 'Eco-Friendly Water Bottle',
                'code' => 'EWB-003',
                'category' => 'Lifestyle',
                'status' => 'Testing',
                'description' => 'Sustainable stainless steel water bottle that keeps drinks cold for 24 hours.',
            ],
            [
                'name' => 'Portable Phone Charger',
                'code' => 'PPC-004',
                'category' => 'Electronics',
                'status' => 'Scaling',
                'description' => '20,000mAh power bank with fast charging and multiple USB ports.',
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'code' => 'EOC-005',
                'category' => 'Furniture',
                'status' => 'Testing',
                'description' => 'Comfortable office chair with lumbar support and adjustable armrests.',
            ],
            [
                'name' => 'LED Desk Lamp',
                'code' => 'LDL-006',
                'category' => 'Home',
                'status' => 'Scaling',
                'description' => 'Modern desk lamp with adjustable brightness and USB charging port.',
            ],
            [
                'name' => 'Wireless Gaming Mouse',
                'code' => 'WGM-007',
                'category' => 'Electronics',
                'status' => 'Testing',
                'description' => 'High-precision gaming mouse with customizable RGB lighting and 16,000 DPI.',
            ],
            [
                'name' => 'Organic Green Tea Set',
                'code' => 'OGT-008',
                'category' => 'Food',
                'status' => 'Inactive',
                'description' => 'Premium organic green tea collection with 6 unique flavors.',
            ],
            [
                'name' => 'Bluetooth Speaker Pro',
                'code' => 'BSP-009',
                'category' => 'Electronics',
                'status' => 'Scaling',
                'description' => 'Waterproof Bluetooth speaker with 360-degree sound and 12-hour battery.',
            ],
            [
                'name' => 'Yoga Mat Premium',
                'code' => 'YMP-010',
                'category' => 'Sports',
                'status' => 'Testing',
                'description' => 'Non-slip yoga mat with extra cushioning and eco-friendly materials.',
            ],
            [
                'name' => 'Smart Home Security Camera',
                'code' => 'SHSC-011',
                'category' => 'Electronics',
                'status' => 'Scaling',
                'description' => '1080p HD security camera with night vision and motion detection.',
            ],
            [
                'name' => 'Bamboo Cutting Board Set',
                'code' => 'BCBS-012',
                'category' => 'Kitchen',
                'status' => 'Testing',
                'description' => 'Durable bamboo cutting boards in 3 sizes, naturally antimicrobial.',
            ],
            [
                'name' => 'Mechanical Keyboard RGB',
                'code' => 'MKR-013',
                'category' => 'Electronics',
                'status' => 'Scaling',
                'description' => 'Mechanical gaming keyboard with RGB backlighting and cherry MX switches.',
            ],
            [
                'name' => 'Stainless Steel Cookware Set',
                'code' => 'SSCS-014',
                'category' => 'Kitchen',
                'status' => 'Failed',
                'description' => '10-piece professional cookware set with heat-resistant handles.',
            ],
            [
                'name' => 'Digital Drawing Tablet',
                'code' => 'DDT-015',
                'category' => 'Electronics',
                'status' => 'Testing',
                'description' => 'Professional drawing tablet with 8192 pressure levels and tilt support.',
            ],
            [
                'name' => 'Running Shoes Elite',
                'code' => 'RSE-016',
                'category' => 'Sports',
                'status' => 'Scaling',
                'description' => 'Lightweight running shoes with responsive cushioning and breathable mesh.',
            ],
            [
                'name' => 'Smart LED Light Bulbs',
                'code' => 'SLLB-017',
                'category' => 'Home',
                'status' => 'Scaling',
                'description' => 'WiFi-enabled color-changing LED bulbs compatible with voice assistants.',
            ],
            [
                'name' => 'Camping Tent 4-Person',
                'code' => 'CT4P-018',
                'category' => 'Outdoor',
                'status' => 'Testing',
                'description' => 'Waterproof 4-person tent with easy setup and ventilation system.',
            ],
            [
                'name' => 'Coffee Maker Deluxe',
                'code' => 'CMD-019',
                'category' => 'Kitchen',
                'status' => 'Inactive',
                'description' => 'Programmable coffee maker with thermal carafe and auto-shutoff.',
            ],
            [
                'name' => 'Phone Case Ultra Protective',
                'code' => 'PCUP-020',
                'category' => 'Accessories',
                'status' => 'Scaling',
                'description' => 'Military-grade drop protection phone case with raised edges.',
            ],
        ];

        foreach ($products as $productData) {
            Product::create([
                'workspace_id' => $workspace->id,
                'owner_id' => $owner->id,
                'title' => $productData['name'], // Use name as title
                'name' => $productData['name'],
                'code' => $productData['code'],
                'category' => $productData['category'],
                'status' => $productData['status'],
                'description' => $productData['description'],
            ]);
        }

        $this->command->info('Successfully seeded 20 products!');
    }
}
