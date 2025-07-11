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
     * @param int|null $staffId
     * @return User
     */
    public function registerUser(string $phoneNumber, int $branchId, ?int $staffId = null): User
    {
        // Generate a unique barcode
        $barcode = $this->barcodeService->generateUniqueBarcode($branchId);
        
        // Create the user
        $user = $this->userRepository->create([
            'barcode' => $barcode,
            'phone_number' => $phoneNumber,
            'branch_id' => $branchId,
            'registered_by' => $staffId,
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

    /**
     * Find user by barcode prefix (first 8 digits)
     * 
     * @param string $barcodePrefix
     * @return User|null
     */
    public function findUserByBarcodePrefix(string $barcodePrefix): ?User
    {
        // Validate that the prefix is exactly 8 digits
        if (!preg_match('/^\d{8}$/', $barcodePrefix)) {
            return null;
        }
        
        // Find user where barcode starts with the prefix
        return User::where('barcode', 'like', $barcodePrefix . '%')->first();
    }
} 