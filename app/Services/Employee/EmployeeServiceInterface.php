<?php

namespace App\Services\Employee;

use App\Models\Staff;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

interface EmployeeServiceInterface
{
    /**
     * Get paginated list of employees with their stats
     */
    public function getPaginatedEmployees(int $limit, int $page): array;

    /**
     * Get paginated list of photographers with their stats
     */
    public function getPaginatedPhotographers(int $limit, int $page): array;

    /**
     * Create new employee
     */
    public function createEmployee(array $data): Staff;

    /**
     * Create new photographer
     */
    public function createPhotographer(array $data): Staff;

    /**
     * Update employee
     */
    public function updateEmployee(Staff $employee, array $data): bool;

    /**
     * Update photographer
     */
    public function updatePhotographer(Staff $photographer, array $data): bool;

    /**
     * Toggle employee status
     */
    public function toggleStatus(Staff $employee): bool;

    /**
     * Delete employee
     */
    public function deleteEmployee(Staff $employee): bool;

    /**
     * Validate employee data
     */
    public function validateEmployeeData(array $data, ?Staff $employee = null): bool;

    /**
     * Validate photographer data
     */
    public function validatePhotographerData(array $data): bool;
} 