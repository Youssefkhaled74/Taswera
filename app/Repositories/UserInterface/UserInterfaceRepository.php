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
use Symfony\Component\HttpFoundation\Response;
use App\Traits\ApiResponse;

class UserInterfaceRepository implements UserInterfaceRepositoryInterface
{
    use ApiResponse;
    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode): array
    {
        // Get user by barcode with branch relationship
        $user = User::with('branch')
            ->where('barcode', 'LIKE', $barcode . '%')
            ->first();

        if (!$user) {
            return [];
        }

        // Get all photos for the user with relationships
        $photos = Photo::with(['staff', 'branch'])
            ->where('barcode_prefix', $barcode)
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
        $barcodePrefix = $barcode; // use provided barcode directly
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
            'barcode_prefix' => $barcodePrefix,
            'file_path' => "/storage/{$path}", // Add /storage prefix for public URL access
            'original_filename' => $originalFilename,
            'branch_id' => 1,
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

        // Attach to the latest print request for this barcode prefix (if exists)
        $latestPrintRequest = PrintRequest::where('barcode_prefix', $barcodePrefix)
            ->orderByDesc('created_at')
            ->first();

        if ($latestPrintRequest) {
            $latestPrintRequest->photos()->attach($photo->id, [
                'quantity' => 1,
                'unit_price' => 50,
            ]);
        }

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

            // Reset any previous selection: move all user's ready_to_print photos back to pending
            Photo::where('user_id', $user->id)
                ->where('status', 'ready_to_print')
                ->update(['status' => 'pending']);

            // Use the barcode provided in the request as the barcode prefix
            $barcodePrefix = $barcode;
            $existingPrintRequests = PrintRequest::where('user_id', $user->id)
                ->where('barcode_prefix', $barcodePrefix)
                ->whereIn('status', ['pending', 'ready_to_print'])
                ->where(function ($q) {
                    $q->whereNull('is_paid')->orWhere('is_paid', false);
                })
                ->get();

            foreach ($existingPrintRequests as $existing) {
                // detach related photos then delete
                $existing->photos()->detach();
                $existing->delete();
            }

            // Parse requested photo IDs and quantities
            $photoItems = collect($data['photo_ids']); // expects [ ['id'=>.., 'quantity'=>..], ... ]
            $photoIds = $photoItems->pluck('id')->all();

            // Get and validate photos belong to user
            $photos = Photo::whereIn('id', $photoIds)
                ->where('barcode_prefix', $barcodePrefix)
                ->get();

            if ($photos->count() !== count($photoIds)) {
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
                'branch_id' => $photos->first()->branch_id,
                'barcode_prefix' => $barcodePrefix,
                'payment_method' => 'cash',
                'package_id' => $packageId,
                'status' => 'pending',
                'metadata' => [
                    'created_from' => 'user_interface',
                    'num_photos' => $photos->count()
                ]
            ]);

            // Attach photos to print request
            $attachPayload = $photoItems
                ->keyBy('id')
                ->map(function ($item) {
                    return [
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'unit_price' => 50,
                    ];
                })
                ->only($photos->pluck('id')->all())
                ->toArray();
            $printRequest->photos()->attach($attachPayload);

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