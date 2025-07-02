<?php

namespace Database\Seeders;

use App\Models\Frame;
use Illuminate\Database\Seeder;

class FrameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $frames = [
            [
                'name' => 'Classic Frame',
                'file_path' => 'frames/classic.png',
                'thumbnail_path' => 'frames/thumbnails/classic.png',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Modern Frame',
                'file_path' => 'frames/modern.png',
                'thumbnail_path' => 'frames/thumbnails/modern.png',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Vintage Frame',
                'file_path' => 'frames/vintage.png',
                'thumbnail_path' => 'frames/thumbnails/vintage.png',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Holiday Frame',
                'file_path' => 'frames/holiday.png',
                'thumbnail_path' => 'frames/thumbnails/holiday.png',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Special Frame',
                'file_path' => 'frames/special.png',
                'thumbnail_path' => 'frames/thumbnails/special.png',
                'is_active' => true,
                'branch_id' => 1, // Main Branch only
            ],
        ];

        foreach ($frames as $frame) {
            Frame::create($frame);
        }
    }
} 