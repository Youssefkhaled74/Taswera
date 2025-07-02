<?php

namespace App\Services\Photo;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

interface PhotoServiceInterface
{
    /**
     * Upload a photo for a user by barcode
     * 
     * @param string $barcode
     * @param UploadedFile $photo
     * @param int $staffId
     * @param int $branchId
     * @return Photo|null
     */
    public function uploadPhotoByBarcode(
        string $barcode, 
        UploadedFile $photo, 
        int $staffId, 
        int $branchId
    ): ?Photo;

    /**
     * Get photos for a user by barcode and phone number
     * 
     * @param string $barcode
     * @param string $phoneNumber
     * @return Collection
     */
    public function getPhotosByBarcodeAndPhone(string $barcode, string $phoneNumber): Collection;
} 