<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    use ApiResponse;

    /**
     * Staff login
     *
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $staff = Staff::where('email', $validated['email'])->first();

        if (!$staff || !Hash::check($validated['password'], $staff->password)) {
            return $this->errorResponse('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        $token = $staff->createToken('staff-token')->plainTextToken;

        return $this->successResponse([
            'staff' => new StaffResource($staff),
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Staff logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * Display a listing of staff members.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $query = Staff::with('branch');
        return $this->successResponse(
            paginate($query, StaffResource::class, $limit, $page),
            'Staff members retrieved successfully'
        );
    }

    /**
     * Store a new staff member.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:staff,email',
            'password' => 'required|string|min:8',
            'branch_id' => 'required|exists:branches,id',
            'role' => 'required|string|in:staff,admin',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        
        $staff = Staff::create($validated);
        return $this->successResponse(
            new StaffResource($staff),
            'Staff member created successfully',
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified staff member.
     */
    public function show(Staff $staff): JsonResponse
    {
        if (!$staff) {
            return $this->errorResponse('Staff member not found', Response::HTTP_NOT_FOUND);
        }
        return $this->successResponse(
            new StaffResource($staff->load('branch')),
            'Staff member retrieved successfully'
        );
    }

    /**
     * Update the specified staff member.
     *
     * @throws ValidationException
     */
    public function update(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:staff,email,' . $staff->id,
            'branch_id' => 'sometimes|required|exists:branches,id',
            'role' => 'sometimes|required|string|in:staff,admin',
        ]);

        $staff->update($validated);
        return $this->successResponse(
            new StaffResource($staff->fresh()->load('branch')),
            'Staff member updated successfully'
        );
    }

    /**
     * Remove the specified staff member.
     */
    public function destroy(Staff $staff): JsonResponse
    {
        $staff->delete();
        return $this->successResponse(null, 'Staff member deleted successfully');
    }

    /**
     * Change staff member password.
     *
     * @throws ValidationException
     */
    public function changePassword(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $staff->password)) {
            return $this->errorResponse('Current password is incorrect', Response::HTTP_UNAUTHORIZED);
        }

        $staff->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return $this->successResponse(null, 'Password changed successfully');
    }
} 