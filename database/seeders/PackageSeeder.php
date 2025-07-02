<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic Package',
                'price' => 25.00,
                'photo_count' => 2,
                'description' => 'Basic package with 2 photos',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Standard Package',
                'price' => 50.00,
                'photo_count' => 5,
                'description' => 'Standard package with 5 photos',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Premium Package',
                'price' => 100.00,
                'photo_count' => 10,
                'description' => 'Premium package with 10 photos and special frames',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Main Branch Special',
                'price' => 75.00,
                'photo_count' => 7,
                'description' => 'Special package only available at Main Branch',
                'is_active' => true,
                'branch_id' => 1, // Main Branch
            ],
            [
                'name' => 'North Branch Special',
                'price' => 60.00,
                'photo_count' => 6,
                'description' => 'Special package only available at North Branch',
                'is_active' => true,
                'branch_id' => 2, // North Branch
            ],
        ];

        foreach ($packages as $package) {
            Package::create($package);
        }
    }
} 