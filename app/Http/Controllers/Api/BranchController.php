<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of branches.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $query = Branch::query();
        return $this->successResponse(
            paginate($query, BranchResource::class, $limit, $page),
            'Branches retrieved successfully'
        );
    }

    /**
     * Store a new branch.
     *
     * @throws ValidationException
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|email|unique:branches,email',
        ]);

        $branch = Branch::create($validated);
        return $this->successResponse(
            new BranchResource($branch),
            'Branch created successfully',
            Response::HTTP_CREATED
        );
    }

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch): JsonResponse
    {
        return $this->successResponse(
            new BranchResource($branch),
            'Branch retrieved successfully'
        );
    }

    /**
     * Update the specified branch.
     *
     * @throws ValidationException
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'email' => 'sometimes|required|email|unique:branches,email,' . $branch->id,
        ]);

        $branch->update($validated);
        return $this->successResponse(
            new BranchResource($branch->fresh()),
            'Branch updated successfully'
        );
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $branch->delete();
        return $this->successResponse(null, 'Branch deleted successfully');
    }
} 