<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserInterface\UserInterfaceServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserInterfaceController extends Controller
{
    use ApiResponse;

    protected $userInterfaceService;

    public function __construct(UserInterfaceServiceInterface $userInterfaceService)
    {
        $this->userInterfaceService = $userInterfaceService;
    }

    /**
     * Get user photos by barcode and phone number
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getUserPhotos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|string|min:8',
            'phone_number' => 'required|string'
        ]);

        $result = $this->userInterfaceService->getUserPhotos(
            $validated['barcode'],
            $validated['phone_number']
        );

        if (empty($result)) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->successResponse($result, 'Photos retrieved successfully');
    }

    /**
     * Get all available packages for a specific branch
     *
     * @param int $branchId
     * @return JsonResponse
     */
    public function getPackages(int $branchId): JsonResponse
    {
        $packages = $this->userInterfaceService->getPackages($branchId);
        return $this->successResponse($packages, 'Packages retrieved successfully');
    }

    /**
     * Add a new photo to user's collection
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function addUserPhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => 'required|string|min:8',
            'phone_number' => 'required|string',
            'photo' => 'required|image|max:10240' // Max 10MB
        ]);

        $result = $this->userInterfaceService->addUserPhoto(
            $validated['barcode'],
            $validated['phone_number'],
            ['photo' => $validated['photo']]
        );

        if (empty($result)) {
            return $this->errorResponse('User not found', 404);
        }

        return $this->successResponse($result, 'Photo uploaded successfully');
    }
} 