<?php

namespace App\Services\Photo;

use App\Models\Photo;
use App\Models\User;
use App\Repositories\Photo\PhotoRepositoryInterface;
use App\Services\Barcode\BarcodeServiceInterface;
use App\Services\User\UserServiceInterface;
use App\Traits\HandlesMediaUploads;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class PhotoService implements PhotoServiceInterface
{
    use HandlesMediaUploads;

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
        
        $img = Image::make($photo->getRealPath());
        $img->fit(300, 300, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $img->save(storage_path("app/public/{$thumbnailPath}"));
        
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
    ): ?Photo {
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
        
        $img = Image::make($photo->getRealPath());
        $img->fit(300, 300, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $img->save(storage_path("app/public/{$thumbnailPath}"));
        
        // Create photo record
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