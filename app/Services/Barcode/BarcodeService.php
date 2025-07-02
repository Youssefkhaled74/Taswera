<?php

namespace App\Services\Barcode;

use App\Models\User;
use App\Repositories\User\UserRepositoryInterface;
use Illuminate\Support\Str;

class BarcodeService implements BarcodeServiceInterface
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * Generate a unique 8-digit barcode
     * 
     * @param int $branchId
     * @return string
     */
    public function generateUniqueBarcode(int $branchId): string
    {
        $attempts = 0;
        $maxAttempts = 10;
        
        do {
            // Generate an 8-digit numeric code
            $numericPart = str_pad(mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            
            // Generate a 3-character suffix
            $suffix = strtoupper(Str::random(3));
            
            // Combine to create the full barcode
            $barcode = $numericPart . '-' . $suffix;
            
            // Check if barcode already exists
            $exists = $this->userRepository->barcodeExists($barcode);
            
            $attempts++;
        } while ($exists && $attempts < $maxAttempts);
        
        // If we couldn't generate a unique barcode after max attempts, throw an exception
        if ($exists) {
            throw new \Exception('Could not generate a unique barcode after ' . $maxAttempts . ' attempts');
        }
        
        return $barcode;
    }

    /**
     * Validate a barcode format
     * 
     * @param string $barcode
     * @return bool
     */
    public function validateBarcodeFormat(string $barcode): bool
    {
        // Check if barcode matches the pattern: 8 digits, hyphen, 3 uppercase letters
        return preg_match('/^\d{8}-[A-Z]{3}$/', $barcode) === 1;
    }

    /**
     * Extract the 8-digit prefix from a barcode
     * 
     * @param string $barcode
     * @return string
     */
    public function extractBarcodePrefix(string $barcode): string
    {
        if (!$this->validateBarcodeFormat($barcode)) {
            throw new \InvalidArgumentException('Invalid barcode format');
        }
        
        return substr($barcode, 0, 8);
    }
} 