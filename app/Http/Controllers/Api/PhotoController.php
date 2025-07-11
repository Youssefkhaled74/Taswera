<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\Photo\PhotoServiceInterface;
use App\Services\User\UserServiceInterface;
use App\Traits\ApiResponse;
use App\Traits\HandlesMediaUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
    use ApiResponse, HandlesMediaUploads;

    protected $photoService;
    protected $userService;

    public function __construct(
        PhotoServiceInterface $photoService,
        UserServiceInterface $userService
    ) {
        $this->photoService = $photoService;
        $this->userService = $userService;
    }

    /**
     * Get photos for offline dashboard
     */
    public function offlineDashboard(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        
        $query = Photo::with(['staff', 'branch'])
            ->where('sync_status', 'pending')
            ->where('branch_id', Auth::user()->branch_id)
            ->latest();

        return $this->successResponse(
            paginate($query, PhotoResource::class, $limit, $page),
            'Offline dashboard photos retrieved successfully'
        );
    }

    /**
     * Get photos taken by specific staff member
     */
    public function staffPhotos(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        
        $query = Photo::with(['staff', 'branch'])
            ->where('staff_id', Auth::id())
            ->latest();

        return $this->successResponse(
            paginate($query, PhotoResource::class, $limit, $page),
            'Staff photos retrieved successfully'
        );
    }

    /**
     * Upload new photos
     *
     * @throws ValidationException
     */
    public function uploadPhotos(Request $request): JsonResponse
    {
        $request->validate([
            'photos.*' => 'required|image|max:10240', // 10MB max per photo
            'barcode_prefix' => 'required|string|size:8', // 8-digit barcode prefix
        ]);

        try {
            DB::beginTransaction();

            // Find user by barcode prefix
            $user = $this->userService->findUserByBarcodePrefix($request->barcode_prefix);
            if (!$user) {
                return $this->errorResponse('User not found for the given barcode prefix', Response::HTTP_NOT_FOUND);
            }

            $uploadedPhotos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $uploadedPhoto = $this->photoService->uploadPhoto(
                        $photo,
                        $user->id,
                        Auth::id(),
                        Auth::user()->branch_id,
                        $request->barcode_prefix
                    );
                    
                    if ($uploadedPhoto) {
                        $uploadedPhotos[] = $uploadedPhoto;
                    }
                }
            }

            DB::commit();

            return $this->successResponse(
                PhotoResource::collection($uploadedPhotos),
                'Photos uploaded successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Photo upload failed: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);
            return $this->errorResponse('Failed to upload photos: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update photo sync status
     */
    public function updateSyncStatus(Request $request, Photo $photo): JsonResponse
    {
        $request->validate([
            'sync_status' => 'required|in:pending,synced,failed',
        ]);

        $photo->update([
            'sync_status' => $request->sync_status,
        ]);

        return $this->successResponse(
            new PhotoResource($photo),
            'Photo sync status updated successfully'
        );
    }

    /**
     * Delete a photo
     */
    public function destroy(Photo $photo): JsonResponse
    {
        // Only allow deletion if photo belongs to the staff member or is in their branch
        if ($photo->staff_id !== Auth::id() && $photo->branch_id !== Auth::user()->branch_id) {
            return $this->errorResponse('Unauthorized', Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();
            
            // Delete the physical file
            $this->deleteMedia($photo->file_path);
            
            // Delete the database record
            $photo->delete();
            
            DB::commit();
            
            return $this->successResponse(null, 'Photo deleted successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Photo deletion failed: ' . $e->getMessage(), [
                'exception' => $e,
                'photo_id' => $photo->id
            ]);
            return $this->errorResponse('Failed to delete photo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get ready to print barcodes
     */
    public function getReadyToPrintBarcodes(Request $request): JsonResponse
    {
        $query = Photo::select('file_path')
            ->where('status', 'ready_to_print')
            ->where('branch_id', Auth::user()->branch_id)
            ->get()
            ->map(function ($photo) {
                return $photo->getBarcode();
            })
            ->unique();

        return $this->successResponse(
            $query->values()->all(),
            'Ready to print barcodes retrieved successfully'
        );
    }

    /**
     * Get ready to print photos by barcode
     */
    public function getReadyToPrintPhotosByBarcode(string $barcodePrefix): JsonResponse
    {
        $query = Photo::with(['staff', 'branch'])
            ->where('status', 'ready_to_print')
            ->where('branch_id', Auth::user()->branch_id)
            ->where('file_path', 'like', "%{$barcodePrefix}%")
            ->get();

        return $this->successResponse(
            PhotoResource::collection($query),
            'Ready to print photos retrieved successfully'
        );
    }

    /**
     * Get staff uploaded barcodes
     */
    public function getStaffUploadedBarcodes(int $staffId): JsonResponse
    {
        $query = Photo::select('file_path')
            ->where('uploaded_by', $staffId)
            ->get()
            ->map(function ($photo) {
                return $photo->getBarcode();
            })
            ->unique();

        return $this->successResponse(
            $query->values()->all(),
            'Staff uploaded barcodes retrieved successfully'
        );
    }

    /**
     * Get photos by barcode prefix
     */
    public function getPhotosByBarcodePrefix(string $barcodePrefix): JsonResponse
    {
        $query = Photo::with(['staff', 'branch'])
            ->where('file_path', 'like', "%{$barcodePrefix}%")
            ->get();

        return $this->successResponse(
            PhotoResource::collection($query),
            'Photos retrieved successfully'
        );
    }
} 