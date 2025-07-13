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

        return $this->successResponse($result, 'Photos retrieved successfully');
    }
} 