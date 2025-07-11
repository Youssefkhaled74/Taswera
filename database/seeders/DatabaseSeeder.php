<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed in the correct order to handle dependencies
        $this->call([
            BranchSeeder::class,     // First, create branches
            StaffSeeder::class,       // Then staff members
            UserSeeder::class,        // Then users
            PackageSeeder::class,     // Then packages
            FrameSeeder::class,       // Then frames
            FilterSeeder::class,      // Then filters
            PhotoSeeder::class,       // Then photos
            PhotoStatusSeeder::class, // Then update photo statuses
            OrderSeeder::class,       // Then orders
            OrderItemSeeder::class,   // Then order items
            PaymentSeeder::class,     // Then payments
            SyncLogSeeder::class,     // Finally, sync logs
        ]);
    }
}
