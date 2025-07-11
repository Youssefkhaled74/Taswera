<?php

namespace App\Services\Photo;

use Illuminate\Http\UploadedFile;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Collection;

interface PhotoServiceInterface
{
    /**
     * Upload a photo and assign it to a user
     * 
     * @param UploadedFile $photo
     * @param int $userId
     * @param int $staffId
     * @param int $branchId
     * @param string $barcodePrefix
     * @return Photo|null
     */
    public function uploadPhoto(
        UploadedFile $photo,
        int $userId,
        int $staffId,
        int $branchId,
        string $barcodePrefix
    ): ?Photo;

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