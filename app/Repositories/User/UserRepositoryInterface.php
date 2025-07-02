<?php

namespace App\Repositories\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Create a new user with the given data
     * 
     * @param array $data
     * @return User
     */
    public function create(array $data): User;

    /**
     * Find a user by ID
     * 
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Find a user by barcode
     * 
     * @param string $barcode
     * @return User|null
     */
    public function findByBarcode(string $barcode): ?User;

    /**
     * Find a user by barcode and phone number
     * 
     * @param string $barcode
     * @param string $phoneNumber
     * @return User|null
     */
    public function findByBarcodeAndPhone(string $barcode, string $phoneNumber): ?User;

    /**
     * Check if a barcode already exists
     * 
     * @param string $barcode
     * @return bool
     */
    public function barcodeExists(string $barcode): bool;

    /**
     * Update the user's last visit timestamp
     * 
     * @param int $userId
     * @return bool
     */
    public function updateLastVisit(int $userId): bool;
} 