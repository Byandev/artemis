<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CSRAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                "csr" => "Ana Reyes",
                "orders" => 120,
                "sales" => 185430.75,
                "delivered" => 142300.5,
                "returning" => 43130.25,
                "rts_rate" => 23.26,
                "rmo_confirmed" => 10,
                "rmo_assigned" => 10,
                "rmo_called" => 8,
                "total_call_time" => "18:42"
            ],
            [
                "csr" => "Ben Santos",
                "orders" => 95,
                "sales" => 128900,
                "delivered" => 101500,
                "returning" => 27400,
                "rts_rate" => 21.26,
                "rmo_confirmed" => 10,
                "rmo_assigned" => 5,
                "rmo_called" => 4,
                "total_call_time" => "09:15"
            ],
            [
                "csr" => "Carla Lim",
                "orders" => 0,
                "sales" => 0,
                "delivered" => 0,
                "returning" => 0,
                "rts_rate" => 0.00,
                "rmo_confirmed" => 0,
                "rmo_assigned" => 3,
                "rmo_called" => 2,
                "total_call_time" => "04:30"
            ],
            [
                "csr" => "Diego Cruz",
                "orders" => 1420,
                "sales" => 2489750.5,
                "delivered" => 2110420.75,
                "returning" => 379329.75,
                "rts_rate" => 15.24,
                "rmo_confirmed" => 875,
                "rmo_assigned" => 642,
                "rmo_called" => 588,
                "total_call_time" => "46:18:09"
            ],
            [
                "csr" => "Ella Tan",
                "orders" => 7,
                "sales" => 8950.25,
                "delivered" => 7650.25,
                "returning" => 1300,
                "rts_rate" => 14.52,
                "rmo_confirmed" => 4,
                "rmo_assigned" => 1,
                "rmo_called" => 1,
                "total_call_time" => "01:12"
            ]
        ];

        foreach ($data as $item) {
            $item['created_at'] = now(); // Automatically sets it to May 14, 2026
            $item['updated_at'] = now();
            DB::table('csr_analytics')->insert($item);
        }
    }
}