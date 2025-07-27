<?php

namespace App\Services\BranchManager;

use App\Models\BranchManager;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

class BranchManagerService implements BranchManagerServiceInterface
{
    /**
     * Get branch staff members.
     */
    public function getBranchStaff(BranchManager $manager)
    {
        return Staff::where('branch_id', $manager->branch_id)
            ->with(['branch', 'uploadedPhotos']);
    }

    /**
     * Get branch information.
     */
    public function getBranchInfo(BranchManager $manager): array
    {
        $branch = $manager->branch()->with([
            'staff',
            'photos' => function ($query) {
                $query->latest()->take(5);
            }
        ])->first();

        return [
            'branch' => $branch,
            'stats' => [
                'total_staff' => $branch->staff->count(),
                'total_photos' => $branch->photos->count(),
                'recent_photos' => $branch->photos,
            ],
        ];
    }

    public function register(array $data): BranchManager
    {
        $data['password'] = Hash::make($data['password']);
        return BranchManager::create($data);
    }
} 