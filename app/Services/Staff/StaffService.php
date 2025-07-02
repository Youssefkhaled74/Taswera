<?php

namespace App\Services\Staff;

use App\Models\Staff;
use App\Repositories\Staff\StaffRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StaffService implements StaffServiceInterface
{
    protected $staffRepository;

    public function __construct(StaffRepositoryInterface $staffRepository)
    {
        $this->staffRepository = $staffRepository;
    }

    /**
     * Authenticate staff with email and password
     * 
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        // Find staff by email
        $staff = $this->staffRepository->findByEmail($email);

        if (!$staff || !Hash::check($password, $staff->password)) {
            return null;
        }

        // Generate API token
        $token = Str::random(80);
        $staff->api_token = $token;
        $staff->save();

        return [
            'staff' => $staff,
            'token' => $token,
        ];
    }

    /**
     * Get all staff members with their statistics
     * 
     * @param int|null $branchId
     * @return Collection
     */
    public function getAllStaffWithStats(?int $branchId = null): Collection
    {
        // Get staff members
        $staffMembers = $branchId 
            ? $this->staffRepository->getByBranch($branchId)
            : $this->staffRepository->getAll();

        // Add statistics to each staff member
        return $staffMembers->map(function ($staff) {
            $staff->photo_count = $this->staffRepository->getPhotoCount($staff->id);
            $staff->user_count = $this->staffRepository->getUserCount($staff->id);
            return $staff;
        });
    }

    /**
     * Get photos uploaded by a specific staff member
     * 
     * @param int $staffId
     * @return Collection
     */
    public function getPhotosByStaff(int $staffId): Collection
    {
        return $this->staffRepository->getPhotos($staffId);
    }
} 