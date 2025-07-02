<?php

namespace Database\Seeders;

use App\Models\Filter;
use Illuminate\Database\Seeder;

class FilterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filters = [
            [
                'name' => 'Black & White',
                'code' => 'grayscale(100%)',
                'thumbnail_path' => 'filters/thumbnails/bw.jpg',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Sepia',
                'code' => 'sepia(100%)',
                'thumbnail_path' => 'filters/thumbnails/sepia.jpg',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Vintage',
                'code' => 'sepia(50%) contrast(85%) brightness(90%)',
                'thumbnail_path' => 'filters/thumbnails/vintage.jpg',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'High Contrast',
                'code' => 'contrast(150%)',
                'thumbnail_path' => 'filters/thumbnails/contrast.jpg',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
            [
                'name' => 'Warm',
                'code' => 'saturate(150%) hue-rotate(10deg)',
                'thumbnail_path' => 'filters/thumbnails/warm.jpg',
                'is_active' => true,
                'branch_id' => null, // Available at all branches
            ],
        ];

        foreach ($filters as $filter) {
            Filter::create($filter);
        }
    }
} 