<?php

namespace Database\Seeders;

use App\Models\Photo;
use Illuminate\Database\Seeder;

class PhotoStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all photos
        $photos = Photo::all();
        
        foreach ($photos as $photo) {
            // 30% chance to mark a photo as ready_to_print
            if (rand(1, 100) <= 30) {
                $photo->update([
                    'status' => 'ready_to_print'
                ]);
            }
        }
    }
} 