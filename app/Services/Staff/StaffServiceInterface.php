<?php

namespace App\Services\Staff;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;

interface StaffServiceInterface
{
    /**
     * Authenticate staff with email and password
     * 
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array;

    /**
     * Get all staff members with their statistics
     * 
     * @param int|null $branchId
     * @return Collection
     */
    public function getAllStaffWithStats(?int $branchId = null): Collection;

    /**
     * Get photos uploaded by a specific staff member
     * 
     * @param int $staffId
     * @return Collection
     */
    public function getPhotosByStaff(int $staffId): Collection;
} 