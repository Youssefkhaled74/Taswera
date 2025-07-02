<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Frame;
use App\Models\Filter;
use Illuminate\Database\Seeder;

class OrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all orders
        $orders = Order::all();
        
        // Get all frames and filters
        $frames = Frame::all();
        $filters = Filter::all();
        
        foreach ($orders as $order) {
            // Get photos for this user
            $photos = Photo::where('user_id', $order->user_id)->get();
            
            if ($photos->isEmpty()) {
                continue;
            }
            
            // Get the package photo count
            $photoCount = $order->package->photo_count;
            
            // Ensure we don't exceed available photos
            $photoCount = min($photoCount, $photos->count());
            
            // Select random photos
            $selectedPhotos = $photos->random($photoCount);
            
            foreach ($selectedPhotos as $photo) {
                // Randomly decide if a frame should be applied
                $frame = rand(0, 10) > 5 ? $frames->random()->name : null;
                
                // Randomly decide if a filter should be applied
                $filter = rand(0, 10) > 5 ? $filters->random()->name : null;
                
                // Create the order item
                OrderItem::create([
                    'order_id' => $order->id,
                    'photo_id' => $photo->id,
                    'frame' => $frame,
                    'filter' => $filter,
                    'edited_photo_path' => $frame || $filter ? str_replace('.jpg', '_edited.jpg', $photo->file_path) : null,
                ]);
            }
        }
    }
} 