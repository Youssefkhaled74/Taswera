<?php

namespace App\Http\Controllers\Api\OnlineDashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Services\Employee\EmployeeServiceInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EmployeeServiceInterface $employeeService
    ) {
    }

    /**
     * Get all employees (non-photographers)
     */
    public function getEmployees(Request $request): JsonResponse
    {
        try {
            $data = $this->employeeService->getPaginatedEmployees(
                $request->input('limit', 10),
                $request->input('page', 1)
            );

            return $this->successResponse($data, 'Employees retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve employees: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all photographers
     */
    public function getPhotographers(Request $request): JsonResponse
    {
        try {
            $data = $this->employeeService->getPaginatedPhotographers(
                $request->input('limit', 10),
                $request->input('page', 1)
            );

            return $this->successResponse($data, 'Photographers retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve photographers: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Add new employee
     */
    public function addEmployee(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'email',
                    'unique:staff,email',
                    'max:255'
                ],
                'password' => ['required', 'string', 'min:6'],
                'phone' => ['required', 'string', 'max:20'],
                'branch_id' => ['required', 'exists:branches,id'],
                'role' => [
                    'required',
                    'string',
                    Rule::notIn(['photographer']),
                    Rule::in(['manager', 'staff', 'admin'])
                ],
                'status' => ['required', Rule::in(['active', 'inactive'])]
            ], [
                'name.required' => 'The name field is required.',
                'name.max' => 'The name cannot exceed 255 characters.',
                'email.required' => 'The email field is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.unique' => 'This email is already registered.',
                'password.required' => 'The password field is required.',
                'password.min' => 'The password must be at least 6 characters.',
                'phone.required' => 'The phone number is required.',
                'phone.max' => 'The phone number cannot exceed 20 characters.',
                'branch_id.required' => 'Please select a branch.',
                'branch_id.exists' => 'The selected branch does not exist.',
                'role.required' => 'Please select a role.',
                'role.in' => 'Invalid role selected.',
                'role.not_in' => 'Cannot create photographer through this endpoint.',
                'status.required' => 'Please select a status.',
                'status.in' => 'Invalid status selected.'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()
                );
            }

            $data = $validator->validated();
            $data['password'] = Hash::make($data['password']);
            
            $employee = Staff::create($data);
            
            return $this->successResponse(
                new StaffResource($employee->load('branch')),
                'Employee added successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to add employee: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Add new photographer
     */
    public function addPhotographer(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'branch_id' => ['required', 'exists:branches,id'],
                'phone' => ['nullable', 'string', 'max:20']
            ], [
                'name.required' => 'The name field is required.',
                'name.max' => 'The name cannot exceed 255 characters.',
                'branch_id.required' => 'Please select a branch.',
                'branch_id.exists' => 'The selected branch does not exist.',
                'phone.required' => 'The phone number is required.',
                'phone.max' => 'The phone number cannot exceed 20 characters.'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()
                );
            }

            $data = $validator->validated();
            $data['role'] = 'photographer';
            $data['status'] = 'active';
            $data['phone'] = $request->input('phone', 'N/A'); // Optional phone field
            $data['email'] = strtolower(str_replace(' ', '.', $data['name'])) . '@photographer.com';
            $data['password'] = Hash::make('password123');
            
            $photographer = Staff::create($data);
            
            return $this->successResponse(
                new StaffResource($photographer->load('branch')),
                'Photographer added successfully',
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to add photographer: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Toggle employee status
     */
    public function toggleStatus(Staff $employee): JsonResponse
    {
        try {
            $employee->update([
                'status' => $employee->status === 'active' ? 'inactive' : 'active'
            ]);
            
            return $this->successResponse(
                new StaffResource($employee->fresh()->load('branch')),
                'Employee status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update status: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update employee
     */
    public function updateEmployee(Request $request, Staff $employee): JsonResponse
    {
        try {
            if ($employee->role === 'photographer') {
                return $this->errorResponse(
                    'Cannot update photographer through this endpoint',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'email',
                    Rule::unique('staff')->ignore($employee->id),
                    'max:255'
                ],
                'password' => ['nullable', 'string', 'min:6'],
                'phone' => ['required', 'string', 'max:20'],
                'branch_id' => ['required', 'exists:branches,id'],
                'role' => [
                    'required',
                    'string',
                    Rule::notIn(['photographer']),
                    Rule::in(['manager', 'staff', 'admin'])
                ],
                'status' => ['required', Rule::in(['active', 'inactive'])]
            ], [
                'name.required' => 'The name field is required.',
                'name.max' => 'The name cannot exceed 255 characters.',
                'email.required' => 'The email field is required.',
                'email.email' => 'Please provide a valid email address.',
                'email.unique' => 'This email is already registered.',
                'password.min' => 'The password must be at least 6 characters.',
                'phone.required' => 'The phone number is required.',
                'phone.max' => 'The phone number cannot exceed 20 characters.',
                'branch_id.required' => 'Please select a branch.',
                'branch_id.exists' => 'The selected branch does not exist.',
                'role.required' => 'Please select a role.',
                'role.in' => 'Invalid role selected.',
                'role.not_in' => 'Cannot update to photographer role.',
                'status.required' => 'Please select a status.',
                'status.in' => 'Invalid status selected.'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()
                );
            }

            $data = $validator->validated();
            
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            
            $employee->update($data);
            
            return $this->successResponse(
                new StaffResource($employee->fresh()->load('branch')),
                'Employee updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update employee: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Update photographer
     */
    public function updatePhotographer(Request $request, Staff $photographer): JsonResponse
    {
        try {
            if ($photographer->role !== 'photographer') {
                return $this->errorResponse(
                    'This endpoint is only for photographers',
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
                'branch_id' => ['required', 'exists:branches,id'],
                'phone' => ['nullable', 'string', 'max:20'],
                'status' => ['nullable', Rule::in(['active', 'inactive'])]
            ], [
                'name.required' => 'The name field is required.',
                'name.max' => 'The name cannot exceed 255 characters.',
                'branch_id.required' => 'Please select a branch.',
                'branch_id.exists' => 'The selected branch does not exist.',
                'phone.required' => 'The phone number is required.',
                'phone.max' => 'The phone number cannot exceed 20 characters.',
                'status.required' => 'Please select a status.',
                'status.in' => 'Invalid status selected.'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    'Validation failed',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    $validator->errors()
                );
            }

            $data = $validator->validated();
            $photographer->update($data);
            
            return $this->successResponse(
                new StaffResource($photographer->fresh()->load('branch')),
                'Photographer updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update photographer: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete employee or photographer
     */
    public function destroy(Staff $employee): JsonResponse
    {
        try {
            $employee->delete();
            
            return $this->successResponse(null, 'Employee deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete employee: ' . $e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
} 