<?php

namespace App\Http\Controllers\Api\OnlineDashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\Admin\AdminServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    use ApiResponse;

    protected $adminService;

    public function __construct(AdminServiceInterface $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Register a new admin
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'phone' => 'required|string|unique:admins,phone',
            'password' => 'required|string|min:8|confirmed',
            'is_super_admin' => 'sometimes|boolean',
            'permissions' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        try {
            // Register admin
            $admin = $this->adminService->register($request->all());

            // Generate token using admin guard
            $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

            return $this->successResponse([
                'admin' => new AdminResource($admin),
                'token' => $token,
            ], 'Admin registered successfully', Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to register admin: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Login admin
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
            return $this->errorResponse('Validation failed', Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        try {
            // Find admin by email
            $admin = Admin::where('email', $request->email)->first();

            // Check if admin exists and password is correct
            if (!$admin || !Hash::check($request->password, $admin->password)) {
                return $this->errorResponse('Invalid credentials', Response::HTTP_UNAUTHORIZED);
            }

            // Generate token with admin abilities
            $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

            return $this->successResponse([
                'admin' => new AdminResource($admin),
                'token' => $token,
            ], 'Login successful');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to login: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Logout admin
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user('admin')->currentAccessToken()->delete();
            return $this->successResponse(null, 'Logged out successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to logout: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
} 