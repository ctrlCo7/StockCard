<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Reference data is always seeded. Demonstration users, items and transactions
     * are seeded only outside production.
     */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
