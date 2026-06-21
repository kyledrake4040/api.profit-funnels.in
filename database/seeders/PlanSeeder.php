<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $plans = [
            [
                'name' => 'Starter',
                'description' => 'For individuals getting their first funnel live.',
                'price' => 29.00,
                'interval' => config('custom.plan.interval_monthly'),
                'features' => ['funnels' => 3, 'pages_per_funnel' => 10, 'support' => 'email'],
            ],
            [
                'name' => 'Pro',
                'description' => 'For marketers running multiple funnels.',
                'price' => 79.00,
                'interval' => config('custom.plan.interval_monthly'),
                'features' => ['funnels' => 25, 'pages_per_funnel' => 50, 'support' => 'priority'],
            ],
            [
                'name' => 'Agency',
                'description' => 'For teams and agencies managing clients at scale.',
                'price' => 199.00,
                'interval' => config('custom.plan.interval_monthly'),
                'features' => ['funnels' => 'unlimited', 'pages_per_funnel' => 'unlimited', 'support' => 'dedicated'],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['slug' => Str::slug($plan['name'])],
                [
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'price' => $plan['price'],
                    'currency' => 'USD',
                    'interval' => $plan['interval'],
                    'features' => $plan['features'],
                    'status' => config('custom.plan.status_active'),
                ]
            );
        }
    }
}
