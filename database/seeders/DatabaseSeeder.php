<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'platform.annual_fee' => ['value' => '0', 'currency' => 'BDT'],
            'platform.minimum_withdrawal' => ['value' => '0', 'currency' => 'BDT'],
            'platform.referral_commission_percent' => ['value' => '0', 'currency' => null],
        ] as $key => $setting) {
            DB::table('platform_settings')->updateOrInsert(
                ['key' => $key, 'version' => 1],
                [
                    'value' => $setting['value'],
                    'currency' => $setting['currency'],
                    'effective_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
