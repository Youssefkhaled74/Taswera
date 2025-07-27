<?php

namespace App\Http\Controllers\Api\OnlineDashboard;

use App\Models\Staff;
use App\Models\Branch;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Http\Resources\BranchResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Access\AuthorizesRequests;

class BranchController extends Controller
{
    use ApiResponse;
    public function index(Request $request): JsonResponse
    {
        // Get query builder, not collection
        $branchesQuery = Branch::with(['staff', 'photos']);

        // Get pagination parameters from request, with defaults
        $limit = $request->input('limit', 15);
        $page = $request->input('page', 1);

        // Use the helper paginate function
        $paginatedBranches = paginate($branchesQuery, BranchResource::class, $limit, $page);

        return $this->successResponse($paginatedBranches, 'Branches retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['exists:staff,id'],
            'photographer_ids' => ['sometimes', 'array'],
            'photographer_ids.*' => ['exists:staff,id'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors', 422, $validator->errors());
        }

        // Extract and validate all unique staff IDs
        $employeeIds = $request->input('employee_ids', []);
        $photographerIds = $request->input('photographer_ids', []);
        $allStaffIds = array_unique(array_merge($employeeIds, $photographerIds));

        if (!empty($allStaffIds)) {
            $existingStaffIds = \App\Models\Staff::whereIn('id', $allStaffIds)->pluck('id')->all();
            $missingIds = array_diff($allStaffIds, $existingStaffIds);

            if (!empty($missingIds)) {
                return $this->errorResponse('The following staff IDs do not exist: ' . implode(', ', $missingIds), 422);
            }

            // Check if any staff already has a branch
            $assignedStaff = \App\Models\Staff::whereIn('id', $allStaffIds)
                ->whereNotNull('branch_id')
                ->pluck('id')
                ->all();

            if (!empty($assignedStaff)) {
                return $this->errorResponse('The following staff are already assigned to a branch: ' . implode(', ', $assignedStaff), 422);
            }
        }

        $branch = Branch::create([
            'name' => $request->input('name'),
            'location' => $request->input('location'),
            'is_active' => true,
        ]);

        // Update photographer roles and assign branch
        if (!empty($photographerIds)) {
            \App\Models\Staff::whereIn('id', $photographerIds)->update([
                'branch_id' => $branch->id,
                'role' => 'photographer',
            ]);
        }

        // Assign remaining staff (employees) to the branch without changing their role
        if (!empty($employeeIds)) {
            $nonPhotographerIds = array_diff($employeeIds, $photographerIds);
            if (!empty($nonPhotographerIds)) {
                \App\Models\Staff::whereIn('id', $nonPhotographerIds)->update(['branch_id' => $branch->id]);
            }
        }

        $branch->load(['staff']);
        $response['branch'] = new BranchResource($branch);
        $response['staff'] = StaffResource::collection($branch->staff);
        return $this->successResponse($response, 'Branch created successfully with associated staff', 201);
    }
    public function show(Branch $branch): JsonResponse
    {
        $branch->load(['staff', 'photos']);
        return $this->successResponse(new BranchResource($branch), 'Branch retrieved successfully');
    }
    public function update(Request $request, Branch $branch): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'employee_ids' => ['sometimes', 'array'],
            'employee_ids.*' => ['exists:staff,id'],
            'photographer_ids' => ['sometimes', 'array'],
            'photographer_ids.*' => ['exists:staff,id'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation errors', 422, $validator->errors());
        }

        if (!Branch::where('id', $branch->id)->exists()) {
            return $this->errorResponse('Branch not found', 404);
        }

        if (!$request->has('employee_ids') && !$request->has('photographer_ids')) {
            return $this->errorResponse('At least one of employee_ids or photographer_ids must be provided', 422);
        }

        if ($request->has('is_active')) {
            $branch->is_active = $request->input('is_active');
        }

        // Extract and validate all unique staff IDs
        $employeeIds = $request->input('employee_ids', []);
        $photographerIds = $request->input('photographer_ids', []);
        $allStaffIds = array_unique(array_merge($employeeIds, $photographerIds));

        if (!empty($allStaffIds)) {
            $existingStaffIds = \App\Models\Staff::whereIn('id', $allStaffIds)->pluck('id')->all();
            $missingIds = array_diff($allStaffIds, $existingStaffIds);

            if (!empty($missingIds)) {
                return $this->errorResponse('The following staff IDs do not exist: ' . implode(', ', $missingIds), 422);
            }

            // Check if any staff already has a different branch
            $currentBranchStaff = $branch->staff()->pluck('id')->all();
            $newStaffIds = array_diff($allStaffIds, $currentBranchStaff);

            if (!empty($newStaffIds)) {
                $assignedStaff = \App\Models\Staff::whereIn('id', $newStaffIds)
                    ->whereNotNull('branch_id')
                    ->where('branch_id', '!=', $branch->id)
                    ->pluck('id')
                    ->all();

                if (!empty($assignedStaff)) {
                    return $this->errorResponse('The following staff are already assigned to a different branch: ' . implode(', ', $assignedStaff), 422);
                }
            }
        }

        // Update branch details
        $branch->update($request->only(['name', 'location', 'is_active']));

        // Update photographer roles and assign branch
        if (!empty($photographerIds)) {
            \App\Models\Staff::whereIn('id', $photographerIds)->update([
                'branch_id' => $branch->id,
                'role' => 'photographer',
            ]);
        }

        // Assign remaining staff (employees) to the branch without changing their role
        if (!empty($employeeIds)) {
            $nonPhotographerIds = array_diff($employeeIds, $photographerIds);
            if (!empty($nonPhotographerIds)) {
                \App\Models\Staff::whereIn('id', $nonPhotographerIds)->update(['branch_id' => $branch->id]);
            }
        }

        // Clear staff not included in the new lists (optional: remove this if staff should not be unassigned)
        if (!empty($allStaffIds)) {
            \App\Models\Staff::where('branch_id', $branch->id)
                ->whereNotIn('id', $allStaffIds)
                ->update(['branch_id' => null]);
        }

        $branch->load(['staff']);
        $response['branch'] = new BranchResource($branch);
        $response['staff'] = StaffResource::collection($branch->staff);

        return $this->successResponse($response, 'Branch updated successfully with associated staff');
    }
    public function destroy(Branch $branch): JsonResponse
    {
        // Begin a transaction to ensure consistency
        DB::beginTransaction();

        try {
            // Soft delete the branch
            $branch->delete();

            // // Optionally soft-delete or update related models
            // $branch->staff()->update(['branch_id' => null]);  // Set branch_id to null for staff
            // $branch->photos()->delete();  // Soft delete photos if they use SoftDeletes
            // $branch->users()->update(['branch_id' => null]);  // Set branch_id to null for users
            // $branch->orders()->delete();  // Soft delete orders if they use SoftDeletes
            // $branch->payments()->delete();  // Soft delete payments if they use SoftDeletes
            // $branch->syncLogs()->delete();  // Soft delete sync logs if they use SoftDeletes
            // $branch->packages()->delete();  // Soft delete packages if they use SoftDeletes
            // $branch->frames()->delete();  // Soft delete frames if they use SoftDeletes
            // $branch->filters()->delete();  // Soft delete filters if they use SoftDeletes

            DB::commit();

            return $this->successResponse(null, 'Branch soft deleted successfully with related data handled');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to soft delete branch: ' . $e->getMessage(), 500);
        }
    }
    public function getUnassignedEmployees(): JsonResponse
    {

        $employees = Staff::whereNull('branch_id')
            ->where('role', '!=', 'photographer')
            ->whereNull('deleted_at') // Exclude soft-deleted staff
            ->get();

        return $this->successResponse(StaffResource::collection($employees), 'Unassigned employees retrieved successfully');
    }
    public function getUnassignedPhotographers(): JsonResponse
    {

        $photographers = Staff::whereNull('branch_id')
            ->where('role', 'photographer')
            ->whereNull('deleted_at') // Exclude soft-deleted staff
            ->get();

        return $this->successResponse(StaffResource::collection($photographers), 'Unassigned photographers retrieved successfully');
    }
}
