<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Photo\PhotoServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PhotoController extends Controller
{
    use ApiResponse;

    protected $photoService;

    public function __construct(PhotoServiceInterface $photoService)
    {
        $this->photoService = $photoService;
    }

    /**
     * Upload a photo for a user by barcode
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function upload(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string',
            'photo' => 'required|image|max:10240', // Max 10MB
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, 'Validation failed', $validator->errors());
        }

        try {
            // Get the authenticated staff ID
            $staffId = Auth::guard('staff')->id();
            
            if (!$staffId) {
                return $this->errorResponse(401, 'Unauthorized');
            }

            // Upload the photo
            $photo = $this->photoService->uploadPhotoByBarcode(
                $request->barcode,
                $request->file('photo'),
                $staffId,
                $request->branch_id
            );

            if (!$photo) {
                return $this->errorResponse(404, 'User not found or invalid barcode');
            }

            return $this->successResponse(201, 'Photo uploaded successfully', [
                'photo' => $photo,
                'file_url' => asset('storage/' . $photo->file_path),
                'thumbnail_url' => asset('storage/' . $photo->thumbnail_path),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Failed to upload photo: ' . $e->getMessage());
        }
    }

    /**
     * Get photos for a user by barcode and phone number
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getPhotos(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string',
            'phone_number' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, 'Validation failed', $validator->errors());
        }

        try {
            // Get photos
            $photos = $this->photoService->getPhotosByBarcodeAndPhone(
                $request->barcode,
                $request->phone_number
            );

            if ($photos->isEmpty()) {
                return $this->successResponse(200, 'No photos found', [
                    'photos' => [],
                ]);
            }

            // Add URLs to photos
            $photosWithUrls = $photos->map(function ($photo) {
                $photo->file_url = asset('storage/' . $photo->file_path);
                $photo->thumbnail_url = asset('storage/' . $photo->thumbnail_path);
                return $photo;
            });

            return $this->successResponse(200, 'Photos retrieved successfully', [
                'photos' => $photosWithUrls,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Failed to retrieve photos: ' . $e->getMessage());
        }
    }
} 