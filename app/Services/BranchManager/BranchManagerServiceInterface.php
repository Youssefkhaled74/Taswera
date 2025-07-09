<?php

namespace App\Services\BranchManager;

use App\Models\BranchManager;
use Illuminate\Database\Eloquent\Collection;

interface BranchManagerServiceInterface
{
    /**
     * Authenticate a branch manager.
     */
    public function authenticate(string $email, string $password): ?BranchManager;

    /**
     * Get branch staff members.
     */
    public function getBranchStaff(BranchManager $manager): Collection;

    /**
     * Get branch information.
     */
    public function getBranchInfo(BranchManager $manager): array;

    /**
     * Register a branch manager.
     */
    public function register(array $data): BranchManager;
} 