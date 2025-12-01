<?php

namespace Database\Seeders;

use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShopAndPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // get existing workspace ids, fall back to workspace id 1 if none exist
        $workspaceIds = DB::table('workspaces')->pluck('id')->toArray();
        if (empty($workspaceIds)) {
            $workspaceIds = [1];
        }

        // ensure we have existing user ids so owner_id FK is valid
        $userIds = DB::table('users')->pluck('id')->toArray();
        if (empty($userIds)) {
            $userId = DB::table('users')->insertGetId([
                'name' => $faker->name(),
                'email' => $faker->unique()->safeEmail(),
                'password' => bcrypt('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $userIds = [$userId];
        }

        // 2. Create 10 shops
        for ($i = 1; $i <= 10; $i++) {
            $workspaceId = $faker->randomElement($workspaceIds);

            $shopId = DB::table('shops')->insertGetId([
                'workspace_id' => $workspaceId,
                'name' => $faker->company(),
                'avatar_url' => $faker->imageUrl(200, 200, 'business'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Create 3-5 pages per shop
            $pagesCount = $faker->numberBetween(3, 5);
            for ($j = 1; $j <= $pagesCount; $j++) {
                DB::table('pages')->insert([
                    'shop_id' => $shopId,
                    'workspace_id' => $workspaceId,
                    'owner_id' => $faker->randomElement($userIds),
                    'name' => $faker->company().' Page',
                    'facebook_url' => $faker->url(),
                    'pos_token' => 'TEST_POS_'.$faker->unique()->numberBetween(1000, 9999),
                    'botcake_token' => $faker->boolean(50) ? 'BOT_'.$faker->numberBetween(100, 999) : null,
                    'infotxt_token' => $faker->boolean(50) ? 'INFO_'.$faker->numberBetween(100, 999) : null,
                    'infotxt_user_id' => $faker->boolean(50) ? 'USER_'.$faker->numberBetween(1, 50) : null,
                    'orders_last_synced_at' => $faker->dateTimeThisYear(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
