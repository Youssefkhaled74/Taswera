<?php

namespace App\Services\BranchManager;

use App\Models\BranchManager;
use Illuminate\Database\Eloquent\Collection;

interface BranchManagerServiceInterface
{
    /**
     * Get branch staff members.
     */
    public function getBranchStaff(BranchManager $manager): Collection;

    /**
     * Get branch information.
     */
    public function getBranchInfo(BranchManager $manager): array;

    /**
     * Register a new branch manager.
     */
    public function register(array $data): BranchManager;
} 