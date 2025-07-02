<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Main Branch',
                'location' => 'Downtown',
                'is_active' => true,
            ],
            [
                'name' => 'North Branch',
                'location' => 'North City',
                'is_active' => true,
            ],
            [
                'name' => 'East Branch',
                'location' => 'East City',
                'is_active' => true,
            ],
            [
                'name' => 'South Branch',
                'location' => 'South City',
                'is_active' => false,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::create($branch);
        }
    }
} 