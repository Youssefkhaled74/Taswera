<?php

namespace App\Repositories\Admin;

use App\Models\Admin;

class AdminRepository implements AdminRepositoryInterface
{
    /**
     * Create a new admin
     * 
     * @param array $data
     * @return Admin
     */
    public function create(array $data): Admin
    {
        return Admin::create($data);
    }

    /**
     * Find admin by email
     * 
     * @param string $email
     * @return Admin|null
     */
    public function findByEmail(string $email): ?Admin
    {
        return Admin::where('email', $email)->first();
    }

    /**
     * Find admin by phone
     * 
     * @param string $phone
     * @return Admin|null
     */
    public function findByPhone(string $phone): ?Admin
    {
        return Admin::where('phone', $phone)->first();
    }
} 