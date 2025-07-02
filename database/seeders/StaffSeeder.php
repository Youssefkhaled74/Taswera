<?php

namespace Database\Seeders;

use App\Models\Staff;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staffMembers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@taswera.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'branch_id' => 1, // Main Branch
            ],
            [
                'name' => 'Photographer 1',
                'email' => 'photographer1@taswera.com',
                'password' => Hash::make('password'),
                'role' => 'photographer',
                'branch_id' => 1, // Main Branch
            ],
            [
                'name' => 'Cashier 1',
                'email' => 'cashier1@taswera.com',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'branch_id' => 1, // Main Branch
            ],
            [
                'name' => 'Staff North',
                'email' => 'staff.north@taswera.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => 2, // North Branch
            ],
            [
                'name' => 'Staff East',
                'email' => 'staff.east@taswera.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'branch_id' => 3, // East Branch
            ],
        ];

        foreach ($staffMembers as $staff) {
            Staff::create($staff);
        }
    }
} 