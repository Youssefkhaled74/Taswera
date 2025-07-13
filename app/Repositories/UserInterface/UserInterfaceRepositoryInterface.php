<?php

namespace App\Repositories\UserInterface;

interface UserInterfaceRepositoryInterface
{
    /**
     * Get user photos by barcode and phone number
     *
     * @param string $barcode
     * @param string $phoneNumber
     * @return array
     */
    public function getUserPhotos(string $barcode, string $phoneNumber): array;
} 