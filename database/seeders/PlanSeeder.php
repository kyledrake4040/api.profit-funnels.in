<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

final class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'        => 'Starter',
                'slug'        => 'starter',
                'description' => 'Attribution dashboard for one business.',
                'price'       => 99.00,
                'currency'    => 'CAD',
                'interval'    => 'Monthly',
                'features'    => json_encode([
                    'Attribution dashboard',
                    'GoHighLevel + QuickBooks hookup',
                    'Weekly revenue-by-channel report',
                    '1 business',
                ]),
                'status' => 'Active',
            ],
            [
                'name'        => 'Pro',
                'slug'        => 'pro',
                'description' => 'Everything in Starter plus automated content scheduling.',
                'price'       => 249.00,
                'currency'    => 'CAD',
                'interval'    => 'Monthly',
                'features'    => json_encode([
                    'Everything in Starter',
                    'Automated content scheduling',
                    'Before/after & GBP posting',
                    'Priority support',
                ]),
                'status' => 'Active',
            ],
            [
                'name'        => 'Done For You',
                'slug'        => 'done-for-you',
                'description' => 'We run the full attribution engine end to end.',
                'price'       => 499.00,
                'currency'    => 'CAD',
                'interval'    => 'Monthly',
                'features'    => json_encode([
                    'Everything in Pro',
                    'We run it end to end',
                    'Monthly strategy review',
                    'Up to 3 locations',
                ]),
                'status' => 'Active',
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
