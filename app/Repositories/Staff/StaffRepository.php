<?php

namespace App\Repositories\Staff;

use App\Models\Photo;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StaffRepository implements StaffRepositoryInterface
{
    protected $model;

    public function __construct(Staff $model)
    {
        $this->model = $model;
    }

    /**
     * Find a staff member by email
     * 
     * @param string $email
     * @return Staff|null
     */
    public function findByEmail(string $email): ?Staff
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Find a staff member by ID
     * 
     * @param int $id
     * @return Staff|null
     */
    public function findById(int $id): ?Staff
    {
        return $this->model->find($id);
    }

    /**
     * Get all staff members
     * 
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->model->all();
    }

    /**
     * Get all staff members by branch ID
     * 
     * @param int $branchId
     * @return Collection
     */
    public function getByBranch(int $branchId): Collection
    {
        return $this->model->where('branch_id', $branchId)->get();
    }

    /**
     * Get count of photos uploaded by a staff member
     * 
     * @param int $staffId
     * @return int
     */
    public function getPhotoCount(int $staffId): int
    {
        return Photo::where('uploaded_by', $staffId)->count();
    }

    /**
     * Get count of users registered by a staff member
     * 
     * @param int $staffId
     * @return int
     */
    public function getUserCount(int $staffId): int
    {
        return User::where('registered_by', $staffId)->count();
    }

    /**
     * Get photos uploaded by a staff member
     * 
     * @param int $staffId
     * @return Collection
     */
    public function getPhotos(int $staffId): Collection
    {
        return Photo::where('uploaded_by', $staffId)->with('user')->get();
    }
} 