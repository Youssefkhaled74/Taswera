<?php

namespace App\Services\Admin;

use App\Models\Admin;

interface AdminServiceInterface
{
    /**
     * Register a new admin
     * 
     * @param array $data
     * @return Admin
     */
    public function register(array $data): Admin;

    /**
     * Login admin and return token
     * 
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array;
} 