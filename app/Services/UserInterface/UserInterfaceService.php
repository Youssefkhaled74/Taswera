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
     * Get user photos by barcode
     *
     * @param string $barcode
     * @return array
     */
    public function getUserPhotos(string $barcode): array
    {
        return $this->userInterfaceRepository->getUserPhotos($barcode);
    }

    /**
     * Get all available packages for a specific branch
     *
     * @param int $branchId
     * @return array
     */
    public function getPackages(int $branchId): array
    {
        return $this->userInterfaceRepository->getPackages($branchId);
    }

    /**
     * Add a new photo to user's collection
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @param array $photoData
     * @return array
     */
    public function addUserPhoto(string $barcode, string $phoneNumber, array $photoData): array
    {
        return $this->userInterfaceRepository->addUserPhoto($barcode, $phoneNumber, $photoData);
    }

    /**
     * Select photos for printing and create invoice
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @param array $data
     * @return array
     */
    public function selectPhotosForPrinting(string $barcode, string $phoneNumber, array $data): array
    {
        return $this->userInterfaceRepository->selectPhotosForPrinting($barcode, $phoneNumber, $data);
    }

    /**
     * Get photos ready to print for a user
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getPhotosReadyToPrint(string $barcode, string $phoneNumber): array
    {
        return $this->userInterfaceRepository->getPhotosReadyToPrint($barcode, $phoneNumber);
    }
} 