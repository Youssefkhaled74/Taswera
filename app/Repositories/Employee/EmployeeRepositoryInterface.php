<?php

namespace App\Repositories\Employee;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Builder;

interface EmployeeRepositoryInterface
{
    /**
     * Get paginated list of non-photographer employees
     */
    public function getPaginatedEmployees(int $limit, int $page): Builder;

    /**
     * Get paginated list of photographers
     */
    public function getPaginatedPhotographers(int $limit, int $page): Builder;

    /**
     * Get total count of photographers
     */
    public function getPhotographerCount(): int;

    /**
     * Create new employee
     */
    public function create(array $data): Staff;

    /**
     * Update employee
     */
    public function update(Staff $employee, array $data): bool;

    /**
     * Toggle employee status
     */
    public function toggleStatus(Staff $employee): bool;

    /**
     * Delete employee
     */
    public function delete(Staff $employee): bool;

    /**
     * Find employee by ID
     */
    public function findById(int $id): ?Staff;
} 