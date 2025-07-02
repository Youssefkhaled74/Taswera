<?php

namespace Database\Seeders;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Seeder;

class PhotoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();
        
        foreach ($users as $user) {
            // Extract the 8-digit code from the barcode
            $barcodePrefix = substr($user->barcode, 0, 8);
            
            // Create 3-5 photos for each user
            $photoCount = rand(3, 5);
            
            for ($i = 1; $i <= $photoCount; $i++) {
                Photo::create([
                    'user_id' => $user->id,
                    'file_path' => "photos/{$user->branch_id}/" . date('Y/m/d') . "/{$barcodePrefix}/{$barcodePrefix}_photo{$i}.jpg",
                    'original_filename' => "photo{$i}.jpg",
                    'uploaded_by' => rand(1, 5), // Random staff ID (1-5)
                    'branch_id' => $user->branch_id,
                    'is_edited' => false,
                    'thumbnail_path' => "photos/{$user->branch_id}/" . date('Y/m/d') . "/{$barcodePrefix}/thumbnails/{$barcodePrefix}_photo{$i}_thumb.jpg",
                ]);
            }
        }
    }
} 