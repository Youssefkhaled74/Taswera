<?php

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Create a new user with the given data
     * 
     * @param array $data
     * @return User
     */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    /**
     * Find a user by ID
     * 
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Find a user by barcode
     * 
     * @param string $barcode
     * @return User|null
     */
    public function findByBarcode(string $barcode): ?User
    {
        return $this->model->where('barcode', $barcode)->first();
    }

    /**
     * Find a user by barcode and phone number
     * 
     * @param string $barcode
     * @param string $phoneNumber
     * @return User|null
     */
    public function findByBarcodeAndPhone(string $barcode, string $phoneNumber): ?User
    {
        return $this->model->where('barcode', $barcode)
            ->where('phone_number', $phoneNumber)
            ->first();
    }

    /**
     * Check if a barcode already exists
     * 
     * @param string $barcode
     * @return bool
     */
    public function barcodeExists(string $barcode): bool
    {
        return $this->model->where('barcode', $barcode)->exists();
    }

    /**
     * Update the user's last visit timestamp
     * 
     * @param int $userId
     * @return bool
     */
    public function updateLastVisit(int $userId): bool
    {
        $user = $this->findById($userId);
        
        if (!$user) {
            return false;
        }
        
        $user->last_visit = now();
        return $user->save();
    }
} 