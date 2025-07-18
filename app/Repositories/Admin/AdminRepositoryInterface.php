<?php

namespace App\Repositories\Admin;

use App\Models\Admin;

interface AdminRepositoryInterface
{
    /**
     * Create a new admin
     * 
     * @param array $data
     * @return Admin
     */
    public function create(array $data): Admin;

    /**
     * Find admin by email
     * 
     * @param string $email
     * @return Admin|null
     */
    public function findByEmail(string $email): ?Admin;

    /**
     * Find admin by phone
     * 
     * @param string $phone
     * @return Admin|null
     */
    public function findByPhone(string $phone): ?Admin;
} 