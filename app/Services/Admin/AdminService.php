<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Repositories\Admin\AdminRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminService implements AdminServiceInterface
{
    protected $adminRepository;

    public function __construct(AdminRepositoryInterface $adminRepository)
    {
        $this->adminRepository = $adminRepository;
    }

    /**
     * Register a new admin
     * 
     * @param array $data
     * @return Admin
     */
    public function register(array $data): Admin
    {
        // Check if email or phone already exists
        if ($this->adminRepository->findByEmail($data['email'])) {
            throw new \Exception('Email already exists');
        }

        if ($this->adminRepository->findByPhone($data['phone'])) {
            throw new \Exception('Phone number already exists');
        }

        // Hash password
        $data['password'] = Hash::make($data['password']);
        
        // Set default permissions if not provided
        if (!isset($data['permissions'])) {
            $data['permissions'] = [
                'view_dashboard' => true,
                'manage_branches' => false,
                'manage_staff' => false,
                'view_reports' => true,
            ];
        }

        // Create admin
        return $this->adminRepository->create($data);
    }

    /**
     * Login admin and return token
     * 
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        // Find admin by email
        $admin = $this->adminRepository->findByEmail($email);

        if (!$admin || !Hash::check($password, $admin->password)) {
            return null;
        }

        // Generate token
        $token = $admin->createToken('admin-token')->plainTextToken;

        return [
            'admin' => $admin,
            'token' => $token,
        ];
    }
} 