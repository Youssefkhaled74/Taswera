<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all completed orders
        $completedOrders = Order::where('status', 'completed')->get();
        
        foreach ($completedOrders as $order) {
            // Create payment for completed orders
            Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total_price,
                'method' => rand(0, 1) ? 'cash' : 'credit',
                'paid_at' => $order->updated_at,
                'received_by' => $order->processed_by,
                'transaction_id' => rand(0, 1) ? 'TR' . rand(100000, 999999) : null,
                'branch_id' => $order->branch_id,
            ]);
        }
    }
} 