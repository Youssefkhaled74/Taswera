<?php

namespace App\Services\Employee;

use App\Models\Staff;
use App\Repositories\Employee\EmployeeRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeService implements EmployeeServiceInterface
{
    public function __construct(
        private readonly EmployeeRepositoryInterface $employeeRepository
    ) {
    }

    public function getPaginatedEmployees(int $limit, int $page): array
    {
        $paginator = $this->employeeRepository->getPaginatedEmployees($limit, $page);
        return paginate($paginator, 'App\Http\Resources\StaffResource', $limit, $page);
    }

    public function getPaginatedPhotographers(int $limit, int $page): array
    {
        $paginator = $this->employeeRepository->getPaginatedPhotographers($limit, $page);
        $data = paginate($paginator, 'App\Http\Resources\StaffResource', $limit, $page);
        $data['photographer_count'] = $this->employeeRepository->getPhotographerCount();
        return $data;
    }

    public function createEmployee(array $data): Staff
    {
        if (!$this->validateEmployeeData($data)) {
            throw ValidationException::withMessages(['validation' => 'Invalid employee data']);
        }

        $data['password'] = Hash::make($data['password']);
        return $this->employeeRepository->create($data);
    }

    public function createPhotographer(array $data): Staff
    {
        if (!$this->validatePhotographerData($data)) {
            throw ValidationException::withMessages(['validation' => 'Invalid photographer data']);
        }

        $data['role'] = 'photographer';
        $data['status'] = 'active';
        $data['email'] = $data['name'] . '@photographer.com'; // Temporary email
        $data['password'] = Hash::make('password123'); // Temporary password

        return $this->employeeRepository->create($data);
    }

    public function updateEmployee(Staff $employee, array $data): bool
    {
        if ($employee->role === 'photographer') {
            throw ValidationException::withMessages(['role' => 'Cannot update photographer through this method']);
        }

        if (!$this->validateEmployeeData($data, $employee)) {
            throw ValidationException::withMessages(['validation' => 'Invalid employee data']);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->employeeRepository->update($employee, $data);
    }

    public function updatePhotographer(Staff $photographer, array $data): bool
    {
        if ($photographer->role !== 'photographer') {
            throw ValidationException::withMessages(['role' => 'This method is only for photographers']);
        }

        if (!$this->validatePhotographerData($data)) {
            throw ValidationException::withMessages(['validation' => 'Invalid photographer data']);
        }

        return $this->employeeRepository->update($photographer, $data);
    }

    public function toggleStatus(Staff $employee): bool
    {
        return $this->employeeRepository->toggleStatus($employee);
    }

    public function deleteEmployee(Staff $employee): bool
    {
        return $this->employeeRepository->delete($employee);
    }

    public function validateEmployeeData(array $data, ?Staff $employee = null): bool
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', $employee ? Rule::unique('staff')->ignore($employee->id) : 'unique:staff'],
            'phone' => 'required|string|max:20',
            'branch_id' => 'required|exists:branches,id',
            'role' => ['required', Rule::notIn(['photographer'])],
            'status' => 'required|in:active,inactive',
        ];

        if (!$employee) {
            $rules['password'] = 'required|string|min:6';
        } else {
            $rules['password'] = 'nullable|string|min:6';
        }

        $validator = Validator::make($data, $rules);
        return !$validator->fails();
    }

    public function validatePhotographerData(array $data): bool
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
        ]);

        return !$validator->fails();
    }
} 