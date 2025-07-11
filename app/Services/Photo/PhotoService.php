<?php

namespace App\Services\Photo;

use App\Models\Photo;
use App\Repositories\Photo\PhotoRepositoryInterface;
use App\Services\User\UserServiceInterface;
use App\Services\Barcode\BarcodeServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PhotoService implements PhotoServiceInterface
{
    protected $photoRepository;
    protected $userService;
    protected $barcodeService;

    public function __construct(
        PhotoRepositoryInterface $photoRepository,
        UserServiceInterface $userService,
        BarcodeServiceInterface $barcodeService
    ) {
        $this->photoRepository = $photoRepository;
        $this->userService = $userService;
        $this->barcodeService = $barcodeService;
    }

    /**
     * Upload a photo and assign it to a user
     */
    public function uploadPhoto(
        UploadedFile $photo,
        int $userId,
        int $staffId,
        int $branchId,
        string $barcodePrefix
    ): ?Photo {
        // Read the file contents before moving it
        $fileContents = file_get_contents($photo->getRealPath());
        
        // Generate file path without 'public' prefix
        $datePath = date('Y/m/d');
        $fileName = $barcodePrefix . '_' . time() . '_' . Str::random(5) . '.' . $photo->getClientOriginalExtension();
        $filePath = "photos/{$branchId}/{$datePath}/{$barcodePrefix}/{$fileName}";
        
        // Create directory if it doesn't exist (directly in public)
        $directory = public_path(dirname($filePath));
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Store the original photo directly in public directory
        $photo->move($directory, $fileName);
        
        // Create thumbnail
        $thumbnailPath = "photos/{$branchId}/{$datePath}/{$barcodePrefix}/thumbnails/{$fileName}";
        $thumbnailDirectory = public_path(dirname($thumbnailPath));
        if (!file_exists($thumbnailDirectory)) {
            mkdir($thumbnailDirectory, 0755, true);
        }
        
        // Create thumbnail using GD
        $sourceImage = imagecreatefromstring($fileContents);
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Calculate thumbnail dimensions (300x300 max)
        $targetWidth = 300;
        $targetHeight = 300;
        
        // Maintain aspect ratio
        if ($sourceWidth > $sourceHeight) {
            $targetHeight = floor($sourceHeight * ($targetWidth / $sourceWidth));
        } else {
            $targetWidth = floor($sourceWidth * ($targetHeight / $sourceHeight));
        }
        
        // Create thumbnail image
        $thumbnailImage = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $thumbnailImage, 
            $sourceImage,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $sourceWidth, $sourceHeight
        );
        
        // Save thumbnail directly to public directory
        $thumbnailFullPath = public_path($thumbnailPath);
        switch (strtolower($photo->getClientOriginalExtension())) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnailImage, $thumbnailFullPath, 80);
                break;
            case 'png':
                imagepng($thumbnailImage, $thumbnailFullPath, 8);
                break;
        }
        
        // Free up memory
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        // Create photo record with paths relative to public directory
        return $this->photoRepository->create([
            'user_id' => $userId,
            'file_path' => $filePath,
            'original_filename' => $photo->getClientOriginalName(),
            'uploaded_by' => $staffId,
            'branch_id' => $branchId,
            'is_edited' => false,
            'thumbnail_path' => $thumbnailPath,
            'status' => 'pending'
        ]);
    }

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
    ): ?Photo {
        // Validate barcode format
        if (!$this->barcodeService->validateBarcodeFormat($barcode)) {
            return null;
        }
        
        // Get the user
        $user = $this->userService->getUserByBarcode($barcode);
        
        if (!$user) {
            return null;
        }
        
        // Extract the 8-digit prefix from the barcode
        $barcodePrefix = $this->barcodeService->extractBarcodePrefix($barcode);
        
        // Generate file path
        $datePath = date('Y/m/d');
        $fileName = $barcodePrefix . '_' . time() . '_' . Str::random(5) . '.' . $photo->getClientOriginalExtension();
        $filePath = "photos/{$branchId}/{$datePath}/{$barcodePrefix}/{$fileName}";
        
        // Create directory if it doesn't exist
        $directory = dirname(storage_path("app/public/{$filePath}"));
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        // Store the original photo
        $photo->storeAs('public', $filePath);
        
        // Create thumbnail
        $thumbnailPath = "photos/{$branchId}/{$datePath}/{$barcodePrefix}/thumbnails/{$fileName}";
        $thumbnailDirectory = dirname(storage_path("app/public/{$thumbnailPath}"));
        if (!file_exists($thumbnailDirectory)) {
            mkdir($thumbnailDirectory, 0755, true);
        }
        
        // Create thumbnail using GD
        $sourceImage = imagecreatefromstring(file_get_contents($photo->getRealPath()));
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Calculate thumbnail dimensions (300x300 max)
        $targetWidth = 300;
        $targetHeight = 300;
        
        // Maintain aspect ratio
        if ($sourceWidth > $sourceHeight) {
            $targetHeight = floor($sourceHeight * ($targetWidth / $sourceWidth));
        } else {
            $targetWidth = floor($sourceWidth * ($targetHeight / $sourceHeight));
        }
        
        // Create thumbnail image
        $thumbnailImage = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled(
            $thumbnailImage, 
            $sourceImage,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $sourceWidth, $sourceHeight
        );
        
        // Save thumbnail
        $thumbnailFullPath = storage_path("app/public/{$thumbnailPath}");
        switch (strtolower($photo->getClientOriginalExtension())) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumbnailImage, $thumbnailFullPath, 80);
                break;
            case 'png':
                imagepng($thumbnailImage, $thumbnailFullPath, 8);
                break;
        }
        
        // Free up memory
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        // Create photo record
        return $this->photoRepository->create([
            'user_id' => $user->id,
            'file_path' => $filePath,
            'original_filename' => $photo->getClientOriginalName(),
            'uploaded_by' => $staffId,
            'branch_id' => $branchId,
            'is_edited' => false,
            'thumbnail_path' => $thumbnailPath,
        ]);
    }

    /**
     * Get photos for a user by barcode and phone number
     * 
     * @param string $barcode
     * @param string $phoneNumber
     * @return Collection
     */
    public function getPhotosByBarcodeAndPhone(string $barcode, string $phoneNumber): Collection
    {
        // Validate user access
        $user = $this->userService->validateUserAccess($barcode, $phoneNumber);
        
        if (!$user) {
            return Collection::make([]);
        }
        
        // Get photos for the user
        return $this->photoRepository->getPhotosByUserId($user->id);
    }
} 