<?php

namespace App\Repositories\Staff;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;

interface StaffRepositoryInterface
{
    /**
     * Find a staff member by email
     * 
     * @param string $email
     * @return Staff|null
     */
    public function findByEmail(string $email): ?Staff;

    /**
     * Find a staff member by ID
     * 
     * @param int $id
     * @return Staff|null
     */
    public function findById(int $id): ?Staff;

    /**
     * Get all staff members
     * 
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Get all staff members by branch ID
     * 
     * @param int $branchId
     * @return Collection
     */
    public function getByBranch(int $branchId): Collection;

    /**
     * Get count of photos uploaded by a staff member
     * 
     * @param int $staffId
     * @return int
     */
    public function getPhotoCount(int $staffId): int;

    /**
     * Get count of users registered by a staff member
     * 
     * @param int $staffId
     * @return int
     */
    public function getUserCount(int $staffId): int;

    /**
     * Get photos uploaded by a staff member
     * 
     * @param int $staffId
     * @return Collection
     */
    public function getPhotos(int $staffId): Collection;
} 