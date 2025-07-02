<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Staff\StaffServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    use ApiResponse;

    protected $staffService;

    public function __construct(StaffServiceInterface $staffService)
    {
        $this->staffService = $staffService;
    }

    /**
     * Login a staff member
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, 'Validation failed', $validator->errors());
        }

        try {
            // Attempt login
            $result = $this->staffService->login(
                $request->email,
                $request->password
            );

            if (!$result) {
                return $this->errorResponse(401, 'Invalid credentials');
            }

            return $this->successResponse(200, 'Login successful', [
                'staff' => $result['staff'],
                'token' => $result['token'],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Failed to login: ' . $e->getMessage());
        }
    }

    /**
     * Get all staff members with their statistics
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllStaff(Request $request): JsonResponse
    {
        try {
            // Get branch ID from request if provided
            $branchId = $request->branch_id;
            
            // Get all staff with stats
            $staffMembers = $this->staffService->getAllStaffWithStats($branchId);

            return $this->successResponse(200, 'Staff members retrieved successfully', [
                'staff' => $staffMembers,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Failed to retrieve staff members: ' . $e->getMessage());
        }
    }

    /**
     * Get photos uploaded by a specific staff member
     * 
     * @param Request $request
     * @param int $staffId
     * @return JsonResponse
     */
    public function getStaffPhotos(Request $request, int $staffId): JsonResponse
    {
        try {
            // Get photos uploaded by staff
            $photos = $this->staffService->getPhotosByStaff($staffId);

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