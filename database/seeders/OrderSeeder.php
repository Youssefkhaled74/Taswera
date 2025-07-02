<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use App\Models\Package;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();
        
        // Get all packages
        $packages = Package::all();
        
        // Create 1-2 orders for each user
        foreach ($users as $user) {
            $orderCount = rand(1, 2);
            
            for ($i = 1; $i <= $orderCount; $i++) {
                // Get a random package that's either global or matches the user's branch
                $availablePackages = $packages->filter(function ($package) use ($user) {
                    return $package->branch_id === null || $package->branch_id === $user->branch_id;
                });
                
                if ($availablePackages->isEmpty()) {
                    continue;
                }
                
                $package = $availablePackages->random();
                
                // Create the order
                Order::create([
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'total_price' => $package->price,
                    'status' => rand(0, 10) > 2 ? 'completed' : 'pending', // 80% completed, 20% pending
                    'processed_by' => rand(1, 5), // Random staff ID (1-5)
                    'branch_id' => $user->branch_id,
                    'whatsapp_link' => "https://taswera.com/photos/{$user->barcode}/" . md5($user->barcode . time()),
                    'link_expires_at' => now()->addDays(1),
                ]);
            }
        }
    }
} 