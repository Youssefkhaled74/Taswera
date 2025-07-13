<?php

namespace App\Services\UserInterface;

use App\Repositories\UserInterface\UserInterfaceRepositoryInterface;

class UserInterfaceService implements UserInterfaceServiceInterface
{
    protected $userInterfaceRepository;

    public function __construct(UserInterfaceRepositoryInterface $userInterfaceRepository)
    {
        $this->userInterfaceRepository = $userInterfaceRepository;
    }

    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode, string $phoneNumber): array
    {
        return $this->userInterfaceRepository->getUserPhotos($barcode, $phoneNumber);
    }
} 