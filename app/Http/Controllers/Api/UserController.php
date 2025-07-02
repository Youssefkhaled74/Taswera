<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\User\UserServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ApiResponse;

    protected $userService;

    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Register a new user with a generated barcode
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|min:10|max:15',
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(422, 'Validation failed', $validator->errors());
        }

        try {
            // Get the authenticated staff ID
            $staffId = Auth::guard('staff')->id();
            
            // Register the user
            $user = $this->userService->registerUser(
                $request->phone_number,
                $request->branch_id,
                $staffId
            );

            return $this->successResponse(201, 'User registered successfully', [
                'user' => $user,
                'barcode' => $user->barcode,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Failed to register user: ' . $e->getMessage());
        }
    }

    /**
     * Validate user access by barcode and phone number
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validateAccess(Request $request): JsonResponse
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
            // Validate user access
            $user = $this->userService->validateUserAccess(
                $request->barcode,
                $request->phone_number
            );

            if (!$user) {
                return $this->errorResponse(404, 'User not found or invalid credentials');
            }

            return $this->successResponse(200, 'Access validated successfully', [
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse(500, 'Failed to validate access: ' . $e->getMessage());
        }
    }
} 