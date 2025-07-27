<?php

namespace App\Services\UserInterface;

interface UserInterfaceServiceInterface
{
    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode, string $phoneNumber): array;

    /**
     * Get all available packages for a specific branch
     *
     * @param int $branchId
     * @return array
     */
    public function getPackages(int $branchId): array;

    /**
     * Add a new photo to user's collection
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @param array $photoData
     * @return array
     */
    public function addUserPhoto(string $barcode, string $phoneNumber, array $photoData): array;

    /**
     * Select photos for printing and create invoice
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @param array $data
     * @return array
     */
    public function selectPhotosForPrinting(string $barcode, string $phoneNumber, array $data): array;

    /**
     * Get photos ready to print for a user
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getPhotosReadyToPrint(string $barcode, string $phoneNumber): array;

    /**
     * Get photos ready to print for a user
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    
}

