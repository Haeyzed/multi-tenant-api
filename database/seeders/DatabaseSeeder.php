<?php

declare(strict_types=1);

namespace Database\Seeders;

use Database\Seeders\Central\DemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            WorldSeeder::class,
            DemoSeeder::class,
        ]);
    }
}
