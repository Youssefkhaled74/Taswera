<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use App\Services\Barcode\BarcodeServiceInterface;

class UserService implements UserServiceInterface
{
    protected $userRepository;
    protected $barcodeService;

    public function __construct(
        UserRepositoryInterface $userRepository,
        BarcodeServiceInterface $barcodeService
    ) {
        $this->userRepository = $userRepository;
        $this->barcodeService = $barcodeService;
    }

    /**
     * Register a new user with a generated barcode
     * 
     * @param string $phoneNumber
     * @param int $branchId
     * @return User
     */
    public function registerUser(string $phoneNumber, int $branchId): User
    {
        // Generate a unique barcode
        $barcode = $this->barcodeService->generateUniqueBarcode($branchId);
        
        // Create the user
        $user = $this->userRepository->create([
            'barcode' => $barcode,
            'phone_number' => $phoneNumber,
            'branch_id' => $branchId,
            'last_visit' => now(),
        ]);
        
        return $user;
    }

    /**
     * Validate user access by barcode and phone number
     * 
     * @param string $barcode
     * @param string $phoneNumber
     * @return User|null
     */
    public function validateUserAccess(string $barcode, string $phoneNumber): ?User
    {
        // Validate barcode format
        if (!$this->barcodeService->validateBarcodeFormat($barcode)) {
            return null;
        }
        
        // Find the user
        $user = $this->userRepository->findByBarcodeAndPhone($barcode, $phoneNumber);
        
        if ($user) {
            // Update last visit timestamp
            $this->userRepository->updateLastVisit($user->id);
        }
        
        return $user;
    }

    /**
     * Get user by barcode
     * 
     * @param string $barcode
     * @return User|null
     */
    public function getUserByBarcode(string $barcode): ?User
    {
        // Validate barcode format
        if (!$this->barcodeService->validateBarcodeFormat($barcode)) {
            return null;
        }
        
        return $this->userRepository->findByBarcode($barcode);
    }
} 