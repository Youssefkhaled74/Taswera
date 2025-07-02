<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\SyncLog;
use App\Models\Order;
use App\Models\User;
use App\Models\Photo;
use Illuminate\Database\Seeder;

class SyncLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all branches
        $branches = Branch::all();
        
        foreach ($branches as $branch) {
            // Create sync logs for the past 7 days
            for ($i = 0; $i < 7; $i++) {
                $date = now()->subDays($i);
                
                // Get counts for this branch on this date
                $totalSales = Order::where('branch_id', $branch->id)
                    ->where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->sum('total_price');
                
                $totalOrders = Order::where('branch_id', $branch->id)
                    ->whereDate('created_at', $date)
                    ->count();
                
                $totalUsers = User::where('branch_id', $branch->id)
                    ->whereDate('created_at', $date)
                    ->count();
                
                $totalPhotos = Photo::where('branch_id', $branch->id)
                    ->whereDate('created_at', $date)
                    ->count();
                
                // Create sync log
                SyncLog::create([
                    'branch_id' => $branch->id,
                    'synced_at' => $date->setHour(23)->setMinute(50),
                    'total_sales' => $totalSales,
                    'total_orders' => $totalOrders,
                    'total_users' => $totalUsers,
                    'total_photos' => $totalPhotos,
                    'status' => 'success',
                    'error_message' => null,
                ]);
            }
        }
    }
} 