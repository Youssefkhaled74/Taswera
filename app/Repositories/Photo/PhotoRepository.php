<?php

namespace App\Repositories\Photo;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PhotoRepository implements PhotoRepositoryInterface
{
    protected $model;

    public function __construct(Photo $model)
    {
        $this->model = $model;
    }

    /**
     * Create a new photo with the given data
     * 
     * @param array $data
     * @return Photo
     */
    public function create(array $data): Photo
    {
        return $this->model->create($data);
    }

    /**
     * Find a photo by ID
     * 
     * @param int $id
     * @return Photo|null
     */
    public function findById(int $id): ?Photo
    {
        return $this->model->find($id);
    }

    /**
     * Get photos by user ID
     * 
     * @param int $userId
     * @return Collection
     */
    public function getPhotosByUserId(int $userId): Collection
    {
        return $this->model->where('user_id', $userId)->get();
    }

    /**
     * Get photos by user barcode
     * 
     * @param string $barcode
     * @return Collection
     */
    public function getPhotosByBarcode(string $barcode): Collection
    {
        $user = User::where('barcode', $barcode)->first();
        
        if (!$user) {
            return Collection::make([]);
        }
        
        return $this->getPhotosByUserId($user->id);
    }
} 