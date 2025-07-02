<?php

namespace App\Services\Barcode;

interface BarcodeServiceInterface
{
    /**
     * Generate a unique 8-digit barcode
     * 
     * @param int $branchId
     * @return string
     */
    public function generateUniqueBarcode(int $branchId): string;

    /**
     * Validate a barcode format
     * 
     * @param string $barcode
     * @return bool
     */
    public function validateBarcodeFormat(string $barcode): bool;

    /**
     * Extract the 8-digit prefix from a barcode
     * 
     * @param string $barcode
     * @return string
     */
    public function extractBarcodePrefix(string $barcode): string;
} 