<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\BranchManager;

class BranchPolicy
{
    /**
     * Determine if the branch manager can view the branch.
     */
    public function view(BranchManager $manager, Branch $branch): bool
    {
        return $manager->branch_id === $branch->id;
    }

    /**
     * Determine if the branch manager can view staff members.
     */
    public function viewStaff(BranchManager $manager, Branch $branch): bool
    {
        return $manager->branch_id === $branch->id;
    }

    /**
     * Determine if the branch manager can view branch statistics.
     */
    public function viewStats(BranchManager $manager, Branch $branch): bool
    {
        return $manager->branch_id === $branch->id;
    }
} 