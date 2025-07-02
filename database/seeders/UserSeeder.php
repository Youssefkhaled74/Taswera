<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'barcode' => '12345678-ABC',
                'phone_number' => '1234567890',
                'branch_id' => 1, // Main Branch
                'last_visit' => now(),
            ],
            [
                'barcode' => '23456789-DEF',
                'phone_number' => '2345678901',
                'branch_id' => 1, // Main Branch
                'last_visit' => now()->subDays(1),
            ],
            [
                'barcode' => '34567890-GHI',
                'phone_number' => '3456789012',
                'branch_id' => 2, // North Branch
                'last_visit' => now()->subDays(2),
            ],
            [
                'barcode' => '45678901-JKL',
                'phone_number' => '4567890123',
                'branch_id' => 3, // East Branch
                'last_visit' => now()->subDays(3),
            ],
            [
                'barcode' => '56789012-MNO',
                'phone_number' => '5678901234',
                'branch_id' => 1, // Main Branch
                'last_visit' => now()->subDays(4),
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
} 