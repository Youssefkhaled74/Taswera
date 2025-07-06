<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Services\Photo\PhotoServiceInterface;
use App\Traits\ApiResponse;
use App\Traits\HandlesMediaUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PhotoController extends Controller
{
    use ApiResponse, HandlesMediaUploads;

    protected $photoService;

    public function __construct(PhotoServiceInterface $photoService)
    {
        $this->photoService = $photoService;
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
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'photos.*' => 'required|image|max:10240', // 10MB max per photo
            'metadata' => 'nullable|json',
        ]);

        try {
            DB::beginTransaction();

            $uploadedPhotos = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $this->uploadMedia($photo, 'photos');
                    
                    $uploadedPhotos[] = Photo::create([
                        'file_path' => $path,
                        'staff_id' => Auth::id(),
                        'branch_id' => Auth::user()->branch_id,
                        'status' => 'pending',
                        'sync_status' => 'pending',
                        'metadata' => $request->input('metadata'),
                    ]);
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
            return $this->errorResponse('Failed to delete photo: ' . $e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
} 