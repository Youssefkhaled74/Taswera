<?php

namespace App\Repositories\Photo;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection;

interface PhotoRepositoryInterface
{
    /**
     * Create a new photo with the given data
     * 
     * @param array $data
     * @return Photo
     */
    public function create(array $data): Photo;

    /**
     * Find a photo by ID
     * 
     * @param int $id
     * @return Photo|null
     */
    public function findById(int $id): ?Photo;

    /**
     * Get photos by user ID
     * 
     * @param int $userId
     * @return Collection
     */
    public function getPhotosByUserId(int $userId): Collection;

    /**
     * Get photos by user barcode
     * 
     * @param string $barcode
     * @return Collection
     */
    public function getPhotosByBarcode(string $barcode): Collection;
} 