<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed the subscription catalog.
     */
    public function run(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'price_cents' => 499,
                'currency' => 'USD',
                'interval' => 'month',
                'device_limit' => 1,
                'features' => ['1 device', 'Core VPN protection', 'Standard locations'],
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'price_cents' => 999,
                'currency' => 'USD',
                'interval' => 'month',
                'device_limit' => 5,
                'features' => ['5 devices', 'Priority locations', 'Kill switch', 'Split tunnel'],
            ],
            [
                'code' => 'team',
                'name' => 'Team',
                'price_cents' => 2499,
                'currency' => 'USD',
                'interval' => 'month',
                'device_limit' => 25,
                'features' => ['25 devices', 'Admin controls', 'Centralized billing', 'Priority support'],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::query()->updateOrCreate(
                ['code' => $plan['code']],
                $plan,
            );
        }
    }
}
