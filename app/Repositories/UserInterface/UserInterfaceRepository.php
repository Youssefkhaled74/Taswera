<?php

namespace App\Repositories\UserInterface;

use App\Models\User;
use App\Models\Photo;
use App\Models\Package;
use App\Models\PrintRequest;
use App\Http\Resources\PhotoResource;
use App\Http\Resources\PackageResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\PrintRequestResource;
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

    /**
     * Select photos for printing and create print request
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @param array $data
     * @return array
     */
    public function selectPhotosForPrinting(string $barcode, string $phoneNumber, array $data): array
    {
        // Start transaction
        return DB::transaction(function () use ($barcode, $phoneNumber, $data) {
            // Get user
            $user = User::where('barcode', 'LIKE', $barcode . '%')
                ->where('phone_number', $phoneNumber)
                ->first();

            if (!$user) {
                return [];
            }

            // Get and validate photos
            $photos = Photo::whereIn('id', $data['photo_ids'])
                ->where('user_id', $user->id)
                ->get();

            if ($photos->count() !== count($data['photo_ids'])) {
                throw new \Exception('Invalid photo selection');
            }

            // Validate package if provided
            $packageId = $data['package_id'] ?? null;
            if ($packageId) {
                $package = Package::find($packageId);
                if (!$package || !$package->is_active || $package->branch_id !== $user->branch_id) {
                    throw new \Exception('Invalid package selection');
                }
            }

            // Create print request
            $printRequest = PrintRequest::create([
                'user_id' => $user->id,
                'branch_id' => $user->branch_id,
                'barcode_prefix' => substr($user->barcode, 0, 8),
                'payment_method' => 'cash',
                'package_id' => $packageId,
                'status' => 'pending',
                'metadata' => [
                    'created_from' => 'user_interface',
                    'num_photos' => $photos->count()
                ]
            ]);

            // Attach photos to print request
            $printRequest->photos()->attach($photos->pluck('id'));

            // Update photos status
            $photos->each(function ($photo) {
                $photo->update(['status' => 'ready_to_print']);
            });

            // Load relationships
            $printRequest->load(['user', 'branch', 'package', 'photos']);

            return [
                'print_request' => new PrintRequestResource($printRequest),
                'photos' => PhotoResource::collection($photos->fresh()),
                'summary' => [
                    'num_photos' => $photos->count(),
                    'payment_method' => 'cash',
                    'package_id' => $packageId
                ]
            ];
        });
    }

    public function getPhotosReadyToPrint(string $barcode, string $phoneNumber): array
    {
        // Get user by barcode and phone number
        $user = User::where('barcode', 'LIKE', $barcode . '%')
            ->where('phone_number', $phoneNumber)
            ->first();

        if (!$user) {
            return [];
        }

        // Get photos that are ready to print for this user
        $photos = Photo::with(['staff', 'branch'])
            ->where('user_id', $user->id)
            ->where('status', 'ready_to_print')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get print requests associated with these photos
        $printRequests = PrintRequest::with(['photos', 'user', 'branch', 'package'])
            ->where('user_id', $user->id)
            ->where('status', 'ready_to_print')
            ->get();

        return [
            'user' => [
                'barcode' => $user->barcode,
                'phone_number' => $user->phone_number,
                'branch_id' => $user->branch_id,
                'branch' => new BranchResource($user->branch)
            ],
            'photos' => PhotoResource::collection($photos)->resolve(),
            'print_requests' => PrintRequestResource::collection($printRequests)->resolve()
        ];
    }
} 