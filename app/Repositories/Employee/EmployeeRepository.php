<?php

namespace App\Repositories\Employee;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(private Staff $model)
    {
    }

    public function getPaginatedEmployees(int $limit, int $page): Builder
    {
        return $this->model
            ->nonPhotographers()
            ->with('branch')
            ->latest();
    }

    public function getPaginatedPhotographers(int $limit, int $page): Builder
    {
        return $this->model
            ->photographers()
            ->with('branch')
            ->latest();
    }

    public function getPhotographerCount(): int
    {
        return $this->model->photographers()->count();
    }

    public function create(array $data): Staff
    {
        return $this->model->create($data);
    }

    public function update(Staff $employee, array $data): bool
    {
        return $employee->update($data);
    }

    public function toggleStatus(Staff $employee): bool
    {
        return $employee->update([
            'status' => $employee->status === 'active' ? 'inactive' : 'active'
        ]);
    }

    public function delete(Staff $employee): bool
    {
        return $employee->delete();
    }

    public function findById(int $id): ?Staff
    {
        return $this->model->with('branch')->find($id);
    }
} 