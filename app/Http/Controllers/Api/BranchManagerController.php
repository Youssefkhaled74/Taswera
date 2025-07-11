<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BranchManager\LoginRequest;
use App\Http\Resources\BranchManagerResource;
use App\Http\Resources\BranchResource;
use App\Http\Resources\StaffResource;
use App\Models\Branch;
use App\Models\BranchManager;
use App\Services\BranchManager\BranchManagerServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class BranchManagerController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(
        private readonly BranchManagerServiceInterface $branchManagerService
    ) {}

    /**
     * Handle branch manager login.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $manager = BranchManager::where('phone', $request->phone)->first();

        if (!$manager || !Hash::check($request->password, $manager->password)) {
            return $this->errorResponse('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        $token = $manager->createToken('branch-manager-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'manager' => new BranchManagerResource($manager->load('branch')),
        ], 'Login successful');
    }

    /**
     * Get branch information and statistics.
     */
    public function getBranchInfo(Request $request): JsonResponse
    {
        $manager = $request->user();
        $branch = Branch::find($manager->branch_id);

        $this->authorize('viewStats', $branch);

        $branchInfo = $this->branchManagerService->getBranchInfo($manager);

        return $this->successResponse([
            'branch' => new BranchResource($branchInfo['branch']),
            'stats' => $branchInfo['stats'],
        ], 'Branch information retrieved successfully');
    }

    /**
     * Get branch staff members with their photo and customer statistics.
     */
    public function getBranchStaff(Request $request): JsonResponse
    {
        $manager = $request->user();
        $branch = Branch::find($manager->branch_id);

        $this->authorize('viewStaff', $branch);

        $staff = $this->branchManagerService->getBranchStaff($manager);

        // Load the uploadedPhotos relationship for counting
        $staff->load(['branch', 'uploadedPhotos']);

        return $this->successResponse(
            StaffResource::collection($staff),
            'Branch staff retrieved successfully'
        );
    }

    /**
     * Logout branch manager.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, 'Logged out successfully');
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:branch_managers',
            'phone' => 'required|string|unique:branch_managers',
            'password' => 'required|string|min:8',
            'branch_id' => 'required|exists:branches,id',
        ]);

        $manager = $this->branchManagerService->register($data);
        return $this->successResponse(
            new BranchManagerResource($manager), 
            'Branch manager registered successfully'
        );
    }
} 