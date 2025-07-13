<?php

namespace App\Repositories\UserInterface;

use App\Models\User;
use App\Models\Photo;
use App\Models\Package;
use App\Http\Resources\PhotoResource;
use App\Http\Resources\PackageResource;
use App\Http\Resources\BranchResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserInterfaceRepository implements UserInterfaceRepositoryInterface
{
    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode, string $phoneNumber): array
    {
        // Get user by barcode and phone number with branch relationship
        $user = User::with('branch')
            ->where('barcode', 'LIKE', $barcode . '%')
            ->where('phone_number', $phoneNumber)
            ->first();

        if (!$user) {
            return [];
        }

        // Get all photos for the user with relationships
        $photos = Photo::with(['staff', 'branch'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'user' => [
                'barcode' => $user->barcode,
                'phone_number' => $user->phone_number,
                'branch_id' => $user->branch_id,
                'branch' => new BranchResource($user->branch)
            ],
            'photos' => PhotoResource::collection($photos)->resolve()
        ];
    }

    /**
     * Get all available packages for a specific branch
     *
     * @param int $branchId
     * @return array
     */
    public function getPackages(int $branchId): array
    {
        $packages = Package::where('is_active', true)
            ->where('branch_id', $branchId)
            ->orderBy('price', 'asc')
            ->get();

        return PackageResource::collection($packages)->resolve();
    }

    /**
     * Add a new photo to user's collection
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @param array $photoData
     * @return array
     */
    public function addUserPhoto(string $barcode, string $phoneNumber, array $photoData): array
    {
        // Get user by barcode and phone number
        $user = User::where('barcode', 'LIKE', $barcode . '%')
            ->where('phone_number', $phoneNumber)
            ->first();

        if (!$user) {
            return [];
        }

        // Create the directory structure: storage/photos/{user_id}/{year}/{month}/{day}/{barcode_prefix}/
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        $barcodePrefix = substr($user->barcode, 0, 8);
        $directory = "photos/{$user->id}/{$year}/{$month}/{$day}/{$barcodePrefix}";

        // Ensure the directory exists
        Storage::disk('public')->makeDirectory($directory);

        // Store the photo
        $file = $photoData['photo'];
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$barcodePrefix}_" . uniqid() . ".{$extension}";
        $path = $file->storeAs($directory, $filename, 'public');

        // Create photo record
        $photo = Photo::create([
            'user_id' => $user->id,
            'file_path' => "/storage/{$path}", // Add /storage prefix for public URL access
            'original_filename' => $originalFilename,
            'branch_id' => $user->branch_id,
            'status' => 'pending',
            'sync_status' => 'pending',
            'metadata' => [
                'uploaded_from' => 'user_interface',
                'original_name' => $originalFilename,
                'year' => $year,
                'month' => $month,
                'day' => $day
            ]
        ]);

        // Load relationships and return formatted response
        $photo->load(['staff', 'branch']);
        
        return [
            'message' => 'Photo uploaded successfully',
            'photo' => new PhotoResource($photo)
        ];
    }
} 