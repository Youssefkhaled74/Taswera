<?php

namespace App\Services\User;

use App\Models\User;

interface UserServiceInterface
{
    /**
     * Register a new user with a generated barcode
     * 
     * @param string $phoneNumber
     * @param int $branchId
     * @return User
     */
    public function registerUser(string $phoneNumber, int $branchId): User;

    /**
     * Validate user access by barcode and phone number
     * 
     * @param string $barcode
     * @param string $phoneNumber
     * @return User|null
     */
    public function validateUserAccess(string $barcode, string $phoneNumber): ?User;

    /**
     * Get user by barcode
     * 
     * @param string $barcode
     * @return User|null
     */
    public function getUserByBarcode(string $barcode): ?User;
} 